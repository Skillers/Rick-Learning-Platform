// Unified notification bell — used on every page (student view + manager pages).
// Renders the bell badge + dropdown, the student dashboard tile, and an enlarged
// "all notifications" modal, wherever those elements are present. Type-aware:
//   To_grade → teacher worklist ("Na te kijken"); clears when graded, NOT on click
//   Grade    → student FYI ("Beoordeeld …"); marked read when the student clicks it
//
// Markup contract: a `.notif-wrap` with `#notifBadge` + `#notifMenu`/`#notifList`.
// Optional: dashboard tile (`#notifCardList`, `#notifCardCount`) and modal
// (`#notifModalOverlay`, `#notifModalList`). Call Notifications.init(name[, {onItem}]).
(function () {
  let _user = null, _items = [], _onItem = null;

  function esc(s) {
    return String(s ?? "").replace(/&/g, "&amp;").replace(/</g, "&lt;")
      .replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#39;");
  }
  function verdictWord(v) { return v === "V" ? "voldoende" : v === "X" ? "onvoldoende" : "beoordeeld"; }

  // Time for today, date for older.
  function timeLabel(ts) {
    if (!ts) return "";
    const d = new Date(String(ts).replace(" ", "T"));
    if (isNaN(d)) return "";
    const now = new Date();
    const sameDay = d.getFullYear() === now.getFullYear() && d.getMonth() === now.getMonth() && d.getDate() === now.getDate();
    return sameDay
      ? d.toLocaleTimeString("nl-NL", { hour: "2-digit", minute: "2-digit" })
      : d.toLocaleDateString("nl-NL", { day: "numeric", month: "short" });
  }

  function view(n) {
    if (n.type === "To_grade") {
      const title = n.read_at
        ? "Nagekeken" + (n.graded_by ? " door " + n.graded_by : "")
        : "Na te kijken: open vraag";
      return { title, sub: (n.student || "") + (n.course_name ? " · " + n.course_name : "") };
    }
    return { title: "Beoordeeld: " + verdictWord(n.verdict), sub: (n.course_name || "") + (n.page_title ? " · " + n.page_title : "") };
  }
  // ── Date helpers: recency for the badge + bucketing for the enlarged view ──
  function parseTs(ts) { const d = new Date(String(ts).replace(" ", "T")); return isNaN(d) ? null : d; }
  function sameDay(a, b) { return a.getFullYear() === b.getFullYear() && a.getMonth() === b.getMonth() && a.getDate() === b.getDate(); }
  function sameMonth(a, b) { return a.getFullYear() === b.getFullYear() && a.getMonth() === b.getMonth(); }
  function weekStart(x) { const d = new Date(x.getFullYear(), x.getMonth(), x.getDate()); d.setDate(d.getDate() - ((d.getDay() + 6) % 7)); return d.getTime(); }
  function sameWeek(a, b) { return weekStart(a) === weekStart(b); }
  function withinLastMonth(ts) { const d = parseTs(ts); if (!d) return false; const c = new Date(); c.setMonth(c.getMonth() - 1); return d >= c; }
  function groupOf(n) {
    const d = parseTs(n.created_at), now = new Date();
    if (!d) return "Ouder";
    if (sameDay(d, now)) return "Vandaag";
    const y = new Date(now); y.setDate(now.getDate() - 1);
    if (sameDay(d, y)) return "Gisteren";
    if (sameWeek(d, now)) return "Deze week";
    const lw = new Date(now); lw.setDate(now.getDate() - 7);
    if (sameWeek(d, lw)) return "Vorige week";
    if (sameMonth(d, now)) return "Deze maand";
    const lm = new Date(now.getFullYear(), now.getMonth() - 1, 1);
    if (sameMonth(d, lm)) return "Vorige maand";
    return "Ouder";
  }
  const GROUP_ORDER = ["Vandaag", "Gisteren", "Deze week", "Vorige week", "Deze maand", "Vorige maand", "Ouder"];
  const TILE_LIMIT = 20;

  // Unread older than a month stays unread but no longer counts toward the badge.
  function unread() { return _items.filter(n => !n.read_at && withinLastMonth(n.created_at)).length; }

  function itemHtml(n) {
    const v = view(n), t = timeLabel(n.created_at);
    const sub = esc(v.sub) + (t ? (v.sub ? " · " : "") + esc(t) : "");
    return `<button class="notif-item ${n.read_at ? "read" : "unread"}" onclick="Notifications.open(${n.id})">
      <div class="notif-item-title">${esc(v.title)}</div>
      <div class="notif-item-sub">${sub}</div></button>`;
  }
  function tileItemHtml(n) {
    const v = view(n), t = timeLabel(n.created_at);
    return `<div class="notif-card-item ${n.read_at ? "read" : ""}" onclick="Notifications.open(${n.id})" title="${esc(v.sub)}">${esc(v.title)} — ${esc(n.course_name || "")}${t ? ` · ${esc(t)}` : ""}</div>`;
  }

  function render() {
    const badge = document.getElementById("notifBadge");
    if (badge) { const n = unread(); badge.textContent = n > 9 ? "9+" : String(n); badge.style.display = n > 0 ? "" : "none"; }

    const list = document.getElementById("notifList");
    if (list) list.innerHTML = _items.length ? _items.map(itemHtml).join("") : `<div class="notif-empty">Geen meldingen</div>`;

    // Enlarged modal — grouped by recency (Vandaag … Ouder).
    const modalList = document.getElementById("notifModalList");
    if (modalList) {
      if (!_items.length) modalList.innerHTML = `<div class="notif-empty">Geen meldingen</div>`;
      else {
        const groups = {};
        _items.forEach(n => { const g = groupOf(n); (groups[g] || (groups[g] = [])).push(n); });
        modalList.innerHTML = GROUP_ORDER.filter(g => groups[g])
          .map(g => `<div class="notif-group-head">${g}</div>` + groups[g].map(itemHtml).join("")).join("");
      }
    }

    // Dashboard tile — 20 most recent, then a "show more" that opens the modal.
    const cnt = document.getElementById("notifCardCount");
    if (cnt) cnt.textContent = unread() > 0 ? unread() + " nieuw" : "";
    const tile = document.getElementById("notifCardList");
    if (tile) {
      if (!_items.length) tile.innerHTML = `<div class="notif-empty">Geen meldingen</div>`;
      else {
        let h = _items.slice(0, TILE_LIMIT).map(tileItemHtml).join("");
        if (_items.length > TILE_LIMIT) h += `<button class="notif-card-more-btn" onclick="Notifications.openModal()">Toon meer (${_items.length - TILE_LIMIT})</button>`;
        tile.innerHTML = h;
      }
    }
  }

  async function load() {
    if (!_user) return;
    try {
      const r = await fetch(`api/notifications.php?username=${encodeURIComponent(_user)}&limit=100`);
      if (!r.ok) throw new Error("HTTP " + r.status);
      _items = (await r.json()).notifications || [];
    } catch (e) { console.warn("[notifications]", e); _items = []; }
    render();
  }

  // Mark specific notifications read (server only honours the student's own 'Grade'
  // rows; 'To_grade' clears via grading). Optimistic local update + re-render.
  async function markRead(ids) {
    ids = (ids || []).filter(Boolean);
    if (!ids.length) return;
    _items = _items.map(n => ids.includes(n.id) ? { ...n, read_at: n.read_at || "now" } : n);
    render();
    try {
      await fetch("api/notifications.php", { method: "POST", headers: { "Content-Type": "application/json" }, body: JSON.stringify({ username: _user, ids }) });
    } catch (e) {}
  }

  window.Notifications = {
    init(username, opts) { _user = username; _onItem = (opts && opts.onItem) || null; load(); },
    refresh: load,
    openModal() { const ov = document.getElementById("notifModalOverlay"); if (ov) { render(); ov.classList.add("visible"); } },
    closeModal() { document.getElementById("notifModalOverlay")?.classList.remove("visible"); },
    open(id) {
      const n = _items.find(x => x.id == id);
      document.getElementById("notifMenu")?.classList.remove("open");
      this.closeModal();
      if (!n) return;
      // Clicking a graded notification marks just that one read for the student.
      if (n.type === "Grade" && !n.read_at) markRead([n.id]);
      if (_onItem) { _onItem(n); return; }
      // No in-app handler (we're on another page): navigate with a deep-link so the
      // target page opens the right thing. To_grade → grading page + expand the
      // answer; Grade → main page + open the graded lesson.
      window.location = n.type === "To_grade"
        ? "./HomeworkManager?did=" + n.did_question_id
        : "./?course=" + n.course_id + "&page=" + n.page_id;
    },
  };

  window.toggleNotifMenu = function () {
    const menu = document.getElementById("notifMenu");
    if (!menu) return;
    const opening = !menu.classList.contains("open");
    document.getElementById("avatarMenu")?.classList.remove("open");
    menu.classList.toggle("open", opening);
    if (opening) load();
  };

  document.addEventListener("click", (e) => {
    const w = document.querySelector(".notif-wrap");
    if (w && !w.contains(e.target)) document.getElementById("notifMenu")?.classList.remove("open");
  });
})();

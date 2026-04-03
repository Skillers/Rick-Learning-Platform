/**
 * ICT Leerlijn — App Logic
 * ============================================
 * Pages  = lesson units shown in the sidebar
 * Sections = content chunks within a page
 *
 * Sections are grouped into "slides" automatically:
 * once accumulated rendered height exceeds
 * SECTION_HEIGHT_LIMIT (1.5x viewport), a new slide
 * starts. The student flips through slides with
 * next/prev buttons inside the content area.
 * The sidebar entry stays on the page level.
 */

import { initSidebar, syncSidebarActive, toggleGroup, updateSidebarUser, getCourses, getSectionsByPage, TYPE_LABELS } from './sidebar.js';
import { highlight } from './highlighter.js';


/* ── Data ────────────────────────────────────── */
let COURSES = [];

const STUDENT = {
  name:    "Student",
  initials:"ST",
  xp:      1240,
  xpNext:  1500,
  level:   7,
  streak:  12,
};

/* ── State ───────────────────────────────────── */
let currentCourse   = null;
let currentLesson   = null;
let currentSlides   = [];
let currentSlideIdx = 0;
let fullView        = false;
let prevLesson      = null;
let nextLesson      = null;

/* ── Init ────────────────────────────────────── */
let _stopLoginAnim = null;

document.addEventListener("DOMContentLoaded", () => {
  const saved = sessionStorage.getItem("ict_user");
  if (saved) {
    STUDENT.name     = saved;
    STUDENT.initials = initials(saved);
    bootApp();
  } else {
    _stopLoginAnim = startLoginAnimation();
  }
});

async function bootApp() {
  if (_stopLoginAnim) { _stopLoginAnim(); _stopLoginAnim = null; }
  document.getElementById("login-screen").style.display  = "none";
  document.getElementById("app-layout").style.display    = "";
  await initSidebar("sidebar-mount", loadLesson);
  COURSES = getCourses();
  updateSidebarUser(STUDENT);
  buildDashboard();
  buildHeatmap();
  showView("dashboard");
  setTopbar("Dashboard", "Welkom terug, " + STUDENT.name + "!");

  // Populate avatar menu
  document.getElementById("avatarMenuName").textContent = STUDENT.name;
  document.getElementById("avatarMenuSub").textContent  = STUDENT.initials;
}

function toggleAvatarMenu() {
  document.getElementById("avatarMenu").classList.toggle("open");
}

function handleLogout() {
  sessionStorage.removeItem("ict_user");
  document.getElementById("avatarMenu").classList.remove("open");
  document.getElementById("app-layout").style.display   = "none";
  document.getElementById("login-screen").style.display = "";
  _stopLoginAnim = startLoginAnimation();
}

document.addEventListener("click", (e) => {
  const wrap = document.querySelector(".avatar-wrap");
  if (wrap && !wrap.contains(e.target)) {
    document.getElementById("avatarMenu")?.classList.remove("open");
  }
});

function handleLogin(e) {
  e.preventDefault();
  const name = document.getElementById("loginName").value.trim();
  const pass = document.getElementById("loginPass").value.trim();
  const err  = document.getElementById("loginError");

  if (!name || !pass) {
    err.textContent = "Vul beide velden in.";
    return;
  }
  err.textContent = "";

  STUDENT.name     = name;
  STUDENT.initials = initials(name);
  sessionStorage.setItem("ict_user", name);
  bootApp();
}

function showLoginView(view) {
  document.getElementById("lv-login").style.display    = view === "login"    ? "" : "none";
  document.getElementById("lv-forgot").style.display   = view === "forgot"   ? "" : "none";
  document.getElementById("lv-register").style.display = view === "register" ? "" : "none";
}

// ── Live register validation ──────────────────────────────────────────────────
function debounce(fn, ms) {
  let t;
  return (...args) => { clearTimeout(t); t = setTimeout(() => fn(...args), ms); };
}

function setHint(id, inputId, msg, valid) {
  const hint  = document.getElementById(id);
  const input = document.getElementById(inputId);
  hint.textContent = msg;
  hint.className   = "field-hint " + (msg ? (valid ? "hint-valid" : "hint-invalid") : "");
  input.classList.toggle("input-valid",   !!msg && valid);
  input.classList.toggle("input-invalid", !!msg && !valid);
}

document.addEventListener("DOMContentLoaded", () => {
  const elUser  = document.getElementById("regUsername");
  const elEmail = document.getElementById("regEmail");
  const elPass  = document.getElementById("regPass");
  const elPass2 = document.getElementById("regPass2");

  // Password match — instant
  function checkPassMatch() {
    const p1 = elPass.value;
    const p2 = elPass2.value;
    if (!p2) { setHint("hintPass", "regPass2", "", false); return; }
    if (p1 === p2) setHint("hintPass", "regPass2", "Wachtwoorden komen overeen ✓", true);
    else           setHint("hintPass", "regPass2", "Wachtwoorden komen niet overeen", false);
  }
  elPass.addEventListener("input",  checkPassMatch);
  elPass2.addEventListener("input", checkPassMatch);

  // Username — debounced API check
  const checkUsername = debounce((value) => {
    if (value.length < 4) {
      setHint("hintUsername", "regUsername",
        value.length ? "Minimaal 4 tekens vereist" : "", false);
      return;
    }
    if (/\s/.test(value)) {
      setHint("hintUsername", "regUsername", "Geen spaties toegestaan", false);
      return;
    }
    fetch(`api/check_availability.php?username=${encodeURIComponent(value)}`)
      .then(r => r.json())
      .then(({ available }) => {
        if (document.getElementById("regUsername").value.trim() !== value) return;
        if (available === undefined) return;
        setHint("hintUsername", "regUsername",
          available ? "Gebruikersnaam beschikbaar ✓" : "Gebruikersnaam al in gebruik",
          available);
      })
      .catch(() => { setHint("hintUsername", "regUsername", "", false); });
  }, 400);

  elUser.addEventListener("input", (e) => {
    const v = e.target.value.trim();
    if (!v) { setHint("hintUsername", "regUsername", "", false); return; }
    checkUsername(v);
  });

  // Email — debounced API check
  const checkEmail = debounce((value) => {
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value)) {
      setHint("hintEmail", "regEmail", "Voer een geldig e-mailadres in", false);
      return;
    }
    fetch(`api/check_availability.php?email=${encodeURIComponent(value)}`)
      .then(r => r.json())
      .then(({ available }) => {
        if (document.getElementById("regEmail").value.trim() !== value) return;
        if (available === undefined) return;
        setHint("hintEmail", "regEmail",
          available ? "E-mailadres beschikbaar ✓" : "E-mailadres al in gebruik",
          available);
      })
      .catch(() => { setHint("hintEmail", "regEmail", "", false); });
  }, 400);

  elEmail.addEventListener("input", (e) => {
    const v = e.target.value.trim();
    if (!v) { setHint("hintEmail", "regEmail", "", false); return; }
    checkEmail(v);
  });
});

function handleForgot(e) {
  e.preventDefault();
  const field = document.getElementById("forgotField").value.trim();
  const err   = document.getElementById("forgotError");
  const ok    = document.getElementById("forgotSuccess");
  const btn   = document.getElementById("forgotBtn");

  if (!field) { err.textContent = "Vul je e-mailadres in."; return; }
  if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(field)) { err.textContent = "Voer een geldig e-mailadres in."; return; }
  err.textContent = "";
  ok.style.display = "";
  btn.disabled     = true;
  btn.textContent  = "Verstuurd";
}

function handleRegister(e) {
  e.preventDefault();
  const user  = document.getElementById("regUsername").value.trim();
  const email = document.getElementById("regEmail").value.trim();
  const pass  = document.getElementById("regPass").value;
  const pass2 = document.getElementById("regPass2").value;
  const err   = document.getElementById("registerError");

  if (!user || !email || !pass || !pass2) {
    err.textContent = "Vul alle velden in."; return;
  }
  if (user.length < 4) {
    err.textContent = "Gebruikersnaam moet minimaal 4 tekens bevatten."; return;
  }
  if (/\s/.test(user)) {
    err.textContent = "Gebruikersnaam mag geen spaties bevatten."; return;
  }
  if (pass.length < 9) {
    err.textContent = "Wachtwoord moet minimaal 9 tekens bevatten."; return;
  }
  if (pass !== pass2) {
    err.textContent = "Wachtwoorden komen niet overeen."; return;
  }
  err.textContent = "";

  fetch("api/register.php", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ username: user, email, password: pass }),
  })
    .then(r => r.json().then(data => ({ ok: r.ok, data })))
    .then(({ ok, data }) => {
      if (!ok) { err.textContent = data.error; return; }
      STUDENT.name     = user;
      STUDENT.initials = initials(user);
      sessionStorage.setItem("ict_user", user);
      bootApp();
    })
    .catch(() => { err.textContent = "Er ging iets mis. Probeer het opnieuw."; });
}

function initials(name) {
  const parts = name.trim().split(/[\s._-]+/);
  return parts.length >= 2
    ? (parts[0][0] + parts[parts.length - 1][0]).toUpperCase()
    : name.slice(0, 2).toUpperCase();
}

/* ═══════════════════════════════════════════════
   DASHBOARD
═══════════════════════════════════════════════ */
function buildDashboard() {
  document.getElementById("userInitials").textContent = STUDENT.initials;
  document.getElementById("userName").textContent     = STUDENT.name;
  document.getElementById("userLevel").textContent    = `Niveau ${STUDENT.level} · ${STUDENT.xp} XP`;
  document.getElementById("xpMiniFill").style.width   = Math.round((STUDENT.xp / STUDENT.xpNext) * 100) + "%";
  document.getElementById("streakBadge").textContent  = `🔥 ${STUDENT.streak}-daagse streak`;
  document.getElementById("topbarAvatar").textContent = STUDENT.initials;

  const grid = document.getElementById("coursesGrid");
  grid.innerHTML = "";
  COURSES.forEach(course => grid.appendChild(buildCourseCard(course)));

  const activeCourse = COURSES.find(c => c.lessons.some(l => l.status === "active"));
  if (activeCourse) {
    const al = activeCourse.lessons.find(l => l.status === "active");
    if (al) {
      document.getElementById("continueBtn").addEventListener("click", () => loadLesson(activeCourse.id, al.id));
      document.getElementById("welcomeSub").textContent = `Je bent bezig met "${al.title}" in ${activeCourse.name}`;
    }
  }
}

function buildCourseCard(course) {
  const card = el("div", "course-card");
  card.addEventListener("click", () => {
    const active = course.lessons.find(l => l.status === "active") || course.lessons[0];
    loadLesson(course.id, active.id);
  });
  const header = el("div", "course-card-header");
  const tw = el("div");
  tw.append(el("div", "course-card-title", course.name), el("div", "course-card-cat", course.category));
  header.append(el("div", "course-card-icon " + course.color, course.icon), tw);

  const bar  = el("div", "progress-bar");
  const fill = el("div", "progress-fill");
  fill.style.width = course.progress.pct + "%";
  fill.style.background = course.progress.color;
  bar.appendChild(fill);

  const footer = el("div", "course-card-footer");
  const pct = el("div", "progress-pct", course.progress.pct + "%");
  pct.style.color = course.progress.color;
  footer.append(pct, el("div", "lesson-count", `${course.progress.done} van ${course.progress.total} lessen`));
  card.append(header, bar, footer);
  return card;
}

/* ═══════════════════════════════════════════════
   HEATMAP
═══════════════════════════════════════════════ */
function buildHeatmap() {
  const c = document.getElementById("heatmap");
  c.innerHTML = "";
  Array.from({ length: 84 }, (_, i) => {
    const r = Math.random(), rec = i / 84;
    return rec > 0.85 && r < 0.7 ? Math.ceil(r * 4) : rec > 0.6 && r < 0.5 ? Math.ceil(r * 3) : r < 0.25 ? Math.ceil(r * 4) : 0;
  }).forEach(lv => c.appendChild(el("div", "heatmap-cell" + (lv > 0 ? " l" + lv : ""))));
}

/* ═══════════════════════════════════════════════
   LESSON LOADING
═══════════════════════════════════════════════ */
function loadLesson(courseId, lessonId, sectionIdx = 0) {
  const course = COURSES.find(c => c.id == courseId);
  if (!course) { console.error("[loadLesson] course not found", courseId, COURSES.map(c=>c.id)); return; }
  const lesson = course.lessons.find(l => l.id == lessonId);
  if (!lesson) { console.error("[loadLesson] lesson not found", lessonId, course.lessons.map(l=>l.id)); return; }

  currentCourse = course;
  currentLesson = lesson;

  // Sync sidebar highlight via the sidebar module
  syncSidebarActive(courseId, lessonId);

  document.getElementById("lessonBreadcrumb").innerHTML = `${course.name} <span>›</span> ${TYPE_LABELS[lesson.type].label}`;
  document.getElementById("lessonTitle").textContent    = lesson.title;
  const tag = document.getElementById("lessonTypeTag");
  const tl  = TYPE_LABELS[lesson.type];
  tag.textContent = tl.label.charAt(0).toUpperCase() + tl.label.slice(1);
  tag.className   = "lesson-tag " + tl.cls;
  document.getElementById("lessonXP").textContent = `+${lesson.xp} XP bij voltooiing`;

  buildLessonPanel(course, lesson);
  showView("lesson");
  setTopbar(lesson.title, `${course.name} › ${lesson.title}`);
  document.querySelector(".main").scrollTo(0, 0);

  // Prev / next page nav
  const idx  = course.lessons.indexOf(lesson);
  prevLesson = course.lessons[idx - 1] ?? null;
  nextLesson = (course.lessons[idx + 1]?.status !== "locked") ? (course.lessons[idx + 1] ?? null) : null;
  const prevBtn = document.getElementById("btnPrev");
  prevBtn.onclick = prevLesson ? () => loadLesson(courseId, prevLesson.id) : null;

  // Load & paginate
  loadLessonContent(course, lesson).then(sections => paginateSections(sections, sectionIdx));
}

async function loadLessonContent(course, lesson) {
  if (lesson.file) {
    try {
      const res = await fetch(lesson.file);
      if (res.ok) return parseSections(await res.text());
    } catch (_) {}
  }

  const builtin = BUILTIN_CONTENT[`${course.id}-${lesson.id}`];
  if (builtin) return parseSections(builtin);

  // Load section content from the database
  const dbSections = getSectionsByPage()[lesson.id];
  if (!dbSections || !dbSections.length) {
    return [{ title: null, html: '<p class="lesson-text">Inhoud voor <strong>' + lesson.title + '</strong> wordt nog toegevoegd.</p>' }];
  }

  // Fetch components for this page from the API
  let componentsBySection = {};
  try {
    const res = await fetch(`../api/section_content.php?page_id=${lesson.id}`);
    if (res.ok) {
      const components = await res.json();
      components.forEach(c => {
        if (!componentsBySection[c.section_id]) componentsBySection[c.section_id] = [];
        componentsBySection[c.section_id].push(c);
      });
    }
  } catch (_) {}

  return dbSections.map(s => ({
    title: s.title,
    html:  buildSectionHTML(componentsBySection[s.id] || []),
  }));
}

function escHtml(str) {
  return String(str)
    .replace(/&/g, '&amp;').replace(/</g, '&lt;')
    .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

function buildSectionHTML(components) {
  if (!components.length) {
    return `<div style="border:2px dashed var(--border2);border-radius:var(--radius);padding:32px;text-align:center;margin-top:8px;">
              <div style="font-size:28px;margin-bottom:8px;">📝</div>
              <div style="font-size:13px;color:var(--text2);">Inhoud wordt nog toegevoegd.</div>
            </div>`;
  }
  return components.map(renderComponent).join('');
}

function renderComponent(c) {
  const text = c.content || '';
  switch (c.type) {
    case 'text':
      return `<p class="lesson-text">${escHtml(text).replace(/\n/g, '<br>')}</p>`;

    case 'code':
      return `<div class="code-block">${highlight(text, c.language)}</div>`;

    case 'tip':
      return `<div class="info-box info-box-blue">
        <div class="info-box-title">💡 Tip</div>
        <p>${escHtml(text).replace(/\n/g, '<br>')}</p>
      </div>`;

    case 'warning':
      return `<div class="info-box info-box-orange">
        <div class="info-box-title">⚠️ Let op</div>
        <p>${escHtml(text).replace(/\n/g, '<br>')}</p>
      </div>`;

    case 'success':
      return `<div class="info-box info-box-green">
        <div class="info-box-title">✅ Goed gedaan</div>
        <p>${escHtml(text).replace(/\n/g, '<br>')}</p>
      </div>`;

    case 'exercise':
      return `<div class="exercise-box">${escHtml(text).replace(/\n/g, '<br>')}</div>`;

    default:
      return `<p class="lesson-text">${escHtml(text).replace(/\n/g, '<br>')}</p>`;
  }
}

/**
 * Split HTML into sections.
 * Priority: <section data-title="..."> tags.
 * Fallback: split on <h3> boundaries.
 */
function parseSections(html) {
  const wrap = document.createElement("div");
  wrap.innerHTML = html.trim();

  const tags = wrap.querySelectorAll("section");
  if (tags.length) {
    return Array.from(tags).map(s => ({
      title: s.dataset.title || s.querySelector("h3,h4")?.textContent || null,
      html:  s.innerHTML.trim()
    }));
  }

  // Fallback: split on h3
  const result  = [];
  let cur = { title: null, nodes: [] };
  Array.from(wrap.childNodes).forEach(node => {
    if (node.nodeName === "H3") {
      if (cur.nodes.length) result.push(cur);
      cur = { title: node.textContent, nodes: [node] };
    } else {
      cur.nodes.push(node);
    }
  });
  if (cur.nodes.length) result.push(cur);

  return result.map(s => ({
    title: s.title,
    html:  s.nodes.map(n => { const d = document.createElement("div"); d.appendChild(n.cloneNode(true)); return d.innerHTML; }).join("")
  }));
}

/* ═══════════════════════════════════════════════
   SECTION PAGINATION
═══════════════════════════════════════════════ */
function updatePrevVisibility() {
  const btn = document.getElementById("btnPrev");
  if (!btn) return;
  btn.style.display = (prevLesson && (fullView || currentSlideIdx === 0)) ? "" : "none";
}

function updateNextPageNav() {
  const nav = document.getElementById("nextPageNav");
  if (!nav) return;
  if (nextLesson) {
    nav.innerHTML = `<div class="next-page-wrap">
      <button class="btn btn-primary next-page-btn" id="btnNext">Ga naar volgend onderdeel →</button>
    </div>`;
    document.getElementById("btnNext").addEventListener("click", () => {
      loadLesson(currentCourse.id, nextLesson.id);
    });
  } else {
    nav.innerHTML = `<div class="next-page-wrap">
      <button class="btn btn-ghost next-page-btn" onclick="showDashboard()">← Terug naar dashboard</button>
    </div>`;
  }
}
function paginateSections(sections, startIdx = 0) {
  currentSlideIdx = 0;
  fullView = false;
  const btn = document.getElementById("viewToggleBtn");
  if (btn) btn.textContent = "Switch to full view";
  // One section per slide
  currentSlides = sections.map(s => [s]);
  renderSlide(Math.min(startIdx, sections.length - 1));
}

function toggleView() {
  fullView = !fullView;
  const btn = document.getElementById("viewToggleBtn");
  if (btn) btn.textContent = fullView ? "Switch to section view" : "Switch to full view";
  if (fullView) renderFullView();
  else renderSlide(currentSlideIdx);
}

function renderFullView() {
  const allSections = currentSlides.flat();
  const sectionsHTML = allSections.map((s, globalIdx) => {
    const sectionKey   = `${currentCourse.id}__${currentLesson.id}__${globalIdx}`;
    const reportCount  = getReportCount(sectionKey);
    const reportedClass = reportCount > 0 ? " reported" : "";
    return `
    <div class="page-section" data-section-key="${sectionKey}">
      <div class="section-header-row">
        ${s.title ? `<h3 class="section-heading">${s.title}</h3>` : `<div></div>`}
        <button class="report-btn${reportedClass}" data-section-key="${sectionKey}" data-section-title="${s.title || "Sectie " + (globalIdx + 1)}" title="Fout melden in deze sectie">
          ${reportCount > 0 ? `<span class="report-count">${reportCount}</span>` : ""}⚑
        </button>
      </div>
      ${s.html}
    </div>`;
  }).join("");
  const el = document.getElementById("lessonContent");
  el.classList.add("full-view");
  el.innerHTML = sectionsHTML;
  wireReportButtons();
  updateNextPageNav();
  updatePrevVisibility();
}

function renderSlide(idx) {
  currentSlideIdx = Math.max(0, Math.min(idx, currentSlides.length - 1));
  document.getElementById("lessonContent")?.classList.remove("full-view");
  const slide  = currentSlides[currentSlideIdx];
  const total  = currentSlides.length;
  const isLast = currentSlideIdx === total - 1;
  const isFirst= currentSlideIdx === 0;

  const sectionsHTML = slide.map((s, i) => {
    const globalIdx = currentSlides.slice(0, currentSlideIdx).reduce((a, sl) => a + sl.length, 0) + i;
    const sectionKey = `${currentCourse.id}__${currentLesson.id}__${globalIdx}`;
    const reportCount = getReportCount(sectionKey);
    const reportedClass = reportCount > 0 ? " reported" : "";
    return `
    <div class="page-section" data-section-key="${sectionKey}">
      <div class="section-header-row">
        ${s.title ? `<h3 class="section-heading">${s.title}</h3>` : `<div></div>`}
        <button class="report-btn${reportedClass}" data-section-key="${sectionKey}" data-section-title="${s.title || "Sectie " + (globalIdx + 1)}" title="Fout melden in deze sectie">
          ${reportCount > 0 ? `<span class="report-count">${reportCount}</span>` : ""}⚑
        </button>
      </div>
      ${s.html}
    </div>`;
  }).join("");

  // Only show navigation chrome if there are multiple slides
  if (total <= 1) {
    document.getElementById("lessonContent").innerHTML = sectionsHTML;
    wireReportButtons();
    updateSlideCounter(0, 1);
    updateNextPageNav();
    updatePrevVisibility();
    return;
  }

  const progressPct = Math.round(((currentSlideIdx + 1) / total) * 100);

  const dots = Array.from({ length: total }, (_, i) => {
    const sectionTitle = currentSlides[i]?.[0]?.title || `Sectie ${i + 1}`;
    return `<button class="slide-dot${i === currentSlideIdx ? " active" : ""}" data-slide-to="${i}" title="${sectionTitle}" aria-label="${sectionTitle}"></button>`;
  }).join("");

  document.getElementById("lessonContent").innerHTML = `
    <div class="slide-header">
      <div class="slide-progress-track">
        <div class="slide-progress-fill" style="width:${progressPct}%"></div>
      </div>
    </div>

    ${sectionsHTML}

    <div class="slide-footer">
      <div class="slide-nav-bar">
        <button class="slide-arrow slide-arrow-prev" data-slide-to="${currentSlideIdx - 1}" ${isFirst ? "disabled" : ""} aria-label="Vorige sectie">
          <svg width="16" height="16" viewBox="0 0 16 16" fill="none"><path d="M10 3L5 8L10 13" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </button>

        <div class="slide-nav-center">
          <div class="slide-dots">${dots}</div>
          <span class="slide-counter">${currentSlideIdx + 1} / ${total}</span>
        </div>

        ${isLast
          ? `<div class="slide-arrow slide-arrow-done" aria-label="Pagina voltooid">
               <svg width="16" height="16" viewBox="0 0 16 16" fill="none"><path d="M3 8L6.5 11.5L13 5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
             </div>`
          : `<button class="slide-arrow slide-arrow-next" data-slide-to="${currentSlideIdx + 1}" aria-label="Volgende sectie">
               <svg width="16" height="16" viewBox="0 0 16 16" fill="none"><path d="M6 3L11 8L6 13" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
             </button>`
        }
      </div>
    </div>`;

  wireReportButtons();

  // Wire nav buttons
  document.getElementById("lessonContent").querySelectorAll("[data-slide-to]").forEach(btn => {
    const to = parseInt(btn.dataset.slideTo, 10);
    if (!isNaN(to) && to >= 0 && to < total) {
      btn.addEventListener("click", () => {
        renderSlide(to);
        document.querySelector(".main").scrollTo({ top: 0, behavior: "smooth" });
      });
    }
  });

  updateSlideCounter(currentSlideIdx, total);
  if (isLast) updateNextPageNav();
  else document.getElementById("nextPageNav").innerHTML = "";
  updatePrevVisibility();
}

function updateSlideCounter(idx, total) {
  const counter = document.getElementById("slideCounter");
  if (counter) counter.style.display = "none";
}

/* ═══════════════════════════════════════════════
   REPORT SYSTEM
   Reports are stored in localStorage as an array
   under key "ict-reports". Each report:
   {
     id:           string (timestamp-based)
     sectionKey:   "courseId__lessonId__sectionIdx"
     sectionTitle: string
     course:       string
     lesson:       string
     type:         "fout" | "onduidelijk" | "opdracht" | "anders"
     note:         string
     timestamp:    ISO string
     status:       "open" | "opgelost"
   }
═══════════════════════════════════════════════ */

const REPORT_TYPES = [
  { value: "fout",        label: "Fout in de inhoud",        icon: "✗" },
  { value: "onduidelijk", label: "Onduidelijke uitleg",       icon: "?" },
  { value: "opdracht",    label: "Probleem met de opdracht",  icon: "!" },
  { value: "anders",      label: "Iets anders",               icon: "…" },
];

function getReports() {
  try { return JSON.parse(localStorage.getItem("ict-reports") || "[]"); } catch { return []; }
}
function saveReports(reports) {
  localStorage.setItem("ict-reports", JSON.stringify(reports));
}
function getReportCount(sectionKey) {
  return getReports().filter(r => r.sectionKey === sectionKey && r.status === "open").length;
}
function getTotalOpenReports() {
  return getReports().filter(r => r.status === "open").length;
}

function wireReportButtons() {
  document.querySelectorAll(".report-btn").forEach(btn => {
    btn.addEventListener("click", e => {
      e.stopPropagation();
      openReportModal(btn.dataset.sectionKey, btn.dataset.sectionTitle);
    });
  });
}

function openReportModal(sectionKey, sectionTitle) {
  // Remove any existing modal
  document.getElementById("reportModal")?.remove();

  const overlay = el("div", "report-overlay");
  overlay.id = "reportModal";
  overlay.addEventListener("click", e => { if (e.target === overlay) closeReportModal(); });

  overlay.innerHTML = `
    <div class="report-modal">
      <div class="report-modal-header">
        <div>
          <div class="report-modal-title">Fout melden</div>
          <div class="report-modal-sub">${sectionTitle}</div>
        </div>
        <button class="report-close-btn" onclick="closeReportModal()">✕</button>
      </div>

      <div class="report-modal-body">
        <div class="report-field-label">Wat klopt er niet?</div>
        <div class="report-type-grid">
          ${REPORT_TYPES.map(t => `
            <label class="report-type-option">
              <input type="radio" name="reportType" value="${t.value}" ${t.value === "fout" ? "checked" : ""}>
              <span class="report-type-icon">${t.icon}</span>
              <span class="report-type-label">${t.label}</span>
            </label>`).join("")}
        </div>

        <div class="report-field-label" style="margin-top:14px;">Toelichting <span style="opacity:.5;font-weight:400">(optioneel)</span></div>
        <textarea class="report-textarea" id="reportNote" placeholder="Beschrijf kort wat er fout is of wat er onduidelijk is..." rows="3"></textarea>

        <div class="report-modal-footer">
          <button class="report-cancel-btn" onclick="closeReportModal()">Annuleren</button>
          <button class="report-submit-btn" onclick="submitReport('${sectionKey}', '${sectionTitle.replace(/'/g, "\\'")}')">Verstuur melding</button>
        </div>
      </div>
    </div>`;

  document.body.appendChild(overlay);
  // Animate in
  requestAnimationFrame(() => overlay.classList.add("visible"));
  document.getElementById("reportNote").focus();
}

function closeReportModal() {
  const overlay = document.getElementById("reportModal");
  if (!overlay) return;
  overlay.classList.remove("visible");
  setTimeout(() => overlay.remove(), 200);
}

function submitReport(sectionKey, sectionTitle) {
  const type  = document.querySelector('input[name="reportType"]:checked')?.value || "anders";
  const note  = document.getElementById("reportNote")?.value.trim() || "";

  const report = {
    id:           Date.now().toString(36),
    sectionKey,
    sectionTitle,
    course:       currentCourse?.name || "?",
    lesson:       currentLesson?.title || "?",
    type,
    note,
    timestamp:    new Date().toISOString(),
    status:       "open",
  };

  const reports = getReports();
  reports.unshift(report);
  saveReports(reports);

  closeReportModal();
  showReportToast();

  // Refresh the current slide so button turns orange + count updates
  renderSlide(currentSlideIdx);
  updateReportBadge();
}

function showReportToast() {
  document.getElementById("reportToast")?.remove();
  const toast = el("div", "report-toast", "✓ Melding verstuurd — bedankt!");
  toast.id = "reportToast";
  document.body.appendChild(toast);
  requestAnimationFrame(() => toast.classList.add("visible"));
  setTimeout(() => {
    toast.classList.remove("visible");
    setTimeout(() => toast.remove(), 300);
  }, 3000);
}

function updateReportBadge() {
  const badge = document.getElementById("reportBadge");
  const count = getTotalOpenReports();
  if (!badge) return;
  badge.textContent = count;
  badge.style.display = count > 0 ? "inline-flex" : "none";
}

/* ── Report inbox (teacher view in panel) ──── */
function buildReportPanel() {
  const panel = document.getElementById("reportPanel");
  if (!panel) return;

  const reports = getReports();
  const openReports = reports.filter(r => r.status === "open");
  updateReportBadge();

  if (openReports.length === 0) {
    panel.innerHTML = `<div style="font-size:12px;color:var(--text3);text-align:center;padding:8px 0;">Geen meldingen 👍</div>`;
    return;
  }

  panel.innerHTML = openReports.slice(0, 5).map(r => {
    const typeInfo = REPORT_TYPES.find(t => t.value === r.type) || REPORT_TYPES[3];
    const date     = new Date(r.timestamp).toLocaleDateString("nl-NL", { day:"numeric", month:"short" });
    return `
      <div class="report-item">
        <div class="report-item-header">
          <span class="report-item-icon">${typeInfo.icon}</span>
          <span class="report-item-type">${typeInfo.label}</span>
          <span class="report-item-date">${date}</span>
        </div>
        <div class="report-item-loc">${r.course} › ${r.lesson} › ${r.sectionTitle}</div>
        ${r.note ? `<div class="report-item-note">"${r.note}"</div>` : ""}
        <button class="report-resolve-btn" onclick="resolveReport('${r.id}')">Opgelost ✓</button>
      </div>`;
  }).join("");

  if (openReports.length > 5) {
    panel.innerHTML += `<div style="font-size:11px;color:var(--text3);text-align:center;margin-top:8px;">+${openReports.length - 5} meer meldingen</div>`;
  }
}

function resolveReport(id) {
  const reports = getReports();
  const r = reports.find(r => r.id === id);
  if (r) r.status = "opgelost";
  saveReports(reports);
  buildReportPanel();
  renderSlide(currentSlideIdx); // refresh flag counts
}


/* ═══════════════════════════════════════════════
   LESSON PANEL
═══════════════════════════════════════════════ */
function buildLessonPanel(course, lesson) {
  const miniProg = document.getElementById("miniProgress");
  const label = el("div"); label.style.cssText = "font-size:12px;color:var(--text2);margin-bottom:8px;"; label.textContent = course.name;
  const bar   = el("div", "progress-bar"); bar.style.height = "6px";
  const fill  = el("div", "progress-fill"); fill.style.cssText = `width:${course.progress.pct}%;background:${course.progress.color};height:6px;`;
  bar.appendChild(fill);
  const sub = el("div"); sub.style.cssText = "font-size:11px;color:var(--text3);margin-top:4px;"; sub.textContent = `${course.progress.pct}% · ${course.progress.done}/${course.progress.total} lessen`;
  miniProg.innerHTML = ""; miniProg.append(label, bar, sub);

  const xpPct = Math.round((STUDENT.xp / STUDENT.xpNext) * 100);
  document.getElementById("xpFill").style.width    = xpPct + "%";
  document.getElementById("xpCurrent").textContent = STUDENT.xp;
  document.getElementById("xpNext").textContent    = `Level ${STUDENT.level + 1} bij ${STUDENT.xpNext}`;
  document.getElementById("xpLevel").textContent   = `Level ${STUDENT.level}`;
  buildTaskList(lesson);
  buildReportPanel();
}

function buildTaskList(lesson) {
  const tasks = generateTasks(lesson);
  const list  = document.getElementById("tasksList"); list.innerHTML = "";
  tasks.forEach(task => {
    const item   = el("div", "task-item");
    const check  = el("div", "task-check" + (task.done ? " done" : ""), task.done ? "✓" : "");
    const textEl = el("div", "task-text"  + (task.done ? " done" : ""), task.label);
    check.addEventListener("click", () => {
      task.done = !task.done;
      check.classList.toggle("done", task.done); check.textContent = task.done ? "✓" : "";
      textEl.classList.toggle("done", task.done);
    });
    item.append(check, textEl); list.appendChild(item);
  });
}

function generateTasks(lesson) {
  const base = [
    { label: "Lees de theorie door",   done: lesson.status === "done" },
    { label: "Bekijk de voorbeelden",  done: lesson.status === "done" },
  ];
  if (lesson.type === "exercise") { base.push({ label: "Maak de opdrachten", done: false }); base.push({ label: "Test je code", done: false }); }
  if (lesson.type === "quiz")    { base.length=0; base.push({ label:"Lees de instructies",done:false},{label:"Beantwoord alle vragen",done:false},{label:"Controleer je score",done:false}); }
  if (lesson.type === "project") { base.push({label:"Plan je aanpak",done:false},{label:"Bouw de basis",done:false},{label:"Voeg features toe",done:false},{label:"Lever in via portal",done:false}); }
  return base;
}

/* ═══════════════════════════════════════════════
   VIEWS & HELPERS
═══════════════════════════════════════════════ */
function showView(name) {
  document.querySelectorAll(".view").forEach(v => v.classList.remove("active"));
  document.getElementById("view-" + name)?.classList.add("active");
}
function setTopbar(title, sub) {
  document.getElementById("pageTitle").textContent = title;
  document.getElementById("pageSub").textContent   = sub;
}
function showDashboard() {
  showView("dashboard");
  setTopbar("Dashboard", "Welkom terug! Je bent goed op weg.");
  // Clear sidebar active state by passing null — sidebar module handles its own DOM
  document.querySelectorAll(".lesson-item").forEach(e => e.classList.remove("active"));
}
function el(tag, className = "", text = "") {
  const e = document.createElement(tag);
  if (className) e.className = className;
  if (text) e.textContent = text;
  return e;
}

/* ═══════════════════════════════════════════════
   THEME TOGGLE
═══════════════════════════════════════════════ */
(function initTheme() {
  const saved = localStorage.getItem("ict-theme") || "dark";
  applyTheme(saved);
})();

function toggleTheme() {
  const current = document.documentElement.dataset.theme || "dark";
  const next    = current === "dark" ? "light" : "dark";
  applyTheme(next);
  localStorage.setItem("ict-theme", next);
}

function applyTheme(theme) {
  document.documentElement.dataset.theme = theme;
  const btn = document.getElementById("themeToggle");
  if (btn) btn.textContent = theme === "dark" ? "🌙" : "☀️";
}

/* ═══════════════════════════════════════════════
   BUILT-IN LESSON CONTENT
   Use <section data-title="..."> to define split points.
═══════════════════════════════════════════════ */
const BUILTIN_CONTENT = {

"python-loops": `
<section data-title="Introductie">
  <p class="lesson-text">In Python gebruik je loops om code herhaaldelijk uit te voeren. Er zijn twee soorten: de <strong>for-loop</strong> en de <strong>while-loop</strong>.</p>
  <div class="info-box info-box-blue">
    <div class="info-box-title">💡 Wanneer welke?</div>
    <p>Gebruik een <strong>for-loop</strong> als je weet hoeveel keer. Gebruik een <strong>while-loop</strong> als je herhaalt totdat iets verandert.</p>
  </div>
</section>
<section data-title="For-loop">
  <p class="lesson-text">De for-loop itereert over een reeks waarden, zoals een lijst of een <code>range()</code>.</p>
  <div class="code-block"><span class="cm"># Print getallen 1 tot 5</span>
<span class="kw">for</span> i <span class="kw">in</span> <span class="fn">range</span>(<span class="nm">1</span>, <span class="nm">6</span>):
    <span class="fn">print</span>(<span class="st">f"Getal: {i}"</span>)

<span class="cm"># Over een lijst itereren</span>
namen = [<span class="st">"Anna"</span>, <span class="st">"Boris"</span>, <span class="st">"Clara"</span>]
<span class="kw">for</span> naam <span class="kw">in</span> namen:
    <span class="fn">print</span>(<span class="st">f"Hallo, {naam}!"</span>)</div>
  <div class="info-box info-box-blue">
    <div class="info-box-title">💡 range() uitgelegd</div>
    <p><code>range(5)</code> → 0..4 &nbsp;·&nbsp; <code>range(1,6)</code> → 1..5 &nbsp;·&nbsp; <code>range(0,10,2)</code> → 0,2,4,6,8</p>
  </div>
</section>
<section data-title="While-loop">
  <p class="lesson-text">De while-loop blijft draaien <strong>zolang een conditie waar is</strong>. Let op: vergeet de teller niet te verhogen!</p>
  <div class="code-block">teller = <span class="nm">0</span>
<span class="kw">while</span> teller &lt; <span class="nm">5</span>:
    <span class="fn">print</span>(<span class="st">f"Teller: {teller}"</span>)
    teller += <span class="nm">1</span></div>
  <div class="info-box info-box-orange">
    <div class="info-box-title">⚠️ Oneindige loop</div>
    <p>Als de conditie nooit <code>False</code> wordt stopt je programma nooit. Stop met <strong>Ctrl+C</strong>.</p>
  </div>
  <p class="lesson-text">Extra commando's: <code>break</code> stopt direct · <code>continue</code> slaat de rest over · <code>else</code> voert uit bij normale afsluiting.</p>
</section>
<section data-title="Opdrachten">
  <div class="exercise-box">
    <h4>🎯 Opdracht 1 — For-loop</h4>
    <p class="lesson-text">Schrijf een for-loop die alle even getallen van 2 t/m 20 print. Tip: <code>range(2, 21, 2)</code></p>
  </div>
  <div class="exercise-box">
    <h4>🎯 Opdracht 2 — While-loop</h4>
    <p class="lesson-text">Tel op: 1 + 2 + 3 ... Stop zodra de som &gt; 50. Print de som en het laatste opgetelde getal.</p>
  </div>
  <div class="exercise-box">
    <h4>🎯 Bonus</h4>
    <p class="lesson-text">Maak een lijst van 5 namen. Loop er overheen en print: "Hallo [naam], welkom bij Python!"</p>
  </div>
</section>`,

"python-intro": `
<section data-title="Wat is Python?">
  <p class="lesson-text">Python is een van de meest populaire programmeertalen. Het is <strong>leesbaar, veelzijdig</strong> en perfect als eerste taal.</p>
  <div class="info-box info-box-green">
    <div class="info-box-title">✅ Toepassingen</div>
    <p>Data science, web development (Django/Flask), scripting, AI/ML en games (Pygame).</p>
  </div>
</section>
<section data-title="Je eerste programma">
  <p class="lesson-text">Maak een bestand <code>hallo.py</code> en typ:</p>
  <div class="code-block"><span class="fn">print</span>(<span class="st">"Hallo, wereld!"</span>)</div>
  <p class="lesson-text">Geen puntkomma's, geen klasses — gewoon code die werkt.</p>
</section>`,

"unity-scripting": `
<section data-title="MonoBehaviour basis">
  <p class="lesson-text">In Unity schrijf je gedrag voor GameObjects met <strong>C# scripts</strong> die erven van <code>MonoBehaviour</code>.</p>
  <div class="code-block"><span class="kw">using</span> UnityEngine;
<span class="kw">public class</span> <span class="cl">MijnScript</span> : MonoBehaviour {
    <span class="kw">void</span> <span class="fn">Start</span>()  { Debug.<span class="fn">Log</span>(<span class="st">"Gestart!"</span>); }
    <span class="kw">void</span> <span class="fn">Update</span>() { <span class="cm">// elke frame</span> }
}</div>
  <div class="info-box info-box-blue">
    <div class="info-box-title">💡 Start vs Update</div>
    <p><strong>Start()</strong> = eenmalig bij opstarten. <strong>Update()</strong> = elke frame (~60x/sec).</p>
  </div>
</section>
<section data-title="Beweging programmeren">
  <p class="lesson-text">Gebruik <code>transform.position</code> met <code>Time.deltaTime</code> voor framerate-onafhankelijke beweging.</p>
  <div class="code-block"><span class="kw">public class</span> <span class="cl">PlayerMovement</span> : MonoBehaviour {
    <span class="kw">public float</span> speed = <span class="nm">5f</span>;
    <span class="kw">void</span> <span class="fn">Update</span>() {
        <span class="kw">float</span> h = Input.<span class="fn">GetAxis</span>(<span class="st">"Horizontal"</span>);
        <span class="kw">float</span> v = Input.<span class="fn">GetAxis</span>(<span class="st">"Vertical"</span>);
        transform.position +=
            <span class="kw">new</span> <span class="fn">Vector3</span>(h, <span class="nm">0</span>, v) * speed * Time.deltaTime;
    }
}</div>
  <div class="info-box info-box-orange">
    <div class="info-box-title">⚠️ Time.deltaTime</div>
    <p>Altijd gebruiken bij beweging — anders is je game framerate-afhankelijk.</p>
  </div>
</section>
<section data-title="Opdrachten">
  <div class="exercise-box">
    <h4>🎯 Opdracht 1</h4>
    <p class="lesson-text">Maak een script <code>Rotator</code> met <code>public float rotationSpeed = 90</code>. Gebruik <code>transform.Rotate(0, rotationSpeed * Time.deltaTime, 0)</code> in Update().</p>
  </div>
  <div class="exercise-box">
    <h4>🎯 Opdracht 2</h4>
    <p class="lesson-text">Breid <code>PlayerMovement</code> uit: beweeg omhoog/omlaag met Q en E via <code>Input.GetKey(KeyCode.Q)</code>.</p>
  </div>
</section>`,

"math4-verhoudingen": `
<section data-title="Wat is een verhouding?">
  <p class="lesson-text">Een <strong>verhouding</strong> geeft aan hoe twee grootheden zich tot elkaar verhouden: <em>a : b</em>.</p>
  <div class="exercise-box">
    <p class="lesson-text"><strong>Voorbeeld:</strong> Klas met 12 jongens en 8 meisjes.</p>
    <p class="lesson-text">12 : 8 → deel door GGD (4) → <strong>3 : 2</strong></p>
  </div>
</section>
<section data-title="Schalen met verhoudingen">
  <p class="lesson-text">Als de verhouding 3:2 is en er zijn 18 jongens, hoeveel meisjes?</p>
  <div class="exercise-box">
    <p class="lesson-text">18 ÷ 3 = 6 (schalingsfactor) &nbsp;→&nbsp; 6 × 2 = <strong>12 meisjes</strong></p>
  </div>
  <div class="info-box info-box-blue">
    <div class="info-box-title">💡 Tip</div>
    <p>Schrijf verhoudingen altijd vereenvoudigd. Controleer door terug te rekenen.</p>
  </div>
</section>
<section data-title="Oefeningen">
  <div class="exercise-box"><h4>🎯 Opdracht 1</h4><p class="lesson-text">Vereenvoudig: a) 15:10 &nbsp; b) 24:18 &nbsp; c) 100:75</p></div>
  <div class="exercise-box"><h4>🎯 Opdracht 2</h4><p class="lesson-text">Recept voor 4 personen: 300g bloem. Hoeveel voor 6 personen?</p></div>
  <div class="exercise-box"><h4>🎯 Opdracht 3</h4><p class="lesson-text">Zout:water = 1:19, je hebt 380ml water. Hoeveel zout? Hoeveel ml totaal?</p></div>
  <div class="info-box info-box-green"><div class="info-box-title">✅ Antwoorden</div><p>1: 3:2 / 4:3 / 4:3 &nbsp;·&nbsp; 2: 450g &nbsp;·&nbsp; 3: 20ml zout, 400ml totaal</p></div>
</section>`,

};

/* ═══════════════════════════════════════════════
   LOGIN CANVAS ANIMATION
   Floating syntax tokens drift upward at low
   opacity using the platform's code-highlight colors.
═══════════════════════════════════════════════ */
function startLoginAnimation() {
  const canvas = document.getElementById("loginCanvas");
  if (!canvas) return null;
  const ctx = canvas.getContext("2d");

  const TOKENS = [
    { text: "def",      dark: "#ff7b72", light: "#cf222e" },
    { text: "class",    dark: "#ffa657", light: "#953800" },
    { text: "return",   dark: "#ff7b72", light: "#cf222e" },
    { text: "for",      dark: "#ff7b72", light: "#cf222e" },
    { text: "if",       dark: "#ff7b72", light: "#cf222e" },
    { text: "import",   dark: "#ff7b72", light: "#cf222e" },
    { text: "print()",  dark: "#d2a8ff", light: "#8250df" },
    { text: "range()",  dark: "#d2a8ff", light: "#8250df" },
    { text: "const",    dark: "#ff7b72", light: "#cf222e" },
    { text: "let",      dark: "#ff7b72", light: "#cf222e" },
    { text: "async",    dark: "#ff7b72", light: "#cf222e" },
    { text: "=>",       dark: "#58a6ff", light: "#0550ae" },
    { text: "{ }",      dark: "#58a6ff", light: "#0550ae" },
    { text: "[ ]",      dark: "#58a6ff", light: "#0550ae" },
    { text: "public",   dark: "#ff7b72", light: "#cf222e" },
    { text: "void",     dark: "#ff7b72", light: "#cf222e" },
    { text: "new",      dark: "#ff7b72", light: "#cf222e" },
    { text: "using",    dark: "#ff7b72", light: "#cf222e" },
    { text: "//",       dark: "#8b949e", light: "#57606a" },
    { text: "#",        dark: "#8b949e", light: "#57606a" },
    { text: "true",     dark: "#ffa657", light: "#953800" },
    { text: "null",     dark: "#ffa657", light: "#953800" },
    { text: "while",    dark: "#ff7b72", light: "#cf222e" },
  ];

  function resize() {
    canvas.width  = canvas.offsetWidth;
    canvas.height = canvas.offsetHeight;
  }

  function makeParticle(fromBottom = true) {
    const t = TOKENS[Math.floor(Math.random() * TOKENS.length)];
    return {
      x:          Math.random() * canvas.width,
      y:          fromBottom ? canvas.height + 20 : Math.random() * canvas.height,
      vy:         -(0.15 + Math.random() * 0.35),
      opacity:    fromBottom ? 0 : Math.random() * 0.18,
      maxOpacity: 0.12 + Math.random() * 0.10,
      text:       t.text,
      darkColor:  t.dark,
      lightColor: t.light,
      size:       12 + Math.random() * 5,
      fadingIn:   true,
    };
  }

  resize();
  window.addEventListener("resize", resize);

  const particles = Array.from({ length: 35 }, () => makeParticle(false));

  let rafId;
  function draw() {
    ctx.clearRect(0, 0, canvas.width, canvas.height);

    for (const p of particles) {
      p.y += p.vy;

      if (p.fadingIn) {
        p.opacity += 0.0015;
        if (p.opacity >= p.maxOpacity) p.fadingIn = false;
      }

      if (p.y < -30) Object.assign(p, makeParticle(true));

      const isLight = document.documentElement.dataset.theme === 'light';
      ctx.globalAlpha = p.opacity;
      ctx.fillStyle   = isLight ? p.lightColor : p.darkColor;
      ctx.font        = `500 ${p.size}px 'JetBrains Mono', monospace`;
      ctx.fillText(p.text, p.x, p.y);
    }

    ctx.globalAlpha = 1;
    rafId = requestAnimationFrame(draw);
  }

  draw();

  return function stop() {
    cancelAnimationFrame(rafId);
    window.removeEventListener("resize", resize);
  };
}

/* ═══════════════════════════════════════════════
   GLOBAL EXPORTS
   ES modules are scoped — functions called from
   HTML onclick="" attributes must be on window.
═══════════════════════════════════════════════ */
window.toggleTheme      = toggleTheme;
window.showDashboard    = showDashboard;
window.toggleView       = toggleView;
window.closeReportModal = closeReportModal;
window.submitReport     = submitReport;
window.resolveReport    = resolveReport;
window.handleLogin      = handleLogin;
window.handleForgot     = handleForgot;
window.handleRegister   = handleRegister;
window.showLoginView    = showLoginView;
window.toggleAvatarMenu = toggleAvatarMenu;
window.handleLogout     = handleLogout;

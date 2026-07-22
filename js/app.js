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

import { initSidebar, syncSidebarActive, syncSidebarSection, toggleGroup, updateSidebarUser, getCourses, getSectionsByPage, TYPE_LABELS, updatePageStatus, updateSidebarSectionStatus, refreshCourseProgress } from './sidebar.js';
import { highlight } from './highlighter.js';


/* ── Data ────────────────────────────────────── */
let COURSES = [];

const STUDENT = {
  name:    "Student",
  initials:"ST",
  role:    "User",
  unread:  0,
  email:   "",
  xp:           0,
  level:        1,
  xpIntoLevel:  0,
  xpForNext:    500,
  weeklyXP:     0,
  streak:        0,
  longestStreak: 0,
  memberSince:   "",
};

/* ── XP curve ─────────────────────────────────
   Cost L→L+1: 500 * 1.05^(L-1)
   Total to reach L: 10000 * (1.05^(L-1) - 1)
═══════════════════════════════════════════════ */
function xpLevelFromTotal(totalXP) {
  if (totalXP <= 0) return 1;
  return Math.floor(Math.log(totalXP / 10000 + 1) / Math.log(1.05)) + 1;
}
function xpToReachLevel(level) {
  if (level <= 1) return 0;
  return Math.round(10000 * (Math.pow(1.05, level - 1) - 1));
}
function xpForNextLevel(level) {
  return Math.round(500 * Math.pow(1.05, level - 1));
}
function xpProgress(totalXP) {
  const level    = xpLevelFromTotal(totalXP);
  const base     = xpToReachLevel(level);
  const forNext  = xpForNextLevel(level);
  const into     = Math.max(0, totalXP - base);
  return {
    level,
    intoLevel: into,
    forNext,
    percent:   forNext > 0 ? Math.round(into / forNext * 100) : 0,
  };
}
// Recompute the derived fields after STUDENT.xp changes so render functions can read them directly.
function refreshStudentProgress() {
  const p = xpProgress(STUDENT.xp);
  STUDENT.level       = p.level;
  STUDENT.xpIntoLevel = p.intoLevel;
  STUDENT.xpForNext   = p.forNext;
}

/* ── State ───────────────────────────────────── */
let currentCourse   = null;
let currentLesson   = null;
let currentSlides   = [];
let currentSlideIdx = 0;
let fullView        = false;
let prevLesson      = null;
let nextLesson      = null;
let _fullViewObserver = null;
// Section ids the student has actually scrolled into view while in full view.
// Full view shows every section at once, so there's no per-slide "leave" to
// award them — we award the ones that were genuinely seen on lesson exit.
let _fullViewSeen = new Set();

/* ── Init ────────────────────────────────────── */
let _stopLoginAnim = null;

document.addEventListener("DOMContentLoaded", () => {
  const saved = sessionStorage.getItem("ict_user");
  if (saved) {
    STUDENT.name     = saved;
    STUDENT.initials = initials(saved);
    STUDENT.email    = sessionStorage.getItem("ict_email") || "";
    bootApp();
  } else {
    _stopLoginAnim = startLoginAnimation();
  }
});

async function bootApp() {
  if (_stopLoginAnim) { _stopLoginAnim(); _stopLoginAnim = null; }

  // Refresh user data from DB — validates the account still exists
  try {
    const res  = await fetch(`api/me.php?username=${encodeURIComponent(STUDENT.name)}`);
    if (!res.ok) {
      // Account gone or invalid — back to login
      sessionStorage.removeItem("ict_user");
      sessionStorage.removeItem("ict_email");
      document.getElementById("login-screen").style.display = "";
      _stopLoginAnim = startLoginAnimation();
      return;
    }
    const data     = await res.json();
    if (data.username) { STUDENT.name = data.username; STUDENT.initials = initials(data.username); }
    STUDENT.role   = data.role || STUDENT.role;
    STUDENT.unread = data.unreadNotifications ?? STUDENT.unread;
    STUDENT.email  = data.email || STUDENT.email;
    if (data.email) sessionStorage.setItem("ict_email", data.email);
    STUDENT.streak        = data.currentStreak ?? STUDENT.streak;
    STUDENT.longestStreak = data.longestStreak ?? STUDENT.longestStreak;
    STUDENT.memberSince   = data.memberSince   ?? STUDENT.memberSince;
    STUDENT.xp            = data.totalXP       ?? STUDENT.xp;
    STUDENT.level         = data.level         ?? STUDENT.level;
    STUDENT.xpIntoLevel   = data.xpIntoLevel   ?? STUDENT.xpIntoLevel;
    STUDENT.xpForNext     = data.xpForNext     ?? STUDENT.xpForNext;
    STUDENT.weeklyXP      = data.weeklyXP      ?? STUDENT.weeklyXP;
    refreshStudentProgress();
  } catch {
    // Offline / API unavailable — continue with cached data
  }

  document.getElementById("login-screen").style.display  = "none";
  document.getElementById("app-layout").style.display    = "";

  // Populate avatar menu first — name + actual account role (not initials).
  // Done before the sidebar/dashboard build so a failure there can't leave the
  // menu showing the static "Studentnaam / student" placeholders.
  document.getElementById("avatarMenuName").textContent = STUDENT.name;
  document.getElementById("avatarMenuSub").textContent  = roleLabel(STUDENT.role);
  initStudentNotifications();

  await initSidebar("sidebar-mount", loadLesson, showCourse, STUDENT.name);
  COURSES = getCourses();
  updateSidebarUser(STUDENT);
  buildDashboard();
  buildHeatmap();
  showView("dashboard");
  setTopbar("Dashboard", "Welkom terug, " + STUDENT.name + "!");
  openLessonFromDeepLink();
}

// Arrived from a "Beoordeeld" notification on another page (?course=&page=)?
// Open that lesson, then strip the params so a manual reload stays on the dashboard.
function openLessonFromDeepLink() {
  const p = new URLSearchParams(location.search);
  const courseId = p.get("course"), pageId = p.get("page");
  if (!courseId || !pageId) return;
  history.replaceState(null, "", location.pathname);
  loadLesson(courseId, pageId);
}

// Dutch display label for an account role; falls back to the raw value.
function roleLabel(role) {
  return { User: "Student", Teacher: "Docent", Superadmin: "Beheerder" }[role] || role || "Student";
}

function toggleAvatarMenu() {
  document.getElementById("avatarMenu").classList.toggle("open");
}

function handleLogout() {
  sessionStorage.removeItem("ict_user");
  sessionStorage.removeItem("ict_email");
  document.getElementById("avatarMenu").classList.remove("open");
  document.getElementById("app-layout").style.display   = "none";
  document.getElementById("login-screen").style.display = "";
  showLoginView("login");
  _stopLoginAnim = startLoginAnimation();
}

document.addEventListener("click", (e) => {
  const wrap = document.querySelector(".avatar-wrap");
  if (wrap && !wrap.contains(e.target)) {
    document.getElementById("avatarMenu")?.classList.remove("open");
  }
});

// Notifications: the bell + dashboard tile are driven by the shared
// js/notifications.js component (window.Notifications), used on every page so
// behaviour is identical. The student view passes an onItem hook so clicking a
// graded notification opens the page in-app instead of a full navigation.
function initStudentNotifications() {
  if (!window.Notifications) return;
  Notifications.init(STUDENT.name, {
    onItem: (n) => {
      // A teacher viewing the main page can have To_grade items — those belong on
      // the grading page, not the in-app lesson view.
      if (n.type === "To_grade") { window.location = "./HomeworkManager?did=" + n.did_question_id; return; }
      if (n.course_id && n.page_id) loadLesson(n.course_id, n.page_id);
    },
  });
}

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
  const emailInput = document.getElementById("accountEmail");
  emailInput.addEventListener("click",   () => { emailInput.readOnly = false; emailInput.focus(); });
  emailInput.addEventListener("blur",    () => saveAccountEmail());
  emailInput.addEventListener("keydown", (e) => { if (e.key === "Enter") emailInput.blur(); });

  document.getElementById("emailChangeModal").addEventListener("click", (e) => {
    if (e.target.id === "emailChangeModal") closeEmailModal();
  });
});

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

  elPass.addEventListener("blur", () => {
    const v = elPass.value;
    if (!v) { setHint("hintPassLen", "regPass", "", false); return; }
    if (v.length < 9) setHint("hintPassLen", "regPass", `Wachtwoord is te kort (${v.length}/9 tekens)`, false);
    else              setHint("hintPassLen", "regPass", "Wachtwoord lang genoeg ✓", true);
  });

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
      STUDENT.email    = email;
      sessionStorage.setItem("ict_user", user);
      sessionStorage.setItem("ict_email", email);
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
   STREAK BADGE
═══════════════════════════════════════════════ */
const _STREAK_FLAME  = '<svg class="flame" viewBox="0 0 24 24" fill="currentColor"><path d="M12 23a7 7 0 0 1-7-7c0-3.1 1.9-5.6 3.6-7.6.3 1.3 1.2 2.1 2.1 2.4C11.4 8.6 10 5.4 12.4 1c1 4 3.1 5.2 4.2 7.8C17.7 11 19 13.2 19 16a7 7 0 0 1-7 7z"/></svg>';
const _STREAK_TROPHY = '<svg class="flame" viewBox="0 0 24 24" fill="currentColor"><path d="M19 5h-2V3H7v2H5c-1.1 0-2 .9-2 2v1c0 2.55 1.92 4.63 4.39 4.94A5.01 5.01 0 0 0 11 18.9V21H7v2h10v-2h-4v-2.1a5.01 5.01 0 0 0 3.61-5.96C19.08 12.63 21 10.55 21 8V7c0-1.1-.9-2-2-2zM5 8V7h2v3.82C5.84 10.4 5 9.3 5 8zm14 0c0 1.3-.84 2.4-2 2.82V7h2v1z"/></svg>';

// Picks one of four states from current (c) and longest (l) streak.
// A single day is counted but not yet shown as a "streak". Threshold is 2.
function renderStreakBadge() {
  const el = document.getElementById("streakBadge");
  const c  = STUDENT.streak || 0;
  const l  = STUDENT.longestStreak || 0;
  el.className = "streak-badge";

  // Case 1: never streaked (new account). No badge at all.
  if (c < 2 && l < 2) {
    el.style.display = "none";
    el.innerHTML = "";
    el.removeAttribute("title");
    return;
  }
  el.style.display = "";

  if (c >= 2 && c >= l) {
    // Case 4: current streak is the personal record
    el.classList.add("badge-record");
    el.title = `${c} dagen op rij: persoonlijk record!`;
    el.innerHTML =
      `<span class="twin">${_STREAK_FLAME}${_STREAK_TROPHY}</span>` +
      `<span class="days">${c} dagen</span><span class="rec">record!</span>`;
  } else if (c >= 2) {
    // Case 3: active streak, record is higher
    el.classList.add("badge-fire");
    el.title = `Huidige streak: ${c} dagen. Record: ${l} dagen.`;
    el.innerHTML =
      `${_STREAK_FLAME}<span class="days">${c} dagen</span>` +
      `<span class="sep">·</span><span class="trophy">🏆 ${l}</span>`;
  } else {
    // Case 2: streak just broke, old record survives (ice cube)
    el.classList.add("badge-cold");
    el.title = `Je vorige streak van ${l} dagen is verbroken. Kom morgen terug!`;
    el.innerHTML =
      `${_STREAK_FLAME}<span class="days">Nieuwe start</span>` +
      `<span class="sep">·</span><span class="trophy">🏆 ${l}</span>`;
  }
}

// Member-since: relative buckets (vandaag, gisteren, deze/vorige week,
// deze/vorige maand), falling back to "maand jaar" once older than that.
function formatMemberSince(dateStr) {
  if (!dateStr) return "";
  const parts = dateStr.split(/[-T :]/);
  if (parts.length < 3) return "";
  const created = new Date(+parts[0], +parts[1] - 1, +parts[2]);
  if (isNaN(created)) return "";

  const today = new Date();
  today.setHours(0, 0, 0, 0);
  const dayDiff = Math.round((today - created) / 86400000);

  if (dayDiff === 0) return "Lid sinds vandaag";
  if (dayDiff === 1) return "Lid sinds gisteren";

  // Calendar week, Monday-based
  const weekStart = (d) => {
    const x = new Date(d);
    x.setDate(x.getDate() - ((x.getDay() + 6) % 7));
    x.setHours(0, 0, 0, 0);
    return x;
  };
  const thisWeek = weekStart(today);
  const lastWeek = new Date(thisWeek); lastWeek.setDate(lastWeek.getDate() - 7);
  if (created >= thisWeek) return "Lid sinds deze week";
  if (created >= lastWeek) return "Lid sinds vorige week";

  // Calendar month
  const thisMonth = new Date(today.getFullYear(), today.getMonth(),     1);
  const lastMonth = new Date(today.getFullYear(), today.getMonth() - 1, 1);
  if (created >= thisMonth) return "Lid sinds deze maand";
  if (created >= lastMonth) return "Lid sinds vorige maand";

  const months = ["jan","feb","mrt","apr","mei","jun","jul","aug","sep","okt","nov","dec"];
  return `Lid sinds ${months[created.getMonth()]} ${created.getFullYear()}`;
}

// Dashboard XP tile. Three states: new (level 1, 0 XP), active (gaining this week), idle (no XP this week).
function renderXPCard() {
  const el = document.getElementById("xpStatCard");
  if (!el) return;

  const xp        = STUDENT.xp || 0;
  const level     = STUDENT.level || 1;
  const into      = STUDENT.xpIntoLevel || 0;
  const forNext   = STUDENT.xpForNext || 1;
  const weekly    = STUDENT.weeklyXP || 0;
  const pct       = Math.max(0, Math.min(100, Math.round(into / forNext * 100)));
  const nextLevel = level + 1;

  let stateClass, trend, sparkles = "";
  if (xp === 0) {
    stateClass = "xp-new";
    trend      = "Voltooi een sectie om te beginnen";
  } else if (weekly > 0) {
    stateClass = "xp-active";
    trend      = `+${weekly} deze week`;
    sparkles   = '<span class="sparkle sp1">✦</span><span class="sparkle sp2">✦</span>';
  } else {
    stateClass = "xp-idle";
    trend      = "Geen XP deze week";
  }

  el.className = "stat-card " + stateClass;
  el.innerHTML =
    sparkles +
    `<div class="stat-icon">⚡</div>` +
    `<div class="stat-value">${xp}</div>` +
    `<div class="stat-label">XP totaal</div>` +
    `<div class="xp-card-level">Niveau ${level} → ${nextLevel}</div>` +
    `<div class="xp-card-bar"><div class="xp-card-fill" style="width:${pct}%"></div></div>` +
    `<div class="xp-card-progress-text">${into} / ${forNext} XP</div>` +
    `<div class="stat-trend">${trend}</div>`;
}

// Dashboard streak tile. Always visible (unlike the topbar badge), with 4 states.
function renderStreakCard() {
  const el = document.getElementById("streakStatCard");
  if (!el) return;
  const c = STUDENT.streak || 0;
  const l = STUDENT.longestStreak || 0;
  const since = formatMemberSince(STUDENT.memberSince);

  let stateClass, icon, value, trend, sparkles = "";

  if (c >= 2 && c >= l) {
    // Case 4: current streak is the personal record
    stateClass = "streak-record";
    icon       = "🔥";
    value      = `${c}`;
    trend      = "🏆 Persoonlijk record!";
    sparkles   = '<span class="sparkle sp1">✦</span><span class="sparkle sp2">✦</span>' +
                 '<span class="sparkle sp3">✦</span><span class="sparkle sp4">✦</span>';
  } else if (c >= 2) {
    // Case 3: active streak, record is higher
    stateClass = "streak-fire";
    icon       = "🔥";
    value      = `${c}`;
    trend      = `Record: ${l} dagen`;
  } else if (l >= 2) {
    // Case 2: streak just broke, old record survives (ice cube)
    stateClass = "streak-cold";
    icon       = "🧊";
    value      = "Nieuwe start";
    trend      = `Verbroken · record was ${l}`;
  } else {
    // Case 1: brand new, no streak yet (muted)
    stateClass = "streak-new";
    icon       = "🔥";
    value      = "Nieuwe start";
    trend      = "Start vandaag je streak!";
  }

  el.className = "stat-card " + stateClass;
  el.innerHTML =
    sparkles +
    `<div class="stat-icon">${icon}</div>` +
    `<div class="stat-value">${value}</div>` +
    `<div class="stat-label">Dag streak</div>` +
    `<div class="stat-trend">${trend}</div>` +
    `<div class="member-since">${since}</div>`;
}

/* ═══════════════════════════════════════════════
   DASHBOARD
═══════════════════════════════════════════════ */
function buildDashboard() {
  // Pull the latest completion state into the course objects so tiles reflect
  // progress made since load (e.g. returning from a lesson) without a reload.
  refreshCourseProgress();
  if (window.Notifications) Notifications.refresh();   // refresh tile + bell count
  document.getElementById("userInitials").textContent = STUDENT.initials;
  document.getElementById("userName").textContent     = STUDENT.name;
  updateSidebarUser(STUDENT);
  renderXPCard();
  renderStreakBadge();
  renderStreakCard();
  document.getElementById("topbarAvatar").textContent = STUDENT.initials;

  const grid = document.getElementById("coursesGrid");
  grid.innerHTML = "";

  // Group courses by subject/section
  const grouped = {};
  COURSES.forEach(course => {
    const key = course.section || "Overig";
    if (!grouped[key]) grouped[key] = [];
    grouped[key].push(course);
  });

  Object.entries(grouped).forEach(([sectionName, courses]) => {
    const sectionEl = el("div", "courses-section");
    const title = el("div", "courses-section-title", sectionName);
    const sectionGrid = el("div", "courses-section-grid");
    courses.forEach(course => sectionGrid.appendChild(buildCourseCard(course)));
    sectionEl.append(title, sectionGrid);
    grid.appendChild(sectionEl);
  });

  const saved = JSON.parse(sessionStorage.getItem("ict_last") || "null");
  const resumeCourse = saved ? COURSES.find(c => c.id == saved.courseId) : null;
  const resumeLesson = resumeCourse ? resumeCourse.lessons.find(l => l.id == saved.lessonId) : null;

  const destEl = document.getElementById("continueDest");
  const labelEl = document.querySelector(".continue-label");

  if (resumeCourse && resumeLesson) {
    document.getElementById("continueBtn").onclick = () => loadLesson(resumeCourse.id, resumeLesson.id, saved.sectionIdx ?? 0);
    document.getElementById("welcomeSub").textContent = `Je was bezig met ${resumeCourse.name}`;
    labelEl.textContent = "Verder leren";
    destEl.textContent = resumeLesson.title;
  } else {
    const firstCourse = COURSES[0];
    const firstLesson = firstCourse?.lessons[0];
    if (firstCourse && firstLesson) {
      document.getElementById("continueBtn").onclick = () => loadLesson(firstCourse.id, firstLesson.id);
      document.getElementById("welcomeSub").textContent = `Kies een cursus hieronder om te beginnen.`;
      labelEl.textContent = "Start leren";
      destEl.textContent = "";
    }
  }
}

/* ═══════════════════════════════════════════════
   XP OVERVIEW
═══════════════════════════════════════════════ */
async function showXPOverview() {
  document.getElementById("avatarMenu").classList.remove("open");
  if (currentLesson) { leaveCurrentLesson(); currentLesson = null; currentCourse = null; }
  setTopbar("XP overzicht", STUDENT.name);
  showView("xp");

  // Wire the filter toggles once.
  const cbOpen   = document.getElementById("xpOnlyOpen");
  const cbEarned = document.getElementById("xpOnlyEarned");
  const reload   = () => loadXPOverview(cbOpen?.checked, cbEarned?.checked);
  if (cbOpen && !cbOpen.dataset.wired) {
    cbOpen.dataset.wired = "1";
    cbOpen.addEventListener("change", reload);
  }
  if (cbEarned && !cbEarned.dataset.wired) {
    cbEarned.dataset.wired = "1";
    cbEarned.addEventListener("change", reload);
  }
  reload();
}

async function loadXPOverview(onlyOpen, onlyEarned) {
  const body = document.getElementById("xpTableBody");
  const empty = document.getElementById("xpEmpty");
  body.innerHTML = `<tr><td colspan="6" class="xp-loading">Laden…</td></tr>`;
  empty.style.display = "none";

  try {
    const q = new URLSearchParams({ username: STUDENT.name });
    if (onlyOpen)   q.set("only_open", "1");
    if (onlyEarned) q.set("only_earned", "1");
    const res = await fetch(`api/xp_overview.php?${q.toString()}`);
    if (!res.ok) throw new Error("HTTP " + res.status);
    const data = await res.json();
    renderXPOverview(data);
  } catch (err) {
    console.error("[xp-overview]", err);
    // Fall back to the empty state instead of an alarming error message —
    // the user gets a clean page, the diagnostic stays in the console.
    renderXPOverview({ summary: { earned: 0, available: 0, open: 0 }, rows: [] });
  }
}

function renderXPOverview(data) {
  // Summary cards
  const summary = data.summary || {};
  const grid = document.getElementById("xpSummaryGrid");
  grid.innerHTML = `
    <div class="xp-summary-card">
      <div class="xp-summary-icon">⚡</div>
      <div class="xp-summary-value">${summary.earned ?? 0}</div>
      <div class="xp-summary-label">Verdiend</div>
    </div>
    <div class="xp-summary-card">
      <div class="xp-summary-icon">🎯</div>
      <div class="xp-summary-value">${summary.available ?? 0}</div>
      <div class="xp-summary-label">Beschikbaar</div>
    </div>
    <div class="xp-summary-card xp-summary-open">
      <div class="xp-summary-icon">📦</div>
      <div class="xp-summary-value">${summary.open ?? 0}</div>
      <div class="xp-summary-label">Open</div>
    </div>`;

  // Rows
  const body  = document.getElementById("xpTableBody");
  const empty = document.getElementById("xpEmpty");
  const rows  = data.rows || [];
  if (!rows.length) {
    body.innerHTML = "";
    empty.style.display = "";
    return;
  }
  empty.style.display = "none";
  body.innerHTML = rows.map(r => `
    <tr class="xp-row" data-page-id="${r.page_id}">
      <td>
        <div class="xp-course-cell">
          <span class="xp-course-icon" style="${courseIconStyle(r.course_color)}">${r.course_icon || ''}</span>
          <span>${escHtml(r.course_name)}</span>
        </div>
      </td>
      <td>${escHtml(r.page_title)}</td>
      <td class="num">${r.earned_xp}</td>
      <td class="num">${r.total_xp}</td>
      <td class="num">
        ${r.has_open
          ? `<span class="xp-open-badge">${r.open_xp}</span>`
          : `<span class="xp-done-badge">✓</span>`}
      </td>
      <td class="num"><span class="xp-row-chevron">⌄</span></td>
    </tr>`).join("");

  // Clicking a row unfolds its per-section XP breakdown instead of navigating
  // to the lesson.
  body.querySelectorAll(".xp-row").forEach((row, i) => {
    row.addEventListener("click", () => toggleXPDetail(row, rows[i]));
  });
}

// Insert (or remove) the expandable detail row showing per-section rewards.
function toggleXPDetail(row, r) {
  const next = row.nextElementSibling;
  if (next && next.classList.contains("xp-detail-row")) {
    next.remove();
    row.classList.remove("expanded");
    return;
  }
  row.classList.add("expanded");
  const tr = document.createElement("tr");
  tr.className = "xp-detail-row";
  tr.innerHTML = `<td colspan="6">${buildXPDetail(r)}</td>`;
  row.after(tr);
}

// Per-section reward list for one page row: section title + earned / total XP,
// plus the page-completion bonus when the page awards one.
function buildXPDetail(r) {
  const line = (name, earned, xp, extraCls = "") => {
    const cls = xp === 0 ? "none"
              : earned >= xp ? "earned"
              : earned > 0 ? "partial"
              : "open";
    const val = xp === 0 ? "Geen XP" : `+${earned} / ${xp} XP`;
    return `<div class="xp-detail-item ${extraCls}">
      <span class="xp-detail-name">${escHtml(name)}</span>
      <span class="xp-detail-val xp-detail-${cls}">${val}</span>
    </div>`;
  };

  const items = (r.sections || []).map((s, i) =>
    line(s.title || `Sectie ${i + 1}`, s.earned, s.xp)
  );
  if (r.page_xp > 0) {
    items.push(line("Pagina voltooid", r.page_earned, r.page_xp, "xp-detail-page"));
  }
  if (!items.length) {
    items.push(`<div class="xp-detail-empty">Geen XP-onderdelen op deze pagina.</div>`);
  }
  return `<div class="xp-detail">${items.join("")}</div>`;
}

function showAccount() {
  document.getElementById("avatarMenu").classList.remove("open");
  document.getElementById("accountAvatar").textContent = STUDENT.initials;
  document.getElementById("accountName").textContent   = STUDENT.name;
  const emailEl = document.getElementById("accountEmail");
  emailEl.value    = STUDENT.email || "";
  emailEl.readOnly = true;
  setTopbar("Mijn account", STUDENT.name);
  showView("account");
}

let _pendingEmail = "";

function saveAccountEmail() {
  const emailEl  = document.getElementById("accountEmail");
  const newEmail = emailEl.value.trim();
  emailEl.readOnly = true;
  if (!newEmail || newEmail === STUDENT.email) return;
  if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(newEmail)) {
    emailEl.value = STUDENT.email || "";
    return;
  }
  _pendingEmail = newEmail;
  document.getElementById("emailModalOld").textContent = STUDENT.email || "(geen)";
  document.getElementById("emailModalNew").textContent = newEmail;
  document.getElementById("emailModalStep1").style.display = "";
  document.getElementById("emailModalStep2").style.display = "none";
  document.getElementById("emailModalPass").value  = "";
  document.getElementById("emailModalError").textContent = "";
  document.getElementById("emailChangeModal").classList.add("visible");
  document.getElementById("emailModalPass").focus;
}

function closeEmailModal() {
  document.getElementById("emailChangeModal").classList.remove("visible");
  const emailEl = document.getElementById("accountEmail");
  emailEl.value    = STUDENT.email || "";
  emailEl.readOnly = true;
  _pendingEmail    = "";
}

function emailModalNext() {
  document.getElementById("emailModalStep1").style.display = "none";
  document.getElementById("emailModalStep2").style.display = "";
  setTimeout(() => document.getElementById("emailModalPass").focus(), 50);
}

async function emailModalConfirm() {
  const pass    = document.getElementById("emailModalPass").value;
  const errEl   = document.getElementById("emailModalError");
  const saveBtn = document.getElementById("emailModalSaveBtn");
  if (!pass) { errEl.textContent = "Vul je wachtwoord in."; return; }

  saveBtn.disabled     = true;
  saveBtn.textContent  = "Opslaan…";
  errEl.textContent    = "";

  try {
    const res  = await fetch("api/update_email.php", {
      method:  "POST",
      headers: { "Content-Type": "application/json" },
      body:    JSON.stringify({ username: STUDENT.name, email: _pendingEmail, password: pass }),
    });
    const data = await res.json();
    if (res.ok) {
      STUDENT.email = _pendingEmail;
      sessionStorage.setItem("ict_email", _pendingEmail);
      document.getElementById("accountEmail").value = _pendingEmail;
      document.getElementById("emailChangeModal").classList.remove("visible");
      _pendingEmail = "";
    } else {
      errEl.textContent = data.error || "Er ging iets mis.";
    }
  } catch {
    errEl.textContent = "Geen verbinding. Probeer het opnieuw.";
  } finally {
    saveBtn.disabled    = false;
    saveBtn.textContent = "Opslaan";
  }
}

function showCourse(courseId) {
  const course = COURSES.find(c => c.id == courseId);
  if (!course) return;
  if (currentLesson) { leaveCurrentLesson(); currentLesson = null; }

  const icon = document.getElementById("courseViewIcon");
  icon.textContent  = course.icon;
  icon.className    = "course-view-icon";
  icon.style.cssText = courseIconStyle(course.color);

  document.getElementById("courseViewTitle").textContent = course.name;
  document.getElementById("courseViewSub").textContent   =
    `${course.progress.done} van ${course.progress.total} lessen voltooid`;

  document.getElementById("courseViewFill").style.width      = course.progress.pct + "%";
  document.getElementById("courseViewFill").style.background = course.progress.color;

  document.getElementById("courseViewDesc").textContent =
    course.description ||
    "Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur.";

  const sectionsByPage = getSectionsByPage();
  const list = document.getElementById("courseViewList");
  list.innerHTML = "";

  course.lessons.forEach((lesson, i) => {
    const tl       = TYPE_LABELS[lesson.type] ?? { label: lesson.type, cls: "type-lesson" };
    const sections = sectionsByPage[lesson.id] || [];

    // Page row — clicking opens the lesson
    const item = el("div", "course-page-item");
    item.addEventListener("click", () => loadLesson(course.id, lesson.id));

    const num  = el("div", "course-page-num", String(i + 1));
    const info = el("div", "course-page-info");
    const titleRow = el("div", "course-page-title-row");
    titleRow.appendChild(el("div", "course-page-title", lesson.title));
    titleRow.appendChild(el("span", "lesson-tag " + tl.cls, tl.label));
    info.appendChild(titleRow);
    if (sections.length) {
      info.appendChild(el("div", "course-page-meta", `${sections.length} onderdelen`));
    }
    item.append(num, info);
    list.appendChild(item);

    // Section rows beneath the page
    if (sections.length) {
      const secList = el("div", "course-section-list");
      sections.forEach((s, idx) => {
        const row = el("div", "course-section-row");
        row.addEventListener("click", () => loadLesson(course.id, lesson.id, idx));
        row.appendChild(el("div", "course-section-dot"));
        row.appendChild(el("div", "course-section-name", s.title));
        secList.appendChild(row);
      });
      list.appendChild(secList);
    }
  });

  setTopbar(course.name, course.section);
  showView("course");
}

function buildCourseCard(course) {
  const card = el("div", "course-card");
  card.addEventListener("click", () => showCourse(course.id));
  const header = el("div", "course-card-header");
  const tw = el("div");
  tw.append(el("div", "course-card-title", course.name), el("div", "course-card-cat", course.category));
  const cardIcon = el("div", "course-card-icon", course.icon);
  cardIcon.style.cssText = courseIconStyle(course.color);
  header.append(cardIcon, tw);

  const bar  = el("div", "progress-bar");
  const fill = el("div", "progress-fill");
  fill.style.width = course.progress.pct + "%";
  fill.style.background = course.progress.color;
  bar.appendChild(fill);

  const footer = el("div", "course-card-footer");
  const pct = el("div", "progress-pct", course.progress.pct + "%");
  pct.style.color = course.progress.color;
  footer.append(pct, el("div", "lesson-count", `${course.progress.done} van ${course.progress.total} lessen`));
  const action = el("div", "course-card-action", "Bekijk cursus →");
  card.append(header, bar, footer, action);
  return card;
}

/* ═══════════════════════════════════════════════
   HEATMAP
═══════════════════════════════════════════════ */
function buildHeatmap() {
  const c = document.getElementById("heatmap");
  if (!c) return;   // heatmap is optional — don't let a missing element abort bootApp
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

  // Fire leave-awards for whatever we're walking away from. A different lesson
  // cascades to the page; the same lesson (a sidebar section jump) still needs
  // the section we were on to be awarded before we re-paginate.
  if (currentLesson) {
    if (currentLesson.id != lessonId) leaveCurrentLesson();
    else leaveCurrentSection();
  }

  currentCourse = course;
  currentLesson = lesson;

  // Sync sidebar highlight via the sidebar module
  syncSidebarActive(courseId, lessonId, 0);

  // Record page open and mark in-progress (INSERT IGNORE keeps existing Completed value)
  fetch("api/open_page.php", {
    method:  "POST",
    headers: { "Content-Type": "application/json" },
    body:    JSON.stringify({ username: STUDENT.name, page_id: lessonId }),
  }).catch(() => {});
  updatePageStatus(lessonId, 'in-progress');

  document.getElementById("lessonBreadcrumb").innerHTML = `${course.name} <span>›</span> ${TYPE_LABELS[lesson.type].label}`;
  document.getElementById("lessonTitle").textContent = lesson.title;
  const header = document.querySelector(".lesson-view-header");
  header.className = "lesson-view-header";
  header.style.cssText = courseTintStyle(course.color);
  const tag = document.getElementById("lessonTypeTag");
  const tl  = TYPE_LABELS[lesson.type];
  tag.textContent = tl.label.charAt(0).toUpperCase() + tl.label.slice(1);
  tag.className   = "lesson-tag " + tl.cls;
  // Lesson header — duration + XP from DB. Hide when the value is 0.
  const durEl = document.getElementById("lessonDuration");
  const xpEl  = document.getElementById("lessonXP");
  if (durEl) {
    if (lesson.estimatedDuration > 0) {
      durEl.textContent = `⏱ ~${lesson.estimatedDuration} min`;
      durEl.style.display = "";
    } else {
      durEl.style.display = "none";
    }
  }
  if (xpEl) {
    if (lesson.xp > 0) {
      xpEl.textContent = `+${lesson.xp} XP bij voltooiing`;
      xpEl.style.display = "";
    } else {
      xpEl.style.display = "none";
    }
  }

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

  // Load saved quiz submissions + already-awarded sections in parallel with
  // content fetch. paginateSections renders the first slide synchronously, so
  // the submissions need to be in memory by then for wireQuizzes() to see them.
  Promise.all([
    loadLessonContent(course, lesson),
    loadQuizSubmissions(lessonId),
    loadSectionAwards(lessonId),
  ]).then(([sections]) => {
    paginateSections(sections, sectionIdx);
    applySectionAwardsToSidebar();
  });
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

  // Load section content from the database. Passing the student's name lets the
  // API serve the version they're pinned to if they finished a test here (else
  // live). Sections AND content must come from the SAME version so their section
  // ids line up — so we fetch this page's sections with the name too, rather than
  // reuse the sidebar's (always-live) cache.
  const uname = (typeof STUDENT !== 'undefined' && STUDENT && STUDENT.name) ? STUDENT.name : '';
  const uq = uname ? `&username=${encodeURIComponent(uname)}` : '';

  let dbSections = getSectionsByPage()[lesson.id];
  if (uname) {
    try {
      const sres = await fetch(`../api/sections.php?page_id=${lesson.id}${uq}`);
      if (sres.ok) {
        const rows = await sres.json();
        if (Array.isArray(rows) && rows.length) dbSections = rows;
      }
    } catch (_) {}
  }
  if (!dbSections || !dbSections.length) {
    return [{ title: null, html: '<p class="lesson-text">Inhoud voor <strong>' + lesson.title + '</strong> wordt nog toegevoegd.</p>' }];
  }

  // Fetch components for this page from the API (same version as the sections above).
  let componentsBySection = {};
  try {
    const res = await fetch(`../api/section_content.php?page_id=${lesson.id}${uq}`);
    if (res.ok) {
      const components = await res.json();
      components.forEach(c => {
        if (!componentsBySection[c.section_id]) componentsBySection[c.section_id] = [];
        componentsBySection[c.section_id].push(c);
      });
    }
  } catch (_) {}

  return dbSections.map(s => {
    const comps = componentsBySection[s.id] || [];
    // Collect question_ids so we can decide later when this section is complete.
    const questionIds = comps
      .filter(c => c.type === 'quiz')
      .map(c => { try { return JSON.parse(c.content).question_id; } catch { return null; } })
      .filter(qid => qid != null);
    return {
      section_id: s.id,
      title:      s.title,
      xp:         +s.xp_reward || 0,
      html:       buildSectionHTML(comps),
      questionIds,
    };
  });
}

// Inline XP badge for a section heading. Hidden when XP is 0.
function sectionXPBadge(xp) {
  return xp > 0 ? `<span class="section-xp-badge">+${xp} XP</span>` : '';
}

function escHtml(str) {
  return String(str)
    .replace(/&/g, '&amp;').replace(/</g, '&lt;')
    .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

/* ── Course colors ───────────────────────────────
   A course color is now a hex (#rrggbb) chosen via the admin color picker, or a
   legacy CSS class (c-python, …) for older courses. resolve → hex, then derive
   the icon gradient / tint inline so any color renders without a CSS class. */
const _LEGACY_COURSE_COLORS = {
  'c-python': '#388bfd', 'c-js': '#e3b341', 'c-java': '#f78166',
  'c-unity': '#bc8cff', 'c-vr': '#3fb950', 'c-math': '#8b949e',
};
function courseHex(c) {
  if (!c) return '#8b949e';
  if (c[0] === '#') return c;
  return _LEGACY_COURSE_COLORS[c] || '#8b949e';
}
function _courseRGB(c) {
  let h = courseHex(c).replace('#', '');
  if (h.length === 3) h = h.split('').map(x => x + x).join('');
  const n = parseInt(h, 16);
  return [(n >> 16) & 255, (n >> 8) & 255, n & 255];
}
function courseTint(c, a) { const [r, g, b] = _courseRGB(c); return `rgba(${r},${g},${b},${a})`; }
function _courseDarken(c, f) { const [r, g, b] = _courseRGB(c); return `rgb(${Math.round(r * f)},${Math.round(g * f)},${Math.round(b * f)})`; }
function courseIconStyle(c) { return `background:linear-gradient(135deg, ${courseHex(c)}, ${_courseDarken(c, 0.7)});color:#fff;`; }
function courseTintStyle(c) { return `background:${courseTint(c, 0.08)};border-color:${courseTint(c, 0.2)};`; }

function buildSectionHTML(components) {
  if (!components.length) {
    return `<div style="border:2px dashed var(--border2);border-radius:var(--radius);padding:32px;text-align:center;margin-top:8px;">
              <div style="font-size:28px;margin-bottom:8px;">📝</div>
              <div style="font-size:13px;color:var(--text2);">Inhoud wordt nog toegevoegd.</div>
            </div>`;
  }
  return components.map(c => renderComponent(c) + renderEmptySpace(c)).join('');
}

function renderEmptySpace(c) {
  const es = c.emptyspace;
  if (!es) return '';
  const before = es.before || 0;
  const after  = es.after  || 0;
  const type   = es.type   || 'nothing';
  if (type === 'nothing' && before === 0) return '';

  if (type === 'nothing') {
    return `<div class="spacer-block" style="height:${before}px;"></div>`;
  }

  return `<div class="spacer-block">
    <div style="height:${before}px;"></div>
    <hr class="spacer-line spacer-${type}">
    ${after ? `<div style="height:${after}px;"></div>` : ''}
  </div>`;
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

    case 'quiz':
      return renderQuiz(text, c.id);

    case 'multimedia':
      return renderMultimedia(text);

    default:
      return `<p class="lesson-text">${escHtml(text).replace(/\n/g, '<br>')}</p>`;
  }
}

function renderQuiz(jsonStr, componentId) {
  let data;
  try { data = JSON.parse(jsonStr); } catch { return ''; }
  // Key the DOM by question_id, not component_id — a single component can hold
  // multiple PQQuestions and we'd otherwise get duplicate ids in the page.
  const questionId = data.question_id || componentId;
  const qId = `quiz-q${questionId}`;
  const letters = ['A', 'B', 'C', 'D'];
  const letterCls = ['quiz-letter-a', 'quiz-letter-b', 'quiz-letter-c', 'quiz-letter-d'];
  // On a Test page every question shows its max points next to the label.
  const isTest = currentLesson?.type === 'test';
  const pts = data.points != null ? data.points : null;
  const pointsBadge = (isTest && pts != null)
    ? `<span class="quiz-points-badge">${pts} ${pts === 1 ? 'punt' : 'punten'}</span>` : '';

  if (data.open_question) {
    const allowDoc  = !!data.allow_document;
    const allowImg  = !!data.allow_image;
    const allowFile = allowDoc || allowImg;
    const accept = [
      ...(allowDoc ? ['.pdf', '.doc', '.docx', '.txt'] : []),
      ...(allowImg ? ['.png', '.jpg', '.jpeg'] : []),
    ].join(',');
    const kinds = [allowDoc ? 'document' : null, allowImg ? 'afbeelding' : null].filter(Boolean).join(' of ');
    const fileBlock = allowFile ? `
      <label class="quiz-input-label">Bestand toevoegen (${kinds})</label>
      <input class="quiz-file" type="file" accept="${accept}" id="${qId}-file" style="margin:4px 0 2px;color:var(--text2);font-size:13px;">` : '';
    return `<div class="quiz-box" id="${qId}" data-question-id="${questionId}" data-open="1"${allowFile ? ' data-allow-file="1"' : ''}>
      <span class="quiz-label">Vraag${pointsBadge}</span>
      <div class="quiz-question">${escHtml(data.question)}</div>
      ${data.image ? `<img class="quiz-image" src="${escHtml(data.image)}" alt="">` : ''}
      <label class="quiz-input-label">Jouw antwoord</label>
      <input class="quiz-input" type="text" placeholder="Typ je antwoord hier..." id="${qId}-input">
      ${fileBlock}
      <button class="quiz-check-btn" data-quiz-submit="${qId}">Lever in</button>
      <div class="quiz-feedback" id="${qId}-feedback"></div>
    </div>`;
  }

  const answers = data.answers || [];
  const correctCount = answers.filter(a => a.is_correct).length;
  const isMulti = correctCount > 1;
  const inputType = isMulti ? 'checkbox' : 'radio';

  const opts = answers.map((a, i) => {
    const optId = `${qId}-opt-${i}`;
    const letter = letters[i] || String.fromCharCode(65 + i);
    const cls = letterCls[i] || letterCls[0];
    return `<label class="quiz-option" id="${optId}" data-correct="${a.is_correct}" data-answer-id="${a.id}">
      <span class="quiz-letter ${cls}">${letter}</span>
      <input type="${inputType}" name="${qId}" value="${a.id}">
      <span class="quiz-option-text">${escHtml(a.text)}</span>
      <span class="quiz-radio${isMulti ? ' quiz-check' : ''}"></span>
    </label>`;
  }).join('');

  return `<div class="quiz-box" id="${qId}" data-question-id="${questionId}" data-multi="${isMulti ? '1' : '0'}">
    <span class="quiz-label">Vraag${pointsBadge}</span>
    ${isMulti ? '<div class="quiz-hint">Meerdere antwoorden zijn juist</div>' : ''}
    <div class="quiz-question">${escHtml(data.question)}</div>
    ${data.image ? `<img class="quiz-image" src="${escHtml(data.image)}" alt="">` : ''}
    <div class="quiz-options">${opts}</div>
    <button class="quiz-check-btn" data-quiz-submit="${qId}">Controleer →</button>
    <div class="quiz-feedback" id="${qId}-feedback"></div>
  </div>`;
}

function renderMultimedia(jsonStr) {
  let data;
  try { data = JSON.parse(jsonStr); } catch { return ''; }

  // Prefer uploaded file path over URL when present
  const src = data.uploaded || data.url || '';
  const type = data.media_type || '';
  if (!src) return '';

  const mid = 'media-' + Math.random().toString(36).slice(2, 9);
  const typeWord = type === 'video' ? 'video' : (type === 'audio' ? 'audio' : 'afbeelding');

  // Broken-media fallback (shown via onerror)
  const brokenHtml = `
    <div class="media-broken" id="${mid}-broken" style="display:none">
      <div class="media-broken-icon">⚠️</div>
      <div class="media-broken-text">
        <strong>Oeps!</strong> De ${typeWord} die je zocht bestaat niet meer.
        <div class="media-broken-sub">Waarschuw een docent door op de rode vlag te klikken!</div>
      </div>
      <button class="media-broken-flag" title="Docent waarschuwen" onclick="reportBrokenMedia('${escHtml(src)}','${typeWord}')">🚩</button>
    </div>`;

  if (type === 'image') {
    return `<div class="media-block media-image">
      <img id="${mid}" src="${escHtml(src)}" alt="" loading="lazy"
        onerror="this.style.display='none';document.getElementById('${mid}-broken').style.display='flex';">
      ${brokenHtml}
    </div>`;
  }

  if (type === 'video') {
    // For YouTube URLs, embed with iframe (can't detect broken via onerror, but URL check below helps)
    const ytMatch = src.match(/(?:youtube\.com\/watch\?v=|youtu\.be\/)([a-zA-Z0-9_-]+)/);
    if (ytMatch) {
      const embedUrl = `https://www.youtube.com/embed/${ytMatch[1]}`;
      return `<div class="media-block media-video">
        <iframe src="${escHtml(embedUrl)}" allowfullscreen loading="lazy"></iframe>
      </div>`;
    }
    // For direct video files, we can catch errors
    return `<div class="media-block media-video-direct">
      <video id="${mid}" controls src="${escHtml(src)}"
        onerror="this.style.display='none';document.getElementById('${mid}-broken').style.display='flex';"></video>
      ${brokenHtml}
    </div>`;
  }

  if (type === 'audio') {
    return `<div class="media-block media-audio">
      <audio id="${mid}" controls src="${escHtml(src)}"
        onerror="this.style.display='none';document.getElementById('${mid}-broken').style.display='flex';"></audio>
      ${brokenHtml}
    </div>`;
  }

  return '';
}

function reportBrokenMedia(src, typeWord) {
  alert(`Bedankt! Een docent wordt op de hoogte gebracht dat deze ${typeWord} niet meer werkt.\n\n(${src})`);
}

// Map of question_id -> { picked_answer_ids: number[], open_answer: string|null }
// Populated from the API in loadLesson, written to after each submit.
let _quizSubmissions = {};

async function loadQuizSubmissions(pageId) {
  _quizSubmissions = {};
  if (!STUDENT.name || !pageId) return;
  try {
    const res = await fetch(`api/quiz_answers.php?username=${encodeURIComponent(STUDENT.name)}&page_id=${pageId}`);
    if (!res.ok) return;
    const rows = await res.json();
    rows.forEach(r => {
      _quizSubmissions[r.question_id] = {
        picked_answer_ids: r.picked_answer_ids || [],
        open_answer:       r.open_answer,
        file_name:         r.file_name,
        file_path:         r.file_path,
        verdict:           r.verdict,       // 'none' | 'V' | 'X'
        feedback:          r.feedback,
      };
    });
  } catch {}
}

function wireQuizzes() {
  document.querySelectorAll('.quiz-check-btn[data-quiz-submit]').forEach(btn => {
    if (btn.dataset.wired === '1') return;
    btn.dataset.wired = '1';
    btn.addEventListener('click', () => submitQuiz(btn.dataset.quizSubmit));
  });
  // Replay any prior submissions so returning students see their locked answer.
  document.querySelectorAll('.quiz-box[data-question-id]').forEach(box => {
    const qid = parseInt(box.dataset.questionId, 10);
    const saved = _quizSubmissions[qid];
    if (saved) applyQuizState(box, saved);
  });
}

function submitQuiz(qId) {
  const box = document.getElementById(qId);
  if (!box) return;
  const questionId = parseInt(box.dataset.questionId, 10);
  const isOpen    = box.dataset.open === '1';

  if (isOpen) {
    submitOpenQuiz(box, qId, questionId);
    return;
  }

  const isMulti   = box.dataset.multi === '1';
  const inputType = isMulti ? 'checkbox' : 'radio';
  const checked   = Array.from(box.querySelectorAll(`input[type="${inputType}"]:checked`));
  const fb        = document.getElementById(qId + '-feedback');
  if (!checked.length) { fb.textContent = 'Selecteer een antwoord.'; fb.className = 'quiz-feedback'; return; }

  const pickedIds = checked.map(c => parseInt(c.value, 10)).filter(n => !isNaN(n));
  persistQuiz(questionId, { picked_answer_ids: pickedIds }).then(() => {
    _quizSubmissions[questionId] = { picked_answer_ids: pickedIds, open_answer: null };
    applyQuizState(box, _quizSubmissions[questionId]);
    maybeMarkSectionOnQuizAnswered(questionId);
    refreshTestResult();   // keep the Test result panel in sync
  });
}

// Open-question submit: optionally upload a file first, then persist. A submission
// is valid with text, a file, or both — but never empty.
async function submitOpenQuiz(box, qId, questionId) {
  const input     = box.querySelector('.quiz-input');
  const fileInput = box.querySelector('.quiz-file');
  const fb        = document.getElementById(qId + '-feedback');
  const btn       = box.querySelector('.quiz-check-btn');
  const text      = input ? input.value.trim() : '';
  const file      = fileInput && fileInput.files && fileInput.files[0] ? fileInput.files[0] : null;

  if (!text && !file) {
    fb.textContent = box.dataset.allowFile === '1'
      ? 'Typ een antwoord of voeg een bestand toe.'
      : 'Typ een antwoord.';
    fb.className = 'quiz-feedback';
    return;
  }

  let fileInfo = null;
  if (file) {
    if (btn) btn.disabled = true;
    fb.className   = 'quiz-feedback';
    fb.textContent = 'Bestand uploaden…';
    try {
      const fd = new FormData();
      fd.append('file', file);
      fd.append('question_id', questionId);
      const res  = await fetch('api/upload_submission.php', { method: 'POST', body: fd });
      const data = await res.json();
      if (!res.ok) {
        fb.textContent = data.error || 'Uploaden mislukt.';
        fb.className   = 'quiz-feedback wrong';
        if (btn) btn.disabled = false;
        return;
      }
      fileInfo = { file_name: data.original_name, file_path: data.path };
    } catch {
      fb.textContent = 'Uploaden mislukt.';
      fb.className   = 'quiz-feedback wrong';
      if (btn) btn.disabled = false;
      return;
    }
  }

  const record = {
    picked_answer_ids: [],
    open_answer: text,
    file_name:   fileInfo ? fileInfo.file_name : null,
    file_path:   fileInfo ? fileInfo.file_path : null,
  };
  await persistQuiz(questionId, record);
  _quizSubmissions[questionId] = record;
  applyQuizState(box, record);
  maybeMarkSectionOnQuizAnswered(questionId);
  refreshTestResult();   // keep the Test result panel in sync
}

// If this question was the last unanswered one in its section, optimistically
// turn the sidebar marker green. The actual XP award still happens on leave
// (so the green appears now and the toast appears when the student navigates).
function maybeMarkSectionOnQuizAnswered(questionId) {
  const section = _currentSections.find(s => s && s.questionIds.includes(questionId));
  if (!section || !section.id) return;
  if (_completedSectionIds.has(section.id)) return;
  const allDone = section.questionIds.every(qid => _quizSubmissions[qid]);
  if (allDone) updateSidebarSectionStatus(section.id, 'done');
}

async function persistQuiz(questionId, payload) {
  if (!STUDENT.name) return;
  try {
    await fetch('api/quiz_submit.php', {
      method:  'POST',
      headers: { 'Content-Type': 'application/json' },
      body:    JSON.stringify({
        username:           STUDENT.name,
        question_id:        questionId,
        picked_answer_ids:  payload.picked_answer_ids || [],
        open_answer:        payload.open_answer ?? null,
        file_name:          payload.file_name ?? null,
        file_path:          payload.file_path ?? null,
      }),
    });
  } catch {}
}

/* ═══════════════════════════════════════════════
   SECTION / PAGE COMPLETION + XP AWARDS
═══════════════════════════════════════════════ */
// Built from the loaded lesson; { id, questionIds } per section in slide order.
let _currentSections   = [];
let _currentSectionIdx = null;  // index in _currentSections of the slide on screen, or null
let _sectionEnterTime  = 0;     // Date.now() when the visible section last changed
// True once the student clicked the footer button on the last slide while
// sections were still unfinished — they've been redirected to mop them up, so
// the footer shows the guided "next unfinished section / page done" button.
let _redirectedToUnfinished = false;
// section_ids already in UserXPLog for the current page — used to restore green marks.
let _completedSectionIds = new Set();

// Minimum time a student must spend on a text-only section before it awards XP.
// Stops a quick scroll-through from handing out points. Quiz sections are gated
// by their answers, not by time.
const SECTION_MIN_DWELL_MS = 3000;

async function loadSectionAwards(pageId) {
  _completedSectionIds = new Set();
  if (!STUDENT.name || !pageId) return;
  try {
    const res = await fetch(`api/section_awards.php?username=${encodeURIComponent(STUDENT.name)}&page_id=${pageId}`);
    if (!res.ok) return;
    const ids = await res.json();
    _completedSectionIds = new Set(ids.map(Number));
  } catch {}
}

function applySectionAwardsToSidebar() {
  _completedSectionIds.forEach(id => updateSidebarSectionStatus(id, 'done'));
  // Also paint sections whose quizzes are all answered (optimistic — they'll
  // get logged on the next leave/award round-trip, but the marker shows now).
  _currentSections.forEach(s => {
    if (!s || !s.id) return;
    if (_completedSectionIds.has(s.id)) return;
    if (s.questionIds.length === 0) return;  // text-only: only mark after award
    if (s.questionIds.every(qid => _quizSubmissions[qid])) {
      updateSidebarSectionStatus(s.id, 'done');
    }
  });
}

function markSectionCompleteVisually(sectionId) {
  if (!sectionId) return;
  _completedSectionIds.add(sectionId);
  updateSidebarSectionStatus(sectionId, 'done');
}

function setCurrentSections(sections) {
  _currentSections = sections.map(s => ({
    id:          s.section_id || null,
    questionIds: s.questionIds || [],
  }));
  _currentSectionIdx = null;
}

// Called when the visible slide changes. Fires the leave-award for the previous
// slide's section if there was one.
function setVisibleSectionIdx(newIdx) {
  if (_currentSectionIdx === newIdx) return;
  const prev      = _currentSectionIdx;
  const prevEnter = _sectionEnterTime;
  _currentSectionIdx = newIdx;
  _sectionEnterTime  = Date.now();
  if (prev !== null && _currentSections[prev]) {
    maybeAwardSection(_currentSections[prev], Date.now() - prevEnter);
  }
}

// Called from loadLesson + showDashboard before tearing down the current
// lesson. Awaits the section award so the in-transaction cascade has a chance
// to fire the page award too. Only falls back to a page-only call when there
// was no visible section (e.g. full view).
async function leaveCurrentLesson() {
  const lessonId = currentLesson?.id;
  const visible  = _currentSectionIdx !== null ? _currentSections[_currentSectionIdx] : null;
  const dwell    = _sectionEnterTime ? Date.now() - _sectionEnterTime : 0;
  const sections = _currentSections;   // capture before reset
  const seen     = _fullViewSeen;
  _currentSectionIdx = null;
  _currentSections   = [];

  // Section view: award the slide we're leaving (text is dwell-gated).
  if (visible) await maybeAwardSection(visible, dwell);

  // Full view: award each section the student actually scrolled into view.
  // maybeAwardSection still enforces completion, so quiz sections only count
  // once answered; text sections count because they were genuinely seen.
  for (const s of sections) {
    if (s && s.id && seen.has(s.id)) await maybeAwardSection(s, Infinity);
  }

  // Page award — section awards already cascade, but this also covers the
  // "everything was already awarded" and 0-section cases.
  if (lessonId) await postAwardXP({ page_id: lessonId });
}

// Fire the leave-award for the visible section when staying in the same lesson
// but jumping to another section (e.g. a sidebar section click re-runs
// loadLesson, which then re-paginates and resets the index).
function leaveCurrentSection() {
  const visible = _currentSectionIdx !== null ? _currentSections[_currentSectionIdx] : null;
  if (visible) {
    const dwell = _sectionEnterTime ? Date.now() - _sectionEnterTime : 0;
    maybeAwardSection(visible, dwell);
  }
  _currentSectionIdx = null;
}

async function maybeAwardSection(section, dwellMs = Infinity) {
  if (!section || !section.id) return;
  const isTextOnly = section.questionIds.length === 0;
  const complete   = isTextOnly
                  || section.questionIds.every(qid => _quizSubmissions[qid]);
  if (!complete) return;
  // Text-only sections only count once the student has actually spent time on
  // them — a sub-5s scroll-through shouldn't hand out XP. Quiz sections are
  // gated by their answers, so they ignore the dwell time.
  if (isTextOnly && dwellMs < SECTION_MIN_DWELL_MS) return;
  // Mark complete right away — optimistically logging it locally keeps the
  // guided-navigation state consistent (no false "unfinished" while the POST is
  // still in flight) and gives the user feedback even for 0-XP sections.
  _completedSectionIds.add(section.id);
  updateSidebarSectionStatus(section.id, 'done');
  await postAwardXP({ section_id: section.id });
}

/* ── Guided "finish remaining sections" navigation ───
   A section counts as done once it's been awarded (text sections, after the
   dwell-leave) or all its quizzes are answered. */
function isSectionComplete(section) {
  if (!section || !section.id) return true;  // untracked → can't block progress
  if (_completedSectionIds.has(section.id)) return true;
  if (section.questionIds.length > 0) {
    return section.questionIds.every(qid => _quizSubmissions[qid]);
  }
  return false;  // text-only, not yet dwelled + left
}

// Index of the first section (in slide order) that isn't complete, or -1.
// skipIdx ignores a slide — pass the current one, since leaving it completes it.
function firstUnfinishedSectionIdx(skipIdx = -1) {
  for (let i = 0; i < _currentSections.length; i++) {
    if (i === skipIdx) continue;
    if (!isSectionComplete(_currentSections[i])) return i;
  }
  return -1;
}

function sectionName(idx) {
  return currentSlides[idx]?.[0]?.title || `Sectie ${idx + 1}`;
}

// One endpoint, two possible parameters. Backend awards section if section_id
// is given (and cascades to the page in the same transaction), then awards
// the page if page_id is given. Calling with both is fine and idempotent.
async function postAwardXP(payload) {
  if (!STUDENT.name) return;
  try {
    const res = await fetch('api/award_xp.php', {
      method:  'POST',
      headers: { 'Content-Type': 'application/json' },
      body:    JSON.stringify({ username: STUDENT.name, ...payload }),
    });
    if (!res.ok) {
      console.warn('[award_xp]', 'HTTP', res.status);
      return;
    }
    handleAwardResponse(await res.json());
  } catch (err) {
    console.warn('[award_xp]', err);
  }
}


function handleAwardResponse(data) {
  if (data.section_award) {
    showXPToast(data.section_award.xp, 'Sectie voltooid!');
    markSectionCompleteVisually(data.section_award.section_id);
  }
  if (data.page_award) {
    showXPToast(data.page_award.xp, 'Pagina voltooid!');
    updatePageStatus(data.page_award.page_id, 'done');
  }
  if (typeof data.total_xp === 'number') {
    const gained = Math.max(0, data.total_xp - STUDENT.xp);
    STUDENT.xp        = data.total_xp;
    STUDENT.level     = data.level || STUDENT.level;
    STUDENT.weeklyXP  = (STUDENT.weeklyXP || 0) + gained;
    refreshStudentProgress();
    updateSidebarUser(STUDENT);
    renderXPCard();  // keep the dashboard tile in sync too
  }
}

// Top-of-viewport toast, slides down, auto-dismisses. Multiple awards stack.
function showXPToast(amount, label) {
  const layer = (() => {
    let l = document.getElementById('xpToastLayer');
    if (!l) {
      l = document.createElement('div');
      l.id = 'xpToastLayer';
      l.className = 'xp-toast-layer';
      document.body.appendChild(l);
    }
    return l;
  })();
  const t = el('div', 'xp-toast');
  t.innerHTML = `<span class="xp-toast-icon">⚡</span><span class="xp-toast-amount">+${amount} XP</span><span class="xp-toast-label">${label}</span>`;
  layer.appendChild(t);
  requestAnimationFrame(() => t.classList.add('visible'));
  setTimeout(() => {
    t.classList.remove('visible');
    setTimeout(() => t.remove(), 350);
  }, 3200);
}

// Render a quiz box into its post-submit state: lock inputs, highlight, show feedback.
function applyQuizState(box, saved) {
  const qId  = box.id;
  const fb   = document.getElementById(qId + '-feedback');
  const btn  = box.querySelector('.quiz-check-btn');
  if (btn) btn.disabled = true;

  if (box.dataset.open === '1') {
    const input = box.querySelector('.quiz-input');
    if (input) {
      input.value    = saved.open_answer || '';
      input.disabled = true;
    }
    // Lock the picker and show the handed-in file as a download link (once).
    const fileInput = box.querySelector('.quiz-file');
    if (fileInput) { fileInput.disabled = true; fileInput.style.display = 'none'; }
    if (saved.file_path && !box.querySelector('.quiz-file-link')) {
      const link = document.createElement('a');
      link.className   = 'quiz-file-link';
      link.href        = saved.file_path;
      link.target      = '_blank';
      link.rel         = 'noopener';
      link.textContent = '📎 ' + (saved.file_name || 'Bestand');
      link.style.cssText = 'display:inline-block;margin:8px 0;color:var(--accent,#388bfd);font-size:13px;text-decoration:none;';
      if (fb && fb.parentNode) fb.parentNode.insertBefore(link, fb);
    }
    if (fb) {
      const v = saved.verdict;
      const fbBlock = saved.feedback
        ? `<div class="quiz-review-feedback"><span class="quiz-review-label">Feedback docent</span>${escHtml(saved.feedback)}</div>`
        : '';
      if (v === 'V' || v === 'X') {
        fb.className   = 'quiz-feedback ' + (v === 'V' ? 'correct' : 'wrong');
        fb.innerHTML   = `Beoordeeld: <strong>${v === 'V' ? 'voldoende' : 'onvoldoende'}</strong>` + fbBlock;
      } else {
        fb.className   = 'quiz-feedback';
        fb.textContent = 'Antwoord ingeleverd — wordt nagekeken.';
      }
    }
    return;
  }

  const isMulti   = box.dataset.multi === '1';
  const inputType = isMulti ? 'checkbox' : 'radio';
  const pickedSet = new Set(saved.picked_answer_ids || []);

  box.querySelectorAll(`input[type="${inputType}"]`).forEach(inp => {
    inp.disabled = true;
    if (pickedSet.has(parseInt(inp.value, 10))) inp.checked = true;
  });

  let gotAllCorrect = true;
  let noWrong       = true;
  const correctOpts = box.querySelectorAll('.quiz-option[data-correct="1"]');

  box.querySelectorAll('.quiz-option').forEach(opt => {
    const aid       = parseInt(opt.dataset.answerId, 10);
    const isCorrect = opt.dataset.correct === '1';
    const wasPicked = pickedSet.has(aid);
    if (isCorrect) opt.classList.add('correct');
    else if (wasPicked) { opt.classList.add('wrong'); noWrong = false; }
    if (isCorrect && !wasPicked) gotAllCorrect = false;
  });
  if (!correctOpts.length) gotAllCorrect = false;

  if (fb) {
    if (gotAllCorrect && noWrong) {
      fb.textContent = 'Goed gedaan!'; fb.className = 'quiz-feedback correct';
    } else {
      fb.textContent = 'Helaas, dat is niet juist.'; fb.className = 'quiz-feedback wrong';
    }
  }
}

function allQuizzesAnswered() {
  const quizzes = document.querySelectorAll('.quiz-box');
  if (!quizzes.length) return true;
  for (const box of quizzes) {
    const btn = box.querySelector('.quiz-check-btn');
    // If button is still enabled, the quiz hasn't been submitted
    if (btn && !btn.disabled) {
      showQuizWarning();
      return false;
    }
  }
  return true;
}

function showQuizWarning() {
  // Remove existing warning if present
  let overlay = document.getElementById('quiz-warning-overlay');
  if (overlay) overlay.remove();
  overlay = document.createElement('div');
  overlay.id = 'quiz-warning-overlay';
  overlay.className = 'quiz-warning-overlay';
  overlay.innerHTML = `<div class="quiz-warning-box">
    <div class="quiz-warning-title">Niet alle vragen beantwoord</div>
    <p class="quiz-warning-text">Je hebt nog niet alle vragen beantwoord. Beantwoord eerst alle vragen voordat je verder gaat.</p>
    <button class="btn btn-primary" id="quiz-warning-close">Begrepen</button>
  </div>`;
  document.body.appendChild(overlay);
  document.getElementById('quiz-warning-close').addEventListener('click', () => overlay.remove());
  overlay.addEventListener('click', (e) => { if (e.target === overlay) overlay.remove(); });
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

  // Unfinished sections elsewhere on this page (the current slide is excluded —
  // moving off it will complete it anyway).
  const unfinishedIdx = firstUnfinishedSectionIdx(currentSlideIdx);

  const goToSection = (idx) => {
    if (!allQuizzesAnswered()) return;
    renderSlide(idx);
    document.querySelector(".main").scrollTo({ top: 0, behavior: "smooth" });
  };

  // Guided mode — the student was redirected to mop up skipped sections. The
  // button either points to the next unfinished one or, once the page is fully
  // done, advances to the next onderdeel (or back to the dashboard).
  // Skipped in full view: every section is already on screen, so a redirect to
  // an "unfinished" one is meaningless — the button is just next page / dashboard.
  if (!fullView && _redirectedToUnfinished) {
    if (unfinishedIdx !== -1) {
      nav.innerHTML = `<div class="next-page-wrap guided-nav">
        <div class="guided-nav-msg">Je hebt nog niet alle onderdelen op deze pagina afgerond.</div>
        <button class="btn btn-primary next-page-btn" id="btnNext">Volgende onderdeel: ${sectionName(unfinishedIdx)} →</button>
      </div>`;
      document.getElementById("btnNext").addEventListener("click", () => goToSection(unfinishedIdx));
    } else {
      const label = nextLesson ? "Ga naar volgend onderdeel →" : "← Terug naar dashboard";
      nav.innerHTML = `<div class="next-page-wrap guided-nav guided-nav-done">
        <div class="guided-nav-msg">Je hebt alle onderdelen op deze pagina afgerond! 🎉</div>
        <button class="btn btn-primary next-page-btn" id="btnNext">${label}</button>
      </div>`;
      document.getElementById("btnNext").addEventListener("click", () => {
        if (!allQuizzesAnswered()) return;
        _redirectedToUnfinished = false;
        if (nextLesson) loadLesson(currentCourse.id, nextLesson.id);
        else showDashboard();
      });
    }
    return;
  }

  // Normal mode (reached on the last slide): if sections are still unfinished,
  // redirect to the first one and label the button with its name instead of
  // advancing to the next page. Not in full view — see note above.
  if (!fullView && unfinishedIdx !== -1) {
    nav.innerHTML = `<div class="next-page-wrap">
      <button class="btn btn-primary next-page-btn" id="btnNext">Ga naar: ${sectionName(unfinishedIdx)} →</button>
    </div>`;
    document.getElementById("btnNext").addEventListener("click", () => {
      if (!allQuizzesAnswered()) return;
      _redirectedToUnfinished = true;
      goToSection(unfinishedIdx);
    });
    return;
  }

  if (nextLesson) {
    nav.innerHTML = `<div class="next-page-wrap">
      <button class="btn btn-primary next-page-btn" id="btnNext">Ga naar volgend onderdeel →</button>
    </div>`;
    document.getElementById("btnNext").addEventListener("click", () => {
      if (!allQuizzesAnswered()) return;
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
  _redirectedToUnfinished = false;  // fresh page — start outside guided mode
  _fullViewSeen = new Set();        // fresh page — nothing seen in full view yet
  const btn = document.getElementById("viewToggleBtn");
  if (btn) btn.textContent = "Switch to full view";
  // One section per slide. A Test gets an extra terminal slide: the result
  // summary, reached by navigating past the last section.
  currentSlides = sections.map(s => [s]);
  if (currentLesson?.type === 'test') currentSlides.push([SUMMARY_SLIDE]);
  setCurrentSections(sections);   // real sections only — the summary isn't awardable
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
  const allSections = currentSlides.flat().filter(s => !s.__summary);
  const sectionsHTML = allSections.map((s, globalIdx) => {
    const sectionKey   = `${currentCourse.id}__${currentLesson.id}__${globalIdx}`;
    const reportCount  = getReportCount(sectionKey);
    const reportedClass = reportCount > 0 ? " reported" : "";
    return `
    <div class="page-section" data-section-key="${sectionKey}">
      <div class="section-header-row">
        ${s.title ? `<h3 class="section-heading">${s.title}${sectionXPBadge(s.xp)}</h3>` : `<div>${sectionXPBadge(s.xp)}</div>`}
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
  wireQuizzes();
  updateNextPageNav();
  updatePrevVisibility();

  // Highlight sidebar section on scroll
  if (_fullViewObserver) _fullViewObserver.disconnect();
  const sections = el.querySelectorAll(".page-section");
  _fullViewObserver = new IntersectionObserver(entries => {
    entries.forEach(entry => {
      if (entry.isIntersecting) {
        const idx = Array.from(sections).indexOf(entry.target);
        if (idx >= 0 && currentLesson) syncSidebarSection(currentLesson.id, idx);
        // Remember this section was genuinely scrolled into view so we can award
        // it (per section) when the student leaves the lesson.
        if (idx >= 0 && _currentSections[idx]?.id) _fullViewSeen.add(_currentSections[idx].id);
      }
    });
  }, { root: document.querySelector(".main"), threshold: 0.3 });
  sections.forEach(s => _fullViewObserver.observe(s));

  // Full view shows every section at once but not the result — offer a button
  // that jumps to the summary slide (in section view).
  if (currentLesson?.type === 'test') {
    const cta = document.createElement('div');
    cta.className = 'test-summary-cta';
    cta.innerHTML = `<button class="btn btn-primary" onclick="goToTestSummary()">Naar resultaat →</button>`;
    el.appendChild(cta);
  }
}

// Jump to the Test result slide (leaving full view if active).
function goToTestSummary() {
  const idx = currentSlides.findIndex(isSummarySlide);
  if (idx < 0) return;
  if (fullView) {
    fullView = false;
    const btn = document.getElementById("viewToggleBtn");
    if (btn) btn.textContent = "Switch to full view";
  }
  renderSlide(idx);
  document.querySelector(".main").scrollTo({ top: 0, behavior: "smooth" });
}
window.goToTestSummary = goToTestSummary;

/* ── Test (Toets) result slide ─────────────────────────────
   On a Test page the score/grade summary is its OWN final slide — reached with
   the normal "next" navigation after the last content section (and via a button
   in full view). Values come from api/test_result.php. */
const SUMMARY_SLIDE = { __summary: true };
function isSummarySlide(slide) { return !!(slide && slide.length === 1 && slide[0] && slide[0].__summary); }

// Inner HTML for the summary slide: a heading + the (async-filled) result card.
function summarySlideHTML() {
  return `<div class="page-section test-summary-section">
      <h3 class="section-heading">Resultaat</h3>
      <div id="testResultPanel" class="test-result-card"><div class="tr-foot">Resultaat laden…</div></div>
    </div>`;
}

async function refreshTestResult() {
  const el = document.getElementById('testResultPanel');
  if (!el || currentLesson?.type !== 'test' || !STUDENT) return;
  let d;
  try {
    const r = await fetch(`../api/test_result.php?username=${encodeURIComponent(STUDENT.name)}&page_id=${currentLesson.id}`);
    if (!r.ok) return;
    d = await r.json();
  } catch (_) { return; }
  if (!d || d.error) return;

  const nl    = n => (n == null ? '—' : String(Math.round(n * 100) / 100).replace('.', ','));
  const grade = g => (g == null ? '—' : g.toFixed(1).replace('.', ','));
  const passed = d.grade_current != null && d.grade_current >= d.pass_line;
  const hasPending = d.pending_points > 0;
  const unanswered = Math.max(0, d.question_count - d.answered_count);

  // A student who completed this test is frozen on the version they did; once you
  // publish a newer version, tell them they're looking at the one they finished.
  const oldVersionBanner = d.old_version
    ? `<div class="tr-old-version">Je bekijkt de versie die je hebt afgerond. Er is inmiddels een nieuwere versie van deze toets.</div>`
    : '';

  el.innerHTML = `
    <div class="test-result-head">Resultaat</div>
    ${oldVersionBanner}
    <div class="tr-grid">
      <div class="tr-row"><span>Behaalde punten</span><strong>${nl(d.earned_points)} / ${nl(d.max_points)}</strong></div>
      ${hasPending ? `<div class="tr-row"><span>Nog na te kijken</span><strong>${nl(d.pending_points)} punten</strong></div>` : ''}
      <div class="tr-row tr-grade ${passed ? 'pass' : 'fail'}"><span>Huidig cijfer</span><strong>${grade(d.grade_current)}</strong></div>
      ${hasPending ? `<div class="tr-row tr-grade-possible"><span>Mogelijk cijfer</span><strong>${grade(d.grade_possible)}</strong></div>` : ''}
    </div>
    <div class="tr-foot">Voldoende vanaf ${grade(d.pass_line)}${unanswered > 0 ? ` · nog ${unanswered} vraag/vragen open` : ''}</div>`;
}

function renderSlide(idx) {
  currentSlideIdx = Math.max(0, Math.min(idx, currentSlides.length - 1));
  // Track which section the student is now looking at — this fires the
  // leave-award for the previous slide's section.
  setVisibleSectionIdx(currentSlideIdx);

  // Update saved position with current section index
  if (currentCourse && currentLesson) {
    sessionStorage.setItem("ict_last", JSON.stringify({
      courseId: currentCourse.id, lessonId: currentLesson.id, sectionIdx: currentSlideIdx
    }));
  }

  document.getElementById("lessonContent")?.classList.remove("full-view");
  if (currentLesson) syncSidebarSection(currentLesson.id, currentSlideIdx);
  const slide  = currentSlides[currentSlideIdx];
  const total  = currentSlides.length;
  const isLast = currentSlideIdx === total - 1;
  const isFirst= currentSlideIdx === 0;
  const isSummary = isSummarySlide(slide);

  const sectionsHTML = isSummary ? summarySlideHTML() : slide.map((s, i) => {
    const globalIdx = currentSlides.slice(0, currentSlideIdx).reduce((a, sl) => a + sl.length, 0) + i;
    const sectionKey = `${currentCourse.id}__${currentLesson.id}__${globalIdx}`;
    const reportCount = getReportCount(sectionKey);
    const reportedClass = reportCount > 0 ? " reported" : "";
    return `
    <div class="page-section" data-section-key="${sectionKey}">
      <div class="section-header-row">
        ${s.title ? `<h3 class="section-heading">${s.title}${sectionXPBadge(s.xp)}</h3>` : `<div>${sectionXPBadge(s.xp)}</div>`}
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
    wireQuizzes();
    updateSlideCounter(0, 1);
    updateNextPageNav();
    updatePrevVisibility();
    return;
  }

  const progressPct = Math.round(((currentSlideIdx + 1) / total) * 100);

  const dots = Array.from({ length: total }, (_, i) => {
    const sectionTitle = isSummarySlide(currentSlides[i]) ? 'Resultaat' : (currentSlides[i]?.[0]?.title || `Sectie ${i + 1}`);
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
  wireQuizzes();
  if (isSummary) refreshTestResult();

  // Wire nav buttons
  document.getElementById("lessonContent").querySelectorAll("[data-slide-to]").forEach(btn => {
    const to = parseInt(btn.dataset.slideTo, 10);
    if (!isNaN(to) && to >= 0 && to < total) {
      btn.addEventListener("click", () => {
        // Block forward navigation if quizzes on current slide are unanswered
        if (to > currentSlideIdx && !allQuizzesAnswered()) return;
        renderSlide(to);
        document.querySelector(".main").scrollTo({ top: 0, behavior: "smooth" });
      });
    }
  });

  updateSlideCounter(currentSlideIdx, total);
  // The footer button shows on the last slide as usual, but also on every slide
  // while in guided mode so the student can keep walking through the sections
  // they skipped.
  if (isLast || _redirectedToUnfinished) updateNextPageNav();
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
  // Panel removed — no-op
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
  if (currentLesson) leaveCurrentLesson();
  currentLesson = null;
  currentCourse = null;
  buildDashboard();
  showView("dashboard");
  setTopbar("Dashboard", "Welkom terug! Je bent goed op weg.");
  document.querySelectorAll(".lesson-item").forEach(e => e.classList.remove("active"));
  document.querySelectorAll(".lesson-wrap.open").forEach(w => w.classList.remove("open"));
  document.querySelectorAll(".lesson-wrap.peek").forEach(w => w.classList.remove("peek"));
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
window.toggleAvatarMenu  = toggleAvatarMenu;
window.handleLogout      = handleLogout;
window.showAccount       = showAccount;
window.showXPOverview    = showXPOverview;
window.closeEmailModal   = closeEmailModal;
window.emailModalNext    = emailModalNext;
window.emailModalConfirm = emailModalConfirm;

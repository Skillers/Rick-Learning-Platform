export const TYPE_LABELS = {
  lesson:   { label: "les",     cls: "type-lesson" },
  exercise: { label: "oef",     cls: "type-exercise" },
  quiz:     { label: "quiz",    cls: "type-quiz" },
  project:  { label: "project", cls: "type-project" },
};

let _subjects      = [];
let _courseRows    = [];
let _pageRows      = [];
let _courses       = [];
let _sectionsByPage = {};
let _progressMap   = {}; // pageId -> 'in-progress' | 'done'

export function getCourses()        { return _courses; }
export function getSectionsByPage() { return _sectionsByPage; }

function url(path) {
  return new URL(path, import.meta.url).href;
}

async function fetchJSON(path) {
  const res = await fetch(url(path));
  if (!res.ok) throw new Error(`HTTP ${res.status}`);
  return res.json();
}

export async function initSidebar(mountId, onLessonClick, onCourseClick, username = "") {
  const mount = document.getElementById(mountId);
  if (!mount) {
    console.error(`[Sidebar] Mount element #${mountId} not found`);
    return [];
  }

  try {
    const res = await fetch(url("../components/sidebar.html"));
    if (!res.ok) throw new Error(`HTTP ${res.status}`);
    mount.innerHTML = await res.text();
  } catch (err) {
    console.error("[Sidebar] Failed to load sidebar.html:", err);
    mount.innerHTML = `
      <aside class="sidebar">
        <div class="sidebar-brand">
          <div class="brand-icon">&gt;_</div>
          <div><div class="brand-name">ICT Leerlijn</div><div class="brand-sub">ROC Midden Nederland</div></div>
        </div>
        <div class="sidebar-user">
          <div class="user-avatar" id="userInitials">ST</div>
          <div class="user-info">
            <div class="user-name"  id="userName">Student</div>
            <div class="user-level" id="userLevel">Niveau 7 · 1240 XP</div>
            <div class="xp-mini"><div class="xp-mini-fill" id="xpMiniFill" style="width:62%"></div></div>
          </div>
        </div>
        <nav class="sidebar-nav" id="sidebarNav"></nav>
      </aside>`;
  }

  const nav = document.getElementById("sidebarNav");
  if (!nav) return [];

  // Layer 1 — Subjects
  try {
    _subjects = await fetchJSON("../api/subjects.php");
    _subjects.forEach(s => {
      nav.appendChild(mkEl("div", "nav-section-header", s.name));
      const bucket = mkEl("div", "");
      bucket.id = "subject-" + s.id;
      nav.appendChild(bucket);
    });
  } catch (err) {
    console.error("[Sidebar] Failed to load subjects:", err);
  }

  // Layer 2 — Courses
  try {
    _courseRows = await fetchJSON("../api/courses.php");
    _courseRows.forEach(c => {
      const bucket = document.getElementById("subject-" + c.subject_id);
      if (bucket) bucket.appendChild(buildCourseGroup(c, onLessonClick, onCourseClick));
    });
  } catch (err) {
    console.error("[Sidebar] Failed to load courses:", err);
  }

  // Layer 3 — Pages
  try {
    _pageRows = await fetchJSON("../api/pages.php");
    _pageRows.forEach(p => {
      const list = document.getElementById("lessons-" + p.course_id);
      if (list) list.appendChild(buildLessonItem(p, onLessonClick));
    });
  } catch (err) {
    console.error("[Sidebar] Failed to load pages:", err);
  }

  // Layer 3.5 — Progress
  if (username) {
    try {
      const rows = await fetchJSON(`../api/progress.php?username=${encodeURIComponent(username)}`);
      rows.forEach(r => {
        const status = r.completed == 1 ? 'done' : 'in-progress';
        _progressMap[r.page_id] = status;
        _applyPageStatus(r.page_id, status);
      });
      _refreshCourseBars();
    } catch (err) {
      console.error("[Sidebar] Failed to load progress:", err);
    }
  }

  // Layer 4 — Sections
  try {
    const sectionRows = await fetchJSON("../api/sections.php");
    const byPage = {};
    sectionRows.forEach(s => {
      if (!byPage[s.page_id]) byPage[s.page_id] = [];
      byPage[s.page_id].push(s);
    });
    _sectionsByPage = byPage;
    Object.entries(byPage).forEach(([pageId, sections]) => {
      const sectionList = document.getElementById("section-list-" + pageId);
      if (!sectionList) return;
      const page = _pageRows.find(p => p.id == pageId);
      const courseId = page?.course_id;
      sections.forEach((s, idx) => sectionList.appendChild(buildSectionItem(s, idx, courseId, pageId, onLessonClick)));

      const wrap = sectionList.closest(".lesson-wrap");
      if (!wrap) return;
      wrap.classList.add("has-sections");

      const arrow  = mkEl("div", "lesson-arrow", "▶");
      const typeEl = wrap.querySelector(".lesson-type");
      typeEl.parentElement.insertBefore(arrow, typeEl);

      arrow.addEventListener("click", e => {
        e.stopPropagation();
        const isActive = !!wrap.querySelector(".lesson-item.active");

        if (isActive) {
          // Active page: toggle open, but re-open after 0.5s if closed
          if (wrap.classList.contains("open")) {
            wrap.classList.remove("open");
            setTimeout(() => {
              // Only re-open if still the active page
              if (wrap.querySelector(".lesson-item.active") && !wrap.classList.contains("open")) {
                wrap.classList.add("open");
              }
            }, 500);
          } else {
            wrap.classList.add("open");
          }
        } else {
          // Non-active: toggle peek
          wrap.classList.toggle("peek");
        }
      });

      wrap.addEventListener("mouseleave", () => {
        // Don't close if this is the active page
        if (wrap.querySelector(".lesson-item.active")) return;
        wrap.classList.remove("peek");
        arrow.classList.add("arrow-closing");
        setTimeout(() => arrow.classList.remove("arrow-closing"), 200);
      });
    });
  } catch (err) {
    console.error("[Sidebar] Failed to load sections:", err);
  }

  // Assemble nested structure for app.js
  _courses = _subjects.flatMap(s =>
    _courseRows
      .filter(c => c.subject_id === s.id)
      .map(c => ({
        id:       c.id,
        name:     c.name,
        icon:     c.icon,
        color:    c.color,
        section:  s.name,
        category: "",
        progress: {
          pct:   0,
          done:  0,
          total: _pageRows.filter(p => p.course_id === c.id).length,
          color: "var(--blue)",
        },
        lessons: _pageRows
          .filter(p => p.course_id === c.id)
          .map(p => ({ id: p.id, title: p.title, type: p.type, xp: 0, file: "" })),
      }))
  );

  return _courses;
}

function buildCourseGroup(course, onLessonClick, onCourseClick) {
  const group = mkEl("div", "course-group");
  group.id = "group-" + course.id;

  const header = mkEl("div", "course-header");
  header.addEventListener("click", () => toggleGroup(course.id));

  const icon = mkEl("div", "course-icon " + course.color, course.icon);
  if (onCourseClick) {
    icon.addEventListener("click", e => { e.stopPropagation(); onCourseClick(course.id); });
    icon.title = "Cursusoverzicht openen";
    icon.style.cursor = "pointer";
  }
  const info = mkEl("div", "course-info");
  const bar  = mkEl("div", "course-progress-line");
  const fill = mkEl("div", "course-progress-fill");
  fill.style.width      = "0%";
  fill.style.background = "var(--blue)";
  bar.appendChild(fill);
  info.append(mkEl("div", "course-name", course.name), bar);
  header.append(icon, info, mkEl("div", "course-chevron", "▶"));

  const list = mkEl("div", "lessons-list");
  list.id = "lessons-" + course.id;

  group.append(header, list);
  return group;
}

function buildLessonItem(page, onLessonClick) {
  const wrap = mkEl("div", "lesson-wrap");

  const item = mkEl("div", "lesson-item");
  item.id = `nav-${page.course_id}-${page.id}`;
  item.dataset.pageId = page.id;
  item.addEventListener("click", () => onLessonClick(page.course_id, page.id));

  const typeInfo = TYPE_LABELS[page.type] ?? { label: page.type, cls: "type-lesson" };

  item.append(
    mkEl("div", "lesson-status"),
    mkEl("div", "lesson-name", page.title),
    mkEl("div", "lesson-type " + typeInfo.cls, typeInfo.label)
  );

  const sectionList = mkEl("div", "lesson-sections");
  sectionList.id = "section-list-" + page.id;

  wrap.append(item, sectionList);
  return wrap;
}

function buildSectionItem(section, idx, courseId, pageId, onSectionClick) {
  const item = mkEl("div", "section-item");
  item.addEventListener("click", e => {
    e.stopPropagation();
    onSectionClick(courseId, pageId, idx);
  });
  const indicator = section.has_interaction == 1
    ? mkEl("div", "section-status")
    : mkEl("div", "section-arrow", "›");
  item.append(indicator, mkEl("div", "section-name", section.title));
  return item;
}

export function toggleGroup(id) {
  document.getElementById("group-" + id)?.classList.toggle("open");
}

export function syncSidebarActive(courseId, lessonId, sectionIdx) {
  // Find the previously active wrap (if any)
  const prevActive = document.querySelector(".lesson-item.active");
  const prevWrap   = prevActive?.closest(".lesson-wrap");
  const target     = document.getElementById(`nav-${courseId}-${lessonId}`);
  const newWrap    = target?.closest(".lesson-wrap");

  // Clear all active states
  document.querySelectorAll(".lesson-item").forEach(el => el.classList.remove("active"));
  document.querySelectorAll(".section-item").forEach(s => s.classList.remove("active"));
  document.querySelectorAll(".lesson-wrap.peek").forEach(w => w.classList.remove("peek"));

  // If there's an old wrap that's different from the new one, close it first then open new
  if (prevWrap && prevWrap !== newWrap && (prevWrap.classList.contains("open"))) {
    prevWrap.classList.remove("open");

    setTimeout(() => {
      if (target) {
        target.classList.add("active");
        if (newWrap) newWrap.classList.add("open");
      }
      document.getElementById("group-" + courseId)?.classList.add("open");
      if (sectionIdx != null) syncSidebarSection(lessonId, sectionIdx);
    }, 350); // wait for close animation to fully finish
  } else {
    // No previous or same wrap — open immediately
    if (target) {
      target.classList.add("active");
      if (newWrap) newWrap.classList.add("open");
    }
    document.getElementById("group-" + courseId)?.classList.add("open");
    if (sectionIdx != null) syncSidebarSection(lessonId, sectionIdx);
  }
}

export function syncSidebarSection(lessonId, sectionIdx) {
  const sectionList = document.getElementById("section-list-" + lessonId);
  if (!sectionList) return;
  sectionList.querySelectorAll(".section-item").forEach(s => s.classList.remove("active"));
  const items = sectionList.querySelectorAll(".section-item");
  if (items[sectionIdx]) items[sectionIdx].classList.add("active");
}

export function updateSidebarUser(student) {
  const xpPct = Math.round((student.xp / student.xpNext) * 100);
  const set = (id, val) => { const el = document.getElementById(id); if (el) el.textContent = val; };
  set("userInitials", student.initials);
  set("userName",     student.name);
  set("userLevel",    `Niveau ${student.level} · ${student.xp} XP`);
  const fill = document.getElementById("xpMiniFill");
  if (fill) fill.style.width = xpPct + "%";
}

function _applyPageStatus(pageId, status) {
  const item = document.querySelector(`.lesson-item[data-page-id="${pageId}"]`);
  if (!item) return;
  const dot = item.querySelector('.lesson-status');
  if (!dot) return;
  dot.classList.remove('done', 'in-progress', 'locked');
  if (status === 'done') {
    dot.classList.add('done');
    dot.textContent = '✓';
  } else if (status === 'in-progress') {
    dot.classList.add('in-progress');
    dot.textContent = '';
  }
}

function _refreshCourseBars() {
  _courseRows.forEach(c => {
    const pages = _pageRows.filter(p => p.course_id === c.id);
    const done  = pages.filter(p => _progressMap[p.id] === 'done').length;
    const fill  = document.querySelector(`#group-${c.id} .course-progress-fill`);
    if (fill && pages.length) fill.style.width = Math.round(done / pages.length * 100) + '%';
  });
}

export function updatePageStatus(pageId, status) {
  // Don't downgrade a completed page back to in-progress
  if (status === 'in-progress' && _progressMap[pageId] === 'done') return;
  _progressMap[pageId] = status;
  _applyPageStatus(pageId, status);
  _refreshCourseBars();
}

function mkEl(tag, className = "", text = "") {
  const e = document.createElement(tag);
  if (className) e.className = className;
  if (text)      e.textContent = text;
  return e;
}

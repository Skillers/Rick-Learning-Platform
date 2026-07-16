# 2026-07-03 — Subject & course scoping (teachers + students)

## Problem
- Teachers saw **every** subject on the admin page, even ones with none of their
  courses.
- Students saw **every** course on the dashboard/sidebar, regardless of enrolment.
- Course names could be duplicated freely.
- There was no way for a teacher to "own" an empty subject — subjects were only
  ever reachable through a course.

## Model
A teacher sees a subject when **either**:
1. they participate in (or are enrolled as a student in) a course in it, **or**
2. they've explicitly *joined* it — a new `Teacher_has_Subjects` link.

Requirement 2 (join an already-existing subject and get an empty view that
survives a reload) needs persistence that can't be derived from courses, hence
the new link table.

## Schema change — APPLIED by the user
The user added `Teacher_has_Subjects` to `CreateScriptRickLearningPlatform.sql`
(columns `accounts_username`, `subjects_id`; PK on both; FKs to `accounts` and
`subjects`). Code + `seed.sql` reference this exact name. Rebuild
(drop → create → seed) to pick it up.

## Endpoints
- **`subjects.php` / `courses.php`** — new optional `?username=`. Without it, the
  full list is returned (unchanged; the enrolment/grading managers rely on this).
  With it: superadmin → all; teacher → participating + enrolled + joined subjects
  / participating + enrolled courses; student → enrolled only.
- **`save_subject.php`** — accepts `actor`. Creating a subject (new *or* existing)
  links the teacher via `Teacher_has_Subjects` (INSERT IGNORE). Superadmin: no link
  (sees all).
- **`save_course.php`** — accepts `actor`. Rejects a duplicate course name **within
  the same subject** (case-insensitive) with HTTP 409. On success a teacher creator
  is added to `Teacher_ParticipatesIn_Course` as `Owner`, so the course stays
  scoped/editable for them.

## Front-end
- `js/sidebar.js` (student) passes `?username=` → dashboard + sidebar only show
  enrolled courses/subjects.
- `admin.html` passes `?username=` for subjects + courses; sends `actor` on
  subject/course create. The duplicate-name 409 surfaces in the existing
  create-course error alert and leaves the modal open to fix.

## Decisions (flip if needed)
- Duplicate course names are blocked **per subject**, not globally — the same name
  can exist under different subjects.

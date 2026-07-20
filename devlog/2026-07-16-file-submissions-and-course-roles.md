# 2026-07-16 — File submissions, document preview, course roles

Written retroactively: this covers the parts of commit `bffa518` that never got a devlog
entry. The *test* page type from the same commit is documented separately in
`2026-07-08-test-page-type.md`; everything below is the rest.

## 1. File submissions on open questions

Students can now attach a **document** and/or an **image** to an open question. Which is
allowed is decided per question by the teacher, so a question can accept text only, a file
only, or both.

### Model

```sql
`PQQuestion`.`AllowDocument`      TINYINT NOT NULL DEFAULT 0
`PQQuestion`.`AllowImage`         TINYINT NOT NULL DEFAULT 0
`AC_Did_Question`.`FileName`      VARCHAR(255) NULL   -- original name, for display
`AC_Did_Question`.`FilePath`      VARCHAR(255) NULL   -- 'uploads/<safe-name>'
```

The two flags are question *content*, so they ride the copy-on-write engine like every
other question field — they're in the component canonical in `save_page.php`, in
`update_live.php`, and in the `section_content.php` read path (wire field
`allow_document` / `allow_image`).

`FileName` is kept separately from `FilePath` because the stored name is deliberately
mangled: teachers still need to see what the student called it.

### Upload — `api/upload_submission.php`

Two-stage flow: the file is uploaded first, then its returned path is sent along with the
answer to `quiz_submit.php`.

The endpoint derives the permitted extensions **from the question's own flags**, not from
anything the client sends — `AllowDocument` → `pdf/doc/docx/txt`, `AllowImage` →
`png/jpg/jpeg`. A question with neither flag is rejected outright (403), so a student can't
slip an image onto a doc-only question. Other guards: 25MB cap, extension whitelist,
and a stored filename of `<sanitised-base>_<8 random hex>.<ext>` — the random suffix
prevents collisions and keeps the path unguessable.

### Storing — `api/quiz_submit.php`

The submit endpoint **re-checks** the question's flags rather than trusting that the upload
already passed, and only persists a path matching `^uploads/[A-Za-z0-9_.-]+$` — i.e. one
that looks like something our own upload endpoint produced. A crafted request can't point
`FilePath` at an arbitrary location.

## 2. Document preview in the grading UI — `api/preview_doc.php`

Teachers grading a submission shouldn't have to download a `.docx` to read it. Preview is a
three-tier fallback in `HomeworkManager.html`:

1. **`preview_doc.php`** — converts the file to PDF with **headless LibreOffice** and streams
   it inline. This is the faithful one: real layout, images, tables. The same server-side
   approach itslearning/Blackboard use. Result is cached per file, keyed by
   `sha1(path + mtime)`, so conversion happens only on first view.
2. **mammoth.js** (`js/vendor/mammoth.browser.min.js`) — renders `.docx` *content* in-browser.
   No LibreOffice needed, but layout is lost. Used when tier 1 returns non-200.
3. A plain open/download link.

Implementation notes worth keeping:
- Path is resolved with `realpath` and checked to be inside `uploads/` — no traversal.
- Conversion runs in a per-job temp dir so two files with the same basename can't collide.
- LibreOffice gets a **dedicated writable profile** (`-env:UserInstallation=`). Without it,
  Apache's user has no/locked LO profile and conversion dies with "LibreOffice is already
  running".
- Cache lives in `uploads/.previews/`, which is gitignored.

**Dependency:** this tier needs LibreOffice installed on the server. It's optional — without
it the endpoint 500s and the UI silently falls back to mammoth.

## 3. Course-level roles — `config/course_perms.php`

`Teacher_ParticipatesIn_Course.Role` gained meaning. One helper file is now the single
source of truth for who may do what:

| Role | Edit course | Course settings (rename/move/delete) | Manage teachers | Grade |
|---|---|---|---|---|
| Owner | ✓ | ✓ | ✓ | ✓ |
| Editor | ✓ | | | |
| Grader | | | | ✓ |

`Superadmin` (on `accounts.Role`) overrides all of it regardless of course link. Creating a
course makes the creator its `Owner` (see `2026-07-03-subject-scoping.md`).

Helpers: `can_edit_course`, `can_manage_course`, `can_manage_teachers`, `can_grade_course`.
Anything that touches a course should call one of these rather than re-deriving the rule.

## 4. Smaller pieces

- **`api/page_types.php`** — serves the `PageTypes` lookup table so editor dropdowns stay in
  sync with the DB instead of a hardcoded list.
- **`database/seed_reference.sql`** — the six lookup tables the app cannot run without
  (`PageTypes`, `ComponentType`, `Languages`, `EmptySpaceTypes`, `QuestionContext`,
  `MultiMediaType`). `INSERT IGNORE` only, so it's idempotent and safe on a live DB.
  `seed.sql` already contains this data; this file exists for a database that was built by
  hand and never fully seeded.

## Fixed while writing this — `PossiblePoints` dropped on two write paths

`PossiblePoints` was added to `PQQuestion` for the test page type, but only wired into
`save_page.php` and the read path. Two other write paths still had the pre-points column
list and silently dropped it:

1. **`_clone.php`** — snapshots the outgoing live version into private rows on publish. Its
   `PQQuestion` copy omitted the column, so the archived copy fell back to the default `1`.
   Publish over a live **test** page and the archived version's per-question points were
   silently reset; restore that archive and the cijfers come back wrong.
2. **`update_live.php`** — in-place content edit of a live version. The editor renders the
   points field on any test page including a live one (`admin.html`, `quiz-points-row`), so
   a teacher could change the value, save, and have it silently discarded.

Both now carry the column. Points are **content**, not structure, so `update_live.php`
accepting them is consistent with it accepting question text — it still rejects structural
changes. Note the consequence: editing points on a live test retroactively changes the
cijfer of students who already submitted, since `test_result.php` recomputes from the
current `PossiblePoints`. That's the same behaviour as editing a question's text, but worth
knowing.

`NTerm` was never affected — it lives on `PageVersion`, not on the cloned tree.

**Lesson for next time:** a new `PQQuestion` column has to be added to *five* places —
`save_page.php` (canonical + insert), `update_live.php`, `_clone.php`, and
`section_content.php`. Grep for `AllowImage` to find them all.

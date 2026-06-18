# CLAUDE.md — read this first

Working context for AI assistants on **ICT Leerlijn** (a custom MBO learning
platform). Read this before touching code so you don't have to rediscover the
structure by grepping every file.

## Read first
1. **`README.md`** — project overview, design system, file map, roles/scoping,
   iteration changelog. (Note: its DB-schema diagram predates page versioning —
   trust the create script + this file for the current schema.)
2. **`devlog/`** — design decisions, newest first. The versioning model lives in
   `devlog/2026-06-18-page-versioning.md`.

## Stack & how to run
- Vanilla **HTML/CSS/JS** front-end, **PHP + MySQL (PDO)** back-end. No build step.
- Runs on **XAMPP** (Apache + MySQL). API = plain PHP files in `api/` returning JSON.
- DB creds: `config/db.settings.php`. Connection: `config/db.connection.php` exposes `$pdo`.

## Golden rules
- **RULE 1 — never edit `database/CreateScriptRickLearningPlatform.sql`.** It is the
  schema source of truth, owned by the user. *Advise* changes; the user applies them,
  then rebuilds (drop → create → seed). After a schema change, code targets the new
  script — the running DB must be rebuilt to match.
- Reconcile `database/seed.sql` and `api/*` to whatever the create script currently says.
- UI text is **Dutch**. Small status indicators use a CSS dot-in-ring (`::after` /
  `.ver-dot`), not Unicode symbols. Never add per-student time-tracking.

## Page versioning model (the core recent architecture)
- **`pages`** = stable identity: `Course_Id, order, PageType_Id, Published`.
  `Published` is the teacher's *student-visibility* toggle (not "has a version").
- **`PageVersion`** = per-version metadata: `Title, XpReward, EstimatedDuration,
  Status ∈ {concept, live, archived}`. At most **one live + one concept** per page.
- **`sections` (Id, Title)** and **`components` (Id, type)** are *shared content*.
  Composition lives in junctions: **`PageVersion_has_sections`** (Order, XPReward) and
  **`sections_has_components`** (Order). Component detail rows hang off `components`
  (TextBLocks, CodeSnippets, InfoBoxes, MultiMedia, PQQuestion+PQAnswer, EmptySpace).
- **concept ↔ live share rows** (cheap clone + copy-on-write on edit). **Publishing
  snapshots the outgoing live into private rows** (`set_live_version.php` → `_clone.php`)
  so the new live owns its content privately → it can be edited in place and **archived
  versions are immutable**. Unchanged section/component ids stay stable, so student
  progress (`UserXPLog.sections_Id`, `AC_Did_Question.PQQuestion_Id`) carries forward.
- **Editor edit modes** (`admin.html`, set in `openPageFromDb`):
  - concept → full edit → `save_page.php` (fork-on-change)
  - live & no concept → content-only in place → `update_live.php` (rejects structural changes)
  - live-with-concept / archived → read-only

### Versioning endpoints
`page_versions.php` (list), `create_concept.php`, `save_page.php` (concept edit, COW),
`update_live.php` (in-place live content edit), `set_live_version.php` (publish +
snapshot), `delete_version.php` (remove archived/concept), `set_page_enabled.php`
(student visibility), `delete_page.php`. Read path: `pages.php`, `sections.php`,
`section_content.php`. XP/progress: `award_xp.php`, `xp_overview.php`,
`section_awards.php`, `quiz_answers.php`, `submissions.php` — all resolve a section's
page/XP through the **live** version.

## Testing against a throwaway DB (Windows/XAMPP)
No mysql CLI; drive MySQL via PHP. Pattern used for validation:
1. Build a temp schema with `mysqli` multi_query from create + seed (str_ireplace the
   schema name to e.g. `_rlp_test`).
2. Run the real endpoints over HTTP: `php -S 127.0.0.1:<port>
   -d auto_prepend_file='<ABSOLUTE path to a prepend.php that define()s DB_NAME=_rlp_test>'
   -d display_errors=0 -t .` — the prepend must be an **absolute** path and
   `display_errors=0` (so the define-collision warning doesn't corrupt JSON bodies).
3. `curl`/`file_get_contents` the endpoints; assert via `mysqli`; drop the temp schema.
4. Kill leftover servers with `taskkill //F //IM php.exe` (Git Bash `kill` misses `php.exe`).

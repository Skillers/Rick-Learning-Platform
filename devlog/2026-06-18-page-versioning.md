# Page versioning — concept / live / archived

**Date:** 2026-06-18
**Status:** Implemented & validated

## Why
The page editor overwrote content in place: there was no safe way to draft
changes without affecting students, no history, and editing a published page was
blocked entirely (the old `save_page.php` only did a destructive replace-all on
drafts). We wanted three states — **concept** (draft), **live** (what students
see), **archived** (history) — with the ability to cycle versions, choose which
is live, edit safely, and keep student progress intact across republishes.

## The model
- **`pages`** = stable identity only (`Course_Id, order, PageType_Id, Published`).
  It never changes id, so page-level progress (`accounts_opened_pages`, page-level
  `UserXPLog`) is permanently stable. `Published` is the teacher's
  **student-visibility** toggle.
- **`PageVersion`** = the versioned metadata (`Title, XpReward, EstimatedDuration,
  Status`). Invariant: **≤1 live and ≤1 concept per page**; archived are immutable.
- **`sections` (Id, Title)** + **`components` (Id, type)** are **shared content**.
  Which sections make up a version, and in what order/XP, lives in
  **`PageVersion_has_sections`** (Order, XPReward). Which components make up a
  section lives in **`sections_has_components`** (Order). Detail rows
  (TextBLocks/CodeSnippets/InfoBoxes/MultiMedia/PQQuestion+PQAnswer/EmptySpace)
  hang off `components`.

Structural sharing (git-tree style) is what makes progress carry forward: an
unchanged section keeps the **same id** across versions, so a student's
`UserXPLog.sections_Id` (and quiz `AC_Did_Question.PQQuestion_Id`) still resolves
to the live page after a republish.

## Editing rules
| State | How it edits |
|---|---|
| **Concept** | Full edit. `save_page.php` does **copy-on-write**: an edited *shared* section forks to a new private row (live untouched); an edited *private* concept section is mutated in place (no id churn); unchanged sections are skipped; orphaned rows are GC'd. |
| **Live, no concept** | **In-place, content-only** via `update_live.php`: text/code/callout/media/quiz wording + section titles + existing answers, matched **by position**. Adding/removing/reordering or changing a component's kind → 409. |
| **Live, concept exists** | Locked — edit the concept and publish. |
| **Archived** | Read-only. The only way to change it is to make it live again. |

## Snapshot-on-publish (the key insight)
In-place live editing collides with sharing: after a normal publish, the new live
and the freshly-archived version *share* unchanged rows, so editing live in place
would mutate the archived copy. Fix: **on publish, deep-copy the outgoing live
into its own private rows** (`set_live_version.php` → `_clone.php`) and repoint the
archived version at the copies. The new live keeps the original ids (progress
carries forward); the archived snapshot is fully independent and can never be
touched by a later live edit. Orphaned originals are GC'd.

`set_live_version` also enables the page for students (`pages.Published = 1`) **only
on the first publish**; later publishes respect the teacher's visibility toggle.

## Endpoints
- `page_versions.php` — list a page's versions.
- `create_concept.php` — clone live → the single concept (shares sections).
- `save_page.php` — concept edit (copy-on-write).
- `update_live.php` — in-place live content edit.
- `set_live_version.php` — publish/rollback (+ snapshot outgoing live).
- `delete_version.php` — remove an archived (or concept) version + GC its private content.
- `set_page_enabled.php` — student-visibility toggle.
- `delete_page.php` — delete a page, all versions, content, progress.

Read + XP endpoints (`pages.php`, `sections.php`, `section_content.php`,
`award_xp.php`, `xp_overview.php`, `section_awards.php`, `quiz_answers.php`,
`submissions.php`) all resolve a section's page/XP through the **live** version, and
hide pages where `Published = 0`.

## Editor (`admin.html`)
`openPageFromDb` computes an `editMode` (concept / live / readonly) and loads the
chosen version through `?version_id`. The version bar shows status chips (dot-in-ring:
green=live, amber=concept, grey=archief), a delete ✕ on archived chips, "Nieuw
concept" / "Maak deze versie live", and a student-visibility toggle. Structural
controls (add/remove/reorder section & component) are hidden and guarded outside
concept mode; autosave routes to `save_page` (concept) or `update_live` (live).

## Schema changes the user made (applied to the create script by them)
- `pages`: dropped `title, XPReward, EstimatedDuration`; `published → Published`.
- `sections`: dropped `Pages_Id, Order, XPReward` (now `Id, Title` only).
- `components`: dropped `section_Id, Order` (now `Id, type` only).
- New: `PageVersion`, `PageVersion_has_sections`, `sections_has_components`.

## Validation
Every step was checked against a throwaway build (create + seed) and the live DB:
clone/publish independence, archived immutability after an in-place live edit,
copy-on-write fork vs in-place, structural rejection (409), concept-refusal,
carry-forward of a student's section XP across republish, archived deletion + GC,
and enable/disable visibility. See `CLAUDE.md` for the throwaway-DB test pattern.

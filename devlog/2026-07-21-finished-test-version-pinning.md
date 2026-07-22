# 2026-07-21 — Freezing a finished test on the version the student did

## Problem
Tests (`test` page type) are graded against the **live** version. So if a student
completed a test and the teacher then republished it, the student's grade silently
re-computed against the *new* version — different points, edited/removed questions
orphaning their answers. A completed exam should be immutable for the student who sat it.

## Rule
**A student who has answered every question of a test is "finished"** and gets frozen on
the version they did. A student who answered only part of it stays on the live version and
moves forward on republish (the existing carry-forward behaviour). "Finished" counts
submitted-but-ungraded open questions — so the grade still finalises as the teacher grades
them, even after a republish.

## Schema (added by the user — Rule 1)
`FinishedTests (accounts_username, pages_Id, PageVersion_Id, CompletedAt)`,
PK `(accounts_username, pages_Id)` → one pin per student per page. One row = "this student
completed this test on this version; keep them there."

## Flow
- **`quiz_submit.php`** — after recording an answer on a `test` page, if the student has now
  answered *all* questions of the live version, `INSERT IGNORE` a pin to that version.
- **`_clone.php`** — `clone_version_into_independent()` now **returns an
  origQuestionId → newQuestionId map**. (It already gave the archived snapshot private ids;
  now the caller can see them.)
- **`set_live_version.php`** — after cloning the outgoing live version, it remaps the
  `AC_Did_Question` rows of students pinned to that version onto the archived clone's ids.
  Their answers travel *with the archived version* instead of carrying forward like a
  partial student's. This is the load-bearing bit: without it a finished student's grade
  collapses to the N-term after republish (verified: 10 → 1).
- **`_versions.php`** — `resolve_requested_version()` gained a step: when `?username=` is
  passed (and no explicit `version_id`/`status`), it returns the student's pin if present,
  else live. New helper `finished_test_version()`. This makes **`sections.php` +
  `section_content.php`** serve a finished student their frozen version automatically.
- **`test_result.php`** — grades a finished student against their pinned version and returns
  `frozen` + `old_version` flags.
- **Front-end (`js/app.js`, `css/main.css`)** — `loadLessonContent` fetches this page's
  sections *and* content with `&username=` (both from the same version so section ids line
  up); the result slide shows an "oude versie" banner when `old_version` is true.

## Verified
End-to-end over HTTP against a throwaway DB (`test_finished_tests.php`), 15 checks:
finish detection (pin created for the finisher, not the partial student), grade held at 10
across a republish, finisher graded against the archived version while the partial student
moved to the new live, and both `sections.php` and `section_content.php` serving the right
version per student. Negative control: reverting the `set_live_version` remap drops the
frozen grade 10 → 1, proving the remap is what preserves it.

**Not driven in the browser** (student login requires a password I don't enter), but every
endpoint the front-end calls is verified and `app.js` parses.

## Known limitation
The remap moves `AC_Did_Question`, not `AC_Picked_Answer`. A finished MC student's picked-
answer rows still point at the new live version's answer ids. The **grade** is unaffected
(`PointsAwarded` is stored on the attempt at submit time and is what `test_result.php`
reads), but re-rendering exactly which option they ticked on the archived version isn't
wired. Fine for the grade freeze; note it if per-option review of archived attempts is
added later.

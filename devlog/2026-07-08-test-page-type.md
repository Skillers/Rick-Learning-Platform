# 2026-07-08 — "Test" (Toets) page type: per-question points + grade

A new page type **`test`** (a classroom exam) alongside lesson/exercise/quiz/project.
It reuses the existing section/question model; what's new is **points per question** and a
**graded end result** (Dutch 1–10 cijfer).

## Model

- **Points live on the question** (`PQQuestion.PossiblePoints`, max obtainable). Points are question
  *content*, so they ride the copy-on-write engine in `save_page.php` (added to the component
  canonical) — editing a question's points forks only that component; unchanged questions keep
  their id (and student answers).
- **Earned points per attempt** (`AC_Did_Question.PointsAwarded DECIMAL(6,2)`):
  - **Multiple-choice** is auto-scored at submit (`quiz_submit.php`): partial credit =
    `Points × (options classified correctly ÷ total options)`, where an option is "classified
    correctly" when the student's picked/not-picked state matches `IsCorrect`.
  - **Open questions** stay `NULL` (pending) until the teacher grades them. `grade.php` accepts
    `points_awarded` (0…max), clamps it, stores it, and derives the verdict (`V` if >0 else `X`)
    so the existing To_grade/Grade notification cascade is unchanged.
- **Grade normering** (`PageVersion.NTerm DECIMAL(3,2)`, default `1.00`) is per-version, edited in
  the page-settings panel (test pages only).
- **Grade formula** — one place, `config/grade_scale.php`:
  `cijfer = clamp(1,10, N + 9 × (score ÷ max))`, one decimal. Pass line **5.5**.

## Result summary

`api/test_result.php?username=&page_id=` resolves the live version and returns
`earned_points`, `pending_points` (Σ points of submitted-but-ungraded questions), `max_points`,
and two cijfers:
- **grade_current** = `cijfer(earned, max, N)` — unanswered/ungraded count as 0.
- **grade_possible** = `cijfer(earned + pending, max, N)` — best still achievable.

The student reaches this on a dedicated final **"Resultaat" slide** — an extra virtual slide
appended to the test's pagination (`js/app.js` → `paginateSections` pushes `SUMMARY_SLIDE`;
`renderSlide` renders it via `summarySlideHTML` + `refreshTestResult`). The last content section's
"next" arrow leads to it; full view shows a "Naar resultaat →" button (`goToTestSummary`). Each
question shows its max points inline.

## Schema additions (in the create script; user owns it, code reconciled to match)

```sql
`PQQuestion`.`PossiblePoints`   INT NOT NULL DEFAULT 1
`AC_Did_Question`.`PointsAwarded` DECIMAL(6,2) NULL
`PageVersion`.`NTerm`           DECIMAL(6,2) NOT NULL DEFAULT 1
-- plus PageTypes row (5,'test','type-test') — in seed.sql
```
(The API talks to the DB via the column name `PossiblePoints`; the JSON/wire field between
back-end and editor stays `points`.)

## Files

- New: `config/grade_scale.php`, `api/test_result.php`.
- Backend: `api/quiz_submit.php` (MC scoring), `api/grade.php` + `api/reviews_open.php`
  (points grading), `api/save_page.php` + `api/section_content.php` + `api/page_versions.php`
  (Points/NTerm through the COW + read pipeline).
- Frontend: `admin.html` (type, N-term field, points input), `js/app.js` (badges + result panel),
  `HomeworkManager.html` (points grading), `js/sidebar.js` + `css/main.css` (type badge, panel styles).

## Out of scope

No timers, no attempt limits, no per-student time tracking.

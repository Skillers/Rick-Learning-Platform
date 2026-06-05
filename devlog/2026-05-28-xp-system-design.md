# Devlog — XP System Design

**Date:** 2026-05-28
**Author:** Rick (with Claude as design partner)
**Status:** Schema complete, awaiting PHP implementation

---

## Starting point

Before this session, XP existed in the platform only as **frontend window dressing**. `STUDENT.xp = 1240` was hard-coded in `js/app.js`. The sidebar happily showed "Niveau 7 · 1240 XP" for every user. The admin page had an XP input on the page-meta form, but the value never persisted past page reload — it lived in an in-memory object and disappeared.

The database had nothing related to XP or levels. `AccountStats` tracked streaks and last-login, but no XP totals.

The goal of this session was to **design the database layer for a real XP system**, end to end.

---

## What we set out to build

- A way for teachers to assign XP rewards to lessons.
- A way to track when, why, and how much XP each student earned (audit trail).
- An *estimated* duration per page — but explicitly **not** a per-student time-tracking system. (Rule: time-on-task is an unfair measure of learning.)
- Idempotency: a student must not be able to grind the same section twice for double XP, whether through bugs, double-clicks, or network retries.
- Resilience: deleting a page or section later should not lose the XP history.

---

## A parallel decision — page duration vs time-tracking

Two questions about "time" came up early, and they deserve separate billing from the XP design because the answers shape what the platform will *never* do.

**Where does the duration estimate live?**
The first option proposed was a `DurationMinutes` column on `pages`, with subject and course totals computed as `SUM()` over child pages. I floated three alternatives via a quick question — per page, per subject (top-level), or both. Rick picked per-page, in the same breath as clarifying the XP model: *"xp should be awarded per section and a bonus for full completion of the page. time should be shown per page."* Subject and course-level totals are therefore derived, never stored — single source of truth lives at the page row.

**Who measures the time?**
Mid-session, Rick made the boundary explicit: the platform will **never** record how long a specific student spends on a page, section, or activity. Time-on-task is — in his words — *"an unfair and unrealistic measure"* of learning. Fast learners get punished, slow learners get stigmatised, and the number reflects distraction more than understanding. This was saved as a permanent platform rule ([`memory/feedback_no_time_tracking.md`](../../.claude/projects/C--Repos-Rick-Learning-Platform/memory/feedback_no_time_tracking.md)) so it survives across future design sessions: progress is measured by completion, XP, streaks, and correctness — never elapsed time per user.

That principle is the reason the column ended up named **`EstimatedDuration`** rather than `DurationMinutes` or anything that could be misread as "measured." The "Estimated" prefix is doing semantic work: this is a teacher-set display value ("~15 min"), not a clock reading. The unit is minutes by convention; if that ever becomes ambiguous in the UI, it gets a label there, not a column rename.

The schema also reflects this — there is no `time_spent` column anywhere, no per-page timestamp on `accounts_opened_pages` beyond `Completed`, no session-duration field on `AccountStats`. The shape of the database is itself a statement of values.

---

## How the design evolved

### Round 1 — Reward columns on pages + a separate audit log
First draft put `XPReward` on `pages`, a `XPBonus` field for full-page-completion, `DurationMinutes` on pages, and a separate `xp_log` audit table with a polymorphic `(SourceType, SourceId)` reference. Looked clean on paper.

### Round 2 — "Reward per section, bonus per page" (Rick's preference)
Rick clarified: XP should mostly come from **sections** (small per-step rewards), with a **bonus** on full page completion. Duration belongs on pages. The audit log should record each award with the date only — not the time of day.

Schema reshuffled accordingly: `sections.XPReward` added, `pages.XPBonus` added, log table got a `Source ENUM('section', 'page_bonus')` and `AwardedOn DATE`.

### Round 3 — `RewardOnPage` boolean (then unbooked)
Rick proposed a `RewardOnPage` flag on pages: *if on, the page rewards XP and sections are just structural; if off, sections reward XP and the page is just ordering*. The design moved that direction…

…then Rick changed his mind. **Simpler rule**: a page rewards XP if its `XPReward > 0`, a section rewards XP if its `XPReward > 0`, both can fire independently, both can be zero. No flag needed — `0` is the natural "no reward" signal.

This removed a column from the schema. Good simplification.

### Round 4 — The `UNIQUE` constraint trap
We needed a unique constraint on the log to enforce idempotency. First attempt:

```sql
UNIQUE (accounts_username, Source, pages_Id, sections_Id)
```

**Wrong.** In MySQL, `NULL` values in a `UNIQUE` index are not considered equal — so two log rows with `(emma, Section, NULL, 101)` would both be accepted. The constraint is toothless when one of the columns is always NULL.

Pivot: two separate UNIQUE indexes, each scoped to its source column.

### Round 5 — Always store `pages_Id` (Rick's suggestion)
For display, knowing the parent page of a section award without a JOIN is a big win. Rick proposed always writing `pages_Id` on section-award rows too, even though it's derivable from `sections.Pages_Id`.

Benefits:
- One `GROUP BY pages_Id` answers "XP per page (incl. its sections)" with no joins.
- If a section is deleted later, the page link survives → the audit row stays groupable under its subject.
- Matches the immutable-log philosophy: capture what was true at award time, not at query time.

Cost: the simple two-UNIQUE design from Round 4 stopped working, because section rows would now share `pages_Id` values.

**Solution: a generated column.** `Page_award_Key INT GENERATED ALWAYS AS (CASE WHEN Source = 'Page' THEN pages_Id ELSE NULL END)`. It's `pages_Id` for page-award rows and `NULL` for section rows. The UNIQUE on `(accounts_username, Page_award_Key)` then only constrains page awards; section rows have NULL there and don't conflict with each other.

### Round 6 — Workbench wrangling
Translating the design to MySQL Workbench's EER editor took three review iterations:
1. First export missed all UNIQUE indexes and made `sections_Id` UNSIGNED (FK type mismatch).
2. Second export had UNIQUEs but as **single-column** indexes — would have meant only one student could ever complete each section.
3. Third export got it right: composite `(accounts_username, X)` UNIQUEs.

---

## Technical lessons worth remembering

1. **InnoDB AUTO_INCREMENT is table-global**, not per-group. MyISAM gives per-group counters via composite PKs; InnoDB does not. Don't rely on it for "user-scoped IDs."
2. **MySQL UNIQUE + NULL is permissive.** Multiple NULL values in a UNIQUE column are all considered distinct. If you need conditional uniqueness, use a generated column that's NULL when the constraint shouldn't apply.
3. **Denormalisation in an immutable log is a feature**, not a smell. The log is a historical record — storing redundant context (like `pages_Id` on a section award) preserves meaning even if the source data is later deleted.
4. **Single-column UNIQUE is rarely what you want for join-table-like uniqueness.** Almost always you want `(user_or_owner_id, target_id)` as the composite key.

---

## Final schema (XP layer)

- `pages.XPReward` — XP the page awards on full completion (0 = none)
- `pages.EstimatedDuration` — teacher-set display estimate, never a per-student measurement
- `sections.XPReward` — XP the section awards on its own completion (0 = none)
- `AccountStats.TotalXP` + `AccountStats.Level` — denormalised counters, updated in the same transaction as the log insert
- `UserXPLog` — full audit trail
  - Polymorphic source via nullable `pages_Id` + `sections_Id` + `Source` enum
  - `Page_award_Key` generated column for page-award idempotency
  - Composite UNIQUE `(accounts_username, sections_Id)` for section-award idempotency
  - Composite UNIQUE `(accounts_username, Page_award_Key)` for page-award idempotency
  - `ON DELETE SET NULL` on the source FKs → source can be deleted, history survives
  - `ON DELETE CASCADE` on the user FK → deleting a user wipes their log too

---

## What's next

- **Level formula** lives in PHP (`level_for_xp($total_xp)`) — formula `xpRequired(L) = 10000 * (1.05^(L-1) - 1)`, with the closed-form solver `floor(1 + log(1 + total/10000) / log(1.05))`.
- **The awarder** — single PHP function, single transaction: `INSERT IGNORE` into the log, bump `TotalXP`, recompute `Level`. Idempotent by virtue of the UNIQUE constraints.
- **The dashboard query** — the Subject → Page → Section unfoldable tree. Now trivial thanks to the redundant `pages_Id`.

To revisit later (deferred, not lost):
- Whether to keep `Level` denormalised or compute on read.
- Whether to introduce a `level_thresholds` lookup table when/if the formula needs per-level overrides (milestones, plateaus).
- Whether to denormalise `Subjects_Id` into `UserXPLog` so subject-grouping survives even deeper deletions.

---

## Reflection

The most useful single moment in the design was Rick's pushback on `RewardOnPage`. I had over-engineered a flag to solve a problem the design didn't actually have. Removing it made the schema simpler *and* truer to the intent. The lesson: when a flag exists to distinguish two modes, ask whether the modes can instead be expressed by a single field being zero or non-zero.

The most useful single technical insight was the generated-column trick for conditional uniqueness. That pattern will be useful any time a single table holds rows of multiple "shapes" and each shape has its own uniqueness rule.

-- ─────────────────────────────────────────────────────────────
-- Reset XP state so the next test run starts fresh.
-- Safe to re-run. Run after migrate_xp.sql.
--
-- All-users reset by default. Scope to one account by uncommenting
-- the WHERE clauses below.
-- ─────────────────────────────────────────────────────────────

USE `rick learning platform`;

-- ─── 1. Wipe the XP log ─────────────────────────────────────
-- All users:
TRUNCATE TABLE `UserXPLog`;

-- Single user (use instead of TRUNCATE above):
-- DELETE FROM `UserXPLog` WHERE `accounts_username` = 'Rick';

-- ─── 2. Reset totals on AccountStats ─────────────────────────
UPDATE `AccountStats`
   SET `TotalXP` = 0,
       `Level`   = 1
-- WHERE `accounts_username` = 'Rick'  -- uncomment to scope to one user
;

-- ─── 3. Reset page-completion flags ──────────────────────────
-- Sidebar checks come back to "in progress" so you can re-trigger
-- the page-completion award.
UPDATE `accounts_opened_pages`
   SET `Completed` = 0
-- WHERE `Accounts_username` = 'Rick'  -- uncomment to scope to one user
;

-- ─── 4. (Optional) Clear quiz answers ────────────────────────
-- Uncomment if you also want to re-answer the quizzes (otherwise
-- they stay locked with their previous submission). Order matters:
-- AC_Picked_Answer references AC_Did_Question via FK.
-- DELETE FROM `AC_Picked_Answer`
--   WHERE `AC_Did_Question_Id` IN (
--       SELECT `Id` FROM `AC_Did_Question`
--       -- WHERE `accounts_username` = 'Rick'
--   );
-- DELETE FROM `AC_Did_Question`
--   -- WHERE `accounts_username` = 'Rick'
-- ;

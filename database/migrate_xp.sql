-- ─────────────────────────────────────────────────────────────
-- Migration: add XP / EstimatedDuration columns + UserXPLog
-- Run once against the live DB to catch it up with the create script.
-- Safe to re-run individual statements; PHP throws "Unknown column"
-- on pages.XPReward without these.
-- ─────────────────────────────────────────────────────────────

USE `rick learning platform`;

-- ─── pages: XP + duration ───
ALTER TABLE `pages`
  ADD COLUMN `XPReward`          INT(3) NOT NULL DEFAULT 0,
  ADD COLUMN `EstimatedDuration` INT(3) NOT NULL DEFAULT 0;

-- ─── sections: XP ───
ALTER TABLE `sections`
  ADD COLUMN `XPReward` INT(3) NOT NULL DEFAULT 0;

-- ─── AccountStats: TotalXP + Level + PK ───
ALTER TABLE `AccountStats`
  ADD COLUMN `TotalXP` INT NOT NULL DEFAULT 0,
  ADD COLUMN `Level`   INT NOT NULL DEFAULT 1;

-- Add PK on accounts_username (only if not already present — check first if MySQL complains).
ALTER TABLE `AccountStats`
  ADD PRIMARY KEY (`accounts_username`);

-- ─── Backfill XP + duration for existing pages/sections ───
-- Optional. Comment out if you'd rather set values manually per page.
--   1 = lesson, 2 = exercise, 3 = quiz, 4 = project
UPDATE `pages` SET
  `XPReward` = CASE `PageType_Id`
    WHEN 1 THEN 20    -- les
    WHEN 2 THEN 30    -- oefening
    WHEN 3 THEN 50    -- quiz
    WHEN 4 THEN 100   -- project
    ELSE 20
  END,
  `EstimatedDuration` = CASE `PageType_Id`
    WHEN 1 THEN 10
    WHEN 2 THEN 15
    WHEN 3 THEN 20
    WHEN 4 THEN 60
    ELSE 10
  END
WHERE `XPReward` = 0;

UPDATE `sections` SET `XPReward` = 5 WHERE `XPReward` = 0;

-- ─── New table: UserXPLog ───
CREATE TABLE IF NOT EXISTS `UserXPLog` (
  `Id`                INT NOT NULL AUTO_INCREMENT,
  `accounts_username` VARCHAR(25) NOT NULL,
  `pages_Id`          INT(11) NULL,
  `sections_Id`       INT(11) NULL,
  `Source`            ENUM('Section','Page') NOT NULL,
  `AwardedOn`         DATE NOT NULL,
  `RewardedAmount`    INT(3) NOT NULL,
  `Page_award_Key`    INT(11) GENERATED ALWAYS AS
                        (CASE WHEN Source = 'Page' THEN pages_Id ELSE NULL END) VIRTUAL,
  PRIMARY KEY (`Id`),
  UNIQUE INDEX `Uniq_section_award` (`accounts_username` ASC, `sections_Id` ASC),
  UNIQUE INDEX `Uniq_page_award`    (`accounts_username` ASC, `Page_award_Key` ASC),
  INDEX `fk_UserXPLog_pages1_idx`    (`pages_Id` ASC),
  INDEX `fk_UserXPLog_sections1_idx` (`sections_Id` ASC),
  CONSTRAINT `fk_UserXPLog_pages1`
    FOREIGN KEY (`pages_Id`)    REFERENCES `pages` (`Id`)        ON DELETE SET NULL,
  CONSTRAINT `fk_UserXPLog_sections1`
    FOREIGN KEY (`sections_Id`) REFERENCES `sections` (`Id`)     ON DELETE SET NULL,
  CONSTRAINT `fk_UserXPLog_accounts1`
    FOREIGN KEY (`accounts_username`) REFERENCES `accounts` (`username`) ON DELETE CASCADE
) ENGINE=InnoDB;

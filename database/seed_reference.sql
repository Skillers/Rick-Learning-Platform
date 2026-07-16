-- =============================================================
-- MANDATORY REFERENCE DATA  (run on EVERY database)
-- Run AFTER CreateScriptRickLearningPlatform.sql, on any DB — fresh, hand-built,
-- or production. The demo seed (seed.sql) already contains this data, so a full
-- rebuild (create → seed) doesn't need it; this file exists so a DB that was NOT
-- fully seeded (e.g. your live/production DB) can get the required rows on their own.
--
-- These six tables are fixed lookup/enumeration data the app cannot run without:
--   PageTypes        → creating pages   (pages.PageType_Id FK)
--   ComponentType    → page components  (components.ComponentType_ComponentTypeText FK)
--   Languages        → code snippets    (codesnippets.Languages_Id FK)
--   EmptySpaceTypes  → spacer lines     (EmptySpace.table1_LineType FK)
--   QuestionContext  → quiz attempts    (AC_Did_Question.QuestionContext_ContextType FK)
--   MultiMediaType   → media components (MultiMedia.MultiMediaType FK)
--
-- Safe & idempotent: INSERT IGNORE only adds missing rows; existing data and any
-- extra rows you added are left untouched. Re-running changes nothing.
-- =============================================================

USE `Rick Learning Platform`;

-- Page types (Id is fixed — the editor maps theory→1, exercise→2, quiz→3, project→4, test→5)
INSERT IGNORE INTO `PageTypes` (`Id`, `Name`, `Color`) VALUES
(1, 'lesson',   'type-lesson'),
(2, 'exercise', 'type-exercise'),
(3, 'quiz',     'type-quiz'),
(4, 'project',  'type-project'),
(5, 'test',     'type-test');

-- Component types (must cover every type the editor can create)
INSERT IGNORE INTO `ComponentType` (`ComponentTypeText`) VALUES
('text'),
('code'),
('quiz'),
('tip'),
('warning'),
('multimedia'),
('assignment');

-- Code-snippet languages (Id is fixed — the editor maps by name)
INSERT IGNORE INTO `Languages` (`Id`, `LanguageName`) VALUES
(1, 'Python'),
(2, 'JavaScript'),
(3, 'Java'),
(4, 'C#'),
(5, 'HTML'),
(6, 'CSS'),
(7, 'SQL');

-- Spacer line styles
INSERT IGNORE INTO `EmptySpaceTypes` (`LineType`) VALUES
('nothing'),
('dotted'),
('double_dotted'),
('dashed'),
('double_dashed'),
('line'),
('double_line');

-- Quiz attempt contexts
INSERT IGNORE INTO `QuestionContext` (`ContextType`) VALUES
('section'),
('mind_trainer'),
('refresher');

-- Media types
INSERT IGNORE INTO `MultiMediaType` (`MultiMediaType`) VALUES
('video'),
('image'),
('audio');

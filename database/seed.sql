-- =============================================================
-- ICT Leerlijn — Combined seed
-- Run AFTER CreateScriptRickLearningPlatform.sql
-- =============================================================

USE `Rick Learning Platform`;

-- -------------------------------------------------------------
-- Page types
-- -------------------------------------------------------------
INSERT INTO `PageTypes` (`Id`, `Name`, `Color`) VALUES
(1, 'lesson',   'type-lesson'),
(2, 'exercise', 'type-exercise'),
(3, 'quiz',     'type-quiz'),
(4, 'project',  'type-project');

-- -------------------------------------------------------------
-- Subjects
-- -------------------------------------------------------------
INSERT INTO `Subjects` (`Name`) VALUES
('Programmeren'),   -- id 1
('Game & VR'),      -- id 2
('Rekenen MBO');    -- id 3

-- -------------------------------------------------------------
-- Courses
-- -------------------------------------------------------------
INSERT INTO `Courses` (`Name`, `Icon`, `Color`, `Subject_Id`) VALUES
('Python',                 'PY', 'c-python', 1),  -- id 1
('JavaScript (Processing)','JS', 'c-js',     1),  -- id 2
('Java',                   'JA', 'c-java',   1),  -- id 3
('Unity 6',                'U6', 'c-unity',  2),  -- id 4
('Unity 6 OpenXR / VR',    'VR', 'c-vr',     2),  -- id 5
('Rekenen voor N4',        'N4', 'c-math',   3),  -- id 6
('Rekenen voor N3',        'N3', 'c-math',   3);  -- id 7

-- -------------------------------------------------------------
-- Pages  (71 rows; IDs assigned in insertion order)
-- -------------------------------------------------------------
INSERT INTO `Pages` (`Course_Id`, `title`, `order`, `published`, `PageType_Id`) VALUES

-- Python (course 1) → pages 1–10
(1, 'Introductie Python',    1, 1, 1),
(1, 'Variabelen & types',    2, 1, 1),
(1, 'Loops & iteratie',      3, 1, 2),
(1, 'Functies',              4, 1, 1),
(1, 'Lijsten & dicts',       5, 1, 1),
(1, 'Bestanden lezen',       6, 1, 2),
(1, 'Quiz: Basis Python',    7, 1, 3),
(1, 'OOP & Classes',         8, 1, 1),
(1, 'API''s & requests',     9, 1, 2),
(1, 'Project: Calculator',  10, 1, 4),

-- JavaScript (course 2) → pages 11–20
(2, 'JS Basics',             1, 1, 1),
(2, 'Functies & scope',      2, 1, 1),
(2, 'DOM manipulatie',       3, 1, 2),
(2, 'Events & listeners',    4, 1, 2),
(2, 'Canvas & animaties',    5, 1, 1),
(2, 'Processing / p5.js',    6, 1, 1),
(2, 'Quiz: JS Basis',        7, 1, 3),
(2, 'Fetch & JSON',          8, 1, 2),
(2, 'Creatieve sketch',      9, 1, 2),
(2, 'Project: Mini-game',   10, 1, 4),

-- Java (course 3) → pages 21–32
(3, 'Java Introductie',       1, 1, 1),
(3, 'Datatypes & variabelen', 2, 1, 1),
(3, 'Methoden',               3, 1, 1),
(3, 'Arrays',                 4, 1, 2),
(3, 'OOP & Classes',          5, 1, 1),
(3, 'Overerving',             6, 1, 1),
(3, 'Interfaces',             7, 1, 1),
(3, 'Quiz: Java Basis',       8, 1, 3),
(3, 'Collections',            9, 1, 1),
(3, 'Exceptions',            10, 1, 2),
(3, 'File I/O',              11, 1, 2),
(3, 'Project: Console game', 12, 1, 4),

-- Unity 6 (course 4) → pages 33–43
(4, 'Unity setup & editor',    1, 1, 1),
(4, 'GameObjects & Scene',     2, 1, 1),
(4, 'Components & Inspector',  3, 1, 1),
(4, 'C# Basis in Unity',       4, 1, 1),
(4, 'C# Scripting basics',     5, 1, 2),
(4, 'Physics & colliders',     6, 1, 1),
(4, 'UI Canvas & TextMeshPro', 7, 1, 2),
(4, 'Audio & SFX',             8, 1, 1),
(4, 'Animator & clips',        9, 1, 1),
(4, 'Quiz: Unity Basis',      10, 1, 3),
(4, 'Mini-game project',      11, 1, 4),

-- Unity 6 OpenXR / VR (course 5) → pages 44–51
(5, 'XR Plugin setup',      1, 1, 1),
(5, 'OpenXR configureren',  2, 1, 1),
(5, 'Controllers & input',  3, 1, 1),
(5, 'Locomotion systemen',  4, 1, 2),
(5, 'XR Interactables',     5, 1, 2),
(5, 'UI in VR',             6, 1, 1),
(5, 'Quiz: XR Basis',       7, 1, 3),
(5, 'Project: VR scene',    8, 1, 4),

-- Rekenen N4 (course 6) → pages 52–61
(6, 'Getallen & bewerkingen', 1, 1, 1),
(6, 'Procenten',              2, 1, 2),
(6, 'Breuken',                3, 1, 1),
(6, 'Verhoudingen',           4, 1, 2),
(6, 'Formules gebruiken',     5, 1, 1),
(6, 'Meten & eenheden',       6, 1, 2),
(6, 'Statistiek',             7, 1, 1),
(6, 'Tabellen & grafieken',   8, 1, 2),
(6, 'Ruimtemeten',            9, 1, 1),
(6, 'Examentraining N4',     10, 1, 3),

-- Rekenen N3 (course 7) → pages 62–71
(7, 'Basis rekenen',        1, 1, 1),
(7, 'Procenten & breuken',  2, 1, 2),
(7, 'Verhoudingen',         3, 1, 2),
(7, 'Meten & meetkunde',    4, 1, 2),
(7, 'Formules',             5, 1, 1),
(7, 'Statistiek & kansen',  6, 1, 1),
(7, 'Tabellen & grafieken', 7, 1, 2),
(7, 'Ruimtemeten',          8, 1, 1),
(7, 'Geld & financieel',    9, 1, 2),
(7, 'Examentraining N3',   10, 1, 3);

-- -------------------------------------------------------------
-- Sections  (3–4 per page, explicit IDs required by schema)
-- -------------------------------------------------------------
INSERT INTO `Sections` (`Id`, `Pages_Id`, `Title`, `Order`) VALUES

-- Page 1 — Introductie Python
(1,  1, 'Wat is Python?',          1),
(2,  1, 'Installatie & setup',     2),
(3,  1, 'Je eerste programma',     3),

-- Page 2 — Variabelen & types
(4,  2, 'Wat zijn variabelen?',    1),
(5,  2, 'Datatypes: int, float, str', 2),
(6,  2, 'Type casting',            3),

-- Page 3 — Loops & iteratie
(7,  3, 'Wat is een loop?',        1),
(8,  3, 'De for-loop',             2),
(9,  3, 'De while-loop',           3),
(10, 3, 'Geneste loops',           4),

-- Page 4 — Functies
(11, 4, 'Functies definiëren',     1),
(12, 4, 'Parameters & argumenten', 2),
(13, 4, 'Return-waarden',          3),

-- Page 5 — Lijsten & dicts
(14, 5, 'Lijsten aanmaken',        1),
(15, 5, 'Lijsten bewerken',        2),
(16, 5, 'Dictionaries',            3),
(17, 5, 'Itereren over collecties',4),

-- Page 6 — Bestanden lezen
(18, 6, 'Bestanden openen',        1),
(19, 6, 'Lezen & schrijven',       2),
(20, 6, 'CSV-bestanden',           3),

-- Page 7 — Quiz: Basis Python
(21, 7, 'Quiz: Variabelen',        1),
(22, 7, 'Quiz: Loops & functies',  2),

-- Page 8 — OOP & Classes
(23, 8, 'Klassen aanmaken',        1),
(24, 8, 'Methoden & attributen',   2),
(25, 8, '__init__ en self',        3),
(26, 8, 'Overerving',              4),

-- Page 9 — API's & requests
(27, 9, 'Wat is een API?',         1),
(28, 9, 'HTTP requests',           2),
(29, 9, 'JSON verwerken',          3),

-- Page 10 — Project: Calculator
(30, 10, 'Projectomschrijving',    1),
(31, 10, 'Ontwerp & opzet',        2),
(32, 10, 'Implementatie',          3),
(33, 10, 'Inleveren',              4),

-- Page 11 — JS Basics
(34, 11, 'Wat is JavaScript?',     1),
(35, 11, 'Variabelen: var, let, const', 2),
(36, 11, 'Datatypes & operators',  3),
(37, 11, 'Condities: if / else',   4),

-- Page 12 — Functies & scope
(38, 12, 'Functies declareren',    1),
(39, 12, 'Scope & hoisting',       2),
(40, 12, 'Arrow functions',        3),

-- Page 13 — DOM manipulatie
(41, 13, 'Wat is de DOM?',         1),
(42, 13, 'Elementen selecteren',   2),
(43, 13, 'Inhoud aanpassen',       3),
(44, 13, 'Stijlen via JS',         4),

-- Page 14 — Events & listeners
(45, 14, 'Wat zijn events?',       1),
(46, 14, 'addEventListener',       2),
(47, 14, 'Event object & bubbling',3),

-- Page 15 — Canvas & animaties
(48, 15, 'Het canvas-element',     1),
(49, 15, 'Tekenen met context',    2),
(50, 15, 'requestAnimationFrame',  3),

-- Page 16 — Processing / p5.js
(51, 16, 'p5.js opzetten',         1),
(52, 16, 'setup() en draw()',      2),
(53, 16, 'Vormen & kleuren',       3),

-- Page 17 — Quiz: JS Basis
(54, 17, 'Quiz: Variabelen & functies', 1),
(55, 17, 'Quiz: DOM & events',     2),

-- Page 18 — Fetch & JSON
(56, 18, 'Wat is fetch?',          1),
(57, 18, 'Promises & async/await', 2),
(58, 18, 'JSON verwerken',         3),

-- Page 19 — Creatieve sketch
(59, 19, 'Opdrachtomschrijving',   1),
(60, 19, 'Ontwerp',                2),
(61, 19, 'Uitwerking & inleveren', 3),

-- Page 20 — Project: Mini-game
(62, 20, 'Projectomschrijving',    1),
(63, 20, 'Game-loop opzetten',     2),
(64, 20, 'Gameplay uitwerken',     3),
(65, 20, 'Inleveren',              4),

-- Page 21 — Java Introductie
(66, 21, 'Wat is Java?',           1),
(67, 21, 'JDK installeren',        2),
(68, 21, 'Hello World',            3),

-- Page 22 — Datatypes & variabelen
(69, 22, 'Primitieve datatypes',   1),
(70, 22, 'Variabelen declareren',  2),
(71, 22, 'Type casting',           3),

-- Page 23 — Methoden
(72, 23, 'Methoden schrijven',     1),
(73, 23, 'Parameters & return',    2),
(74, 23, 'Overloading',            3),

-- Page 24 — Arrays
(75, 24, 'Arrays aanmaken',        1),
(76, 24, 'Arrays itereren',        2),
(77, 24, 'Multidimensionale arrays',3),

-- Page 25 — OOP & Classes
(78, 25, 'Klassen & objecten',     1),
(79, 25, 'Constructor',            2),
(80, 25, 'Getters & setters',      3),
(81, 25, 'Access modifiers',       4),

-- Page 26 — Overerving
(82, 26, 'Wat is overerving?',     1),
(83, 26, 'extends & super',        2),
(84, 26, 'Method overriding',      3),

-- Page 27 — Interfaces
(85, 27, 'Wat is een interface?',  1),
(86, 27, 'Implementeren',          2),
(87, 27, 'Interface vs abstract',  3),

-- Page 28 — Quiz: Java Basis
(88, 28, 'Quiz: OOP',              1),
(89, 28, 'Quiz: Methoden & arrays',2),

-- Page 29 — Collections
(90, 29, 'ArrayList',              1),
(91, 29, 'HashMap',                2),
(92, 29, 'Itereren & streams',     3),

-- Page 30 — Exceptions
(93, 30, 'Wat is een exception?',  1),
(94, 30, 'try / catch / finally',  2),
(95, 30, 'Eigen exceptions',       3),

-- Page 31 — File I/O
(96, 31, 'Bestanden lezen',        1),
(97, 31, 'Bestanden schrijven',    2),
(98, 31, 'BufferedReader / Writer',3),

-- Page 32 — Project: Console game
(99,  32, 'Projectomschrijving',   1),
(100, 32, 'Ontwerp & klassen',     2),
(101, 32, 'Implementatie',         3),
(102, 32, 'Inleveren',             4),

-- Page 33 — Unity setup & editor
(103, 33, 'Unity Hub & installatie',1),
(104, 33, 'Editor verkennen',      2),
(105, 33, 'Project aanmaken',      3),

-- Page 34 — GameObjects & Scene
(106, 34, 'Wat is een GameObject?',1),
(107, 34, 'Scene & Hierarchy',     2),
(108, 34, 'Transform component',   3),

-- Page 35 — Components & Inspector
(109, 35, 'Wat zijn components?',  1),
(110, 35, 'Inspector gebruiken',   2),
(111, 35, 'Eigen component toevoegen', 3),

-- Page 36 — C# Basis in Unity
(112, 36, 'C# syntax basis',       1),
(113, 36, 'Variabelen in Unity',   2),
(114, 36, 'Je eerste script',      3),
(115, 36, 'Script koppelen',       4),

-- Page 37 — C# Scripting basics
(116, 37, 'Update() en Start()',   1),
(117, 37, 'Input uitlezen',        2),
(118, 37, 'Object bewegen',        3),

-- Page 38 — Physics & colliders
(119, 38, 'Rigidbody',             1),
(120, 38, 'Collider types',        2),
(121, 38, 'OnCollisionEnter',      3),

-- Page 39 — UI Canvas & TextMeshPro
(122, 39, 'Canvas opzetten',       1),
(123, 39, 'Button & Text',         2),
(124, 39, 'UI aansturen via script',3),

-- Page 40 — Audio & SFX
(125, 40, 'AudioSource toevoegen', 1),
(126, 40, 'Clips afspelen',        2),
(127, 40, 'Volume & trigger',      3),

-- Page 41 — Animator & clips
(128, 41, 'Animator Controller',   1),
(129, 41, 'Animatieclips',         2),
(130, 41, 'Transitions & parameters',3),

-- Page 42 — Quiz: Unity Basis
(131, 42, 'Quiz: Editor & scene',  1),
(132, 42, 'Quiz: Scripting',       2),

-- Page 43 — Mini-game project
(133, 43, 'Projectomschrijving',   1),
(134, 43, 'Ontwerp & prototyping', 2),
(135, 43, 'Uitwerking',            3),
(136, 43, 'Inleveren',             4),

-- Page 44 — XR Plugin setup
(137, 44, 'XR Plugin Framework',   1),
(138, 44, 'OpenXR package laden',  2),
(139, 44, 'Build settings',        3),

-- Page 45 — OpenXR configureren
(140, 45, 'Interaction Profiles',  1),
(141, 45, 'Feature groups',        2),
(142, 45, 'Testen in Play Mode',   3),

-- Page 46 — Controllers & input
(143, 46, 'Input System overview', 1),
(144, 46, 'Controller bindings',   2),
(145, 46, 'Input acties koppelen', 3),

-- Page 47 — Locomotion systemen
(146, 47, 'Teleportatie',          1),
(147, 47, 'Continuous movement',   2),
(148, 47, 'Snap turn',             3),

-- Page 48 — XR Interactables
(149, 48, 'Grab Interactable',     1),
(150, 48, 'Socket Interactor',     2),
(151, 48, 'Hover & Select events', 3),

-- Page 49 — UI in VR
(152, 49, 'World-space canvas',    1),
(153, 49, 'Interactable UI',       2),
(154, 49, 'Laser pointer UI',      3),

-- Page 50 — Quiz: XR Basis
(155, 50, 'Quiz: Setup & input',   1),
(156, 50, 'Quiz: Interactables',   2),

-- Page 51 — Project: VR scene
(157, 51, 'Projectomschrijving',   1),
(158, 51, 'Scene ontwerp',         2),
(159, 51, 'Uitwerking',            3),
(160, 51, 'Inleveren',             4),

-- Page 52 — Getallen & bewerkingen
(161, 52, 'Bewerkingsvolgorde',    1),
(162, 52, 'Afronden',              2),
(163, 52, 'Negatieve getallen',    3),

-- Page 53 — Procenten
(164, 53, 'Wat is een procent?',   1),
(165, 53, 'Berekenen met procenten',2),
(166, 53, 'Toe- en afname',        3),

-- Page 54 — Breuken
(167, 54, 'Breuken vereenvoudigen',1),
(168, 54, 'Optellen & aftrekken',  2),
(169, 54, 'Vermenigvuldigen & delen',3),

-- Page 55 — Verhoudingen
(170, 55, 'Wat is een verhouding?',1),
(171, 55, 'Schaalrekenen',         2),
(172, 55, 'Recept- en mengverhoudingen',3),

-- Page 56 — Formules gebruiken
(173, 56, 'Formules lezen',        1),
(174, 56, 'Formules invullen',     2),
(175, 56, 'Formule omschrijven',   3),

-- Page 57 — Meten & eenheden
(176, 57, 'Lengte & oppervlakte',  1),
(177, 57, 'Inhoud & gewicht',      2),
(178, 57, 'Eenheden omrekenen',    3),

-- Page 58 — Statistiek
(179, 58, 'Gemiddelde',            1),
(180, 58, 'Mediaan & modus',       2),
(181, 58, 'Spreiding',             3),

-- Page 59 — Tabellen & grafieken
(182, 59, 'Tabellen lezen',        1),
(183, 59, 'Staaf- & lijngrafieken',2),
(184, 59, 'Grafieken interpreteren',3),

-- Page 60 — Ruimtemeten
(185, 60, 'Oppervlakte berekenen', 1),
(186, 60, 'Inhoud berekenen',      2),
(187, 60, 'Samengestelde figuren', 3),

-- Page 61 — Examentraining N4
(188, 61, 'Opgaven: Getallen',     1),
(189, 61, 'Opgaven: Verbanden',    2),
(190, 61, 'Opgaven: Meten',        3),

-- Page 62 — Basis rekenen
(191, 62, 'Optellen & aftrekken',  1),
(192, 62, 'Vermenigvuldigen',      2),
(193, 62, 'Delen',                 3),

-- Page 63 — Procenten & breuken
(194, 63, 'Procenten berekenen',   1),
(195, 63, 'Breuken omrekenen',     2),
(196, 63, 'Gemengde opgaven',      3),

-- Page 64 — Verhoudingen
(197, 64, 'Verhoudingstabel',      1),
(198, 64, 'Schaalrekenen',         2),
(199, 64, 'Praktijkopgaven',       3),

-- Page 65 — Meten & meetkunde
(200, 65, 'Lengte, gewicht, tijd', 1),
(201, 65, 'Oppervlakte & omtrek',  2),
(202, 65, 'Eenheden omrekenen',    3),

-- Page 66 — Formules
(203, 66, 'Formules lezen',        1),
(204, 66, 'Formules invullen',     2),
(205, 66, 'Praktijkopgaven',       3),

-- Page 67 — Statistiek & kansen
(206, 67, 'Gemiddelde & mediaan',  1),
(207, 67, 'Kansen berekenen',      2),
(208, 67, 'Diagrammen lezen',      3),

-- Page 68 — Tabellen & grafieken
(209, 68, 'Tabellen invullen',     1),
(210, 68, 'Grafieken lezen',       2),
(211, 68, 'Verbanden herkennen',   3),

-- Page 69 — Ruimtemeten
(212, 69, 'Oppervlakte',           1),
(213, 69, 'Inhoud',                2),
(214, 69, 'Praktijkopgaven',       3),

-- Page 70 — Geld & financieel
(215, 70, 'Rekenen met geld',      1),
(216, 70, 'Loon & belasting',      2),
(217, 70, 'Kortingen & prijzen',   3),

-- Page 71 — Examentraining N3
(218, 71, 'Opgaven: Getallen',     1),
(219, 71, 'Opgaven: Meten',        2),
(220, 71, 'Opgaven: Verbanden',    3);

-- -------------------------------------------------------------
-- Languages
-- -------------------------------------------------------------
INSERT INTO `Languages` (`Id`, `LanguageName`) VALUES
(1, 'Python'),
(2, 'JavaScript'),
(3, 'Java'),
(4, 'C#'),
(5, 'HTML'),
(6, 'CSS'),
(7, 'SQL');

-- -------------------------------------------------------------
-- ComponentType
-- -------------------------------------------------------------
INSERT INTO `ComponentType` (`ComponentTypeText`) VALUES
('text'),
('code'),
('quiz'),
('tip'),
('warning'),
('multimedia'),
('assignment');

-- -------------------------------------------------------------
-- LineType  (line style lookup table)
-- -------------------------------------------------------------
INSERT INTO `EmptySpaceTypes` (`LineType`) VALUES
('nothing'),
('dotted'),
('double_dotted'),
('dashed'),
('double_dashed'),
('line'),
('double_line');

-- -------------------------------------------------------------
-- QuestionContext
-- -------------------------------------------------------------
INSERT INTO `QuestionContext` (`ContextType`) VALUES
('section'),
('mind_trainer'),
('refresher');

-- -------------------------------------------------------------
-- Components
-- -------------------------------------------------------------
INSERT INTO `Components` (`Id`, `ComponentType_ComponentTypeText`, `section_Id`, `Order`) VALUES

-- Python — page 1 (Introductie Python)
(1,  'text', 1,  1),   -- Wat is Python?       — intro tekst
(2,  'code', 3,  1),   -- Je eerste programma  — print()
(3,  'text', 2,  1),   -- Installatie & setup  — uitleg

-- Python — page 2 (Variabelen & types)
(4,  'text', 4,  1),   -- Wat zijn variabelen? — uitleg
(5,  'code', 4,  2),   -- Wat zijn variabelen? — voorbeeld

-- Python — page 3 (Loops & iteratie)
(6,  'text', 7,  1),   -- Wat is een loop?     — uitleg
(7,  'code', 8,  1),   -- De for-loop          — voorbeeld
(8,  'code', 9,  2),   -- De while-loop        — voorbeeld (after text)

-- JavaScript — page 11 (JS Basics)
(9,  'text', 34, 1),   -- Wat is JavaScript?   — intro tekst
(10, 'code', 34, 2),   -- Wat is JavaScript?   — console.log
(11, 'code', 35, 1),   -- Variabelen           — let/const voorbeeld

-- JavaScript — page 13 (DOM manipulatie)
(12, 'text', 41, 1),   -- Wat is de DOM?       — uitleg
(13, 'code', 42, 2),   -- Elementen selecteren — querySelector (after text)
(14, 'code', 43, 1),   -- Inhoud aanpassen     — textContent

-- Java — page 21 (Java Introductie)
(15, 'text', 66, 1),   -- Wat is Java?         — intro tekst
(16, 'code', 68, 1),   -- Hello World          — voorbeeld

-- Java — page 25 (OOP & Classes)
(17, 'text', 78, 1),   -- Klassen & objecten   — uitleg
(18, 'code', 78, 2),   -- Klassen & objecten   — klasse voorbeeld
(19, 'code', 79, 1),   -- Constructor          — voorbeeld

-- C# / Unity — page 36 (C# Basis in Unity)
(20, 'text', 112, 1),  -- C# syntax basis      — intro tekst
(21, 'code', 112, 2),  -- C# syntax basis      — MonoBehaviour
(22, 'code', 113, 1),  -- Variabelen in Unity  — voorbeeld
(23, 'code', 114, 2),  -- Je eerste script     — Input voorbeeld (after text)

-- Rekenen N4 — page 53 (Procenten)
(24, 'text', 164, 1),  -- Wat is een procent?  — uitleg
(25, 'text', 165, 1),  -- Berekenen            — uitleg stappenplan

-- Rekenen N4 — page 54 (Breuken)
(26, 'text', 167, 1),  -- Breuken vereenvoudigen — uitleg
(27, 'text', 168, 1),  -- Optellen & aftrekken   — uitleg

-- Extra text+code combos
-- Python p3: while-loop gets an explanation before the code
(28, 'text', 9,  1),   -- De while-loop        — uitleg (order 1, code becomes order 2)
-- JS p13: DOM uitleg gevolgd door code
(29, 'text', 42, 1),   -- Elementen selecteren — uitleg (querySelector)
-- Java p21: JDK uitleg
(30, 'text', 67, 1),   -- JDK installeren      — uitleg
-- C# p36: Je eerste script uitleg
(31, 'text', 114, 1);  -- Je eerste script     — uitleg (code becomes order 2)

-- -------------------------------------------------------------
-- TextBLocks  (content for 'text' type components)
-- Id is AUTO_INCREMENT
-- -------------------------------------------------------------
INSERT INTO `TextBLocks` (`Component_Id`, `Text`) VALUES
(1,  'Python is een eenvoudige, leesbare programmeertaal. Hij wordt veel gebruikt voor scripting, data-analyse en webdevelopment. Python draait op alle besturingssystemen en is gratis te downloaden via python.org.'),
(3,  'Download Python via python.org en installeer het op je computer. Zorg dat je "Add Python to PATH" aanvinkt tijdens de installatie. Controleer daarna in de terminal met: python --version'),
(4,  'Een variabele is een naam waaraan je een waarde koppelt. Je kunt er later mee rekenen of de waarde opvragen. In Python hoef je het type niet op te geven — Python bepaalt dat zelf.'),
(6,  'Een loop herhaalt een stuk code meerdere keren. Je gebruikt een for-loop als je van tevoren weet hoe vaak, en een while-loop als je herhaalt totdat een conditie niet meer geldt.'),
(9,  'JavaScript is de programmeertaal van het web. Elke browser voert JavaScript direct uit, zonder installatie. Je gebruikt het om webpagina\'s interactief te maken: knoppen, animaties, formulieren.'),
(12, 'De DOM (Document Object Model) is de boomstructuur die de browser maakt van je HTML. Via JavaScript kun je elk element in de DOM ophalen, aanpassen, toevoegen of verwijderen.'),
(15, 'Java is een objectgeoriënteerde programmeertaal die draait op de JVM (Java Virtual Machine). Hierdoor werkt dezelfde Java-code op Windows, Mac en Linux zonder aanpassingen.'),
(17, 'In Java maak je een klasse als sjabloon voor objecten. Een klasse beschrijft welke eigenschappen (attributen) en acties (methoden) een object heeft. Je maakt objecten door een klasse te instantiëren met new.'),
(20, 'In Unity schrijf je scripts in C#. Elk script erft van MonoBehaviour. De methode Start() wordt eenmalig aangeroepen bij het starten van het spel. Update() wordt elke frame aangeroepen — gebruik dit voor beweging en input.'),
(24, 'Een procent is een honderdste deel. 1% van 200 is dus 2. Je berekent een percentage door de waarde te vermenigvuldigen met het percentage gedeeld door 100. Voorbeeld: 25% van 80 = 80 × 0,25 = 20.'),
(25, 'Stap 1: schrijf het percentage als decimaal getal (bijv. 15% = 0,15). Stap 2: vermenigvuldig met het originele getal. Stap 3: controleer of het antwoord logisch is — meer dan 50% moet meer dan de helft zijn.'),
(26, 'Een breuk vereenvoudig je door teller en noemer te delen door hun grootste gemene deler (ggd). Voorbeeld: 6/8 → ggd is 2 → 3/4. Een vereenvoudigde breuk heeft geen gemeenschappelijke delers meer.'),
(27, 'Breuken met dezelfde noemer tel je op door alleen de tellers op te tellen. Hebben ze een andere noemer, maak dan eerst de noemers gelijk (gelijknamig maken) via de kleinste gemene veelvoud (kgv).'),
(28, 'De while-loop blijft herhalen zolang de conditie waar is. Pas op: vergeet de teller niet te verhogen, anders loop je oneindig. Gebruik een while-loop als je niet van tevoren weet hoe vaak je wilt herhalen.'),
(29, 'Met querySelector() zoek je een element op via een CSS-selector. Met getElementById() zoek je op id. Beide geven het eerste gevonden element terug, of null als er niets gevonden wordt.'),
(30, 'De JDK (Java Development Kit) is de toolset waarmee je Java-programma''s schrijft en compileert. Download de LTS-versie via adoptium.net. Controleer de installatie in je terminal met: java -version'),
(31, 'Koppel je script aan een GameObject door het te slepen naar het GameObject in de Inspector. Het script verschijnt dan als component. Public variabelen zijn zichtbaar en aanpasbaar in de Inspector zonder de code te openen.');

-- -------------------------------------------------------------
-- CodeSnippets  (content for 'code' type components)
-- Columns: Id, Components_Id, Languages_Id, Code
-- -------------------------------------------------------------
INSERT INTO `CodeSnippets` (`Id`, `Components_Id`, `Languages_Id`, `Code`) VALUES

-- Python snippets
(1,  2,  1, 'print("Hello, World!")'),

(2,  5,  1,
'naam = "Alice"
leeftijd = 17
gemiddelde = 8.5
print(naam, leeftijd, gemiddelde)'),

(3,  7,  1,
'# Print getallen 1 tot en met 5
for i in range(1, 6):
    print("Getal:", i)

# Itereren over een lijst
namen = ["Anna", "Boris", "Clara"]
for naam in namen:
    print("Hallo,", naam)'),

(4,  8,  1,
'teller = 0
while teller < 5:
    print("Teller:", teller)
    teller += 1

print("Klaar!")'),

-- JavaScript snippets
(5,  10, 2, 'console.log("Hello, World!");'),

(6,  11, 2,
'// const: waarde verandert niet
const naam = "Student";

// let: waarde mag later wijzigen
let score = 0;
score = 10;

console.log(naam, score);'),

(7,  13, 2,
'// Een element ophalen via CSS-selector
const knop = document.querySelector("#mijnKnop");
const titel = document.getElementById("titel");

console.log(knop, titel);'),

(8,  14, 2,
'const titel = document.getElementById("titel");
titel.textContent = "Nieuwe tekst!";

const paragraaf = document.querySelector("p");
paragraaf.innerHTML = "Tekst met <strong>opmaak</strong>";'),

-- Java snippets
(9,  16, 3,
'public class Main {
    public static void main(String[] args) {
        System.out.println("Hello, World!");
    }
}'),

(10, 18, 3,
'public class Student {
    String naam;
    int leeftijd;
}

// Object aanmaken
Student s = new Student();
s.naam = "Alice";
s.leeftijd = 17;
System.out.println(s.naam);'),

(11, 19, 3,
'public class Student {
    String naam;
    int leeftijd;

    // Constructor
    public Student(String naam, int leeftijd) {
        this.naam = naam;
        this.leeftijd = leeftijd;
    }
}

Student s = new Student("Alice", 17);
System.out.println(s.naam);'),

-- C# snippets
(12, 21, 4,
'using UnityEngine;

public class MijnScript : MonoBehaviour
{
    void Start()
    {
        Debug.Log("Script gestart!");
    }

    void Update()
    {
        // Wordt elk frame aangeroepen
    }
}'),

(13, 22, 4,
'using UnityEngine;

public class MijnScript : MonoBehaviour
{
    // Public: zichtbaar in de Inspector
    public float snelheid = 5f;
    public string spelernaam = "Speler";

    void Start()
    {
        Debug.Log("Naam: " + spelernaam);
    }
}'),

(14, 23, 4,
'using UnityEngine;

public class BeweegScript : MonoBehaviour
{
    public float snelheid = 5f;

    void Update()
    {
        float h = Input.GetAxis("Horizontal");
        float v = Input.GetAxis("Vertical");
        transform.Translate(h * snelheid * Time.deltaTime,
                            0,
                            v * snelheid * Time.deltaTime);
    }
}');

-- -------------------------------------------------------------
-- InfoBox components (tip / warning)
-- Component IDs 32–35
-- -------------------------------------------------------------
INSERT INTO `Components` (`Id`, `ComponentType_ComponentTypeText`, `section_Id`, `Order`) VALUES
(32, 'tip',     1,  2),   -- Python p1 s1: tip na "Wat is Python?" tekst
(33, 'warning', 9,  3),   -- Python p3 s9: while-loop oneindige-loop waarschuwing
(34, 'tip',     34, 3),   -- JS p11 s34: tip na "Wat is JavaScript?" tekst
(35, 'warning', 41, 2),   -- JS p13 s41: DOM waarschuwing
(40, 'warning', 5,  2);   -- Python p2 s5: waarschuwing bij datatypes

INSERT INTO `InfoBoxes` (`Id`, `components_Id`, `Text`, `IsWarning`) VALUES
(1, 32, 'Python is een van de meest populaire talen om mee te beginnen. De syntax lijkt op gewoon Engels, waardoor het makkelijker te lezen is dan veel andere talen.', 0),
(2, 33, 'Vergeet niet de teller te verhogen in een while-loop! Als de conditie altijd waar blijft, loopt je programma oneindig door en moet je het geforceerd stoppen (Ctrl+C).', 1),
(3, 34, 'Je kunt JavaScript direct uitproberen in de console van je browser. Druk op F12 en klik op het tabblad "Console" om te beginnen.', 0),
(4, 35, 'Pas op met innerHTML: als je gebruikersinvoer direct in innerHTML plaatst, kan dat een beveiligingsrisico zijn (XSS). Gebruik liever textContent voor platte tekst.', 1),
(5, 40, 'Let op: Python maakt onderscheid tussen int en float. Als je 3 / 2 doet krijg je 1.5 (float), niet 1. Gebruik // voor integer-deling.', 1);

-- -------------------------------------------------------------
-- PubQuiz components (quiz)
-- Component IDs 36–39
-- -------------------------------------------------------------
INSERT INTO `Components` (`Id`, `ComponentType_ComponentTypeText`, `section_Id`, `Order`) VALUES
(36, 'quiz', 5, 1),    -- Python p2 s5: MC over datatypes
(37, 'quiz', 6, 1),    -- Python p2 s6: MC over type casting
(38, 'quiz', 7, 2),    -- Python p3 s7: open vraag over loops
(39, 'quiz', 36, 1);   -- JS p11 s36: MC over datatypes & operators

INSERT INTO `PQQuestion` (`Id`, `Question`, `OpenQuestion`, `component_Id`) VALUES
(1, 'Welk datatype gebruik je in Python voor een geheel getal?', 0, 36),
(2, 'Wat is het resultaat van int("3.5") in Python?', 0, 37),
(3, 'Leg in je eigen woorden uit: wat is het verschil tussen een for-loop en een while-loop?', 1, 38),
(4, 'Wat is het resultaat van typeof 42 in JavaScript?', 0, 39),
(5, 'Welke van de volgende zijn geldige Python datatypes?', 0, 36);

INSERT INTO `PQAnswer` (`PQQuestion_Id`, `AnswerOption`, `IsCorrect`) VALUES
-- Q1: datatypes
(1, 'int',    1),
(1, 'str',    0),
(1, 'float',  0),
(1, 'bool',   0),
-- Q2: type casting
(2, 'Het getal 3',       0),
(2, 'Het getal 3.5',     0),
(2, 'Een foutmelding',   1),
(2, 'De string "3"',     0),
-- Q3: open vraag over loops (meerdere geaccepteerde antwoorden)
(3, 'Een for-loop gebruik je als je weet hoe vaak je wilt herhalen, een while-loop als je dat niet weet.', 1),
(3, 'Een for-loop telt een vast aantal keer, een while-loop herhaalt zolang een conditie waar is.', 1),
-- Q4: typeof in JS
(4, '"number"',   1),
(4, '"integer"',  0),
(4, '"string"',   0),
(4, '"object"',   0),
-- Q5: MC met meerdere goede antwoorden
(5, 'int',    1),
(5, 'float',  1),
(5, 'str',    1),
(5, 'array',  0),
(5, 'char',   0);

-- -------------------------------------------------------------
-- MultiMediaType
-- -------------------------------------------------------------
INSERT INTO `MultiMediaType` (`MultiMediaType`) VALUES
('video'),
('image'),
('audio');

-- -------------------------------------------------------------
-- MultiMedia components
-- Component IDs 41–42
-- -------------------------------------------------------------
INSERT INTO `Components` (`Id`, `ComponentType_ComponentTypeText`, `section_Id`, `Order`) VALUES
(41, 'multimedia', 5, 3),   -- Python p2 s5: image bij datatypes
(42, 'multimedia', 5, 4);   -- Python p2 s5: video bij datatypes

INSERT INTO `MultiMedia` (`Id`, `URL`, `components_Id`, `Uploaded`, `MultiMediaType_MultiMediaType`) VALUES
(1, 'https://deadlock.wiki/images/b/b8/Shiv_card.png?20250819031257', 41, 0, 'image'),
(2, 'https://www.youtube.com/watch?v=YonS9_QJbp8', 42, 0, 'video');

-- -------------------------------------------------------------
-- EmptySpace  (spacing/lines between components)
-- -------------------------------------------------------------
INSERT INTO `EmptySpace` (`BeforeLineSpace`, `AfterLineSpace`, `table1_LineType`, `components_Id`) VALUES
(16, 16, 'dotted',      41),   -- dotted line after image component
(16, 16, 'double_line', 36);   -- double line after quiz component

-- -------------------------------------------------------------
-- Accounts
-- Password hash = bcrypt of 'wachtwoord123' for all demo users
-- -------------------------------------------------------------
INSERT INTO `accounts` (`username`, `Password`, `Email`, `Role`, `CreatedAt`, `Active`) VALUES
('Rick',      '$2y$10$eRXfKe1YN6Y0iaq4whGirulR3FvOeUeeHGsXN7PPFH85F3ie/Xn9e', 'Rick.nl@hotmail.com',         'superadmin', '2024-09-01 08:00:00', 1),
('Marloes',   '$2y$10$eRXfKe1YN6Y0iaq4whGirulR3FvOeUeeHGsXN7PPFH85F3ie/Xn9e', 'marloes@ictcollege.nl',       'docent',     '2024-09-01 08:30:00', 1),
('JanWillem', '$2y$10$eRXfKe1YN6Y0iaq4whGirulR3FvOeUeeHGsXN7PPFH85F3ie/Xn9e', 'jan.willem@student.rocmn.nl', 'student',    '2024-09-02 10:15:00', 1),
('Fatima',    '$2y$10$eRXfKe1YN6Y0iaq4whGirulR3FvOeUeeHGsXN7PPFH85F3ie/Xn9e', 'fatima@student.rocmn.nl',     'student',    '2024-09-02 10:20:00', 1),
('Daan',      '$2y$10$eRXfKe1YN6Y0iaq4whGirulR3FvOeUeeHGsXN7PPFH85F3ie/Xn9e', 'daan@student.rocmn.nl',       'student',    '2024-09-03 09:00:00', 1),
('Priya',     '$2y$10$eRXfKe1YN6Y0iaq4whGirulR3FvOeUeeHGsXN7PPFH85F3ie/Xn9e', 'priya@student.rocmn.nl',      'student',    '2024-09-03 09:05:00', 1),
('Thomas',    '$2y$10$eRXfKe1YN6Y0iaq4whGirulR3FvOeUeeHGsXN7PPFH85F3ie/Xn9e', 'thomas@student.rocmn.nl',     'student',    '2024-09-04 11:00:00', 1),
('Yusuf',     '$2y$10$eRXfKe1YN6Y0iaq4whGirulR3FvOeUeeHGsXN7PPFH85F3ie/Xn9e', 'yusuf@student.rocmn.nl',      'student',    '2024-10-01 08:45:00', 0);

-- -------------------------------------------------------------
-- Course enrollments
-- Course IDs: 1=Python, 2=JS, 3=Java, 4=Unity6, 5=VR, 6=N4, 7=N3
-- -------------------------------------------------------------
INSERT INTO `accounts_has_courses` (`accounts_username`, `courses_Id`, `Enrolled_at`) VALUES
('JanWillem', 1, '2024-09-02 10:20:00'),
('JanWillem', 3, '2024-09-02 10:20:00'),
('Fatima',    1, '2024-09-02 10:25:00'),
('Fatima',    2, '2024-09-02 10:25:00'),
('Fatima',    4, '2024-09-15 12:00:00'),
('Daan',      1, '2024-09-03 09:10:00'),
('Daan',      4, '2024-09-03 09:10:00'),
('Daan',      5, '2024-10-01 10:00:00'),
('Priya',     2, '2024-09-03 09:10:00'),
('Priya',     6, '2024-09-03 09:10:00'),
('Thomas',    1, '2024-09-04 11:05:00'),
('Thomas',    3, '2024-09-04 11:05:00'),
('Thomas',    7, '2024-09-04 11:05:00'),
('Yusuf',     1, '2024-10-01 08:50:00');

-- -------------------------------------------------------------
-- Assignment components
-- Ids 100-104 to avoid clashing with existing 1-42.
-- -------------------------------------------------------------
INSERT INTO `Components` (`Id`, `ComponentType_ComponentTypeText`, `section_Id`, `Order`) VALUES
(100, 'assignment',   9, 4),   -- Python p3 s9  (while-loop)          — FizzBuzz
(101, 'assignment',   4, 3),   -- Python p2 s4  (variabelen)          — Variabelen-oefening
(102, 'assignment',  35, 3),   -- JS     p11 s35 (variabelen)         — Console-opdracht
(103, 'assignment',  78, 3),   -- Java   p25 s78 (klassen & objecten) — Klasse Hond
(104, 'assignment', 164, 2);   -- N4     p53 s164 (procenten)         — Procenten berekenen

-- -------------------------------------------------------------
-- Assigments
-- -------------------------------------------------------------
INSERT INTO `Assigments` (`Id`, `component_Id`, `FileRequired`, `Title`) VALUES
(1, 100, 0, 'FizzBuzz in Python'),
(2, 101, 0, 'Eigen variabelen declareren'),
(3, 102, 0, 'Console.log spelen met variabelen'),
(4, 103, 1, 'Klasse Hond met constructor'),
(5, 104, 0, 'Procenten uitwerken');

-- -------------------------------------------------------------
-- Homework submissions  (Accounts_have_assignments)
-- Mix of text-only / file-only / both across 5 students, 5 assignments.
-- Dates range from Nov 2024 (early coursework) to Jan 2025.
-- -------------------------------------------------------------
INSERT INTO `Accounts_have_assignments`
  (`account_username`, `Assigment_Id`, `SubmittedTextAnswer`, `FileName`, `FilePath`, `SubmittedOn`) VALUES

-- Priya — JS console opdracht
('Priya', 3,
 'let naam = "Priya"; console.log(`Hallo ${naam}`); // werkt!',
 NULL, NULL,
 '2025-01-15 14:30:00'),

-- JanWillem — Python FizzBuzz, tekst + bestand
('JanWillem', 1,
 'Ik heb FizzBuzz tot 100 werkend. Gebruikt % 3 en % 5 in een if-elif keten.',
 'fizzbuzz_janwillem.py', 'submissions/2025/01/fizzbuzz_janwillem.py',
 '2025-01-14 10:20:00'),

-- Daan — Python FizzBuzz, alleen bestand
('Daan', 1,
 NULL,
 'fizzbuzz.py', 'submissions/2025/01/fizzbuzz_daan.py',
 '2025-01-13 09:45:00'),

-- Fatima — JS console opdracht, tekst + bestand
('Fatima', 3,
 'Voorbeelden getest in de browserconsole. Template literals werken met ${}.',
 'console_fatima.js', 'submissions/2025/01/console_fatima.js',
 '2025-01-12 16:00:00'),

-- Fatima — Python variabelen, tekst
('Fatima', 2,
 'Ik heb variabelen gemaakt voor naam, leeftijd en stad en deze geprint met f-strings.',
 NULL, NULL,
 '2025-01-10 11:30:00'),

-- Daan — Python variabelen, tekst
('Daan', 2,
 'Vier variabelen: int, float, str, bool. Geprint met print(type(x), x).',
 NULL, NULL,
 '2025-01-09 13:15:00'),

-- Thomas — Python FizzBuzz, tekst + bestand
('Thomas', 1,
 'Eerst met if-elif, daarna opgeschoond met een aparte functie fizzbuzz(n).',
 'fizzbuzz_thomas.py', 'submissions/2025/01/fizzbuzz_thomas.py',
 '2025-01-07 10:00:00'),

-- JanWillem — Java klasse Hond, tekst + bestand
('JanWillem', 4,
 'Klasse Hond met velden naam, ras, leeftijd. Constructor + toString().',
 'Hond.java', 'submissions/2024/12/Hond_janwillem.java',
 '2024-12-20 14:45:00'),

-- Thomas — Java klasse Hond, alleen bestand
('Thomas', 4,
 NULL,
 'Hond.java', 'submissions/2024/12/Hond_thomas.java',
 '2024-12-10 11:30:00'),

-- Priya — N4 procenten, vroege inzending
('Priya', 5,
 'Uitwerking: 25% van 80 = 20. Stappenplan: deel door 100, keer het percentage.',
 NULL, NULL,
 '2024-11-25 10:00:00');

-- -------------------------------------------------------------
-- Quiz: open-question attempts  (AC_Did_Question)
-- Only PQQuestion 3 has OpenQuestion = 1 in the seed.
-- OpenAnswer and ReviewFeedback ≤ 45 chars (current VARCHAR(45)).
-- 3 unreviewed, 2 already reviewed by docent/admin.
-- -------------------------------------------------------------
INSERT INTO `AC_Did_Question`
  (`accounts_username`, `PQQuestion_Id`, `QuestionContext_ContextType`,
   `AttemptDate`, `OpenAnswer`, `ReviewedBy`, `ReviewedAt`, `ReviewFeedback`) VALUES

('Priya',     3, 'section', '2025-01-15 08:00:00',
 'Ik weet het niet goed',
 NULL, NULL, NULL),

('JanWillem', 3, 'section', '2025-01-13 14:00:00',
 'for = vast, while = zolang waar',
 NULL, NULL, NULL),

('Fatima',    3, 'section', '2025-01-11 10:30:00',
 'for weet je hoe vaak, while niet',
 NULL, NULL, NULL),

('Daan',      3, 'section', '2025-01-08 09:00:00',
 'for itereert lijst, while op conditie',
 'Marloes', '2025-01-11 15:30:00', 'Goed uitgelegd!'),

('Thomas',    3, 'section', '2025-01-03 11:00:00',
 'for heeft teller, while heeft conditie',
 'Rick', '2025-01-06 09:15:00', 'Bijna. Controleer de loops.');

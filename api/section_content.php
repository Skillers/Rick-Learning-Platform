<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/db.connection.php';

$page_id = isset($_GET['page_id']) ? (int)$_GET['page_id'] : 0;
if (!$page_id) { echo '[]'; exit; }

$stmt = $pdo->prepare("
    SELECT
        c.Id            AS id,
        c.TypeName      AS type,
        c.Section_Id    AS section_id,
        c.Order         AS `order`,
        COALESCE(cs.Code, tb.Text) AS content,
        l.LanguageName  AS language
    FROM Components c
    JOIN  Sections     s   ON s.Id           = c.Section_Id
    LEFT JOIN CodeSnippets cs  ON cs.Components_Id = c.Id
    LEFT JOIN Languages   l   ON l.Id             = cs.Languages_Id
    LEFT JOIN TextBLocks  tb  ON tb.Component_Id  = c.Id
    WHERE s.Pages_Id = ?
    ORDER BY c.Section_Id, c.`Order`
");
$stmt->execute([$page_id]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($rows, JSON_UNESCAPED_UNICODE);

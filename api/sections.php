<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/db.connection.php';

$rows = $pdo->query("
    SELECT
        s.`Id`       AS `id`,
        s.`Pages_Id` AS `page_id`,
        s.`Title`    AS `title`,
        s.`Order`    AS `order`,
        EXISTS (
            SELECT 1 FROM `Components` c
            WHERE c.`Section_Id` = s.`Id`
            AND c.`ComponentType_ComponentTypeText` = 'quiz'
        ) AS `has_interaction`
    FROM `Sections` s
    ORDER BY s.`Pages_Id`, s.`Order`
")->fetchAll();

echo json_encode($rows, JSON_UNESCAPED_UNICODE);

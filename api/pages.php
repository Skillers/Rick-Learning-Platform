<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/db.connection.php';

$rows = $pdo->query("
    SELECT
        p.`Id`        AS `id`,
        p.`Course_Id` AS `course_id`,
        p.`title`,
        pt.`Name`     AS `type`
    FROM `Pages` p
    LEFT JOIN `PageTypes` pt ON p.`PageType_Id` = pt.`Id`
    WHERE p.`published` = 1
      AND EXISTS (SELECT 1 FROM `Sections` s WHERE s.`Pages_Id` = p.`Id`)
    ORDER BY p.`Course_Id`, p.`order`
")->fetchAll();

echo json_encode($rows, JSON_UNESCAPED_UNICODE);

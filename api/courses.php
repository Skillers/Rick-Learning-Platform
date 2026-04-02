<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/db.connection.php';

$rows = $pdo->query("
    SELECT
        c.`Id`         AS `id`,
        c.`Name`       AS `name`,
        c.`Icon`       AS `icon`,
        c.`Color`      AS `color`,
        c.`Subject_Id` AS `subject_id`,
        s.`Name`       AS `section`
    FROM `Courses` c
    JOIN `Subjects` s ON c.`Subject_Id` = s.`id`
    ORDER BY c.`Id`
")->fetchAll();

echo json_encode($rows, JSON_UNESCAPED_UNICODE);

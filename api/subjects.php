<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/db.connection.php';

$rows = $pdo->query("
    SELECT `id`, `Name` AS `name`
    FROM `Subjects`
    ORDER BY `id`
")->fetchAll();

echo json_encode($rows, JSON_UNESCAPED_UNICODE);

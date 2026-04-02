<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/db.connection.php';

$rows = $pdo->query("
    SELECT `Id` AS `id`, `Pages_Id` AS `page_id`, `Title` AS `title`, `Order` AS `order`
    FROM `Sections`
    ORDER BY `Pages_Id`, `Order`
")->fetchAll();

echo json_encode($rows, JSON_UNESCAPED_UNICODE);

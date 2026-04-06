<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/db.connection.php';

$username = trim($_GET['username'] ?? '');
if (!$username) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing username']);
    exit;
}

$stmt = $pdo->prepare(
    "SELECT `Pages_Id` AS page_id, `Completed` AS completed
     FROM `Accounts_opened_Pages`
     WHERE `Accounts_username` = ?"
);
$stmt->execute([$username]);

echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));

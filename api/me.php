<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/db.connection.php';

$username = isset($_GET['username']) ? trim($_GET['username']) : '';
if (!$username) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing username']);
    exit;
}

$stmt = $pdo->prepare("SELECT `username`, `Email` FROM `Accounts` WHERE `username` = ?");
$stmt->execute([$username]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$row) {
    http_response_code(404);
    echo json_encode(['error' => 'Account niet gevonden']);
    exit;
}

echo json_encode([
    'username' => $row['username'],
    'email'    => $row['Email'],
]);

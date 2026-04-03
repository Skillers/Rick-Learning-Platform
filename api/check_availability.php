<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/db.connection.php';

$username = isset($_GET['username']) ? trim($_GET['username']) : null;
$email    = isset($_GET['email'])    ? trim($_GET['email'])    : null;

if ($username !== null) {
    $stmt = $pdo->prepare("SELECT 1 FROM `Accounts` WHERE `username` = ?");
    $stmt->execute([$username]);
    echo json_encode(['available' => !$stmt->fetch()]);
    exit;
}

if ($email !== null) {
    $stmt = $pdo->prepare("SELECT 1 FROM `Accounts` WHERE `Email` = ?");
    $stmt->execute([$email]);
    echo json_encode(['available' => !$stmt->fetch()]);
    exit;
}

http_response_code(400);
echo json_encode(['error' => 'Missing parameter']);

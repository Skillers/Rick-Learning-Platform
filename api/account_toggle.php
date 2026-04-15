<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/db.connection.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$username = $data['username'] ?? '';
$active   = isset($data['active']) ? (int)$data['active'] : null;

if (!$username || $active === null) {
    http_response_code(400);
    echo json_encode(['error' => 'username and active required']);
    exit;
}

$stmt = $pdo->prepare("UPDATE `accounts` SET `Active` = :active WHERE `username` = :username");
$stmt->execute(['active' => $active, 'username' => $username]);

echo json_encode(['success' => true, 'username' => $username, 'active' => $active]);

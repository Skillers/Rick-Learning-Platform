<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/db.connection.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$body     = json_decode(file_get_contents('php://input'), true);
$username = trim($body['username'] ?? '');
$page_id  = (int)($body['page_id'] ?? 0);

if (!$username || !$page_id) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing fields']);
    exit;
}

// INSERT IGNORE keeps Completed untouched if the row already exists
$stmt = $pdo->prepare(
    "INSERT IGNORE INTO `Accounts_opened_Pages` (`Accounts_username`, `Pages_Id`) VALUES (?, ?)"
);
$stmt->execute([$username, $page_id]);

echo json_encode(['success' => true]);

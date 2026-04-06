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
$email    = trim($body['email']    ?? '');
$password =      $body['password'] ?? '';

if (!$username || !$email || !$password) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing fields']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['error' => 'Ongeldig e-mailadres']);
    exit;
}

// Verify password
$stmt = $pdo->prepare("SELECT `Password` FROM `Accounts` WHERE `username` = ?");
$stmt->execute([$username]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$row) {
    http_response_code(404);
    echo json_encode(['error' => 'Account niet gevonden']);
    exit;
}
$peppered = hash_hmac('sha256', $password, $username);
if (!password_verify($peppered, $row['Password'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Onjuist wachtwoord']);
    exit;
}

// Check email not already in use by a different account
$stmt = $pdo->prepare("SELECT `username` FROM `Accounts` WHERE `Email` = ? AND `username` != ?");
$stmt->execute([$email, $username]);
if ($stmt->fetch()) {
    http_response_code(409);
    echo json_encode(['error' => 'Dit e-mailadres is al in gebruik']);
    exit;
}

$stmt = $pdo->prepare("UPDATE `Accounts` SET `Email` = ? WHERE `username` = ?");
$stmt->execute([$email, $username]);

echo json_encode(['success' => true]);

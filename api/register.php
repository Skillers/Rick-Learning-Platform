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

// Server-side validation
if (!$username || !$email || !$password) {
    http_response_code(400);
    echo json_encode(['error' => 'Vul alle velden in.']);
    exit;
}
if (strlen($username) < 4) {
    http_response_code(400);
    echo json_encode(['error' => 'Gebruikersnaam moet minimaal 4 tekens bevatten.']);
    exit;
}
if (preg_match('/\s/', $username)) {
    http_response_code(400);
    echo json_encode(['error' => 'Gebruikersnaam mag geen spaties bevatten.']);
    exit;
}
if (strlen($password) < 9) {
    http_response_code(400);
    echo json_encode(['error' => 'Wachtwoord moet minimaal 9 tekens bevatten.']);
    exit;
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['error' => 'Voer een geldig e-mailadres in.']);
    exit;
}

// Check username uniqueness
$stmt = $pdo->prepare("SELECT 1 FROM `Accounts` WHERE `username` = ?");
$stmt->execute([$username]);
if ($stmt->fetch()) {
    http_response_code(409);
    echo json_encode(['error' => 'Deze gebruikersnaam is al in gebruik.']);
    exit;
}

// Check email uniqueness
$stmt = $pdo->prepare("SELECT 1 FROM `Accounts` WHERE `Email` = ?");
$stmt->execute([$email]);
if ($stmt->fetch()) {
    http_response_code(409);
    echo json_encode(['error' => 'Dit e-mailadres is al in gebruik.']);
    exit;
}

// Insert — pepper password with username before hashing
$peppered = hash_hmac('sha256', $password, $username);
$hash     = password_hash($peppered, PASSWORD_BCRYPT);
$stmt = $pdo->prepare("INSERT INTO `Accounts` (`username`, `Password`, `Email`) VALUES (?, ?, ?)");
$stmt->execute([$username, $hash, $email]);

echo json_encode(['success' => true]);

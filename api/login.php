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
$password =      $body['password'] ?? '';

if (!$username || !$password) {
    http_response_code(400);
    echo json_encode(['error' => 'Vul beide velden in']);
    exit;
}

$stmt = $pdo->prepare("SELECT `username`, `Password`, `Email`, `Role`, `Active` FROM `accounts` WHERE `username` = ?");
$stmt->execute([$username]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$row) {
    http_response_code(401);
    echo json_encode(['error' => 'Ongeldige gebruikersnaam of wachtwoord']);
    exit;
}

// Same pepper scheme as update_email.php
$peppered = hash_hmac('sha256', $password, $username);
if (!password_verify($peppered, $row['Password'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Ongeldige gebruikersnaam of wachtwoord']);
    exit;
}

if (!$row['Active']) {
    http_response_code(403);
    echo json_encode(['error' => 'Dit account is gedeactiveerd']);
    exit;
}

echo json_encode([
    'success'  => true,
    'username' => $row['username'],
    'email'    => $row['Email'],
    'role'     => $row['Role']
]);

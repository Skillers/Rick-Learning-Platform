<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/db.connection.php';

$username = isset($_GET['username']) ? trim($_GET['username']) : '';
if (!$username) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing username']);
    exit;
}

$stmt = $pdo->prepare("SELECT `username`, `Email`, `Role` FROM `accounts` WHERE `username` = ?");
$stmt->execute([$username]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$row) {
    http_response_code(404);
    echo json_encode(['error' => 'Account niet gevonden']);
    exit;
}

$result = [
    'username' => $row['username'],
    'email'    => $row['Email'],
    'role'     => $row['Role'],
];

// For docents: return assigned courses and mentored students
// For superadmins: null = no restrictions (bypass all scoping)
if ($row['Role'] === 'docent') {
    $stmt = $pdo->prepare("SELECT `courses_Id` FROM `Teacher_ParticipatesIn_Course` WHERE `accounts_username` = ?");
    $stmt->execute([$username]);
    $result['courses'] = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));

    $stmt = $pdo->prepare("SELECT `accounts_Student` FROM `Teacher_guides_Student` WHERE `accounts_Teacher` = ?");
    $stmt->execute([$username]);
    $result['students'] = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $stmt = $pdo->prepare("SELECT `Groups_GroupNames` FROM `Group_has_Teacher` WHERE `accounts_username` = ?");
    $stmt->execute([$username]);
    $result['groups'] = $stmt->fetchAll(PDO::FETCH_COLUMN);
} elseif ($row['Role'] === 'superadmin') {
    $result['courses']  = null;
    $result['students'] = null;
    $result['groups']   = null;
}

echo json_encode($result, JSON_UNESCAPED_UNICODE);

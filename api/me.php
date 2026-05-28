<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/db.connection.php';

$username = isset($_GET['username']) ? trim($_GET['username']) : '';
if (!$username) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing username']);
    exit;
}

$stmt = $pdo->prepare("SELECT `username`, `Email`, `Role`, `CreatedAt` FROM `accounts` WHERE `username` = ?");
$stmt->execute([$username]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$row) {
    http_response_code(404);
    echo json_encode(['error' => 'Account niet gevonden']);
    exit;
}

$result = [
    'username'    => $row['username'],
    'email'       => $row['Email'],
    'role'        => $row['Role'],
    'memberSince' => substr($row['CreatedAt'], 0, 10),
];

// ── Login streak ────────────────────────────────────────────────
// handleLogin never calls login.php, so me.php is the only endpoint
// hit on every app-boot, so it doubles as the "active today" signal.
// Streak math is day-granular, so repeated same-day boots are no-ops.
$stmt = $pdo->prepare("SELECT `LastLogin`, `LongestStreak`, `CurrentStreak` FROM `AccountStats` WHERE `accounts_username` = ?");
$stmt->execute([$username]);
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$stats) {
    // Account predates AccountStats: start the streak today.
    $current = 1;
    $longest = 1;
    $stmt = $pdo->prepare("INSERT INTO `AccountStats` (`accounts_username`, `LastLogin`, `LongestStreak`, `CurrentStreak`) VALUES (?, NOW(), 1, 1)");
    $stmt->execute([$username]);
} else {
    $current = (int) $stats['CurrentStreak'];
    $longest = (int) $stats['LongestStreak'];
    $gap     = (int) (new DateTime(substr($stats['LastLogin'], 0, 10)))
                      ->diff(new DateTime('today'))->days;

    if ($gap === 1) {
        $current += 1;          // consecutive day, streak grows
    } elseif ($gap >= 2) {
        $current = 1;           // streak broken, today restarts the count
    }                           // gap === 0 → already counted today
    if ($current > $longest) {
        $longest = $current;
    }
    if ($gap > 0) {             // only write when the calendar day changed
        $stmt = $pdo->prepare("UPDATE `AccountStats` SET `LastLogin` = NOW(), `CurrentStreak` = ?, `LongestStreak` = ? WHERE `accounts_username` = ?");
        $stmt->execute([$current, $longest, $username]);
    }
}

$result['currentStreak'] = $current;
$result['longestStreak'] = $longest;

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

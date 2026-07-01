<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/db.connection.php';
require_once __DIR__ . '/../config/course_perms.php';

$method = $_SERVER['REQUEST_METHOD'];
$ROLES  = ['Owner', 'Grader', 'Editor'];

// ── GET: teachers linked to a course + candidates that can still be added ────
if ($method === 'GET') {
    $courseId = (int)($_GET['course_id'] ?? 0);
    if (!$courseId) {
        http_response_code(400);
        echo json_encode(['error' => 'course_id required']);
        exit;
    }
    $stmt = $pdo->prepare("
        SELECT tpc.`accounts_username` AS username, tpc.`Role` AS role,
               a.`Email` AS email, a.`Role` AS account_role
        FROM `Teacher_ParticipatesIn_Course` tpc
        JOIN `accounts` a ON a.`username` = tpc.`accounts_username`
        WHERE tpc.`courses_Id` = ?
        ORDER BY FIELD(tpc.`Role`,'Owner','Editor','Grader'), tpc.`accounts_username`");
    $stmt->execute([$courseId]);
    $teachers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Staff accounts not yet on this course — for the "add teacher" picker.
    $stmt = $pdo->prepare("
        SELECT `username`, `Role` AS account_role FROM `accounts`
        WHERE `Role` IN ('Teacher','Superadmin')
          AND `username` NOT IN (
              SELECT `accounts_username` FROM `Teacher_ParticipatesIn_Course` WHERE `courses_Id` = ?)
        ORDER BY `username`");
    $stmt->execute([$courseId]);
    $candidates = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['teachers' => $teachers, 'candidates' => $candidates], JSON_UNESCAPED_UNICODE);
    exit;
}

// POST (add/update a link) and DELETE (remove) both mutate — Owner/superadmin only.
$in       = json_decode(file_get_contents('php://input'), true) ?: [];
$actor    = trim($in['actor'] ?? '');
$courseId = (int)($in['course_id'] ?? 0);
$username = trim($in['username'] ?? '');

if (!$courseId || !$username) {
    http_response_code(400);
    echo json_encode(['error' => 'course_id and username required']);
    exit;
}
if (!can_manage_teachers($pdo, $actor, $courseId)) {
    http_response_code(403);
    echo json_encode(['error' => 'Alleen de eigenaar kan docenten beheren']);
    exit;
}

if ($method === 'POST') {
    $role = $in['role'] ?? '';
    if (!in_array($role, $ROLES, true)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid role']);
        exit;
    }
    // Only staff accounts can be linked to a course.
    if (!in_array(account_role($pdo, $username), ['Teacher', 'Superadmin'], true)) {
        http_response_code(400);
        echo json_encode(['error' => 'Alleen docenten of beheerders kunnen gekoppeld worden']);
        exit;
    }
    $pdo->prepare(
        "INSERT INTO `Teacher_ParticipatesIn_Course` (`courses_Id`, `accounts_username`, `Role`)
         VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE `Role` = VALUES(`Role`)"
    )->execute([$courseId, $username, $role]);
    echo json_encode(['ok' => true]);
    exit;
}

if ($method === 'DELETE') {
    $pdo->prepare(
        "DELETE FROM `Teacher_ParticipatesIn_Course`
         WHERE `courses_Id` = ? AND `accounts_username` = ?")
        ->execute([$courseId, $username]);
    echo json_encode(['ok' => true]);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);

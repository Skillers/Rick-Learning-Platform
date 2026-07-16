<?php
/**
 * save_course.php — create a new course from the admin Lesontwerper.
 *
 * Input (JSON POST): { subject_id:int, name:string, icon:string, color:string, actor?:string }
 * Output: { ok:true, course: { id, name, icon, color, subject_id, section } }
 *
 * Rejects a duplicate course name within the same subject (case-insensitive).
 * A teacher who creates a course is linked to it as Owner so it stays visible/
 * editable; superadmins see everything so they don't need a link.
 */
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/db.connection.php';
require_once __DIR__ . '/../config/course_perms.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$in = json_decode(file_get_contents('php://input'), true);
if (!is_array($in)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON']);
    exit;
}

$subjectId = (int)($in['subject_id'] ?? 0);
$name      = trim((string)($in['name'] ?? ''));
$icon      = trim((string)($in['icon'] ?? ''));
$color     = trim((string)($in['color'] ?? ''));
$actor     = trim((string)($in['actor'] ?? ''));

if (!$subjectId || $name === '') {
    http_response_code(400);
    echo json_encode(['error' => 'subject_id and name are required']);
    exit;
}

// Sensible fallbacks for the NOT-NULL columns.
if ($icon === '')  $icon  = mb_strtoupper(mb_substr($name, 0, 2));
if ($color === '') $color = '#8b949e';

// Accept a hex color (#rgb / #rrggbb) or a legacy CSS class (c-python, …).
$LEGACY_COLORS = ['c-python', 'c-js', 'c-java', 'c-unity', 'c-vr', 'c-math'];
if (!preg_match('/^#[0-9a-fA-F]{3}([0-9a-fA-F]{3})?$/', $color) && !in_array($color, $LEGACY_COLORS, true)) {
    $color = '#8b949e';
}

try {
    // Subject must exist.
    $chk = $pdo->prepare("SELECT 1 FROM Subjects WHERE id = ?");
    $chk->execute([$subjectId]);
    if (!$chk->fetchColumn()) {
        http_response_code(400);
        echo json_encode(['error' => 'Unknown subject']);
        exit;
    }

    // Reject a duplicate course name within the same subject (case-insensitive).
    $dup = $pdo->prepare("SELECT 1 FROM Courses WHERE Subject_Id = ? AND LOWER(Name) = LOWER(?)");
    $dup->execute([$subjectId, $name]);
    if ($dup->fetchColumn()) {
        http_response_code(409);
        echo json_encode(['error' => 'Er bestaat al een cursus met deze naam in dit onderwerp.']);
        exit;
    }

    $pdo->prepare(
        "INSERT INTO Courses (Name, Icon, Color, Subject_Id) VALUES (?, ?, ?, ?)"
    )->execute([mb_substr($name, 0, 45), mb_substr($icon, 0, 45), mb_substr($color, 0, 45), $subjectId]);

    $id = (int)$pdo->lastInsertId();

    // Whoever creates a course becomes its Owner (teacher or superadmin), so they
    // get full control — the settings cogwheel, teacher management, etc.
    if ($actor !== '' && in_array(account_role($pdo, $actor), ['Teacher', 'Superadmin'], true)) {
        $pdo->prepare(
            "INSERT IGNORE INTO Teacher_ParticipatesIn_Course (courses_Id, accounts_username, Role)
             VALUES (?, ?, 'Owner')"
        )->execute([$id, $actor]);
    }

    $sectionName = (string)$pdo->query("SELECT Name FROM Subjects WHERE id = " . $subjectId)->fetchColumn();

    echo json_encode([
        'ok' => true,
        'course' => [
            'id'         => $id,
            'name'       => $name,
            'icon'       => $icon,
            'color'      => $color,
            'subject_id' => $subjectId,
            'section'    => $sectionName,
        ],
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

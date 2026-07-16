<?php
/**
 * save_subject.php — create a new subject (vak) from the admin Lesontwerper.
 *
 * Input (JSON POST): { name: string, actor?: string }
 * Output: { ok:true, subject: { id, name }, existed?:true }
 *
 * A teacher who creates a subject — new or already-existing — is linked to it via
 * Teacher_has_Subjects so the (possibly empty) subject stays visible to them on the
 * admin page. Superadmins see everything, so they don't need a link.
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
$name  = is_array($in) ? trim((string)($in['name'] ?? '')) : '';
$actor = is_array($in) ? trim((string)($in['actor'] ?? '')) : '';

if ($name === '') {
    http_response_code(400);
    echo json_encode(['error' => 'name is required']);
    exit;
}

// Link a teacher to a subject so an (empty) subject stays visible to them.
// No-op for superadmins (they see everything) and when no actor is supplied.
$linkTeacher = function (int $subjectId) use ($pdo, $actor) {
    if ($actor === '' || account_role($pdo, $actor) !== 'Teacher') return;
    $pdo->prepare(
        "INSERT IGNORE INTO Teacher_has_Subjects (accounts_username, subjects_id) VALUES (?, ?)"
    )->execute([$actor, $subjectId]);
};

try {
    // Avoid duplicates (case-insensitive).
    $dup = $pdo->prepare("SELECT id, Name FROM Subjects WHERE LOWER(Name) = LOWER(?)");
    $dup->execute([$name]);
    $existing = $dup->fetch(PDO::FETCH_ASSOC);
    if ($existing) {
        $linkTeacher((int)$existing['id']);
        // Return the canonical stored name so the UI shows the real casing.
        echo json_encode(['ok' => true, 'subject' => ['id' => (int)$existing['id'], 'name' => $existing['Name']], 'existed' => true], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $pdo->prepare("INSERT INTO Subjects (Name) VALUES (?)")->execute([mb_substr($name, 0, 45)]);
    $id = (int)$pdo->lastInsertId();
    $linkTeacher($id);

    echo json_encode(['ok' => true, 'subject' => ['id' => $id, 'name' => $name]], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

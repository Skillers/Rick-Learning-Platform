<?php
/**
 * update_course.php — rename a course (and optionally icon/color) from the admin.
 *
 * Input (JSON POST): { course_id:int, name:string, icon?:string, color?:string, subject_id?:int }
 * Output: { ok:true, course: { id, name, icon, color, subject_id? } }
 */
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/db.connection.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$in = json_decode(file_get_contents('php://input'), true);
$courseId = is_array($in) ? (int)($in['course_id'] ?? 0) : 0;
$name     = is_array($in) ? trim((string)($in['name'] ?? '')) : '';

if (!$courseId || $name === '') {
    http_response_code(400);
    echo json_encode(['error' => 'course_id and name are required']);
    exit;
}

try {
    $cur = $pdo->prepare("SELECT Id, Name, Icon, Color FROM Courses WHERE Id = ?");
    $cur->execute([$courseId]);
    $course = $cur->fetch(PDO::FETCH_ASSOC);
    if (!$course) {
        http_response_code(404);
        echo json_encode(['error' => 'Course not found']);
        exit;
    }

    $name = mb_substr($name, 0, 45);
    $icon = isset($in['icon']) && trim((string)$in['icon']) !== '' ? mb_substr(trim((string)$in['icon']), 0, 45) : $course['Icon'];

    $color = $course['Color'];
    if (isset($in['color']) && trim((string)$in['color']) !== '') {
        $c = trim((string)$in['color']);
        $legacy = ['c-python', 'c-js', 'c-java', 'c-unity', 'c-vr', 'c-math'];
        if (preg_match('/^#[0-9a-fA-F]{3}([0-9a-fA-F]{3})?$/', $c) || in_array($c, $legacy, true)) {
            $color = mb_substr($c, 0, 45);
        }
    }

    // Optional: move the course to a different subject (validated against subjects).
    $subjectId  = isset($in['subject_id']) ? (int)$in['subject_id'] : 0;
    $setSubject = '';
    $params     = [$name, $icon, $color];
    if ($subjectId > 0) {
        $chk = $pdo->prepare("SELECT 1 FROM subjects WHERE id = ?");
        $chk->execute([$subjectId]);
        if (!$chk->fetchColumn()) {
            http_response_code(400);
            echo json_encode(['error' => 'Onbekend vak']);
            exit;
        }
        $setSubject = ', Subject_Id = ?';
        $params[]   = $subjectId;
    }
    $params[] = $courseId;

    $pdo->prepare("UPDATE Courses SET Name = ?, Icon = ?, Color = ?{$setSubject} WHERE Id = ?")
        ->execute($params);

    echo json_encode([
        'ok' => true,
        'course' => ['id' => $courseId, 'name' => $name, 'icon' => $icon, 'color' => $color]
                    + ($subjectId > 0 ? ['subject_id' => $subjectId] : []),
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

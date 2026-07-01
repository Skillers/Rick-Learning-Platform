<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/db.connection.php';
require_once __DIR__ . '/../config/course_perms.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$body     = json_decode(file_get_contents('php://input'), true) ?: [];
$grader   = trim($body['grader']   ?? '');
$kind     = $body['kind']          ?? '';
$verdict  = $body['verdict']       ?? '';
$feedback = isset($body['feedback']) ? trim($body['feedback']) : '';

// Verdict: 'V' = voldoende, 'X' = onvoldoende, 'none' = terug naar wachtrij.
if (!$grader
    || !in_array($kind, ['assignment', 'open_question'], true)
    || !in_array($verdict, ['V', 'X', 'none'], true)) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing or invalid fields']);
    exit;
}

// Resolve the course this item lives on (via its live page version) — used for
// the permission check and returned so the client can refresh its row.
$student      = trim($body['student'] ?? '');
$assignmentId = (int)($body['assignment_id'] ?? 0);
$didId        = (int)($body['did_question_id'] ?? 0);

if ($kind === 'assignment') {
    if (!$student || !$assignmentId) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing assignment refs (student, assignment_id)']);
        exit;
    }
    $stmt = $pdo->prepare("
        SELECT p.`Course_Id`
        FROM `Assigments` a
        JOIN `components` cp                ON a.`component_Id`   = cp.`Id`
        JOIN `sections_has_components` shc  ON shc.`components_Id` = cp.`Id`
        JOIN `PageVersion_has_sections` pvs ON pvs.`sections_Id`  = shc.`sections_Id`
        JOIN `PageVersion` pv               ON pv.`Id` = pvs.`PageVersion_Id` AND pv.`Status` = 'live'
        JOIN `pages` p                      ON p.`Id` = pv.`pages_Id`
        WHERE a.`Id` = ? LIMIT 1");
    $stmt->execute([$assignmentId]);
} else { // open_question
    if (!$didId) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing did_question_id']);
        exit;
    }
    $stmt = $pdo->prepare("
        SELECT p.`Course_Id`
        FROM `AC_Did_Question` dq
        JOIN `PQQuestion` q                 ON q.`Id` = dq.`PQQuestion_Id`
        JOIN `components` cp                ON q.`component_Id`   = cp.`Id`
        JOIN `sections_has_components` shc  ON shc.`components_Id` = cp.`Id`
        JOIN `PageVersion_has_sections` pvs ON pvs.`sections_Id`  = shc.`sections_Id`
        JOIN `PageVersion` pv               ON pv.`Id` = pvs.`PageVersion_Id` AND pv.`Status` = 'live'
        JOIN `pages` p                      ON p.`Id` = pv.`pages_Id`
        WHERE dq.`Id` = ? LIMIT 1");
    $stmt->execute([$didId]);
}
$courseId = $stmt->fetchColumn();
if ($courseId === false || $courseId === null) {
    http_response_code(404);
    echo json_encode(['error' => 'Item or its live page not found']);
    exit;
}
$courseId = (int)$courseId;

// Authorization: superadmin, Owner, or Grader of this course may grade.
if (!can_grade_course($pdo, $grader, $courseId)) {
    http_response_code(403);
    echo json_encode(['error' => 'Geen rechten om na te kijken voor deze cursus']);
    exit;
}

// Persist the verdict + feedback. Empty feedback is stored as NULL.
$fb = $feedback !== '' ? $feedback : null;
if ($kind === 'assignment') {
    $stmt = $pdo->prepare("
        UPDATE `Accounts_have_assignments`
           SET `Verdict` = ?, `Feedback` = ?, `FeedbackDate` = NOW(), `GradedBy` = ?
         WHERE `account_username` = ? AND `Assigment_Id` = ?");
    $stmt->execute([$verdict, $fb, $grader, $student, $assignmentId]);
} else {
    $stmt = $pdo->prepare("
        UPDATE `AC_Did_Question`
           SET `Verdict` = ?, `ReviewFeedback` = ?, `ReviewedAt` = NOW(), `ReviewedBy` = ?
         WHERE `Id` = ?");
    $stmt->execute([$verdict, $fb, $grader, $didId]);
}
// Note: we don't treat rowCount()===0 as an error — re-saving an identical grade
// changes no rows but is a valid no-op. The item's existence was already checked
// when we resolved its course above.

// Notifications (open questions only). Tell the student their answer was graded,
// and mark the shared course-wide "to grade" notification handled.
if ($kind === 'open_question') {
    // Student notification (Recipient = the answer's owner). Re-grading re-lights
    // it as unread (ReadAt_GradeAt = NULL = unread for the student).
    $pdo->prepare(
        "INSERT INTO `Notifications` (`Recipient`, `Type`, `AC_Did_Question_Id`, `CreatedAt`, `courses_Id`)
         SELECT `accounts_username`, 'Grade', `Id`, NOW(), ? FROM `AC_Did_Question` WHERE `Id` = ?
         ON DUPLICATE KEY UPDATE `ReadAt_GradeAt` = NULL, `CreatedAt` = NOW()"
    )->execute([$courseId, $didId]);
    // The course-wide To_grade is now handled: stamp its ReadAt_GradeAt so it stops
    // counting as unread for every teacher and shows grayed ("Nagekeken door …").
    $pdo->prepare(
        "UPDATE `Notifications` SET `ReadAt_GradeAt` = NOW()
         WHERE `AC_Did_Question_Id` = ? AND `Type` = 'To_grade'"
    )->execute([$didId]);
}

echo json_encode([
    'success'   => true,
    'kind'      => $kind,
    'verdict'   => $verdict,
    'feedback'  => $feedback,
    'graded_by' => $grader,
    'course_id' => $courseId,
]);

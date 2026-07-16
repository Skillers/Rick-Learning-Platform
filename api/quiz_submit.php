<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/db.connection.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$body        = json_decode(file_get_contents('php://input'), true);
$username    = trim($body['username']    ?? '');
$question_id = (int)($body['question_id'] ?? 0);
$picked      = $body['picked_answer_ids'] ?? [];
$open_answer = isset($body['open_answer']) ? trim($body['open_answer']) : null;
$file_name   = isset($body['file_name']) ? trim((string)$body['file_name']) : null;
$file_path   = isset($body['file_path']) ? trim((string)$body['file_path']) : null;

if (!$username || !$question_id) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing fields']);
    exit;
}

// Block second submissions — frontend should also enforce, but defend here too.
$stmt = $pdo->prepare(
    "SELECT Id FROM AC_Did_Question
     WHERE accounts_username = ? AND PQQuestion_Id = ? AND QuestionContext_ContextType = 'section'
     LIMIT 1"
);
$stmt->execute([$username, $question_id]);
if ($stmt->fetchColumn()) {
    http_response_code(409);
    echo json_encode(['error' => 'Already submitted']);
    exit;
}

// Validate question exists + decide MC vs open. Also read its file permissions so a
// crafted request can't attach a file to a question the teacher didn't open for it.
$stmt = $pdo->prepare("SELECT OpenQuestion, AllowDocument, AllowImage, PossiblePoints FROM PQQuestion WHERE Id = ?");
$stmt->execute([$question_id]);
$q = $stmt->fetch();
if ($q === false) {
    http_response_code(404);
    echo json_encode(['error' => 'Question not found']);
    exit;
}
$isOpen    = (int)$q['OpenQuestion'] === 1;
$allowFile = (int)$q['AllowDocument'] === 1 || (int)$q['AllowImage'] === 1;
$qPoints   = (float)$q['PossiblePoints'];

$pdo->beginTransaction();
try {
    // Only persist an attached file on open questions, and only when the path looks
    // like one our own upload endpoint produced (guards against arbitrary values).
    $storeFile = ($isOpen && $allowFile && $file_path && preg_match('#^uploads/[A-Za-z0-9_.-]+$#', $file_path));
    $fileName  = $storeFile && $file_name !== '' ? mb_substr($file_name, 0, 255) : null;
    $filePath  = $storeFile ? mb_substr($file_path, 0, 255) : null;

    $stmt = $pdo->prepare(
        "INSERT INTO AC_Did_Question
            (accounts_username, PQQuestion_Id, QuestionContext_ContextType, AttemptDate, OpenAnswer, FileName, FilePath)
         VALUES (?, ?, 'section', NOW(), ?, ?, ?)"
    );
    $stmt->execute([$username, $question_id, $isOpen ? ($open_answer ?: '') : null, $fileName, $filePath]);
    $didId = (int)$pdo->lastInsertId();

    $savedPicks = [];
    if (!$isOpen) {
        // Pull all valid answers for this question so we can validate IDs, store
        // Correct, and score the question.
        $stmt = $pdo->prepare("SELECT Id, IsCorrect FROM PQAnswer WHERE PQQuestion_Id = ?");
        $stmt->execute([$question_id]);
        $valid = [];
        foreach ($stmt->fetchAll() as $row) {
            $valid[(int)$row['Id']] = (int)$row['IsCorrect'];
        }

        if (is_array($picked) && $picked) {
            $insertPick = $pdo->prepare(
                "INSERT INTO AC_Picked_Answer (AC_Did_Question_Id, PQAnswer_Id, Correct) VALUES (?, ?, ?)"
            );
            foreach ($picked as $pid) {
                $pid = (int)$pid;
                if (!isset($valid[$pid])) continue;
                $insertPick->execute([$didId, $pid, $valid[$pid]]);
                $savedPicks[] = $pid;
            }
        }

        // Auto-score: partial credit = points * (options classified correctly / total).
        // An option is classified correctly when the student's picked/not-picked
        // state matches its IsCorrect flag. Stored on the attempt for Test grading.
        $total = count($valid);
        $awarded = 0.0;
        if ($total > 0) {
            $pickedSet = array_flip($savedPicks);
            $classifiedRight = 0;
            foreach ($valid as $aid => $isCorrect) {
                $isPicked = isset($pickedSet[$aid]);
                if (($isPicked && $isCorrect === 1) || (!$isPicked && $isCorrect === 0)) $classifiedRight++;
            }
            $awarded = round($qPoints * $classifiedRight / $total, 2);
        }
        $pdo->prepare("UPDATE AC_Did_Question SET PointsAwarded = ? WHERE Id = ?")
            ->execute([$awarded, $didId]);
    }

    // Open answers need a teacher review. Create ONE course-scoped "to grade"
    // notification — its audience (the course's teachers + superadmins) is derived
    // at read time, so it stays correct as course assignments change.
    if ($isOpen) {
        $cs = $pdo->prepare(
            "SELECT p.`Course_Id`
             FROM `PQQuestion` q
             JOIN `components` cp                ON q.`component_Id`   = cp.`Id`
             JOIN `sections_has_components` shc  ON shc.`components_Id` = cp.`Id`
             JOIN `PageVersion_has_sections` pvs ON pvs.`sections_Id`  = shc.`sections_Id`
             JOIN `PageVersion` pv               ON pv.`Id` = pvs.`PageVersion_Id` AND pv.`Status` = 'live'
             JOIN `pages` p                      ON p.`Id` = pv.`pages_Id`
             WHERE q.`Id` = ? LIMIT 1");
        $cs->execute([$question_id]);
        $courseId = $cs->fetchColumn();
        if ($courseId !== false) {
            // INSERT IGNORE + the unique (AC_Did_Question_Id, Type) dedupes any retry.
            $pdo->prepare(
                "INSERT IGNORE INTO `Notifications`
                    (`Recipient`, `Type`, `AC_Did_Question_Id`, `CreatedAt`, `courses_Id`)
                 VALUES (NULL, 'To_grade', ?, NOW(), ?)"
            )->execute([$didId, (int)$courseId]);
        }
    }

    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['error' => 'Save failed']);
    exit;
}

echo json_encode([
    'success'           => true,
    'did_question_id'   => $didId,
    'picked_answer_ids' => $savedPicks,
]);

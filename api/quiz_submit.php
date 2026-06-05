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

// Validate question exists + decide MC vs open.
$stmt = $pdo->prepare("SELECT OpenQuestion FROM PQQuestion WHERE Id = ?");
$stmt->execute([$question_id]);
$isOpen = $stmt->fetchColumn();
if ($isOpen === false) {
    http_response_code(404);
    echo json_encode(['error' => 'Question not found']);
    exit;
}
$isOpen = (int)$isOpen === 1;

$pdo->beginTransaction();
try {
    $stmt = $pdo->prepare(
        "INSERT INTO AC_Did_Question
            (accounts_username, PQQuestion_Id, QuestionContext_ContextType, AttemptDate, OpenAnswer)
         VALUES (?, ?, 'section', NOW(), ?)"
    );
    $stmt->execute([$username, $question_id, $isOpen ? ($open_answer ?: '') : null]);
    $didId = (int)$pdo->lastInsertId();

    $savedPicks = [];
    if (!$isOpen && is_array($picked) && $picked) {
        // Pull all valid answers for this question so we can validate IDs and compute Correct.
        $stmt = $pdo->prepare("SELECT Id, IsCorrect FROM PQAnswer WHERE PQQuestion_Id = ?");
        $stmt->execute([$question_id]);
        $valid = [];
        foreach ($stmt->fetchAll() as $row) {
            $valid[(int)$row['Id']] = (int)$row['IsCorrect'];
        }

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

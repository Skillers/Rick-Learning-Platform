<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/db.connection.php';

$username = trim($_GET['username'] ?? '');
$page_id  = (int)($_GET['page_id'] ?? 0);

if (!$username || !$page_id) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing fields']);
    exit;
}

// Pull every section-context attempt this user has on questions belonging to this page.
$stmt = $pdo->prepare("
    SELECT DISTINCT
        dq.Id            AS did_question_id,
        dq.PQQuestion_Id AS question_id,
        dq.OpenAnswer    AS open_answer,
        dq.Verdict       AS verdict,
        dq.ReviewFeedback AS feedback,
        dq.ReviewedAt    AS reviewed_at
    FROM AC_Did_Question dq
    JOIN PQQuestion q ON q.Id = dq.PQQuestion_Id
    JOIN sections_has_components shc ON shc.components_Id = q.component_Id
    JOIN PageVersion_has_sections pvs ON pvs.sections_Id = shc.sections_Id
    JOIN PageVersion pv ON pv.Id = pvs.PageVersion_Id AND pv.Status = 'live'
    WHERE dq.accounts_username           = ?
      AND dq.QuestionContext_ContextType = 'section'
      AND pv.pages_Id                    = ?
");
$stmt->execute([$username, $page_id]);
$attempts = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!$attempts) {
    echo '[]';
    exit;
}

// Fetch picks for all attempts in one query.
$didIds = array_column($attempts, 'did_question_id');
$placeholders = implode(',', array_fill(0, count($didIds), '?'));
$stmt = $pdo->prepare("
    SELECT AC_Did_Question_Id AS did_question_id, PQAnswer_Id AS answer_id
    FROM AC_Picked_Answer
    WHERE AC_Did_Question_Id IN ($placeholders)
");
$stmt->execute($didIds);

$picksByDid = [];
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $p) {
    $picksByDid[(int)$p['did_question_id']][] = (int)$p['answer_id'];
}

$out = [];
foreach ($attempts as $a) {
    $didId = (int)$a['did_question_id'];
    $out[] = [
        'question_id'       => (int)$a['question_id'],
        'open_answer'       => $a['open_answer'],
        'picked_answer_ids' => $picksByDid[$didId] ?? [],
        'verdict'           => $a['verdict'],       // 'none' | 'V' | 'X'
        'feedback'          => $a['feedback'],
        'reviewed_at'       => $a['reviewed_at'],
    ];
}

echo json_encode($out, JSON_UNESCAPED_UNICODE);

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
    SELECT
        dq.Id            AS did_question_id,
        dq.PQQuestion_Id AS question_id,
        dq.OpenAnswer    AS open_answer
    FROM AC_Did_Question dq
    JOIN PQQuestion q   ON q.Id = dq.PQQuestion_Id
    JOIN Components c   ON c.Id = q.component_Id
    JOIN Sections s     ON s.Id = c.Section_Id
    WHERE dq.accounts_username           = ?
      AND dq.QuestionContext_ContextType = 'section'
      AND s.Pages_Id                     = ?
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
    ];
}

echo json_encode($out, JSON_UNESCAPED_UNICODE);

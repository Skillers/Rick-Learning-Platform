<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/db.connection.php';

$page_id = isset($_GET['page_id']) ? (int)$_GET['page_id'] : 0;
if (!$page_id) { echo '[]'; exit; }

// ── 1. Text, code & infobox components ──────────────────────
$stmt = $pdo->prepare("
    SELECT
        c.Id            AS id,
        c.TypeName      AS type,
        c.Section_Id    AS section_id,
        c.`Order`       AS `order`,
        COALESCE(cs.Code, tb.Text, ib.Text) AS content,
        l.LanguageName  AS language,
        ib.IsWarning    AS is_warning
    FROM Components c
    JOIN  Sections      s   ON s.Id            = c.Section_Id
    LEFT JOIN CodeSnippets  cs  ON cs.Components_Id = c.Id
    LEFT JOIN Languages     l   ON l.Id             = cs.Languages_Id
    LEFT JOIN TextBLocks    tb  ON tb.Component_Id  = c.Id
    LEFT JOIN `mydb`.`InfoBoxes` ib ON ib.components_Id = c.Id
    WHERE s.Pages_Id = ?
      AND c.TypeName != 'quiz'
    ORDER BY c.Section_Id, c.`Order`
");
$stmt->execute([$page_id]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Map infobox components to tip/warning type based on IsWarning flag
foreach ($rows as &$row) {
    if ($row['is_warning'] !== null) {
        $row['type'] = $row['is_warning'] ? 'warning' : 'tip';
    }
    unset($row['is_warning']);
}
unset($row);

// ── 2. Quiz components (question + answers) ────────────────
$stmtQ = $pdo->prepare("
    SELECT
        c.Id            AS component_id,
        c.Section_Id    AS section_id,
        c.`Order`       AS `order`,
        q.Id            AS question_id,
        q.Question      AS question,
        q.Image         AS image,
        q.OpenQuestion  AS open_question
    FROM Components c
    JOIN  Sections s ON s.Id = c.Section_Id
    JOIN  `mydb`.`PQQuestion` q ON q.component_Id = c.Id
    WHERE s.Pages_Id = ?
      AND c.TypeName = 'quiz'
    ORDER BY c.Section_Id, c.`Order`
");
$stmtQ->execute([$page_id]);
$questions = $stmtQ->fetchAll(PDO::FETCH_ASSOC);

if ($questions) {
    // Fetch all answers for these questions
    $qIds = array_column($questions, 'question_id');
    $placeholders = implode(',', array_fill(0, count($qIds), '?'));
    $stmtA = $pdo->prepare("
        SELECT PQQuestion_Id AS question_id, AnswerOption AS answer, IsCorrect AS is_correct
        FROM `mydb`.`PQAnswer`
        WHERE PQQuestion_Id IN ($placeholders)
    ");
    $stmtA->execute($qIds);
    $allAnswers = $stmtA->fetchAll(PDO::FETCH_ASSOC);

    // Group answers by question
    $answersByQ = [];
    foreach ($allAnswers as $a) {
        $answersByQ[$a['question_id']][] = [
            'text'       => $a['answer'],
            'is_correct' => (int)$a['is_correct'],
        ];
    }

    // Build quiz rows in the same format as other components
    foreach ($questions as $q) {
        $rows[] = [
            'id'         => (int)$q['component_id'],
            'type'       => 'quiz',
            'section_id' => (int)$q['section_id'],
            'order'      => (int)$q['order'],
            'content'    => json_encode([
                'question'      => $q['question'],
                'image'         => $q['image'],
                'open_question' => (int)$q['open_question'],
                'answers'       => $answersByQ[$q['question_id']] ?? [],
            ], JSON_UNESCAPED_UNICODE),
            'language'   => null,
        ];
    }

    // Re-sort by section_id, then order
    usort($rows, function ($a, $b) {
        return ($a['section_id'] - $b['section_id']) ?: ($a['order'] - $b['order']);
    });
}

echo json_encode($rows, JSON_UNESCAPED_UNICODE);

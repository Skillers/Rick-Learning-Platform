<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/db.connection.php';

$page_id = isset($_GET['page_id']) ? (int)$_GET['page_id'] : 0;
if (!$page_id) { echo '[]'; exit; }

// ── 1. Text, code & infobox components ──────────────────────
$stmt = $pdo->prepare("
    SELECT
        c.Id            AS id,
        c.ComponentType_ComponentTypeText      AS type,
        c.Section_Id    AS section_id,
        c.`Order`       AS `order`,
        COALESCE(cs.Code, tb.Text, ib.Text) AS content,
        l.LanguageName  AS language,
        ib.IsWarning    AS is_warning,
        es.BeforeLineSpace AS es_before,
        es.AfterLineSpace  AS es_after,
        es.table1_LineType AS es_linetype
    FROM Components c
    JOIN  Sections      s   ON s.Id            = c.Section_Id
    LEFT JOIN CodeSnippets  cs  ON cs.Components_Id = c.Id
    LEFT JOIN Languages     l   ON l.Id             = cs.Languages_Id
    LEFT JOIN TextBLocks    tb  ON tb.Component_Id  = c.Id
    LEFT JOIN InfoBoxes ib ON ib.components_Id = c.Id
    LEFT JOIN EmptySpace es ON es.components_Id = c.Id
    WHERE s.Pages_Id = ?
      AND c.ComponentType_ComponentTypeText NOT IN ('quiz','multimedia')
    ORDER BY c.Section_Id, c.`Order`
");
$stmt->execute([$page_id]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Map infobox components to tip/warning type based on IsWarning flag
// and build emptyspace object
foreach ($rows as &$row) {
    if ($row['is_warning'] !== null) {
        $row['type'] = $row['is_warning'] ? 'warning' : 'tip';
    }
    unset($row['is_warning']);
    $row['emptyspace'] = null;
    if ($row['es_linetype'] && $row['es_linetype'] !== 'nothing') {
        $row['emptyspace'] = [
            'before' => (int)$row['es_before'],
            'after'  => $row['es_after'] !== null ? (int)$row['es_after'] : null,
            'type'   => $row['es_linetype'],
        ];
    } elseif ($row['es_before'] > 0) {
        $row['emptyspace'] = [
            'before' => (int)$row['es_before'],
            'after'  => null,
            'type'   => 'nothing',
        ];
    }
    unset($row['es_before'], $row['es_after'], $row['es_linetype']);
}
unset($row);

// ── 2b. Multimedia components ───────────────────────────────
$stmtM = $pdo->prepare("
    SELECT
        c.Id            AS component_id,
        c.Section_Id    AS section_id,
        c.`Order`       AS `order`,
        m.URL           AS url,
        m.Uploaded      AS uploaded,
        m.MultiMediaType_MultiMediaType AS media_type,
        es.BeforeLineSpace AS es_before,
        es.AfterLineSpace  AS es_after,
        es.table1_LineType AS es_linetype
    FROM Components c
    JOIN  Sections  s ON s.Id = c.Section_Id
    JOIN  MultiMedia m ON m.components_Id = c.Id
    LEFT JOIN EmptySpace es ON es.components_Id = c.Id
    WHERE s.Pages_Id = ?
      AND c.ComponentType_ComponentTypeText = 'multimedia'
    ORDER BY c.Section_Id, c.`Order`
");
$stmtM->execute([$page_id]);
$mediaRows = $stmtM->fetchAll(PDO::FETCH_ASSOC);

foreach ($mediaRows as $mr) {
    $es = null;
    if ($mr['es_linetype'] && $mr['es_linetype'] !== 'nothing') {
        $es = ['before' => (int)$mr['es_before'], 'after' => $mr['es_after'] !== null ? (int)$mr['es_after'] : null, 'type' => $mr['es_linetype']];
    } elseif ((int)$mr['es_before'] > 0) {
        $es = ['before' => (int)$mr['es_before'], 'after' => null, 'type' => 'nothing'];
    }
    $rows[] = [
        'id'         => (int)$mr['component_id'],
        'type'       => 'multimedia',
        'section_id' => (int)$mr['section_id'],
        'order'      => (int)$mr['order'],
        'content'    => json_encode([
            'url'        => $mr['url'],
            'uploaded'   => (int)$mr['uploaded'],
            'media_type' => $mr['media_type'],
        ], JSON_UNESCAPED_UNICODE),
        'language'   => null,
        'emptyspace' => $es,
    ];
}

// ── 2. Quiz components (question + answers) ────────────────
$stmtQ = $pdo->prepare("
    SELECT
        c.Id            AS component_id,
        c.Section_Id    AS section_id,
        c.`Order`       AS `order`,
        q.Id            AS question_id,
        q.Question      AS question,
        q.Image         AS image,
        q.OpenQuestion  AS open_question,
        es.BeforeLineSpace AS es_before,
        es.AfterLineSpace  AS es_after,
        es.table1_LineType AS es_linetype
    FROM Components c
    JOIN  Sections s ON s.Id = c.Section_Id
    JOIN  PQQuestion q ON q.component_Id = c.Id
    LEFT JOIN EmptySpace es ON es.components_Id = c.Id
    WHERE s.Pages_Id = ?
      AND c.ComponentType_ComponentTypeText = 'quiz'
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
        FROM PQAnswer
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
        $es = null;
        if ($q['es_linetype'] && $q['es_linetype'] !== 'nothing') {
            $es = ['before' => (int)$q['es_before'], 'after' => $q['es_after'] !== null ? (int)$q['es_after'] : null, 'type' => $q['es_linetype']];
        } elseif ((int)$q['es_before'] > 0) {
            $es = ['before' => (int)$q['es_before'], 'after' => null, 'type' => 'nothing'];
        }
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
            'emptyspace' => $es,
        ];
    }

}

// Re-sort all components by section_id, then order
usort($rows, function ($a, $b) {
    return ($a['section_id'] - $b['section_id']) ?: ($a['order'] - $b['order']);
});

echo json_encode($rows, JSON_UNESCAPED_UNICODE);

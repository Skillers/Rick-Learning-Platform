<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/db.connection.php';
require_once __DIR__ . '/_versions.php';

$page_id = isset($_GET['page_id']) ? (int)$_GET['page_id'] : 0;
if (!$page_id) { echo '[]'; exit; }

// Which snapshot to load: ?version_id / ?status / default live.
$versionId = resolve_requested_version($pdo, $page_id);
if (!$versionId) { echo '[]'; exit; }

// ── 1. The version's component membership ───────────────────
// section_id + component_id + order come from the two junctions; a component's
// position is sections_has_components.Order, a section's is PageVersion_has_sections.Order.
$memStmt = $pdo->prepare("
    SELECT
        pvs.`sections_Id`  AS section_id,
        shc.`components_Id` AS component_id,
        shc.`Order`        AS `order`,
        c.`ComponentType_ComponentTypeText` AS type
    FROM `PageVersion_has_sections` pvs
    JOIN `sections_has_components` shc ON shc.`sections_Id` = pvs.`sections_Id`
    JOIN `components` c ON c.`Id` = shc.`components_Id`
    WHERE pvs.`PageVersion_Id` = ?
    ORDER BY pvs.`Order`, shc.`Order`
");
$memStmt->execute([$versionId]);
$members = $memStmt->fetchAll(PDO::FETCH_ASSOC);
if (!$members) { echo '[]'; exit; }

$compIds = array_values(array_unique(array_map(fn($m) => (int)$m['component_id'], $members)));
$ph = implode(',', array_fill(0, count($compIds), '?'));

/** Fetch rows for $compIds and index them by a given column. */
$indexBy = function (string $sql, string $key) use ($pdo, $compIds) {
    $st = $pdo->prepare($sql);
    $st->execute($compIds);
    $out = [];
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) { $out[(int)$r[$key]] = $r; }
    return $out;
};

// ── 2. Detail rows, each keyed by component id ──────────────
$textById  = $indexBy("SELECT `Component_Id`, `Text` FROM `TextBLocks` WHERE `Component_Id` IN ($ph)", 'Component_Id');
$codeById  = $indexBy("SELECT cs.`Components_Id`, cs.`Code`, l.`LanguageName`
                       FROM `CodeSnippets` cs LEFT JOIN `Languages` l ON l.`Id` = cs.`Languages_Id`
                       WHERE cs.`Components_Id` IN ($ph)", 'Components_Id');
$infoById  = $indexBy("SELECT `components_Id`, `Text`, `IsWarning` FROM `InfoBoxes` WHERE `components_Id` IN ($ph)", 'components_Id');
$emptyById = $indexBy("SELECT `components_Id`, `BeforeLineSpace`, `AfterLineSpace`, `table1_LineType`
                       FROM `EmptySpace` WHERE `components_Id` IN ($ph)", 'components_Id');
$mediaById = $indexBy("SELECT `components_Id`, `URL`, `Uploaded`, `MultiMediaType_MultiMediaType`
                       FROM `MultiMedia` WHERE `components_Id` IN ($ph)", 'components_Id');

// Quiz question + answers.
$quizById = $indexBy("SELECT `component_Id`, `Id`, `Question`, `Image`, `OpenQuestion`
                      FROM `PQQuestion` WHERE `component_Id` IN ($ph)", 'component_Id');
$answersByQ = [];
if ($quizById) {
    $qIds = array_map(fn($q) => (int)$q['Id'], $quizById);
    $qph = implode(',', array_fill(0, count($qIds), '?'));
    $aSt = $pdo->prepare("SELECT `Id`, `PQQuestion_Id`, `AnswerOption`, `IsCorrect`
                          FROM `PQAnswer` WHERE `PQQuestion_Id` IN ($qph) ORDER BY `Id`");
    $aSt->execute($qIds);
    foreach ($aSt->fetchAll(PDO::FETCH_ASSOC) as $a) {
        $answersByQ[(int)$a['PQQuestion_Id']][] = [
            'id'         => (int)$a['Id'],
            'text'       => $a['AnswerOption'],
            'is_correct' => (int)$a['IsCorrect'],
        ];
    }
}

/** Build the editor's emptyspace object from an EmptySpace detail row. */
$buildEmptyspace = function (?array $es) {
    if (!$es) return null;
    if ($es['table1_LineType'] && $es['table1_LineType'] !== 'nothing') {
        return ['before' => (int)$es['BeforeLineSpace'],
                'after'  => $es['AfterLineSpace'] !== null ? (int)$es['AfterLineSpace'] : null,
                'type'   => $es['table1_LineType']];
    }
    if ((int)$es['BeforeLineSpace'] > 0) {
        return ['before' => (int)$es['BeforeLineSpace'], 'after' => null, 'type' => 'nothing'];
    }
    return null;
};

// ── 3. Assemble one row per (section, component) ────────────
$rows = [];
foreach ($members as $m) {
    $cid  = (int)$m['component_id'];
    $type = $m['type'];
    $emptyspace = $buildEmptyspace($emptyById[$cid] ?? null);

    $content  = '';
    $language = null;

    if ($type === 'multimedia') {
        $mr = $mediaById[$cid] ?? null;
        $content = json_encode([
            'url'        => $mr['URL'] ?? '',
            'uploaded'   => (int)($mr['Uploaded'] ?? 0),
            'media_type' => $mr['MultiMediaType_MultiMediaType'] ?? 'image',
        ], JSON_UNESCAPED_UNICODE);
    } elseif ($type === 'quiz') {
        $q = $quizById[$cid] ?? null;
        $content = json_encode([
            'question_id'   => (int)($q['Id'] ?? 0),
            'question'      => $q['Question'] ?? '',
            'image'         => $q['Image'] ?? null,
            'open_question' => (int)($q['OpenQuestion'] ?? 0),
            'answers'       => $answersByQ[(int)($q['Id'] ?? 0)] ?? [],
        ], JSON_UNESCAPED_UNICODE);
    } elseif (isset($infoById[$cid])) {
        // InfoBox is authoritative for tip vs warning.
        $type    = ((int)$infoById[$cid]['IsWarning']) ? 'warning' : 'tip';
        $content = $infoById[$cid]['Text'];
    } elseif (isset($codeById[$cid])) {
        $content  = $codeById[$cid]['Code'];
        $language = $codeById[$cid]['LanguageName'];
    } elseif (isset($textById[$cid])) {
        $content = $textById[$cid]['Text'];
    }

    $rows[] = [
        'id'         => $cid,
        'type'       => $type,
        'section_id' => (int)$m['section_id'],
        'order'      => (int)$m['order'],
        'content'    => $content,
        'language'   => $language,
        'emptyspace' => $emptyspace,
    ];
}

echo json_encode($rows, JSON_UNESCAPED_UNICODE);

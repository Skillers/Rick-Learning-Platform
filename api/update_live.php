<?php
/**
 * update_live.php — edit the LIVE version of a page in place (content only).
 *
 * For fixing typos / updating wording without a concept. Allowed:
 *   - section titles
 *   - the content of existing components (text, code, callout, media, quiz
 *     question + existing answers)
 * NOT allowed (returns 409): adding/removing/reordering components or sections,
 * or changing a component's kind. Components are matched BY POSITION, which is
 * safe precisely because structure can't change.
 *
 * Refused if a concept exists for the page — edit that and publish instead.
 * Because the live version's rows are private (publish snapshots the outgoing
 * version), these in-place updates never touch an archived version.
 *
 * Input (JSON POST):
 *   { version_id:int, sections:[ { db_id:int, title, components:[ {type,content,meta} ] } ] }
 * Output: { ok:true, version_id:int, status:'live', sections:[int,...] }
 *   409 { error, structural:true }  when a structural change was attempted
 */
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/db.connection.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$in = json_decode(file_get_contents('php://input'), true);
if (!is_array($in)) { http_response_code(400); echo json_encode(['error' => 'Invalid JSON']); exit; }

$versionId = (int)($in['version_id'] ?? 0);
$sections  = is_array($in['sections'] ?? null) ? $in['sections'] : [];
$title     = trim((string)($in['title'] ?? ''));
$xp        = isset($in['xp']) ? (int)$in['xp'] : null;
if (!$versionId) { http_response_code(400); echo json_encode(['error' => 'version_id is required']); exit; }

$LANG = ['python' => 1, 'javascript' => 2, 'java' => 3, 'c#' => 4, 'csharp' => 4, 'html' => 5, 'css' => 6, 'sql' => 7];

function db_component_type(array $comp): ?string {
    $t = strtolower($comp['type'] ?? '');
    $meta = $comp['meta'] ?? [];
    switch ($t) {
        case 'text':       return 'text';
        case 'code':       return 'code';
        case 'callout':    return (($meta['style'] ?? 'tip') === 'warning') ? 'warning' : 'tip';
        case 'tip':        return 'tip';
        case 'warning':    return 'warning';
        case 'video':
        case 'multimedia': return 'multimedia';
        case 'quiz':       return 'quiz';
        case 'assignment': return 'assignment';
        default:           return null;
    }
}
/** tip/warning share a slot (toggling the callout style is a content edit). */
function slot_kind(string $t): string { return ($t === 'tip' || $t === 'warning') ? 'callout' : $t; }

function structural(string $msg): void { http_response_code(409); echo json_encode(['error' => $msg, 'structural' => true]); exit; }

try {
    $v = $pdo->prepare("SELECT `pages_Id`, `Status` FROM `PageVersion` WHERE `Id` = ?");
    $v->execute([$versionId]);
    $row = $v->fetch(PDO::FETCH_ASSOC);
    if (!$row) { http_response_code(404); echo json_encode(['error' => 'Version not found']); exit; }
    if ($row['Status'] !== 'live') { http_response_code(409); echo json_encode(['error' => 'Alleen de live versie kan zo bewerkt worden']); exit; }
    $pageId = (int)$row['pages_Id'];

    // The single-concept rule: in-place live editing is only for "no concept".
    $hasConcept = $pdo->prepare("SELECT 1 FROM `PageVersion` WHERE `pages_Id` = ? AND `Status` = 'concept' LIMIT 1");
    $hasConcept->execute([$pageId]);
    if ($hasConcept->fetchColumn()) {
        http_response_code(409);
        echo json_encode(['error' => 'Er bestaat een concept; bewerk dat en publiceer.', 'concept_exists' => true]);
        exit;
    }

    // Which sections does this live version link?
    $linked = $pdo->prepare("SELECT `sections_Id` FROM `PageVersion_has_sections` WHERE `PageVersion_Id` = ?");
    $linked->execute([$versionId]);
    $liveSectionIds = array_map('intval', $linked->fetchAll(PDO::FETCH_COLUMN));

    // Structural guard: same set of sections, same count.
    $payloadSecIds = [];
    foreach ($sections as $s) { $payloadSecIds[] = (int)($s['db_id'] ?? 0); }
    sort($liveSectionIds); $cmp = $payloadSecIds; sort($cmp);
    if ($cmp !== $liveSectionIds) structural('Secties toevoegen of verwijderen kan niet op een live versie.');

    $pdo->beginTransaction();

    if ($title !== '') $pdo->prepare("UPDATE `PageVersion` SET `Title` = ? WHERE `Id` = ?")->execute([mb_substr($title, 0, 45), $versionId]);
    if ($xp !== null)  $pdo->prepare("UPDATE `PageVersion` SET `XpReward` = ? WHERE `Id` = ?")->execute([$xp, $versionId]);

    // Prepared in-place updates.
    $updTitle = $pdo->prepare("UPDATE `Sections` SET `Title` = ? WHERE `Id` = ?");
    $updType  = $pdo->prepare("UPDATE `Components` SET `ComponentType_ComponentTypeText` = ? WHERE `Id` = ?");
    $updText  = $pdo->prepare("UPDATE `TextBLocks` SET `Text` = ? WHERE `Component_Id` = ?");
    $updCode  = $pdo->prepare("UPDATE `CodeSnippets` SET `Code` = ?, `Languages_Id` = ? WHERE `Components_Id` = ?");
    $updInfo  = $pdo->prepare("UPDATE `InfoBoxes` SET `Text` = ?, `IsWarning` = ? WHERE `components_Id` = ?");
    $updMedia = $pdo->prepare("UPDATE `MultiMedia` SET `URL` = ?, `Uploaded` = ?, `MultiMediaType_MultiMediaType` = ? WHERE `components_Id` = ?");
    $updQ     = $pdo->prepare("UPDATE `PQQuestion` SET `Question` = ?, `Image` = ?, `OpenQuestion` = ?, `ExpectedResult` = ? WHERE `component_Id` = ?");

    foreach ($sections as $sec) {
        $secId = (int)($sec['db_id'] ?? 0);
        $updTitle->execute([mb_substr(trim((string)($sec['title'] ?? 'Sectie')), 0, 45), $secId]);

        // Stored components of this section, in order.
        $cs = $pdo->prepare("SELECT shc.`components_Id` AS id, c.`ComponentType_ComponentTypeText` AS type
                             FROM `sections_has_components` shc JOIN `components` c ON c.`Id` = shc.`components_Id`
                             WHERE shc.`sections_Id` = ? ORDER BY shc.`Order`");
        $cs->execute([$secId]);
        $stored = $cs->fetchAll(PDO::FETCH_ASSOC);

        // Payload components that map to a real DB type, in order.
        $payloadComps = [];
        foreach (($sec['components'] ?? []) as $c) { if (db_component_type($c) !== null) $payloadComps[] = $c; }

        if (count($payloadComps) !== count($stored)) {
            structural('Componenten toevoegen of verwijderen kan niet op een live versie.');
        }

        foreach ($stored as $i => $st) {
            $comp    = $payloadComps[$i];
            $dbType  = db_component_type($comp);
            $cid     = (int)$st['id'];
            if (slot_kind($dbType) !== slot_kind($st['type'])) {
                structural('Het type van een component wijzigen kan niet op een live versie.');
            }
            $content = (string)($comp['content'] ?? '');
            $meta    = is_array($comp['meta'] ?? null) ? $comp['meta'] : [];

            switch ($dbType) {
                case 'text':
                    $updText->execute([$content, $cid]);
                    break;
                case 'code':
                    $lang = strtolower((string)($meta['language'] ?? 'python'));
                    $updCode->execute([$content, $LANG[$lang] ?? 1, $cid]);
                    break;
                case 'tip':
                case 'warning':
                    if ($dbType !== $st['type']) $updType->execute([$dbType, $cid]);  // callout toggle
                    $updInfo->execute([$content, $dbType === 'warning' ? 1 : 0, $cid]);
                    break;
                case 'multimedia':
                    $isUpload  = ($meta['source'] ?? '') === 'upload';
                    $url       = $isUpload ? (string)($meta['uploaded'] ?? '') : $content;
                    $mediaType = (string)($meta['media_type'] ?? 'image');
                    if (!in_array($mediaType, ['image', 'video', 'audio'], true)) $mediaType = 'image';
                    $updMedia->execute([mb_substr($url, 0, 255), $isUpload ? 1 : 0, $mediaType, $cid]);
                    break;
                case 'quiz':
                case 'assignment':
                    $qd       = is_array($meta['quizData'] ?? null) ? $meta['quizData'] : [];
                    $question = mb_substr((string)($qd['question'] ?? $content), 0, 255);
                    $isOpen   = !empty($qd['open_question']) ? 1 : 0;
                    $image    = isset($qd['image']) ? mb_substr((string)$qd['image'], 0, 255) : null;
                    $expected = ($isOpen && trim((string)($qd['expected_result'] ?? '')) !== '')
                              ? (string)$qd['expected_result'] : null;
                    $updQ->execute([$question, $image, $isOpen, $expected, $cid]);
                    // Update existing answers in place (no add/remove → student picks stay valid).
                    $qIdStmt = $pdo->prepare("SELECT `Id` FROM `PQQuestion` WHERE `component_Id` = ?");
                    $qIdStmt->execute([$cid]);
                    $qId = (int)$qIdStmt->fetchColumn();
                    $aStmt = $pdo->prepare("SELECT `Id` FROM `PQAnswer` WHERE `PQQuestion_Id` = ? ORDER BY `Id`");
                    $aStmt->execute([$qId]);
                    $answerIds = array_map('intval', $aStmt->fetchAll(PDO::FETCH_COLUMN));
                    $payloadAnswers = [];
                    foreach (($qd['answers'] ?? []) as $ans) {
                        $t = trim((string)($ans['text'] ?? ''));
                        if ($t === '') continue;
                        $payloadAnswers[] = [mb_substr($t, 0, 255), !empty($ans['is_correct']) ? 1 : 0];
                    }
                    if (count($payloadAnswers) !== count($answerIds)) {
                        structural('Antwoordopties toevoegen of verwijderen kan niet op een live versie.');
                    }
                    $updA = $pdo->prepare("UPDATE `PQAnswer` SET `AnswerOption` = ?, `IsCorrect` = ? WHERE `Id` = ?");
                    foreach ($answerIds as $j => $aid) $updA->execute([$payloadAnswers[$j][0], $payloadAnswers[$j][1], $aid]);
                    break;
            }
        }
    }

    $pdo->commit();
    echo json_encode(['ok' => true, 'version_id' => $versionId, 'status' => 'live', 'sections' => $liveSectionIds]);

} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

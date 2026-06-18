<?php
/**
 * save_page.php — persist the editor tree into a page's CONCEPT version, using
 * copy-on-write so live/archived versions are never disturbed.
 *
 * Per section:
 *   - unchanged (content signature matches what's stored) → left as-is.
 *   - changed & SHARED with another version            → forked to a new
 *                                                          private section row.
 *   - changed & PRIVATE to this concept                → edited in place
 *                                                          (same id, no churn).
 *   - removed from the payload                          → unlinked; the row is
 *                                                          deleted only if no
 *                                                          version still uses it.
 *
 * Target resolution:
 *   - version_id given            → that version (must be 'concept').
 *   - else db_id (page) given     → the page's concept (must exist).
 *   - else                        → create a new page + its first concept.
 *
 * Input (JSON POST):
 *   { db_id:int|null, version_id:int|null, course_id:int, title, type, xp,
 *     sections:[ { db_id:int|null, title, components:[ {type,content,meta} ] } ] }
 * Output: { ok:true, db_id:int, version_id:int, status:'concept', sections:[int,...] }
 */
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/db.connection.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$in = json_decode(file_get_contents('php://input'), true);
if (!is_array($in)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON']);
    exit;
}

$versionId = isset($in['version_id']) && $in['version_id'] ? (int)$in['version_id'] : 0;
$pageId    = isset($in['db_id'])      && $in['db_id']      ? (int)$in['db_id']      : 0;
$courseId  = (int)($in['course_id'] ?? 0);
$title     = trim((string)($in['title'] ?? ''));
$typeStr   = strtolower(trim((string)($in['type'] ?? 'lesson')));
$xp        = (int)($in['xp'] ?? 0);
$sections  = is_array($in['sections'] ?? null) ? $in['sections'] : [];

$PAGE_TYPE  = ['theory' => 1, 'lesson' => 1, 'exercise' => 2, 'quiz' => 3, 'project' => 4];
$pageTypeId = $PAGE_TYPE[$typeStr] ?? 1;
$LANG = ['python' => 1, 'javascript' => 2, 'java' => 3, 'c#' => 4, 'csharp' => 4, 'html' => 5, 'css' => 6, 'sql' => 7];

const DEFAULT_SECTION_XP = 5;
const DEFAULT_DURATION   = 10;

/** Editor component type → DB ComponentType, or null to skip (e.g. divider). */
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

/** Canonical, comparable representation of one payload component (DB-normalised). */
function payload_canonical(array $comp, array $LANG): ?array {
    $dbType = db_component_type($comp);
    if ($dbType === null) return null;
    $content = (string)($comp['content'] ?? '');
    $meta    = is_array($comp['meta'] ?? null) ? $comp['meta'] : [];
    switch ($dbType) {
        case 'text':    return ['text', $content];
        case 'code':    return ['code', strtolower((string)($meta['language'] ?? 'python')), $content];
        case 'tip':
        case 'warning': return [$dbType, $content];
        case 'multimedia':
            $isUpload  = ($meta['source'] ?? '') === 'upload';
            $url       = $isUpload ? (string)($meta['uploaded'] ?? '') : $content;
            $mediaType = (string)($meta['media_type'] ?? 'image');
            if (!in_array($mediaType, ['image', 'video', 'audio'], true)) $mediaType = 'image';
            return ['multimedia', mb_substr($url, 0, 255), $isUpload ? 1 : 0, $mediaType];
        case 'quiz':
        case 'assignment':
            $qd       = is_array($meta['quizData'] ?? null) ? $meta['quizData'] : [];
            $question = mb_substr((string)($qd['question'] ?? $content), 0, 255);
            $isOpen   = !empty($qd['open_question']) ? 1 : 0;
            $image    = isset($qd['image']) ? mb_substr((string)$qd['image'], 0, 255) : '';
            $answers  = [];
            if (!$isOpen) {
                foreach (($qd['answers'] ?? []) as $ans) {
                    $t = trim((string)($ans['text'] ?? ''));
                    if ($t === '') continue;
                    $answers[] = [mb_substr($t, 0, 255), !empty($ans['is_correct']) ? 1 : 0];
                }
            }
            return [$dbType, $question, $isOpen, $image, $answers];
    }
    return null;
}

/** Canonical list of a stored section's components, same shape as payload_canonical. */
function db_section_components_canonical(PDO $pdo, int $sectionId): array {
    $st = $pdo->prepare("
        SELECT c.`Id`, c.`ComponentType_ComponentTypeText` AS type
        FROM `sections_has_components` shc
        JOIN `components` c ON c.`Id` = shc.`components_Id`
        WHERE shc.`sections_Id` = ?
        ORDER BY shc.`Order`
    ");
    $st->execute([$sectionId]);
    $out = [];
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $cid = (int)$r['Id'];
        switch ($r['type']) {
            case 'text':
                $t = $pdo->prepare("SELECT `Text` FROM `TextBLocks` WHERE `Component_Id` = ?");
                $t->execute([$cid]);
                $out[] = ['text', (string)$t->fetchColumn()];
                break;
            case 'code':
                $t = $pdo->prepare("SELECT cs.`Code`, l.`LanguageName` FROM `CodeSnippets` cs
                                    LEFT JOIN `Languages` l ON l.`Id` = cs.`Languages_Id`
                                    WHERE cs.`Components_Id` = ?");
                $t->execute([$cid]);
                $row = $t->fetch(PDO::FETCH_ASSOC) ?: [];
                $out[] = ['code', strtolower((string)($row['LanguageName'] ?? 'python')), (string)($row['Code'] ?? '')];
                break;
            case 'tip':
            case 'warning':
                $t = $pdo->prepare("SELECT `Text` FROM `InfoBoxes` WHERE `components_Id` = ?");
                $t->execute([$cid]);
                $out[] = [$r['type'], (string)$t->fetchColumn()];
                break;
            case 'multimedia':
                $t = $pdo->prepare("SELECT `URL`, `Uploaded`, `MultiMediaType_MultiMediaType` AS mt FROM `MultiMedia` WHERE `components_Id` = ?");
                $t->execute([$cid]);
                $row = $t->fetch(PDO::FETCH_ASSOC) ?: [];
                $out[] = ['multimedia', (string)($row['URL'] ?? ''), (int)($row['Uploaded'] ?? 0), (string)($row['mt'] ?? 'image')];
                break;
            case 'quiz':
            case 'assignment':
                $t = $pdo->prepare("SELECT `Id`, `Question`, `Image`, `OpenQuestion` FROM `PQQuestion` WHERE `component_Id` = ?");
                $t->execute([$cid]);
                $q = $t->fetch(PDO::FETCH_ASSOC) ?: [];
                $answers = [];
                if (empty($q['OpenQuestion']) && isset($q['Id'])) {
                    $a = $pdo->prepare("SELECT `AnswerOption`, `IsCorrect` FROM `PQAnswer` WHERE `PQQuestion_Id` = ? ORDER BY `Id`");
                    $a->execute([(int)$q['Id']]);
                    foreach ($a->fetchAll(PDO::FETCH_ASSOC) as $ans) {
                        $answers[] = [(string)$ans['AnswerOption'], (int)$ans['IsCorrect']];
                    }
                }
                $out[] = [$r['type'], (string)($q['Question'] ?? ''), (int)($q['OpenQuestion'] ?? 0), (string)($q['Image'] ?? ''), $answers];
                break;
        }
    }
    return $out;
}

/** Signature of a section: title + ordered component canonicals. */
function section_signature(string $title, array $componentCanonicals): string {
    return sha1(json_encode([mb_substr(trim($title), 0, 45), $componentCanonicals], JSON_UNESCAPED_UNICODE));
}

try {
    $pdo->beginTransaction();

    // ── 1. Resolve the target concept version ───────────────────────────────
    if ($versionId) {
        $vs = $pdo->prepare("SELECT `pages_Id`, `Status` FROM `PageVersion` WHERE `Id` = ?");
        $vs->execute([$versionId]);
        $v = $vs->fetch(PDO::FETCH_ASSOC);
        if (!$v) throw new RuntimeException('Version not found', 404);
        if ($v['Status'] !== 'concept') throw new RuntimeException('Alleen een concept kan bewerkt worden', 409);
        $pageId = (int)$v['pages_Id'];
    } elseif ($pageId) {
        $vs = $pdo->prepare("SELECT `Id` FROM `PageVersion` WHERE `pages_Id` = ? AND `Status` = 'concept' LIMIT 1");
        $vs->execute([$pageId]);
        $cid = $vs->fetchColumn();
        if ($cid === false) throw new RuntimeException('Geen concept om te bewerken', 409);
        $versionId = (int)$cid;
    } else {
        // Brand-new page + its first concept.
        if (!$courseId || $title === '') throw new RuntimeException('course_id and title are required', 400);
        $ord = (int)$pdo->query("SELECT COALESCE(MAX(`order`),0)+1 FROM `Pages` WHERE `Course_Id` = " . $courseId)->fetchColumn();
        $pdo->prepare("INSERT INTO `Pages` (`Course_Id`, `order`, `Published`, `PageType_Id`) VALUES (?,?,0,?)")
            ->execute([$courseId, $ord, $pageTypeId]);
        $pageId = (int)$pdo->lastInsertId();
        $pdo->prepare("INSERT INTO `PageVersion` (`pages_Id`, `VersionNo`, `Status`, `Title`, `XpReward`, `EstimatedDuration`, `CreatedAt`)
                       VALUES (?,1,'concept',?,?,?,NOW())")
            ->execute([$pageId, mb_substr($title, 0, 45), $xp, DEFAULT_DURATION]);
        $versionId = (int)$pdo->lastInsertId();
    }

    // ── 2. Update the concept's metadata (title/xp on the version, type on the page) ──
    $pdo->prepare("UPDATE `PageVersion` SET `Title` = ?, `XpReward` = ? WHERE `Id` = ?")
        ->execute([mb_substr($title, 0, 45), $xp, $versionId]);
    $pdo->prepare("UPDATE `pages` SET `PageType_Id` = ? WHERE `Id` = ?")->execute([$pageTypeId, $pageId]);

    // ── 3. Snapshot the concept's current sections (id → meta + signature) ───
    $cur = $pdo->prepare("
        SELECT s.`Id`, s.`Title`, pvs.`XPReward`
        FROM `PageVersion_has_sections` pvs
        JOIN `sections` s ON s.`Id` = pvs.`sections_Id`
        WHERE pvs.`PageVersion_Id` = ?
    ");
    $cur->execute([$versionId]);
    $currentXp = [];     // sectionId → XPReward
    $storedSig = [];     // sectionId → signature
    foreach ($cur->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $sid = (int)$r['Id'];
        $currentXp[$sid] = (int)$r['XPReward'];
        $storedSig[$sid] = section_signature($r['Title'], db_section_components_canonical($pdo, $sid));
    }
    $oldLinkedIds = array_keys($currentXp);

    // Is a section shared with any OTHER version?
    $sharedStmt = $pdo->prepare("SELECT COUNT(*) FROM `PageVersion_has_sections` WHERE `sections_Id` = ? AND `PageVersion_Id` <> ?");
    $isShared = function (int $sid) use ($sharedStmt, $versionId): bool {
        $sharedStmt->execute([$sid, $versionId]);
        return (int)$sharedStmt->fetchColumn() > 0;
    };

    // ── 4. Id counters + prepared inserts ───────────────────────────────────
    $next = [];
    foreach (['Sections', 'Components', 'CodeSnippets', 'InfoBoxes', 'PQQuestion', 'MultiMedia'] as $t) {
        $next[$t] = (int)$pdo->query("SELECT COALESCE(MAX(`Id`),0) FROM `$t`")->fetchColumn();
    }
    $newId = function (string $t) use (&$next) { return ++$next[$t]; };

    $insSection = $pdo->prepare("INSERT INTO `Sections` (`Id`, `Title`) VALUES (?,?)");
    $updSection = $pdo->prepare("UPDATE `Sections` SET `Title` = ? WHERE `Id` = ?");
    $insSHC     = $pdo->prepare("INSERT INTO `sections_has_components` (`sections_Id`, `components_Id`, `Order`) VALUES (?,?,?)");
    $insComp    = $pdo->prepare("INSERT INTO `Components` (`Id`, `ComponentType_ComponentTypeText`) VALUES (?,?)");
    $insText    = $pdo->prepare("INSERT INTO `TextBLocks` (`Component_Id`, `Text`) VALUES (?,?)");
    $insCode    = $pdo->prepare("INSERT INTO `CodeSnippets` (`Id`, `Components_Id`, `Languages_Id`, `Code`) VALUES (?,?,?,?)");
    $insInfo    = $pdo->prepare("INSERT INTO `InfoBoxes` (`Id`, `components_Id`, `Text`, `IsWarning`) VALUES (?,?,?,?)");
    $insMedia   = $pdo->prepare("INSERT INTO `MultiMedia` (`Id`, `URL`, `components_Id`, `Uploaded`, `MultiMediaType_MultiMediaType`) VALUES (?,?,?,?,?)");
    $insQ       = $pdo->prepare("INSERT INTO `PQQuestion` (`Id`, `Question`, `Image`, `OpenQuestion`, `component_Id`) VALUES (?,?,?,?,?)");
    $insA       = $pdo->prepare("INSERT INTO `PQAnswer` (`PQQuestion_Id`, `AnswerOption`, `IsCorrect`) VALUES (?,?,?)");

    // Insert all components of a section (rows + detail + ordering links).
    $addComponents = function (int $secId, array $components)
        use ($newId, $insComp, $insSHC, $insText, $insCode, $insInfo, $insMedia, $insQ, $insA, $LANG) {
        $order = 0;
        foreach ($components as $comp) {
            $dbType = db_component_type($comp);
            if ($dbType === null) continue;
            $order++;
            $compId  = $newId('Components');
            $content = (string)($comp['content'] ?? '');
            $meta    = is_array($comp['meta'] ?? null) ? $comp['meta'] : [];
            $insComp->execute([$compId, $dbType]);
            $insSHC->execute([$secId, $compId, $order]);
            switch ($dbType) {
                case 'text':
                    $insText->execute([$compId, $content]);
                    break;
                case 'code':
                    $lang = strtolower((string)($meta['language'] ?? 'python'));
                    $insCode->execute([$newId('CodeSnippets'), $compId, $LANG[$lang] ?? 1, $content]);
                    break;
                case 'tip':
                case 'warning':
                    $insInfo->execute([$newId('InfoBoxes'), $compId, $content, $dbType === 'warning' ? 1 : 0]);
                    break;
                case 'multimedia':
                    $isUpload  = ($meta['source'] ?? '') === 'upload';
                    $url       = $isUpload ? (string)($meta['uploaded'] ?? '') : $content;
                    $mediaType = (string)($meta['media_type'] ?? 'image');
                    if (!in_array($mediaType, ['image', 'video', 'audio'], true)) $mediaType = 'image';
                    $insMedia->execute([$newId('MultiMedia'), mb_substr($url, 0, 255), $compId, $isUpload ? 1 : 0, $mediaType]);
                    break;
                case 'quiz':
                case 'assignment':
                    $qd       = is_array($meta['quizData'] ?? null) ? $meta['quizData'] : [];
                    $question = mb_substr((string)($qd['question'] ?? $content), 0, 255);
                    $isOpen   = !empty($qd['open_question']) ? 1 : 0;
                    $image    = isset($qd['image']) ? mb_substr((string)$qd['image'], 0, 255) : null;
                    $qId      = $newId('PQQuestion');
                    $insQ->execute([$qId, $question, $image, $isOpen, $compId]);
                    if (!$isOpen) {
                        foreach (($qd['answers'] ?? []) as $ans) {
                            $t = trim((string)($ans['text'] ?? ''));
                            if ($t === '') continue;
                            $insA->execute([$qId, mb_substr($t, 0, 255), !empty($ans['is_correct']) ? 1 : 0]);
                        }
                    }
                    break;
            }
        }
    };

    // Delete a component that no section references any more (+ its detail rows).
    $deleteOrphanComponent = function (int $cid) use ($pdo) {
        // PQAnswer has ON DELETE NO ACTION, so clear it before the component cascade.
        $pdo->prepare("DELETE FROM `PQAnswer` WHERE `PQQuestion_Id` IN (SELECT `Id` FROM `PQQuestion` WHERE `component_Id` = ?)")->execute([$cid]);
        $pdo->prepare("DELETE FROM `EmptySpace` WHERE `components_Id` = ?")->execute([$cid]);
        $pdo->prepare("DELETE FROM `Components` WHERE `Id` = ?")->execute([$cid]);
    };

    // Replace a private section's components in place (keeps the section id).
    $countLinks = $pdo->prepare("SELECT COUNT(*) FROM `sections_has_components` WHERE `components_Id` = ?");
    $replaceComponents = function (int $secId, array $components) use ($pdo, $addComponents, $deleteOrphanComponent, $countLinks) {
        $old = $pdo->prepare("SELECT `components_Id` FROM `sections_has_components` WHERE `sections_Id` = ?");
        $old->execute([$secId]);
        $oldComps = array_map('intval', $old->fetchAll(PDO::FETCH_COLUMN));
        $pdo->prepare("DELETE FROM `sections_has_components` WHERE `sections_Id` = ?")->execute([$secId]);
        foreach ($oldComps as $cid) {
            $countLinks->execute([$cid]);
            if ((int)$countLinks->fetchColumn() === 0) $deleteOrphanComponent($cid);
        }
        $addComponents($secId, $components);
    };

    // ── 5. Walk the payload, applying copy-on-write per section ──────────────
    $finalSections = [];   // [ [sectionId, xp], ... ] in payload order
    foreach ($sections as $sec) {
        $secTitle   = (string)($sec['title'] ?? 'Sectie');
        $components = is_array($sec['components'] ?? null) ? $sec['components'] : [];
        $canon = [];
        foreach ($components as $comp) {
            $c = payload_canonical($comp, $LANG);
            if ($c !== null) $canon[] = $c;
        }
        $desiredSig = section_signature($secTitle, $canon);
        $secDbId    = (int)($sec['db_id'] ?? 0);
        $linked     = $secDbId && isset($currentXp[$secDbId]);

        if ($linked && ($storedSig[$secDbId] ?? null) === $desiredSig) {
            // Unchanged — keep the existing section row untouched.
            $finalSections[] = [$secDbId, $currentXp[$secDbId]];
            continue;
        }

        if ($linked && !$isShared($secDbId)) {
            // Changed but private to this concept — edit in place.
            $updSection->execute([mb_substr(trim($secTitle), 0, 45), $secDbId]);
            $replaceComponents($secDbId, $components);
            $finalSections[] = [$secDbId, $currentXp[$secDbId]];
            continue;
        }

        // New section, or changed while shared — fork a fresh private section.
        $newSec = $newId('Sections');
        $insSection->execute([$newSec, mb_substr(trim($secTitle), 0, 45)]);
        $addComponents($newSec, $components);
        $finalSections[] = [$newSec, $linked ? $currentXp[$secDbId] : DEFAULT_SECTION_XP];
    }

    // ── 6. Rebuild the concept's section links in payload order ──────────────
    $pdo->prepare("DELETE FROM `PageVersion_has_sections` WHERE `PageVersion_Id` = ?")->execute([$versionId]);
    $insLink = $pdo->prepare("INSERT INTO `PageVersion_has_sections` (`PageVersion_Id`, `sections_Id`, `Order`, `XPReward`) VALUES (?,?,?,?)");
    $finalIds = [];
    foreach ($finalSections as $i => [$sid, $xpr]) {
        $insLink->execute([$versionId, $sid, $i + 1, $xpr]);
        $finalIds[] = $sid;
    }

    // ── 7. GC sections the concept no longer links (only if fully unused) ────
    $linkCount = $pdo->prepare("SELECT COUNT(*) FROM `PageVersion_has_sections` WHERE `sections_Id` = ?");
    foreach (array_diff($oldLinkedIds, $finalIds) as $gone) {
        $linkCount->execute([$gone]);
        if ((int)$linkCount->fetchColumn() > 0) continue;   // still used by another version
        $cIds = $pdo->prepare("SELECT `components_Id` FROM `sections_has_components` WHERE `sections_Id` = ?");
        $cIds->execute([$gone]);
        $cIds = array_map('intval', $cIds->fetchAll(PDO::FETCH_COLUMN));
        $pdo->prepare("DELETE FROM `Sections` WHERE `Id` = ?")->execute([$gone]); // cascades sections_has_components
        foreach ($cIds as $cid) {
            $countLinks->execute([$cid]);
            if ((int)$countLinks->fetchColumn() === 0) $deleteOrphanComponent($cid);
        }
    }

    $pdo->commit();
    echo json_encode(['ok' => true, 'db_id' => $pageId, 'version_id' => $versionId, 'status' => 'concept', 'sections' => $finalIds]);

} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    $code = $e instanceof RuntimeException && in_array($e->getCode(), [400, 404, 409], true) ? $e->getCode() : 500;
    http_response_code($code);
    echo json_encode(['error' => $e->getMessage()]);
}

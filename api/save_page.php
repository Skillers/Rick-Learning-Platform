<?php
/**
 * save_page.php — persist a page (and its full section/component tree) from the
 * Lesontwerper admin editor.
 *
 * SCOPE / SAFETY: this endpoint only "replace-all" saves DRAFT pages
 * (Pages.published = 0). Draft pages are unreachable by students, so no
 * UserXPLog / quiz-submission rows reference their section/component IDs, which
 * makes it safe to delete and re-insert the whole tree on every (debounced)
 * autosave. Published pages are rejected — editing those safely needs an
 * ID-preserving diff, which is a separate task.
 *
 * Input (JSON POST):
 *   {
 *     db_id:     int|null,        // page id, null = create new draft
 *     course_id: int,
 *     title:     string,
 *     type:      string,          // theory|lesson|exercise|quiz|project
 *     xp:        int,
 *     sections: [
 *       { title: string, xp?: int, components: [ { type, content, meta } ] }
 *     ]
 *   }
 *
 * Output: { ok:true, db_id:int, published:0, sections:[int,...] }
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

$dbId     = isset($in['db_id']) && $in['db_id'] ? (int)$in['db_id'] : 0;
$courseId = (int)($in['course_id'] ?? 0);
$title    = trim((string)($in['title'] ?? ''));
$typeStr  = strtolower(trim((string)($in['type'] ?? 'lesson')));
$xp       = (int)($in['xp'] ?? 0);
$sections = is_array($in['sections'] ?? null) ? $in['sections'] : [];

if (!$courseId || $title === '') {
    http_response_code(400);
    echo json_encode(['error' => 'course_id and title are required']);
    exit;
}

// Page type name → PageTypes.Id
$PAGE_TYPE = ['theory' => 1, 'lesson' => 1, 'exercise' => 2, 'quiz' => 3, 'project' => 4];
$pageTypeId = $PAGE_TYPE[$typeStr] ?? 1;

// Code language name → Languages.Id
$LANG = ['python' => 1, 'javascript' => 2, 'java' => 3, 'c#' => 4, 'csharp' => 4, 'html' => 5, 'css' => 6, 'sql' => 7];

// Editor component type → DB ComponentType.ComponentTypeText
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
        default:           return null;  // e.g. 'divider' — no DB type, skipped
    }
}

const DEFAULT_SECTION_XP = 5;
const DEFAULT_DURATION   = 10;

try {
    $pdo->beginTransaction();

    // ── 1. Upsert the Pages row ──────────────────────────────────────────────
    if ($dbId) {
        $pubStmt = $pdo->prepare("SELECT published FROM Pages WHERE Id = ?");
        $pubStmt->execute([$dbId]);
        $published = $pubStmt->fetchColumn();
        if ($published === false) {
            throw new RuntimeException('Page not found', 404);
        }
        if ((int)$published === 1) {
            throw new RuntimeException('Published pages cannot be auto-saved this way', 409);
        }
        $pdo->prepare(
            "UPDATE Pages SET Course_Id = ?, title = ?, PageType_Id = ?, XPReward = ? WHERE Id = ?"
        )->execute([$courseId, mb_substr($title, 0, 45), $pageTypeId, $xp, $dbId]);
    } else {
        $ord = (int)$pdo->query("SELECT COALESCE(MAX(`order`),0)+1 FROM Pages WHERE Course_Id = " . $courseId)->fetchColumn();
        $pdo->prepare(
            "INSERT INTO Pages (Course_Id, title, `order`, published, PageType_Id, XPReward, EstimatedDuration)
             VALUES (?, ?, ?, 0, ?, ?, ?)"
        )->execute([$courseId, mb_substr($title, 0, 45), $ord, $pageTypeId, $xp, DEFAULT_DURATION]);
        $dbId = (int)$pdo->lastInsertId();
    }

    // ── 2. Wipe the existing tree for this (draft) page ──────────────────────
    $compIds = $pdo->prepare(
        "SELECT c.Id FROM Components c
         JOIN Sections s ON s.Id = c.section_Id
         WHERE s.Pages_Id = ?"
    );
    $compIds->execute([$dbId]);
    $compIds = array_map('intval', $compIds->fetchAll(PDO::FETCH_COLUMN));

    if ($compIds) {
        $ph = implode(',', array_fill(0, count($compIds), '?'));
        // Quiz answers first (reference PQQuestion)
        $pdo->prepare("DELETE FROM PQAnswer WHERE PQQuestion_Id IN (SELECT Id FROM PQQuestion WHERE component_Id IN ($ph))")->execute($compIds);
        foreach ([
            "DELETE FROM PQQuestion  WHERE component_Id  IN ($ph)",
            "DELETE FROM TextBLocks  WHERE Component_Id  IN ($ph)",
            "DELETE FROM CodeSnippets WHERE Components_Id IN ($ph)",
            "DELETE FROM InfoBoxes   WHERE components_Id  IN ($ph)",
            "DELETE FROM MultiMedia  WHERE components_Id  IN ($ph)",
            "DELETE FROM EmptySpace  WHERE components_Id  IN ($ph)",
        ] as $sql) {
            $pdo->prepare($sql)->execute($compIds);
        }
        $pdo->prepare("DELETE FROM Components WHERE Id IN ($ph)")->execute($compIds);
    }
    $pdo->prepare("DELETE FROM Sections WHERE Pages_Id = ?")->execute([$dbId]);

    // ── 3. Running ID counters for tables without auto-increment ─────────────
    $next = [];
    foreach (['Sections', 'Components', 'CodeSnippets', 'InfoBoxes', 'PQQuestion', 'MultiMedia'] as $t) {
        $next[$t] = (int)$pdo->query("SELECT COALESCE(MAX(Id),0) FROM `$t`")->fetchColumn();
    }
    $newId = function (string $table) use (&$next) { return ++$next[$table]; };

    // Prepared inserts
    $insSection = $pdo->prepare("INSERT INTO Sections (Id, Pages_Id, Title, `Order`, XPReward) VALUES (?,?,?,?,?)");
    $insComp    = $pdo->prepare("INSERT INTO Components (Id, section_Id, `Order`, ComponentType_ComponentTypeText) VALUES (?,?,?,?)");
    $insText    = $pdo->prepare("INSERT INTO TextBLocks (Component_Id, Text) VALUES (?,?)");
    $insCode    = $pdo->prepare("INSERT INTO CodeSnippets (Id, Components_Id, Languages_Id, Code) VALUES (?,?,?,?)");
    $insInfo    = $pdo->prepare("INSERT INTO InfoBoxes (Id, components_Id, Text, IsWarning) VALUES (?,?,?,?)");
    $insMedia   = $pdo->prepare("INSERT INTO MultiMedia (Id, URL, components_Id, Uploaded, MultiMediaType_MultiMediaType) VALUES (?,?,?,?,?)");
    $insQ       = $pdo->prepare("INSERT INTO PQQuestion (Id, Question, Image, OpenQuestion, component_Id) VALUES (?,?,?,?,?)");
    $insA       = $pdo->prepare("INSERT INTO PQAnswer (PQQuestion_Id, AnswerOption, IsCorrect) VALUES (?,?,?)");

    $sectionIds = [];

    foreach ($sections as $sIdx => $sec) {
        $secId    = $newId('Sections');
        $secTitle = mb_substr(trim((string)($sec['title'] ?? 'Sectie')), 0, 45);
        $secXP    = isset($sec['xp']) ? (int)$sec['xp'] : DEFAULT_SECTION_XP;
        $insSection->execute([$secId, $dbId, $secTitle, $sIdx + 1, $secXP]);
        $sectionIds[] = $secId;

        $comps = is_array($sec['components'] ?? null) ? $sec['components'] : [];
        $order = 0;
        foreach ($comps as $comp) {
            $dbType = db_component_type($comp);
            if ($dbType === null) continue;  // unsupported type (e.g. divider)

            $order++;
            $compId  = $newId('Components');
            $content = (string)($comp['content'] ?? '');
            $meta    = is_array($comp['meta'] ?? null) ? $comp['meta'] : [];
            $insComp->execute([$compId, $secId, $order, $dbType]);

            switch ($dbType) {
                case 'text':
                    $insText->execute([$compId, $content]);
                    break;

                case 'code':
                    $langName = strtolower((string)($meta['language'] ?? 'python'));
                    $insCode->execute([$newId('CodeSnippets'), $compId, $LANG[$langName] ?? 1, $content]);
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
                    // Open questions have no answer options; only persist them
                    // for multiple-choice questions.
                    if (!$isOpen) {
                        foreach (($qd['answers'] ?? []) as $ans) {
                            $text = trim((string)($ans['text'] ?? ''));
                            if ($text === '') continue;  // skip blank options
                            $insA->execute([$qId, mb_substr($text, 0, 255), !empty($ans['is_correct']) ? 1 : 0]);
                        }
                    }
                    break;
            }
        }
    }

    $pdo->commit();
    echo json_encode(['ok' => true, 'db_id' => $dbId, 'published' => 0, 'sections' => $sectionIds]);

} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    $code = $e instanceof RuntimeException && in_array($e->getCode(), [404, 409], true) ? $e->getCode() : 500;
    http_response_code($code);
    echo json_encode(['error' => $e->getMessage()]);
}

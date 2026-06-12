<?php
/**
 * delete_page.php — permanently delete a page and everything under it.
 *
 * Cascades through the whole dependency graph (components + their detail rows,
 * quiz questions/answers and the per-account answer logs, sections, the page's
 * XP log + opened-pages rows). Destructive — the caller must confirm.
 *
 * Input (JSON POST): { page_id:int }
 * Output: { ok:true, page_id:int }
 */
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/db.connection.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$in = json_decode(file_get_contents('php://input'), true);
$pageId = is_array($in) ? (int)($in['page_id'] ?? 0) : 0;

if (!$pageId) {
    http_response_code(400);
    echo json_encode(['error' => 'page_id is required']);
    exit;
}

/** Run a DELETE whose only placeholders are an IN-list of ids; no-op on empty. */
function delIn(PDO $pdo, string $sqlPrefix, array $ids): void {
    if (!$ids) return;
    $ph = implode(',', array_fill(0, count($ids), '?'));
    $pdo->prepare("$sqlPrefix ($ph)")->execute($ids);
}

try {
    $pdo->beginTransaction();

    // Section / component / question / answer ids belonging to this page.
    $sectionIds = $pdo->prepare("SELECT Id FROM Sections WHERE Pages_Id = ?");
    $sectionIds->execute([$pageId]);
    $sectionIds = array_map('intval', $sectionIds->fetchAll(PDO::FETCH_COLUMN));

    $compIds = [];
    if ($sectionIds) {
        $ph = implode(',', array_fill(0, count($sectionIds), '?'));
        $q = $pdo->prepare("SELECT Id FROM Components WHERE section_Id IN ($ph)");
        $q->execute($sectionIds);
        $compIds = array_map('intval', $q->fetchAll(PDO::FETCH_COLUMN));
    }

    $questionIds = [];
    if ($compIds) {
        $ph = implode(',', array_fill(0, count($compIds), '?'));
        $q = $pdo->prepare("SELECT Id FROM PQQuestion WHERE component_Id IN ($ph)");
        $q->execute($compIds);
        $questionIds = array_map('intval', $q->fetchAll(PDO::FETCH_COLUMN));
    }

    $answerIds = [];
    if ($questionIds) {
        $ph = implode(',', array_fill(0, count($questionIds), '?'));
        $q = $pdo->prepare("SELECT Id FROM PQAnswer WHERE PQQuestion_Id IN ($ph)");
        $q->execute($questionIds);
        $answerIds = array_map('intval', $q->fetchAll(PDO::FETCH_COLUMN));
    }

    // Delete children → parents.
    delIn($pdo, "DELETE FROM ac_picked_answer WHERE PQAnswer_Id IN", $answerIds);
    delIn($pdo, "DELETE FROM ac_did_question  WHERE PQQuestion_Id IN", $questionIds);
    delIn($pdo, "DELETE FROM PQAnswer         WHERE PQQuestion_Id IN", $questionIds);
    delIn($pdo, "DELETE FROM PQQuestion       WHERE component_Id IN", $compIds);
    delIn($pdo, "DELETE FROM assigments       WHERE component_Id IN", $compIds);
    delIn($pdo, "DELETE FROM CodeSnippets     WHERE Components_Id IN", $compIds);
    delIn($pdo, "DELETE FROM EmptySpace       WHERE components_Id IN", $compIds);
    delIn($pdo, "DELETE FROM InfoBoxes        WHERE components_Id IN", $compIds);
    delIn($pdo, "DELETE FROM MultiMedia       WHERE components_Id IN", $compIds);
    delIn($pdo, "DELETE FROM TextBLocks       WHERE Component_Id IN", $compIds);
    delIn($pdo, "DELETE FROM Components        WHERE Id IN", $compIds);

    // XP log + opened-pages reference sections/page directly.
    delIn($pdo, "DELETE FROM UserXPLog WHERE sections_Id IN", $sectionIds);
    $pdo->prepare("DELETE FROM UserXPLog WHERE pages_Id = ?")->execute([$pageId]);
    $pdo->prepare("DELETE FROM accounts_opened_pages WHERE Pages_Id = ?")->execute([$pageId]);
    $pdo->prepare("DELETE FROM Sections WHERE Pages_Id = ?")->execute([$pageId]);
    $pdo->prepare("DELETE FROM Pages WHERE Id = ?")->execute([$pageId]);

    $pdo->commit();
    echo json_encode(['ok' => true, 'page_id' => $pageId]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

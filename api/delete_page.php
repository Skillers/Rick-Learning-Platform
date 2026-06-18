<?php
/**
 * delete_page.php — permanently delete a page, ALL of its versions, the
 * sections/components those versions reference, and the page's student data.
 *
 * Sections/components belong to a single page's version lineage, so removing
 * the page's versions lets us delete its content outright. Destructive — the
 * caller must confirm.
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

/** Fetch a single-column id list. */
function colIds(PDO $pdo, string $sql, array $args = []): array {
    $st = $pdo->prepare($sql);
    $st->execute($args);
    return array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
}

/** Run a DELETE whose only placeholders are an IN-list of ids; no-op on empty. */
function delIn(PDO $pdo, string $sqlPrefix, array $ids): void {
    if (!$ids) return;
    $ph = implode(',', array_fill(0, count($ids), '?'));
    $pdo->prepare("$sqlPrefix ($ph)")->execute($ids);
}

try {
    $pdo->beginTransaction();

    // Sections referenced by any version of this page, then their components.
    $sectionIds = colIds($pdo,
        "SELECT DISTINCT pvs.`sections_Id`
         FROM `PageVersion` pv
         JOIN `PageVersion_has_sections` pvs ON pvs.`PageVersion_Id` = pv.`Id`
         WHERE pv.`pages_Id` = ?", [$pageId]);

    $compIds = [];
    if ($sectionIds) {
        $ph = implode(',', array_fill(0, count($sectionIds), '?'));
        $compIds = colIds($pdo, "SELECT DISTINCT `components_Id` FROM `sections_has_components` WHERE `sections_Id` IN ($ph)", $sectionIds);
    }

    $questionIds = [];
    if ($compIds) {
        $ph = implode(',', array_fill(0, count($compIds), '?'));
        $questionIds = colIds($pdo, "SELECT `Id` FROM `PQQuestion` WHERE `component_Id` IN ($ph)", $compIds);
    }

    $assignmentIds = [];
    if ($compIds) {
        $ph = implode(',', array_fill(0, count($compIds), '?'));
        $assignmentIds = colIds($pdo, "SELECT `Id` FROM `Assigments` WHERE `component_Id` IN ($ph)", $compIds);
    }

    // Quiz logs: PQAnswer is ON DELETE NO ACTION, so clear it before the
    // PQQuestion delete cascades AC_Did_Question / AC_Picked_Answer.
    delIn($pdo, "DELETE FROM `PQAnswer`   WHERE `PQQuestion_Id` IN", $questionIds);
    delIn($pdo, "DELETE FROM `PQQuestion` WHERE `component_Id`  IN", $compIds);

    // Assignment submissions are ON DELETE NO ACTION → clear before Assigments.
    delIn($pdo, "DELETE FROM `Accounts_have_assignments` WHERE `Assigment_Id` IN", $assignmentIds);
    delIn($pdo, "DELETE FROM `Assigments` WHERE `component_Id` IN", $compIds);

    // Component detail rows (EmptySpace is NO ACTION; the rest cascade, but be explicit).
    delIn($pdo, "DELETE FROM `EmptySpace`   WHERE `components_Id` IN", $compIds);
    delIn($pdo, "DELETE FROM `CodeSnippets` WHERE `Components_Id` IN", $compIds);
    delIn($pdo, "DELETE FROM `InfoBoxes`    WHERE `components_Id` IN", $compIds);
    delIn($pdo, "DELETE FROM `MultiMedia`   WHERE `components_Id` IN", $compIds);
    delIn($pdo, "DELETE FROM `TextBLocks`   WHERE `Component_Id`  IN", $compIds);

    // Page-level XP log + section XP log (FK is SET NULL, so delete explicitly).
    delIn($pdo, "DELETE FROM `UserXPLog` WHERE `sections_Id` IN", $sectionIds);
    $pdo->prepare("DELETE FROM `UserXPLog` WHERE `pages_Id` = ?")->execute([$pageId]);

    // Components (cascades sections_has_components), versions (cascades
    // PageVersion_has_sections), then sections, then the page itself.
    delIn($pdo, "DELETE FROM `Components` WHERE `Id` IN", $compIds);
    $pdo->prepare("DELETE FROM `PageVersion` WHERE `pages_Id` = ?")->execute([$pageId]);
    delIn($pdo, "DELETE FROM `Sections` WHERE `Id` IN", $sectionIds);
    $pdo->prepare("DELETE FROM `accounts_opened_pages` WHERE `Pages_Id` = ?")->execute([$pageId]);
    $pdo->prepare("DELETE FROM `Pages` WHERE `Id` = ?")->execute([$pageId]);

    $pdo->commit();
    echo json_encode(['ok' => true, 'page_id' => $pageId]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

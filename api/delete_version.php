<?php
/**
 * delete_version.php — permanently remove a single version (archived or
 * concept). The live version cannot be deleted this way.
 *
 * Only content the deleted version exclusively owned is removed; anything still
 * referenced by another version (e.g. sections shared with the live version on
 * pre-snapshot data) is left untouched.
 *
 * Input (JSON POST): { version_id:int }
 * Output: { ok:true, version_id:int }
 *   409 when the target is the live version.
 */
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/db.connection.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$in = json_decode(file_get_contents('php://input'), true);
$versionId = is_array($in) ? (int)($in['version_id'] ?? 0) : 0;
if (!$versionId) {
    http_response_code(400);
    echo json_encode(['error' => 'version_id is required']);
    exit;
}

try {
    $vs = $pdo->prepare("SELECT `Status` FROM `PageVersion` WHERE `Id` = ?");
    $vs->execute([$versionId]);
    $status = $vs->fetchColumn();
    if ($status === false) { http_response_code(404); echo json_encode(['error' => 'Version not found']); exit; }
    if ($status === 'live') { http_response_code(409); echo json_encode(['error' => 'De live versie kan niet verwijderd worden.']); exit; }

    $pdo->beginTransaction();

    // Sections this version links.
    $secStmt = $pdo->prepare("SELECT `sections_Id` FROM `PageVersion_has_sections` WHERE `PageVersion_Id` = ?");
    $secStmt->execute([$versionId]);
    $sectionIds = array_map('intval', $secStmt->fetchAll(PDO::FETCH_COLUMN));

    // Drop the version (cascades its PageVersion_has_sections links).
    $pdo->prepare("DELETE FROM `PageVersion` WHERE `Id` = ?")->execute([$versionId]);

    // GC sections/components no version references any more.
    $linkCount = $pdo->prepare("SELECT COUNT(*) FROM `PageVersion_has_sections` WHERE `sections_Id` = ?");
    $compLinks = $pdo->prepare("SELECT COUNT(*) FROM `sections_has_components` WHERE `components_Id` = ?");
    foreach ($sectionIds as $sid) {
        $linkCount->execute([$sid]);
        if ((int)$linkCount->fetchColumn() > 0) continue;       // still used elsewhere
        $cids = $pdo->prepare("SELECT `components_Id` FROM `sections_has_components` WHERE `sections_Id` = ?");
        $cids->execute([$sid]);
        $cids = array_map('intval', $cids->fetchAll(PDO::FETCH_COLUMN));
        $pdo->prepare("DELETE FROM `Sections` WHERE `Id` = ?")->execute([$sid]);   // cascades sections_has_components
        foreach ($cids as $cid) {
            $compLinks->execute([$cid]);
            if ((int)$compLinks->fetchColumn() === 0) {
                $pdo->prepare("DELETE FROM `PQAnswer` WHERE `PQQuestion_Id` IN (SELECT `Id` FROM `PQQuestion` WHERE `component_Id` = ?)")->execute([$cid]);
                $pdo->prepare("DELETE FROM `EmptySpace` WHERE `components_Id` = ?")->execute([$cid]);
                $pdo->prepare("DELETE FROM `Components` WHERE `Id` = ?")->execute([$cid]); // cascades remaining detail
            }
        }
    }

    $pdo->commit();
    echo json_encode(['ok' => true, 'version_id' => $versionId]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

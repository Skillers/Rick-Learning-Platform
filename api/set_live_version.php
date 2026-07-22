<?php
/**
 * set_live_version.php — make a chosen version the live one.
 *
 * Promotes the target version to 'live'; the page's current live version (if
 * any, and different) is pushed to 'archived'. Works for any version — promote
 * the concept to publish it, or an archived version to roll back. The
 * single-concept rule is preserved automatically (promoting the concept leaves
 * the page with none; promoting an archived one leaves the concept untouched).
 *
 * Input (JSON POST): { version_id:int }
 * Output: { ok:true, version_id:int, page_id:int, status:'live' }
 */
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/db.connection.php';
require_once __DIR__ . '/_clone.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$in        = json_decode(file_get_contents('php://input'), true);
$versionId = is_array($in) ? (int)($in['version_id'] ?? 0) : 0;
if (!$versionId) {
    http_response_code(400);
    echo json_encode(['error' => 'version_id is required']);
    exit;
}

try {
    $v = $pdo->prepare("SELECT `pages_Id`, `Status` FROM `PageVersion` WHERE `Id` = ?");
    $v->execute([$versionId]);
    $row = $v->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        http_response_code(404);
        echo json_encode(['error' => 'Version not found']);
        exit;
    }
    $pageId = (int)$row['pages_Id'];

    if ($row['Status'] === 'live') {
        echo json_encode(['ok' => true, 'version_id' => $versionId, 'page_id' => $pageId, 'status' => 'live']);
        exit;
    }

    // A version needs at least one section before it can go live.
    $hasSection = $pdo->prepare("SELECT 1 FROM `PageVersion_has_sections` WHERE `PageVersion_Id` = ? LIMIT 1");
    $hasSection->execute([$versionId]);
    if (!$hasSection->fetchColumn()) {
        http_response_code(409);
        echo json_encode(['error' => 'Een versie zonder secties kan niet live worden gezet.']);
        exit;
    }

    $pdo->beginTransaction();

    // Demote the current live version of this page (if any). Before archiving
    // it, deep-copy its content into private rows so the archived snapshot is
    // fully independent — the new live keeps the original section ids (progress
    // carries forward) and can later be edited in place without ever touching
    // an archived version.
    $outgoing = $pdo->prepare("SELECT `Id` FROM `PageVersion` WHERE `pages_Id` = ? AND `Status` = 'live' AND `Id` <> ? LIMIT 1");
    $outgoing->execute([$pageId, $versionId]);
    $outgoingId = $outgoing->fetchColumn();
    if ($outgoingId !== false) {
        $outgoingId = (int)$outgoingId;
        $questionMap = clone_version_into_independent($pdo, $outgoingId);
        $pdo->prepare("UPDATE `PageVersion` SET `Status` = 'archived', `ArchivedAt` = NOW() WHERE `Id` = ?")
            ->execute([$outgoingId]);

        // Students who FINISHED the outgoing version are pinned to it. The clone gave
        // that (now archived) version its own private question ids, so move their
        // answers onto those ids — their completed test and grade stay with the version
        // they did, instead of carrying forward to the new live like partial students'.
        $fin = $pdo->prepare("SELECT `accounts_username` FROM `FinishedTests` WHERE `pages_Id` = ? AND `PageVersion_Id` = ?");
        $fin->execute([$pageId, $outgoingId]);
        $finishedUsers = $fin->fetchAll(PDO::FETCH_COLUMN);

        if ($finishedUsers && $questionMap) {
            $ph  = implode(',', array_fill(0, count($finishedUsers), '?'));
            $upd = $pdo->prepare("UPDATE `AC_Did_Question` SET `PQQuestion_Id` = ?
                                  WHERE `PQQuestion_Id` = ? AND `QuestionContext_ContextType` = 'section'
                                    AND `accounts_username` IN ($ph)");
            foreach ($questionMap as $origQid => $newQid) {
                $upd->execute(array_merge([$newQid, $origQid], $finishedUsers));
            }
        }
    }

    // Promote the target.
    $pdo->prepare("
        UPDATE `PageVersion`
        SET `Status` = 'live', `PublishedAt` = COALESCE(`PublishedAt`, NOW()), `ArchivedAt` = NULL
        WHERE `Id` = ?
    ")->execute([$versionId]);

    // Enable the page for students on its FIRST publish; on later publishes the
    // teacher's visibility toggle (pages.Published) is left as they set it.
    if ($outgoingId === false) {
        $pdo->prepare("UPDATE `pages` SET `Published` = 1 WHERE `Id` = ?")->execute([$pageId]);
    }

    $pdo->commit();
    echo json_encode(['ok' => true, 'version_id' => $versionId, 'page_id' => $pageId, 'status' => 'live']);

} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

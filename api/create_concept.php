<?php
/**
 * create_concept.php — start (or resume) the single editable concept for a page.
 *
 * A page may have at most ONE concept at a time. The concept is cloned from the
 * page's live version: it gets its own PageVersion row but initially SHARES all
 * the live version's sections (copy-on-write happens later, on first edit). So
 * cloning is cheap — one version row + the section-link rows.
 *
 * Input (JSON POST): { page_id:int }
 * Output:
 *   201 { ok:true, version_id:int, created:true }            new concept
 *   200 { ok:true, version_id:int, created:false }           concept already existed
 */
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/db.connection.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$in     = json_decode(file_get_contents('php://input'), true);
$pageId = is_array($in) ? (int)($in['page_id'] ?? 0) : 0;
if (!$pageId) {
    http_response_code(400);
    echo json_encode(['error' => 'page_id is required']);
    exit;
}

try {
    $pdo->beginTransaction();

    // Enforce the single-concept rule.
    $existing = $pdo->prepare("SELECT `Id` FROM `PageVersion` WHERE `pages_Id` = ? AND `Status` = 'concept' LIMIT 1");
    $existing->execute([$pageId]);
    $conceptId = $existing->fetchColumn();
    if ($conceptId !== false) {
        $pdo->commit();
        echo json_encode(['ok' => true, 'version_id' => (int)$conceptId, 'created' => false]);
        exit;
    }

    // Source to clone from: the live version, else the highest-numbered version.
    $src = $pdo->prepare("
        SELECT `Id`, `Title`, `XpReward`, `EstimatedDuration`
        FROM `PageVersion`
        WHERE `pages_Id` = ?
        ORDER BY (`Status` = 'live') DESC, `VersionNo` DESC
        LIMIT 1
    ");
    $src->execute([$pageId]);
    $source = $src->fetch(PDO::FETCH_ASSOC);

    if (!$source) {
        // No version yet — fall back to the page's own metadata.
        $p = $pdo->prepare("SELECT `PageType_Id` FROM `pages` WHERE `Id` = ?");
        $p->execute([$pageId]);
        $page = $p->fetch(PDO::FETCH_ASSOC);
        if (!$page) { throw new RuntimeException('Page not found', 404); }
        $source = [
            'Id'                => null,
            'Title'             => null,
            'XpReward'          => 10,
            'EstimatedDuration' => 10,
            'pagetypes_Id'      => (int)$page['PageType_Id'],
        ];
    }

    $nextNo = (int)$pdo->query("SELECT COALESCE(MAX(`VersionNo`),0)+1 FROM `PageVersion` WHERE `pages_Id` = " . $pageId)->fetchColumn();

    $ins = $pdo->prepare("
        INSERT INTO `PageVersion`
          (`pages_Id`, `VersionNo`, `Status`, `Title`, `XpReward`, `EstimatedDuration`, `CreatedAt`)
        VALUES (?, ?, 'concept', ?, ?, ?, NOW())
    ");
    $ins->execute([$pageId, $nextNo, $source['Title'], $source['XpReward'], $source['EstimatedDuration']]);
    $newId = (int)$pdo->lastInsertId();

    // Share the source version's sections (same section ids — copy-on-write later).
    if ($source['Id'] !== null) {
        $pdo->prepare("
            INSERT INTO `PageVersion_has_sections` (`PageVersion_Id`, `sections_Id`, `Order`, `XPReward`)
            SELECT ?, `sections_Id`, `Order`, `XPReward`
            FROM `PageVersion_has_sections`
            WHERE `PageVersion_Id` = ?
        ")->execute([$newId, $source['Id']]);
    }

    $pdo->commit();
    http_response_code(201);
    echo json_encode(['ok' => true, 'version_id' => $newId, 'created' => true]);

} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    $code = $e instanceof RuntimeException && $e->getCode() === 404 ? 404 : 500;
    http_response_code($code);
    echo json_encode(['error' => $e->getMessage()]);
}

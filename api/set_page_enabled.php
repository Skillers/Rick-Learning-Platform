<?php
/**
 * set_page_enabled.php — toggle whether a page is visible to students.
 *
 * pages.Published is the teacher's visibility switch: a page is shown to
 * students only when it is enabled AND has a live version with sections.
 * Disabling hides it without affecting its versions or content.
 *
 * Input (JSON POST): { page_id:int, enabled:0|1 }
 * Output: { ok:true, page_id:int, enabled:0|1 }
 */
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/db.connection.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$in      = json_decode(file_get_contents('php://input'), true);
$pageId  = is_array($in) ? (int)($in['page_id'] ?? 0) : 0;
$enabled = is_array($in) && !empty($in['enabled']) ? 1 : 0;
if (!$pageId) {
    http_response_code(400);
    echo json_encode(['error' => 'page_id is required']);
    exit;
}

try {
    $upd = $pdo->prepare("UPDATE `pages` SET `Published` = ? WHERE `Id` = ?");
    $upd->execute([$enabled, $pageId]);
    if ($upd->rowCount() === 0) {
        $exists = $pdo->prepare("SELECT 1 FROM `pages` WHERE `Id` = ?");
        $exists->execute([$pageId]);
        if (!$exists->fetchColumn()) {
            http_response_code(404);
            echo json_encode(['error' => 'Page not found']);
            exit;
        }
    }
    echo json_encode(['ok' => true, 'page_id' => $pageId, 'enabled' => $enabled]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

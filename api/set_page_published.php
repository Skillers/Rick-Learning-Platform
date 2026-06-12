<?php
/**
 * set_page_published.php — flip a page between concept (0) and active (1).
 *
 * Input (JSON POST): { page_id:int, published:0|1 }
 * Output: { ok:true, page_id:int, published:0|1 }
 */
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/db.connection.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$in = json_decode(file_get_contents('php://input'), true);
$pageId    = is_array($in) ? (int)($in['page_id'] ?? 0) : 0;
$published = is_array($in) && !empty($in['published']) ? 1 : 0;

if (!$pageId) {
    http_response_code(400);
    echo json_encode(['error' => 'page_id is required']);
    exit;
}

try {
    // A page needs at least one section before it can go live.
    if ($published) {
        $hasSection = $pdo->prepare("SELECT 1 FROM Sections WHERE Pages_Id = ? LIMIT 1");
        $hasSection->execute([$pageId]);
        if (!$hasSection->fetchColumn()) {
            http_response_code(409);
            echo json_encode(['error' => 'Een pagina zonder secties kan niet actief worden gezet.']);
            exit;
        }
    }

    $upd = $pdo->prepare("UPDATE Pages SET published = ? WHERE Id = ?");
    $upd->execute([$published, $pageId]);
    if ($upd->rowCount() === 0) {
        // Either the page doesn't exist or the value was already set.
        $exists = $pdo->prepare("SELECT 1 FROM Pages WHERE Id = ?");
        $exists->execute([$pageId]);
        if (!$exists->fetchColumn()) {
            http_response_code(404);
            echo json_encode(['error' => 'Page not found']);
            exit;
        }
    }

    echo json_encode(['ok' => true, 'page_id' => $pageId, 'published' => $published]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

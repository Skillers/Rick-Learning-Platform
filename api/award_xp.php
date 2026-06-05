<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/db.connection.php';
require_once __DIR__ . '/../config/xp_curve.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$body       = json_decode(file_get_contents('php://input'), true);
$username   = trim($body['username']    ?? '');
$section_id = isset($body['section_id']) ? (int)$body['section_id'] : 0;
$page_id    = isset($body['page_id'])    ? (int)$body['page_id']    : 0;

if (!$username || (!$section_id && !$page_id)) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing fields']);
    exit;
}

/**
 * Award section XP. Returns amount awarded; 0 if already done, target gone,
 * or XPReward = 0 (we skip logging 0-XP so a later raise is still claimable).
 */
function award_section(PDO $pdo, string $username, int $sectionId): int {
    $stmt = $pdo->prepare("SELECT XPReward FROM sections WHERE Id = ?");
    $stmt->execute([$sectionId]);
    $reward = $stmt->fetchColumn();
    if ($reward === false) return 0;
    $reward = (int)$reward;
    if ($reward === 0) return 0;  // skip — leave the door open for later

    $stmt = $pdo->prepare(
        "INSERT IGNORE INTO UserXPLog
            (accounts_username, pages_Id, sections_Id, Source, AwardedOn, RewardedAmount)
         VALUES (?, NULL, ?, 'Section', CURDATE(), ?)"
    );
    $stmt->execute([$username, $sectionId, $reward]);
    if ($stmt->rowCount() === 0) return 0;

    $pdo->prepare("UPDATE AccountStats SET TotalXP = TotalXP + ? WHERE accounts_username = ?")
        ->execute([$reward, $username]);
    return $reward;
}

/**
 * Award page XP — only if every paid section in the page is already awarded.
 * Always marks accounts_opened_pages.Completed = 1 once that condition holds,
 * regardless of whether the page itself has XP > 0 (so the sidebar check
 * appears even for 0-XP pages).
 *
 * Returns the page XP amount awarded (0 if not yet complete, already done,
 * or page XP = 0).
 */
function award_page(PDO $pdo, string $username, int $pageId): int {
    // Count paid sections in the page vs how many are already awarded.
    // Positional placeholders — native PDO prepares treat each :name as a
    // distinct slot, so we can't reuse :pid twice in one statement.
    $stmt = $pdo->prepare(
        "SELECT
            (SELECT COUNT(*) FROM sections WHERE Pages_Id = ? AND XPReward > 0)
          - (SELECT COUNT(*) FROM UserXPLog
             WHERE accounts_username = ? AND Source = 'Section'
               AND sections_Id IN (SELECT Id FROM sections WHERE Pages_Id = ? AND XPReward > 0))
         AS missing"
    );
    $stmt->execute([$pageId, $username, $pageId]);
    if ((int)$stmt->fetchColumn() > 0) return 0;  // not all paid sections done

    // All paid sections are done — mark the page completed regardless of page XP.
    $pdo->prepare(
        "UPDATE accounts_opened_pages SET Completed = 1
         WHERE Accounts_username = ? AND Pages_Id = ?"
    )->execute([$username, $pageId]);

    // Award page XP if there is any.
    $stmt = $pdo->prepare("SELECT XPReward FROM pages WHERE Id = ?");
    $stmt->execute([$pageId]);
    $reward = (int)$stmt->fetchColumn();
    if ($reward === 0) return 0;

    $stmt = $pdo->prepare(
        "INSERT IGNORE INTO UserXPLog
            (accounts_username, pages_Id, sections_Id, Source, AwardedOn, RewardedAmount)
         VALUES (?, ?, NULL, 'Page', CURDATE(), ?)"
    );
    $stmt->execute([$username, $pageId, $reward]);
    if ($stmt->rowCount() === 0) return 0;

    $pdo->prepare("UPDATE AccountStats SET TotalXP = TotalXP + ? WHERE accounts_username = ?")
        ->execute([$reward, $username]);
    return $reward;
}

$pdo->beginTransaction();
try {
    $sectionAward = null;
    $pageAward    = null;

    if ($section_id) {
        // Find the page this section belongs to so we can attempt the cascade.
        $stmt = $pdo->prepare("SELECT Pages_Id FROM sections WHERE Id = ?");
        $stmt->execute([$section_id]);
        $parentPageId = (int)$stmt->fetchColumn();

        $amount = award_section($pdo, $username, $section_id);
        if ($amount > 0) $sectionAward = ['xp' => $amount, 'section_id' => $section_id];

        // Cascade — always try; award_page itself enforces the completion rule.
        if ($parentPageId && !$page_id) $page_id = $parentPageId;
    }

    if ($page_id) {
        $amount = award_page($pdo, $username, $page_id);
        if ($amount > 0) $pageAward = ['xp' => $amount, 'page_id' => $page_id];
    }

    // Recompute level from the curve so AccountStats.Level stays in sync.
    $stmt = $pdo->prepare("SELECT TotalXP FROM AccountStats WHERE accounts_username = ?");
    $stmt->execute([$username]);
    $totalXP = (int)($stmt->fetchColumn() ?: 0);
    $newLevel = xp_level_from_total($totalXP);
    $pdo->prepare("UPDATE AccountStats SET Level = ? WHERE accounts_username = ?")
        ->execute([$newLevel, $username]);

    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['error' => 'Award failed']);
    exit;
}

echo json_encode([
    'section_award' => $sectionAward,
    'page_award'    => $pageAward,
    'total_xp'      => $totalXP,
    'level'         => $newLevel,
]);

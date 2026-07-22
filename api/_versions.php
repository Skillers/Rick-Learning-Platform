<?php
/**
 * _versions.php — shared helpers for the page-versioning model.
 *
 * Data model (see CreateScript):
 *   pages                       stable logical page (never changes id)
 *   PageVersion                 one row per concept / live / archived snapshot
 *   PageVersion_has_sections    which sections make up a version (+ Order, XPReward)
 *   sections                    reusable content, shared across versions
 *   sections_has_components     which components make up a section (+ Order)
 *   components                  reusable content, shared across sections
 *
 * Invariant enforced by the app: at most ONE 'live' and ONE 'concept'
 * PageVersion per page at any time. Archived versions are immutable.
 */

/**
 * Resolve a page's version id for a given status.
 * @return int|null  the PageVersion.Id, or null if the page has no such version.
 */
function version_for_page(PDO $pdo, int $pageId, string $status): ?int {
    $st = $pdo->prepare(
        "SELECT `Id` FROM `PageVersion`
         WHERE `pages_Id` = ? AND `Status` = ?
         ORDER BY `VersionNo` DESC LIMIT 1"
    );
    $st->execute([$pageId, $status]);
    $id = $st->fetchColumn();
    return $id === false ? null : (int)$id;
}

/**
 * Pick which version a read request should serve.
 *
 * Priority:
 *   1. explicit ?version_id=N        (admin loading a specific snapshot)
 *   2. explicit ?status=concept|live|archived (admin loading "the concept", etc.)
 *   3. ?username=X finished-test pin (student who completed a test → their version)
 *   4. the page's live version       (default — what students see)
 *
 * @return int|null  PageVersion.Id, or null if nothing matches.
 */
function resolve_requested_version(PDO $pdo, int $pageId): ?int {
    $explicit = isset($_GET['version_id']) ? (int)$_GET['version_id'] : 0;
    if ($explicit) {
        // Trust but verify it belongs to this page.
        $st = $pdo->prepare("SELECT `Id` FROM `PageVersion` WHERE `Id` = ? AND `pages_Id` = ?");
        $st->execute([$explicit, $pageId]);
        $id = $st->fetchColumn();
        return $id === false ? null : (int)$id;
    }
    // An admin explicitly asking for a status wins over any pin.
    if (isset($_GET['status'])) {
        $status = strtolower(trim((string)$_GET['status']));
        if (!in_array($status, ['concept', 'live', 'archived'], true)) $status = 'live';
        return version_for_page($pdo, $pageId, $status);
    }
    // A student who finished a test on this page is frozen on the version they did.
    $username = trim((string)($_GET['username'] ?? ''));
    if ($username !== '') {
        $pin = finished_test_version($pdo, $pageId, $username);
        if ($pin !== null) return $pin;
    }
    return version_for_page($pdo, $pageId, 'live');
}

/**
 * The version a student is pinned to for a page because they completed the test,
 * or null if they haven't finished it (so they follow the live version).
 */
function finished_test_version(PDO $pdo, int $pageId, string $username): ?int {
    $st = $pdo->prepare("SELECT `PageVersion_Id` FROM `FinishedTests` WHERE `accounts_username` = ? AND `pages_Id` = ?");
    $st->execute([$username, $pageId]);
    $id = $st->fetchColumn();
    return $id === false ? null : (int)$id;
}

<?php
/**
 * page_versions.php — list every version (concept / live / archived) of a page,
 * for the editor's version cycler.
 *
 * Input (GET): ?page_id=int
 * Output: [ { id, version_no, status, title, section_count,
 *            created_at, published_at, archived_at } ]  ordered newest first
 */
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/db.connection.php';

$pageId = isset($_GET['page_id']) ? (int)$_GET['page_id'] : 0;
if (!$pageId) { echo '[]'; exit; }

$stmt = $pdo->prepare("
    SELECT
        v.`Id`          AS `id`,
        v.`VersionNo`   AS `version_no`,
        v.`Status`      AS `status`,
        v.`Title`       AS `title`,
        pt.`Name`       AS `type`,
        v.`XpReward`    AS `xp_reward`,
        v.`NTerm`       AS `n_term`,
        v.`CreatedAt`   AS `created_at`,
        v.`PublishedAt` AS `published_at`,
        v.`ArchivedAt`  AS `archived_at`,
        (SELECT COUNT(*) FROM `PageVersion_has_sections` x WHERE x.`PageVersion_Id` = v.`Id`) AS `section_count`
    FROM `PageVersion` v
    JOIN `pages` p ON p.`Id` = v.`pages_Id`
    LEFT JOIN `pagetypes` pt ON pt.`Id` = p.`PageType_Id`
    WHERE v.`pages_Id` = ?
    ORDER BY v.`VersionNo` DESC
");
$stmt->execute([$pageId]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($rows as &$r) {
    $r['id']            = (int)$r['id'];
    $r['version_no']    = (int)$r['version_no'];
    $r['xp_reward']     = (int)$r['xp_reward'];
    $r['n_term']        = $r['n_term'] !== null ? (float)$r['n_term'] : 1.0;
    $r['section_count'] = (int)$r['section_count'];
}
unset($r);

echo json_encode($rows, JSON_UNESCAPED_UNICODE);

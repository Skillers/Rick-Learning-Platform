<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/db.connection.php';

// The admin editor needs to see pages that only have a concept version too;
// the student app calls this without the flag and sees live pages only.
$includeUnpublished = !empty($_GET['include_unpublished']);

// A page is "published" when it has a live version. Version-scoped metadata
// (title, type, xp, duration) is read from the live version, falling back to
// the concept version for never-published drafts.
$studentFilter = $includeUnpublished ? '' :
    "WHERE lv.`Id` IS NOT NULL
       AND p.`Published` = 1
       AND EXISTS (SELECT 1 FROM `PageVersion_has_sections` pvs
                   WHERE pvs.`PageVersion_Id` = lv.`Id`)";

$rows = $pdo->query("
    SELECT
        p.`Id`                                       AS `id`,
        p.`Course_Id`                                AS `course_id`,
        COALESCE(lv.`Title`, cv.`Title`)             AS `title`,
        pt.`Name`                                    AS `type`,
        COALESCE(lv.`XpReward`, cv.`XpReward`, 0)    AS `xp_reward`,
        (lv.`Id` IS NOT NULL)                        AS `published`,
        p.`Published`                                AS `enabled`,
        COALESCE(lv.`EstimatedDuration`, cv.`EstimatedDuration`, 10) AS `estimated_duration`,
        lv.`Id`                                      AS `live_version_id`,
        cv.`Id`                                      AS `concept_version_id`
    FROM `pages` p
    LEFT JOIN `PageVersion` lv ON lv.`pages_Id` = p.`Id` AND lv.`Status` = 'live'
    LEFT JOIN `PageVersion` cv ON cv.`pages_Id` = p.`Id` AND cv.`Status` = 'concept'
    LEFT JOIN `pagetypes`  pt ON pt.`Id` = p.`PageType_Id`
    $studentFilter
    ORDER BY p.`Course_Id`, p.`order`
")->fetchAll();

// Normalise types the frontend expects (ints, not numeric strings).
foreach ($rows as &$r) {
    $r['published']          = (int)$r['published'];
    $r['enabled']            = (int)$r['enabled'];
    $r['live_version_id']    = $r['live_version_id']    !== null ? (int)$r['live_version_id']    : null;
    $r['concept_version_id'] = $r['concept_version_id'] !== null ? (int)$r['concept_version_id'] : null;
}
unset($r);

echo json_encode($rows, JSON_UNESCAPED_UNICODE);

<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/db.connection.php';

// The admin editor needs to see draft (unpublished) pages too; the student app
// calls this without the flag and keeps the published-only behaviour.
$includeUnpublished = !empty($_GET['include_unpublished']);
$publishedFilter = $includeUnpublished ? '' : 'WHERE p.`published` = 1
      AND EXISTS (SELECT 1 FROM `Sections` s WHERE s.`Pages_Id` = p.`Id`)';

$rows = $pdo->query("
    SELECT
        p.`Id`                AS `id`,
        p.`Course_Id`         AS `course_id`,
        p.`title`,
        pt.`Name`             AS `type`,
        p.`XPReward`          AS `xp_reward`,
        p.`published`         AS `published`,
        p.`EstimatedDuration` AS `estimated_duration`
    FROM `Pages` p
    LEFT JOIN `PageTypes` pt ON p.`PageType_Id` = pt.`Id`
    $publishedFilter
    ORDER BY p.`Course_Id`, p.`order`
")->fetchAll();

echo json_encode($rows, JSON_UNESCAPED_UNICODE);

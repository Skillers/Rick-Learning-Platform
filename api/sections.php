<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/db.connection.php';
require_once __DIR__ . '/_versions.php';

// Order + XPReward now live on PageVersion_has_sections (per version), and a
// section's components are reached through sections_has_components.
//
//   - default: the live version of every page (what the sidebar shows)
//   - ?page_id=N (+ optional version_id / status): that one page's chosen
//     version, so the admin editor can list a concept's sections.

$pageId = isset($_GET['page_id']) ? (int)$_GET['page_id'] : 0;

$hasInteraction = "EXISTS (
        SELECT 1 FROM `sections_has_components` shc
        JOIN `components` c ON c.`Id` = shc.`components_Id`
        WHERE shc.`sections_Id` = s.`Id`
          AND c.`ComponentType_ComponentTypeText` = 'quiz'
    )";

if ($pageId) {
    $versionId = resolve_requested_version($pdo, $pageId);
    if (!$versionId) { echo '[]'; exit; }

    $stmt = $pdo->prepare("
        SELECT
            s.`Id`        AS `id`,
            $pageId       AS `page_id`,
            s.`Title`     AS `title`,
            pvs.`Order`   AS `order`,
            pvs.`XPReward` AS `xp_reward`,
            $hasInteraction AS `has_interaction`
        FROM `PageVersion_has_sections` pvs
        JOIN `sections` s ON s.`Id` = pvs.`sections_Id`
        WHERE pvs.`PageVersion_Id` = ?
        ORDER BY pvs.`Order`
    ");
    $stmt->execute([$versionId]);
    echo json_encode($stmt->fetchAll(), JSON_UNESCAPED_UNICODE);
    exit;
}

// All pages, live version only.
$rows = $pdo->query("
    SELECT
        s.`Id`         AS `id`,
        pv.`pages_Id`  AS `page_id`,
        s.`Title`      AS `title`,
        pvs.`Order`    AS `order`,
        pvs.`XPReward` AS `xp_reward`,
        $hasInteraction AS `has_interaction`
    FROM `PageVersion` pv
    JOIN `PageVersion_has_sections` pvs ON pvs.`PageVersion_Id` = pv.`Id`
    JOIN `sections` s ON s.`Id` = pvs.`sections_Id`
    WHERE pv.`Status` = 'live'
    ORDER BY pv.`pages_Id`, pvs.`Order`
")->fetchAll();

echo json_encode($rows, JSON_UNESCAPED_UNICODE);

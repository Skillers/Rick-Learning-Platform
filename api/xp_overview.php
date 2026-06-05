<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/db.connection.php';

$username  = trim($_GET['username']  ?? '');
$onlyOpen  = !empty($_GET['only_open']);

if (!$username) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing username']);
    exit;
}

// One row per published page, with page XP, summed section XP, and the
// user's earned-XP total against both.
$stmt = $pdo->prepare("
    SELECT
        p.`Id`        AS page_id,
        p.`title`     AS page_title,
        p.`Course_Id` AS course_id,
        c.`Name`      AS course_name,
        c.`Icon`      AS course_icon,
        c.`Color`     AS course_color,
        p.`XPReward`  AS page_xp,
        COALESCE((SELECT SUM(s.`XPReward`)
                  FROM `sections` s
                  WHERE s.`Pages_Id` = p.`Id`), 0) AS sections_xp,
        COALESCE((SELECT SUM(`RewardedAmount`)
                  FROM `UserXPLog`
                  WHERE `accounts_username` = :u
                    AND (`pages_Id` = p.`Id`
                         OR `sections_Id` IN (SELECT `Id` FROM `sections` WHERE `Pages_Id` = p.`Id`))), 0) AS earned_xp
    FROM `Pages` p
    JOIN `courses` c ON c.`Id` = p.`Course_Id`
    WHERE p.`published` = 1
      AND EXISTS (SELECT 1 FROM `sections` s WHERE s.`Pages_Id` = p.`Id`)
    ORDER BY c.`Name`, p.`order`
");
$stmt->execute([':u' => $username]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$out = [];
$summary = ['earned' => 0, 'available' => 0, 'open' => 0];
foreach ($rows as $r) {
    $pageXP     = (int)$r['page_xp'];
    $sectionsXP = (int)$r['sections_xp'];
    $totalXP    = $pageXP + $sectionsXP;
    $earned     = (int)$r['earned_xp'];
    $open       = max(0, $totalXP - $earned);
    $hasOpen    = $open > 0;

    $summary['earned']    += $earned;
    $summary['available'] += $totalXP;
    $summary['open']      += $open;

    if ($onlyOpen && !$hasOpen) continue;
    if ($totalXP === 0)         continue;  // hide unconfigured pages

    $out[] = [
        'page_id'      => (int)$r['page_id'],
        'page_title'   => $r['page_title'],
        'course_id'    => (int)$r['course_id'],
        'course_name'  => $r['course_name'],
        'course_icon'  => $r['course_icon'],
        'course_color' => $r['course_color'],
        'page_xp'      => $pageXP,
        'sections_xp'  => $sectionsXP,
        'total_xp'     => $totalXP,
        'earned_xp'    => $earned,
        'open_xp'      => $open,
        'has_open'     => $hasOpen,
    ];
}

echo json_encode([
    'summary' => $summary,
    'rows'    => $out,
], JSON_UNESCAPED_UNICODE);

<?php
/**
 * page_types.php — the page types the editor can choose from.
 * Output: [ { id:int, name:string, color:string }, … ] ordered by Id.
 *
 * Source of truth is the `PageTypes` lookup table, so the editor dropdowns stay
 * in sync with the DB instead of a hardcoded list.
 */
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/db.connection.php';

$rows = $pdo->query("
    SELECT `Id` AS `id`, `Name` AS `name`, `Color` AS `color`
    FROM `PageTypes`
    ORDER BY `Id`
")->fetchAll();

foreach ($rows as &$r) { $r['id'] = (int)$r['id']; }
unset($r);

echo json_encode($rows, JSON_UNESCAPED_UNICODE);

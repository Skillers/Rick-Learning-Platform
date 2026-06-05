<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/db.connection.php';

$username = trim($_GET['username'] ?? '');
$page_id  = (int)($_GET['page_id'] ?? 0);

if (!$username || !$page_id) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing fields']);
    exit;
}

// IDs of sections in this page that the user has been awarded XP for.
// Source of truth = UserXPLog (skips 0-XP sections by design — those stay
// unmarked until the teacher raises the XP and the student re-earns it).
$stmt = $pdo->prepare(
    "SELECT u.`sections_Id` AS section_id
     FROM `UserXPLog` u
     JOIN `sections` s ON s.`Id` = u.`sections_Id`
     WHERE u.`accounts_username` = ?
       AND u.`Source` = 'Section'
       AND s.`Pages_Id` = ?"
);
$stmt->execute([$username, $page_id]);
$ids = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));

echo json_encode($ids);

<?php
/**
 * save_subject.php — create a new subject (vak) from the admin Lesontwerper.
 *
 * Input (JSON POST): { name: string }
 * Output: { ok:true, subject: { id, name } }
 */
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/db.connection.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$in = json_decode(file_get_contents('php://input'), true);
$name = is_array($in) ? trim((string)($in['name'] ?? '')) : '';

if ($name === '') {
    http_response_code(400);
    echo json_encode(['error' => 'name is required']);
    exit;
}

try {
    // Avoid duplicates (case-insensitive).
    $dup = $pdo->prepare("SELECT id FROM Subjects WHERE LOWER(Name) = LOWER(?)");
    $dup->execute([$name]);
    $existing = $dup->fetchColumn();
    if ($existing) {
        echo json_encode(['ok' => true, 'subject' => ['id' => (int)$existing, 'name' => $name], 'existed' => true]);
        exit;
    }

    $pdo->prepare("INSERT INTO Subjects (Name) VALUES (?)")->execute([mb_substr($name, 0, 45)]);
    $id = (int)$pdo->lastInsertId();

    echo json_encode(['ok' => true, 'subject' => ['id' => $id, 'name' => $name]], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

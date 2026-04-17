<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/db.connection.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $docent   = $data['docent_username'] ?? '';
    $courseId  = $data['course_id']      ?? 0;

    if (!$docent || !$courseId) {
        http_response_code(400);
        echo json_encode(['error' => 'docent_username and course_id required']);
        exit;
    }

    $stmt = $pdo->prepare("
        INSERT INTO `Teacher_ParticipatesIn_Course` (`courses_Id`, `accounts_username`)
        VALUES (:course_id, :docent)
    ");
    $stmt->execute(['course_id' => $courseId, 'docent' => $docent]);
    echo json_encode(['success' => true]);

} elseif ($method === 'DELETE') {
    $data = json_decode(file_get_contents('php://input'), true);
    $docent   = $data['docent_username'] ?? '';
    $courseId  = $data['course_id']      ?? 0;

    if (!$docent || !$courseId) {
        http_response_code(400);
        echo json_encode(['error' => 'docent_username and course_id required']);
        exit;
    }

    $stmt = $pdo->prepare("
        DELETE FROM `Teacher_ParticipatesIn_Course`
        WHERE `accounts_username` = :docent AND `courses_Id` = :course_id
    ");
    $stmt->execute(['docent' => $docent, 'course_id' => $courseId]);
    echo json_encode(['success' => true]);

} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
}

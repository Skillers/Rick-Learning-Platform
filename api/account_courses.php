<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/db.connection.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $username  = $data['username']  ?? '';
    $courseId  = $data['course_id'] ?? 0;

    if (!$username || !$courseId) {
        http_response_code(400);
        echo json_encode(['error' => 'username and course_id required']);
        exit;
    }

    $stmt = $pdo->prepare("
        INSERT INTO `accounts_has_courses` (`accounts_username`, `courses_Id`, `Enrolled_at`)
        VALUES (:username, :course_id, NOW())
    ");
    $stmt->execute(['username' => $username, 'course_id' => $courseId]);
    echo json_encode(['success' => true]);

} elseif ($method === 'DELETE') {
    $data = json_decode(file_get_contents('php://input'), true);
    $username  = $data['username']  ?? '';
    $courseId  = $data['course_id'] ?? 0;

    if (!$username || !$courseId) {
        http_response_code(400);
        echo json_encode(['error' => 'username and course_id required']);
        exit;
    }

    $stmt = $pdo->prepare("
        DELETE FROM `accounts_has_courses`
        WHERE `accounts_username` = :username AND `courses_Id` = :course_id
    ");
    $stmt->execute(['username' => $username, 'course_id' => $courseId]);
    echo json_encode(['success' => true]);

} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
}

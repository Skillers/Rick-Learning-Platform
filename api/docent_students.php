<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/db.connection.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $docent  = $data['docent_username']  ?? '';
    $student = $data['student_username'] ?? '';

    if (!$docent || !$student) {
        http_response_code(400);
        echo json_encode(['error' => 'docent_username and student_username required']);
        exit;
    }

    $stmt = $pdo->prepare("
        INSERT INTO `Teacher_guides_Student` (`accounts_Student`, `accounts_Teacher`)
        VALUES (:student, :docent)
    ");
    $stmt->execute(['student' => $student, 'docent' => $docent]);
    echo json_encode(['success' => true]);

} elseif ($method === 'DELETE') {
    $data = json_decode(file_get_contents('php://input'), true);
    $docent  = $data['docent_username']  ?? '';
    $student = $data['student_username'] ?? '';

    if (!$docent || !$student) {
        http_response_code(400);
        echo json_encode(['error' => 'docent_username and student_username required']);
        exit;
    }

    $stmt = $pdo->prepare("
        DELETE FROM `Teacher_guides_Student`
        WHERE `accounts_Teacher` = :docent AND `accounts_Student` = :student
    ");
    $stmt->execute(['docent' => $docent, 'student' => $student]);
    echo json_encode(['success' => true]);

} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
}

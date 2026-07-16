<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/db.connection.php';
require_once __DIR__ . '/../config/course_perms.php';

// Always scoped by caller. ?username= is required; the list is filtered by role:
//   Superadmin → all subjects
//   Teacher    → subjects they have a course in OR have joined (Teacher_has_Subject)
//                OR that hold a course they're enrolled in as a student
//   Student    → subjects that hold a course they're enrolled in
$username = isset($_GET['username']) ? trim($_GET['username']) : '';

if ($username === '') {
    http_response_code(400);
    echo json_encode(['error' => 'username required']);
    exit;
}

$role = account_role($pdo, $username);

if ($role === null) {
    http_response_code(404);
    echo json_encode(['error' => 'Account niet gevonden']);
    exit;
}

if ($role === 'Superadmin') {
    $rows = $pdo->query("
        SELECT `id`, `Name` AS `name`
        FROM `Subjects`
        ORDER BY `id`
    ")->fetchAll();
} elseif ($role === 'Teacher') {
    $stmt = $pdo->prepare("
        SELECT `id`, `Name` AS `name`
        FROM `Subjects` s
        WHERE s.`id` IN (
            SELECT c.`Subject_Id`
            FROM `Courses` c
            JOIN `Teacher_ParticipatesIn_Course` t ON t.`courses_Id` = c.`Id`
            WHERE t.`accounts_username` = :u
        )
        OR s.`id` IN (
            SELECT `subjects_id`
            FROM `Teacher_has_Subjects`
            WHERE `accounts_username` = :u2
        )
        OR s.`id` IN (
            SELECT c.`Subject_Id`
            FROM `Courses` c
            JOIN `Student_Has_Course` e ON e.`courses_Id` = c.`Id`
            WHERE e.`accounts_username` = :u3
        )
        ORDER BY s.`id`
    ");
    $stmt->execute(['u' => $username, 'u2' => $username, 'u3' => $username]);
    $rows = $stmt->fetchAll();
} else {
    // Student (or unknown role) — only subjects with an enrolled course.
    $stmt = $pdo->prepare("
        SELECT DISTINCT s.`id`, s.`Name` AS `name`
        FROM `Subjects` s
        JOIN `Courses` c            ON c.`Subject_Id` = s.`id`
        JOIN `Student_Has_Course` e ON e.`courses_Id` = c.`Id`
        WHERE e.`accounts_username` = :u
        ORDER BY s.`id`
    ");
    $stmt->execute(['u' => $username]);
    $rows = $stmt->fetchAll();
}

echo json_encode($rows, JSON_UNESCAPED_UNICODE);

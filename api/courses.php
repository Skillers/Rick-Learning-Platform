<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/db.connection.php';
require_once __DIR__ . '/../config/course_perms.php';

// Always scoped by caller. ?username= is required; the list is filtered by role:
//   Superadmin → all courses
//   Teacher    → courses they participate in (Teacher_ParticipatesIn_Course)
//                OR are enrolled in as a student (Student_Has_Course)
//   Student    → courses they're enrolled in (Student_Has_Course)
$username = isset($_GET['username']) ? trim($_GET['username']) : '';

if ($username === '') {
    http_response_code(400);
    echo json_encode(['error' => 'username required']);
    exit;
}

$select = "
    SELECT
        c.`Id`         AS `id`,
        c.`Name`       AS `name`,
        c.`Icon`       AS `icon`,
        c.`Color`      AS `color`,
        c.`Subject_Id` AS `subject_id`,
        s.`Name`       AS `section`
    FROM `Courses` c
    JOIN `Subjects` s ON c.`Subject_Id` = s.`id`
";

$role = account_role($pdo, $username);

if ($role === null) {
    http_response_code(404);
    echo json_encode(['error' => 'Account niet gevonden']);
    exit;
}

if ($role === 'Superadmin') {
    $rows = $pdo->query($select . " ORDER BY c.`Id`")->fetchAll();
} elseif ($role === 'Teacher') {
    // A teacher sees courses they participate in (Owner/Grader/Editor) AND any
    // course they're enrolled in as a student.
    $stmt = $pdo->prepare($select . "
        WHERE c.`Id` IN (
            SELECT `courses_Id` FROM `Teacher_ParticipatesIn_Course`
            WHERE `accounts_username` = :u1
        )
        OR c.`Id` IN (
            SELECT `courses_Id` FROM `Student_Has_Course`
            WHERE `accounts_username` = :u2
        )
        ORDER BY c.`Id`
    ");
    $stmt->execute(['u1' => $username, 'u2' => $username]);
    $rows = $stmt->fetchAll();
} else {
    // Student (or unknown role) — only enrolled courses.
    $stmt = $pdo->prepare($select . "
        WHERE c.`Id` IN (
            SELECT `courses_Id` FROM `Student_Has_Course`
            WHERE `accounts_username` = :u
        )
        ORDER BY c.`Id`
    ");
    $stmt->execute(['u' => $username]);
    $rows = $stmt->fetchAll();
}

echo json_encode($rows, JSON_UNESCAPED_UNICODE);

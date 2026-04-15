<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/db.connection.php';

// Get all accounts with their enrolled courses
$accounts = $pdo->query("
    SELECT
        a.`username`,
        a.`Email`      AS `email`,
        a.`Role`       AS `role`,
        a.`CreatedAt`  AS `created_at`,
        a.`Active`     AS `active`
    FROM `accounts` a
    ORDER BY a.`Role` DESC, a.`username`
")->fetchAll();

// Get all enrollments
$enrollments = $pdo->query("
    SELECT
        ahc.`accounts_username` AS `username`,
        ahc.`courses_Id`        AS `course_id`,
        ahc.`Enrolled_at`       AS `enrolled_at`,
        c.`Name`                AS `course_name`,
        c.`Icon`                AS `course_icon`,
        c.`Color`               AS `course_color`
    FROM `accounts_has_courses` ahc
    JOIN `courses` c ON ahc.`courses_Id` = c.`Id`
    ORDER BY ahc.`Enrolled_at`
")->fetchAll();

// Get progress per student: completed pages per course
$progress = $pdo->query("
    SELECT
        aop.`Accounts_username`  AS `username`,
        p.`Course_Id`            AS `course_id`,
        COUNT(*)                 AS `opened`,
        SUM(aop.`Completed`)     AS `completed`
    FROM `accounts_opened_pages` aop
    JOIN `pages` p ON aop.`Pages_Id` = p.`Id`
    GROUP BY aop.`Accounts_username`, p.`Course_Id`
")->fetchAll();

// Total published pages per course
$totals = $pdo->query("
    SELECT `Course_Id` AS `course_id`, COUNT(*) AS `total`
    FROM `pages`
    WHERE `published` = 1
    GROUP BY `Course_Id`
")->fetchAll();

echo json_encode([
    'accounts'    => $accounts,
    'enrollments' => $enrollments,
    'progress'    => $progress,
    'totals'      => $totals
], JSON_UNESCAPED_UNICODE);

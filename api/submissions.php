<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/db.connection.php';

$rows = $pdo->query("
    SELECT
        aha.`account_username`     AS `username`,
        aha.`Assigment_Id`         AS `assignment_id`,
        aha.`SubmittedTextAnswer`  AS `text_answer`,
        aha.`FileName`             AS `file_name`,
        aha.`FilePath`             AS `file_path`,
        aha.`SubmittedOn`          AS `submitted_on`,
        a.`Title`                  AS `assignment_title`,
        a.`FileRequired`            AS `file_required`,
        p.`Id`                     AS `page_id`,
        pv.`Title`                 AS `page_title`,
        c.`Id`                     AS `course_id`,
        c.`Name`                   AS `course_name`,
        c.`Icon`                   AS `course_icon`,
        c.`Color`                  AS `course_color`
    FROM `Accounts_have_assignments` aha
    JOIN `Assigments` a   ON aha.`Assigment_Id` = a.`Id`
    JOIN `components` cp  ON a.`component_Id`   = cp.`Id`
    JOIN `sections_has_components` shc ON shc.`components_Id` = cp.`Id`
    JOIN `PageVersion_has_sections` pvs ON pvs.`sections_Id` = shc.`sections_Id`
    JOIN `PageVersion` pv ON pv.`Id` = pvs.`PageVersion_Id` AND pv.`Status` = 'live'
    JOIN `pages` p        ON p.`Id` = pv.`pages_Id`
    JOIN `courses` c      ON p.`Course_Id`      = c.`Id`
    WHERE aha.`SubmittedOn` IS NOT NULL
    ORDER BY aha.`SubmittedOn` DESC
")->fetchAll();

echo json_encode($rows, JSON_UNESCAPED_UNICODE);

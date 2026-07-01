<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/db.connection.php';

// Open-question answers awaiting review (or already reviewed). Mirrors the
// scope joins of submissions.php, resolved through the *live* page version, so
// the teacher UI can grade open questions alongside assignment submissions.
$rows = $pdo->query("
    SELECT
        dq.`Id`               AS `did_question_id`,
        dq.`accounts_username` AS `username`,
        dq.`OpenAnswer`       AS `open_answer`,
        dq.`AttemptDate`      AS `submitted_on`,
        dq.`Verdict`          AS `verdict`,
        dq.`ReviewFeedback`   AS `feedback`,
        dq.`ReviewedBy`       AS `graded_by`,
        dq.`ReviewedAt`       AS `graded_on`,
        q.`Id`                AS `question_id`,
        q.`Question`          AS `question_text`,
        q.`ExpectedResult`    AS `expected_result`,
        p.`Id`                AS `page_id`,
        pv.`Title`            AS `page_title`,
        c.`Id`                AS `course_id`,
        c.`Name`              AS `course_name`,
        c.`Icon`              AS `course_icon`,
        c.`Color`             AS `course_color`
    FROM `AC_Did_Question` dq
    JOIN `PQQuestion` q                 ON q.`Id` = dq.`PQQuestion_Id` AND q.`OpenQuestion` = 1
    JOIN `components` cp                ON q.`component_Id`   = cp.`Id`
    JOIN `sections_has_components` shc  ON shc.`components_Id` = cp.`Id`
    JOIN `PageVersion_has_sections` pvs ON pvs.`sections_Id`  = shc.`sections_Id`
    JOIN `PageVersion` pv               ON pv.`Id` = pvs.`PageVersion_Id` AND pv.`Status` = 'live'
    JOIN `pages` p                      ON p.`Id` = pv.`pages_Id`
    JOIN `courses` c                    ON p.`Course_Id` = c.`Id`
    WHERE dq.`QuestionContext_ContextType` = 'section'
    ORDER BY dq.`AttemptDate` DESC
")->fetchAll();

echo json_encode($rows, JSON_UNESCAPED_UNICODE);

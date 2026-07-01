<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/db.connection.php';

$method = $_SERVER['REQUEST_METHOD'];

// Resolve who's asking: role decides To_grade visibility (superadmin = all).
function load_actor(PDO $pdo, string $username): ?array {
    $stmt = $pdo->prepare("SELECT `Role` FROM `accounts` WHERE `username` = ?");
    $stmt->execute([$username]);
    $role = $stmt->fetchColumn();
    if ($role === false) return null;
    return ['role' => $role, 'is_super' => $role === 'Superadmin'];
}

// ── POST: mark notifications read ───────────────────────────────────────────
// Only the student's own 'Grade' notifications are markable from the bell;
// 'To_grade' clears when the answer is graded, not on a glance.
if ($method === 'POST') {
    $body     = json_decode(file_get_contents('php://input'), true) ?: [];
    $username = trim($body['username'] ?? '');
    $ids      = isset($body['ids']) && is_array($body['ids'])
              ? array_values(array_filter(array_map('intval', $body['ids'])))
              : [];
    if (!$username) {
        http_response_code(400);
        echo json_encode(['error' => 'username required']);
        exit;
    }
    $sql = "UPDATE `Notifications` SET `ReadAt_GradeAt` = NOW()
            WHERE `Type` = 'Grade' AND `Recipient` = ? AND `ReadAt_GradeAt` IS NULL";
    $params = [$username];
    if ($ids) {
        $sql .= " AND `Id` IN (" . implode(',', array_fill(0, count($ids), '?')) . ")";
        $params = array_merge($params, $ids);
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    echo json_encode(['success' => true, 'marked' => $stmt->rowCount()]);
    exit;
}

// ── GET: a user's inbox ─────────────────────────────────────────────────────
// Audience-derived: 'Grade' addressed to me; 'To_grade' for courses I teach
// (or all of them if I'm a superadmin). Display text is joined from the answer
// so it's never stale — graded To_grade rows carry the grader + verdict.
$username   = trim($_GET['username'] ?? '');
$onlyUnread = !empty($_GET['only_unread']);
$limit      = isset($_GET['limit']) ? max(1, min(100, (int)$_GET['limit'])) : 30;
if (!$username) {
    http_response_code(400);
    echo json_encode(['error' => 'username required']);
    exit;
}
$actor = load_actor($pdo, $username);
if (!$actor) {
    http_response_code(404);
    echo json_encode(['error' => 'Unknown user']);
    exit;
}

$sql = "
    SELECT
        n.`Id`                 AS id,
        n.`Type`               AS type,
        n.`AC_Did_Question_Id` AS did_question_id,
        n.`CreatedAt`          AS created_at,
        n.`ReadAt_GradeAt`     AS read_at,
        n.`courses_Id`         AS notif_course_id,
        dq.`accounts_username` AS student,
        dq.`OpenAnswer`        AS open_answer,
        dq.`Verdict`           AS verdict,
        dq.`ReviewFeedback`    AS feedback,
        dq.`ReviewedBy`        AS graded_by,
        q.`Id`                 AS question_id,
        q.`Question`           AS question_text,
        p.`Id`                 AS page_id,
        pv.`Title`             AS page_title,
        c.`Id`                 AS course_id,
        c.`Name`               AS course_name,
        c.`Icon`               AS course_icon,
        c.`Color`              AS course_color
    FROM `Notifications` n
    JOIN `AC_Did_Question` dq           ON dq.`Id` = n.`AC_Did_Question_Id`
    JOIN `PQQuestion` q                 ON q.`Id` = dq.`PQQuestion_Id`
    JOIN `components` cp                ON q.`component_Id`   = cp.`Id`
    JOIN `sections_has_components` shc  ON shc.`components_Id` = cp.`Id`
    JOIN `PageVersion_has_sections` pvs ON pvs.`sections_Id`  = shc.`sections_Id`
    JOIN `PageVersion` pv               ON pv.`Id` = pvs.`PageVersion_Id` AND pv.`Status` = 'live'
    JOIN `pages` p                      ON p.`Id` = pv.`pages_Id`
    JOIN `courses` c                    ON p.`Course_Id` = c.`Id`
    WHERE (
        (n.`Type` = 'Grade' AND n.`Recipient` = :me)
        OR
        (n.`Type` = 'To_grade' AND (:is_super = 1
            OR n.`courses_Id` IN (SELECT `courses_Id` FROM `Teacher_ParticipatesIn_Course` WHERE `accounts_username` = :me2)))
    )";
if ($onlyUnread) $sql .= " AND n.`ReadAt_GradeAt` IS NULL";
$sql .= " ORDER BY n.`CreatedAt` DESC, n.`Id` DESC LIMIT $limit";

$stmt = $pdo->prepare($sql);
$stmt->execute(['me' => $username, 'is_super' => $actor['is_super'] ? 1 : 0, 'me2' => $username]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Unread older than a month stays unread but no longer counts toward the badge.
$monthAgo = strtotime('-1 month');
$unread = 0;
foreach ($rows as $r) {
    if ($r['read_at'] === null && strtotime($r['created_at']) >= $monthAgo) $unread++;
}

echo json_encode(['unread' => $unread, 'notifications' => $rows], JSON_UNESCAPED_UNICODE);

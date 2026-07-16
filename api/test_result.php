<?php
/**
 * test_result.php — score + grade summary for a student on a Test (Toets) page.
 *
 * Resolves the page's LIVE version, then buckets every question's points into:
 *   - earned  : points already awarded (MC auto-scored at submit; open questions
 *               the teacher has graded — AC_Did_Question.PointsAwarded set)
 *   - pending : points tied up in submitted-but-ungraded questions (PointsAwarded NULL)
 *   - max     : Σ of every question's Points on the version
 * and computes two cijfers via config/grade_scale.php:
 *   - grade_current  = cijfer(earned, max)              (unanswered/ungraded score 0)
 *   - grade_possible = cijfer(earned + pending, max)    (best still achievable)
 *
 * GET params: username, page_id
 */
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/db.connection.php';
require_once __DIR__ . '/../config/grade_scale.php';

$username = trim($_GET['username'] ?? '');
$pageId   = (int)($_GET['page_id'] ?? 0);
if (!$username || !$pageId) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing fields']);
    exit;
}

// The live version carries the N-term used to grade this test.
$vStmt = $pdo->prepare("SELECT `Id`, `NTerm` FROM `PageVersion` WHERE `pages_Id` = ? AND `Status` = 'live' LIMIT 1");
$vStmt->execute([$pageId]);
$version = $vStmt->fetch(PDO::FETCH_ASSOC);
if (!$version) { echo json_encode(['error' => 'No live version']); exit; }
$versionId = (int)$version['Id'];
$nTerm     = (float)$version['NTerm'];

// Every question on the live version, with its max points.
$qStmt = $pdo->prepare("
    SELECT q.`Id` AS question_id, q.`PossiblePoints` AS points, q.`OpenQuestion` AS open_question
    FROM `PageVersion_has_sections` pvs
    JOIN `sections_has_components` shc ON shc.`sections_Id`  = pvs.`sections_Id`
    JOIN `components` c                ON c.`Id`             = shc.`components_Id`
    JOIN `PQQuestion` q                ON q.`component_Id`   = c.`Id`
    WHERE pvs.`PageVersion_Id` = ?");
$qStmt->execute([$versionId]);
$questions = $qStmt->fetchAll(PDO::FETCH_ASSOC);

// This student's attempts, indexed by question id.
$attempts = [];
if ($questions) {
    $ids = array_map(fn($q) => (int)$q['question_id'], $questions);
    $ph  = implode(',', array_fill(0, count($ids), '?'));
    $aStmt = $pdo->prepare("
        SELECT `PQQuestion_Id` AS qid, `PointsAwarded` AS awarded, `Verdict` AS verdict
        FROM `AC_Did_Question`
        WHERE `accounts_username` = ? AND `QuestionContext_ContextType` = 'section'
          AND `PQQuestion_Id` IN ($ph)");
    $aStmt->execute(array_merge([$username], $ids));
    foreach ($aStmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $attempts[(int)$r['qid']] = $r;   // one attempt per (student, question)
    }
}

$maxPoints = 0.0; $earned = 0.0; $pending = 0.0; $answered = 0;
$perQuestion = [];
foreach ($questions as $q) {
    $qid = (int)$q['question_id'];
    $pts = (float)$q['points'];
    $maxPoints += $pts;

    $status  = 'unanswered';
    $awarded = 0.0;
    if (isset($attempts[$qid])) {
        $answered++;
        $a = $attempts[$qid];
        if ($a['awarded'] !== null) {
            // Graded (MC always; open once the teacher scored it).
            $awarded = (float)$a['awarded'];
            $earned += $awarded;
            $status = $awarded >= $pts ? 'correct' : ($awarded <= 0 ? 'wrong' : 'partial');
        } else {
            // Submitted, still on a teacher's desk.
            $pending += $pts;
            $status = 'pending';
        }
    }
    $perQuestion[] = [
        'question_id' => $qid,
        'points'      => $pts,
        'awarded'     => isset($attempts[$qid]) && $attempts[$qid]['awarded'] !== null ? $awarded : null,
        'status'      => $status,
    ];
}

echo json_encode([
    'page_id'        => $pageId,
    'n_term'         => $nTerm,
    'pass_line'      => GRADE_PASS_LINE,
    'question_count' => count($questions),
    'answered_count' => $answered,
    'max_points'     => round($maxPoints, 2),
    'earned_points'  => round($earned, 2),
    'pending_points' => round($pending, 2),
    'grade_current'  => cijfer($earned, $maxPoints, $nTerm),
    'grade_possible' => cijfer($earned + $pending, $maxPoints, $nTerm),
    'per_question'   => $perQuestion,
], JSON_UNESCAPED_UNICODE);

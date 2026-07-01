<?php
// Course permission helpers — the single source of truth for who may do what.
// Superadmin (accounts.Role) overrides everything, regardless of course link.
// Course-level roles live in Teacher_ParticipatesIn_Course.Role:
//   Owner  → edit course, manage teachers, grade
//   Editor → edit course
//   Grader → grade

function account_role(PDO $pdo, string $username): ?string {
    $s = $pdo->prepare("SELECT `Role` FROM `accounts` WHERE `username` = ?");
    $s->execute([$username]);
    $r = $s->fetchColumn();
    return $r === false ? null : (string)$r;
}

function is_superadmin(PDO $pdo, string $username): bool {
    return account_role($pdo, $username) === 'Superadmin';
}

/** Course-link role ('Owner'|'Grader'|'Editor') or null if not linked. */
function course_role(PDO $pdo, string $username, int $courseId): ?string {
    $s = $pdo->prepare(
        "SELECT `Role` FROM `Teacher_ParticipatesIn_Course`
         WHERE `accounts_username` = ? AND `courses_Id` = ?");
    $s->execute([$username, $courseId]);
    $r = $s->fetchColumn();
    return $r === false ? null : (string)$r;
}

function can_edit_course(PDO $pdo, string $username, int $courseId): bool {
    if (is_superadmin($pdo, $username)) return true;
    return in_array(course_role($pdo, $username, $courseId), ['Owner', 'Editor'], true);
}

function can_grade_course(PDO $pdo, string $username, int $courseId): bool {
    if (is_superadmin($pdo, $username)) return true;
    return in_array(course_role($pdo, $username, $courseId), ['Owner', 'Grader'], true);
}

function can_manage_teachers(PDO $pdo, string $username, int $courseId): bool {
    if (is_superadmin($pdo, $username)) return true;
    return course_role($pdo, $username, $courseId) === 'Owner';
}

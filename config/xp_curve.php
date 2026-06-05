<?php
/**
 * XP curve helpers.
 * Cost to advance from level L to L+1: 500 * 1.05^(L-1).
 * Cumulative XP to reach level L from start: 10000 * (1.05^(L-1) - 1).
 */

function xp_to_reach_level(int $level): int {
    if ($level <= 1) return 0;
    return (int)round(10000 * (pow(1.05, $level - 1) - 1));
}

function xp_for_next_level(int $level): int {
    return (int)round(500 * pow(1.05, $level - 1));
}

function xp_level_from_total(int $totalXP): int {
    if ($totalXP <= 0) return 1;
    return (int)floor(log($totalXP / 10000 + 1) / log(1.05)) + 1;
}

function xp_progress(int $totalXP): array {
    $level   = xp_level_from_total($totalXP);
    $base    = xp_to_reach_level($level);
    $forNext = xp_for_next_level($level);
    $into    = max(0, $totalXP - $base);
    return [
        'level'      => $level,
        'into_level' => $into,
        'for_next'   => $forNext,
        'percent'    => $forNext > 0 ? (int)round($into / $forNext * 100) : 0,
    ];
}

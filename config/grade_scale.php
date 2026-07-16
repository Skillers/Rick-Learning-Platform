<?php
/**
 * Grade scale helpers for Test (Toets) pages — the Dutch 1–10 "cijfer".
 *
 * Linear normering with an adjustable N-term:
 *     cijfer = N + 9 * (score / max)
 * clamped to [1, 10] and rounded to one decimal. N defaults to 1.0 (the neutral
 * baseline: 0 points → 1.0, full marks → 10.0). A higher N-term lifts every grade
 * (a more lenient norm); a lower one is stricter. Pass line is 5.5.
 */

const GRADE_PASS_LINE = 5.5;

/**
 * Compute a cijfer from a raw score out of a maximum.
 * Returns null when there is nothing to grade (max <= 0), so callers can render
 * "geen cijfer" instead of a meaningless 1.0.
 */
function cijfer(float $score, float $max, float $nTerm = 1.0): ?float {
    if ($max <= 0) return null;
    $ratio = $score / $max;
    if ($ratio < 0) $ratio = 0.0;
    if ($ratio > 1) $ratio = 1.0;
    $grade = $nTerm + 9 * $ratio;
    if ($grade < 1)  $grade = 1.0;
    if ($grade > 10) $grade = 10.0;
    return round($grade, 1);
}

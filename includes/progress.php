<?php
declare(strict_types=1);

/** "18 / 20 (90%)" — percent rounded half up to a whole number. */
function format_grade(int $grade, int $maxPoints): string
{
    $percent = $maxPoints > 0 ? (int) round($grade / $maxPoints * 100) : 0;
    return "{$grade} / {$maxPoints} ({$percent}%)";
}

/** A submission is late when it arrived strictly after the due date. */
function is_late(string $submittedAt, string $dueDate): bool
{
    return strtotime($submittedAt) > strtotime($dueDate);
}

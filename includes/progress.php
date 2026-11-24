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

/** @return array{total: int, submitted: int, graded: int, percent: int} */
function student_course_progress(int $studentId, int $courseId): array
{
    $row = query(
        'SELECT COUNT(a.id) AS total,
                COUNT(s.id) AS submitted,
                COUNT(s.grade) AS graded
         FROM assignments a
         LEFT JOIN submissions s ON s.assignment_id = a.id AND s.student_id = ?
         WHERE a.course_id = ?',
        [$studentId, $courseId]
    )->fetch();

    $total = (int) $row['total'];
    $submitted = (int) $row['submitted'];

    return [
        'total' => $total,
        'submitted' => $submitted,
        'graded' => (int) $row['graded'],
        'percent' => $total > 0 ? (int) round($submitted / $total * 100) : 0,
    ];
}

/** Mean of grade / max_points across graded work, as a percentage; null when nothing is graded. */
function student_average_percent(int $studentId): ?float
{
    $average = query(
        'SELECT AVG(s.grade / a.max_points * 100)
         FROM submissions s JOIN assignments a ON a.id = s.assignment_id
         WHERE s.student_id = ? AND s.grade IS NOT NULL AND a.max_points > 0',
        [$studentId]
    )->fetchColumn();

    return $average === null ? null : (float) $average;
}

/** @return array{students: int, assignments: int, submissions: int, graded: int, submission_rate: int} */
function instructor_course_stats(int $courseId): array
{
    $row = query(
        'SELECT (SELECT COUNT(*) FROM enrollments WHERE course_id = :c1) AS students,
                (SELECT COUNT(*) FROM assignments WHERE course_id = :c2) AS assignments,
                COUNT(s.id) AS submissions,
                COUNT(s.grade) AS graded
         FROM submissions s JOIN assignments a ON a.id = s.assignment_id
         WHERE a.course_id = :c3',
        ['c1' => $courseId, 'c2' => $courseId, 'c3' => $courseId]
    )->fetch();

    $students = (int) $row['students'];
    $assignments = (int) $row['assignments'];
    $submissions = (int) $row['submissions'];
    $expected = $students * $assignments;

    return [
        'students' => $students,
        'assignments' => $assignments,
        'submissions' => $submissions,
        'graded' => (int) $row['graded'],
        'submission_rate' => $expected > 0 ? (int) round($submissions / $expected * 100) : 0,
    ];
}

<?php
declare(strict_types=1);

function owns_course(int $userId, int $courseId): bool
{
    return (bool) query('SELECT 1 FROM courses WHERE id = ? AND instructor_id = ?', [$courseId, $userId])->fetchColumn();
}

function is_enrolled(int $userId, int $courseId): bool
{
    return (bool) query('SELECT 1 FROM enrollments WHERE user_id = ? AND course_id = ?', [$userId, $courseId])->fetchColumn();
}

/** The course owner or an enrolled student. */
function can_view_course_content(array $user, int $courseId): bool
{
    return owns_course($user['id'], $courseId)
        || ($user['role'] === 'student' && is_enrolled($user['id'], $courseId));
}

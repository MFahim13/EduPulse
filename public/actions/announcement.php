<?php
require __DIR__ . '/../../includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('index.php');
}
csrf_verify();

$current = require_role('instructor');
$action = $_POST['action'] ?? '';

// ===== POST ANNOUNCEMENT =====
if ($action === 'create') {
    $course_id = (int) ($_POST['course_id'] ?? 0);
    $message = trim((string) ($_POST['message'] ?? ''));

    if (!owns_course($current['id'], $course_id)) {
        flash('error', 'You can only post announcements in your own courses.');
        redirect('instructor_dashboard.php');
    }

    $length = mb_strlen($message);
    if ($length < 1 || $length > 2000) {
        flash('error', 'Announcements must be between 1 and 2000 characters.');
        redirect('course_detail.php?id=' . $course_id);
    }

    query('INSERT INTO announcements (course_id, instructor_id, message) VALUES (?, ?, ?)', [$course_id, $current['id'], $message]);
    flash('success', 'Announcement posted.');
    redirect('course_detail.php?id=' . $course_id);
}

// ===== DELETE ANNOUNCEMENT =====
if ($action === 'delete') {
    $announcement = query(
        'SELECT a.id, a.course_id FROM announcements a JOIN courses c ON c.id = a.course_id WHERE a.id = ? AND c.instructor_id = ?',
        [(int) ($_POST['announcement_id'] ?? 0), $current['id']]
    )->fetch();

    if (!$announcement) {
        flash('error', 'Announcement not found.');
        redirect('instructor_dashboard.php');
    }

    query('DELETE FROM announcements WHERE id = ?', [$announcement['id']]);
    flash('success', 'Announcement deleted.');
    redirect('course_detail.php?id=' . $announcement['course_id']);
}

redirect('index.php');

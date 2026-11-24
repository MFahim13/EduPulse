<?php
require __DIR__ . '/../../includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('index.php');
}
csrf_verify();

$current = require_login();
$action = $_POST['action'] ?? '';

/** Parse a datetime-local value ("2026-05-01T14:30") into MySQL format, or null. */
function parse_due_date(string $value): ?string
{
    foreach (['Y-m-d\TH:i', 'Y-m-d\TH:i:s', 'Y-m-d H:i:s', 'Y-m-d H:i'] as $format) {
        $date = DateTimeImmutable::createFromFormat($format, $value);
        if ($date !== false) {
            return $date->format('Y-m-d H:i:s');
        }
    }
    return null;
}

// ===== CREATE ASSIGNMENT (Instructor Only) =====
if ($action === 'create') {
    $course_id = (int) ($_POST['course_id'] ?? 0);
    $title = trim((string) ($_POST['title'] ?? ''));
    $description = trim((string) ($_POST['description'] ?? ''));
    $due_date = parse_due_date(trim((string) ($_POST['due_date'] ?? '')));
    $max_points = (int) ($_POST['max_points'] ?? 0);

    if ($current['role'] !== 'instructor' || !owns_course($current['id'], $course_id)) {
        flash('error', "You don't have permission to create assignments for this course.");
        redirect('instructor_dashboard.php');
    }

    $errors = [];
    if ($title === '' || $description === '') {
        $errors[] = 'Title and instructions are required.';
    }
    if ($due_date === null) {
        $errors[] = 'Please choose a valid due date.';
    }
    if ($max_points < 1 || $max_points > 1000) {
        $errors[] = 'Max points must be between 1 and 1000.';
    }

    // Optional attachment, stored only once everything else is valid
    $file_path = null;
    $has_attachment = isset($_FILES['assignment_file']) && (int) $_FILES['assignment_file']['error'] !== UPLOAD_ERR_NO_FILE;
    if (!$errors && $has_attachment) {
        try {
            $file_path = store_upload($_FILES['assignment_file'], 'assignment')['key'];
        } catch (UploadError $e) {
            $errors[] = $e->getMessage();
        }
    }

    if ($errors) {
        flash('error', $errors);
        redirect('course_detail.php?id=' . $course_id);
    }

    query(
        'INSERT INTO assignments (course_id, title, description, due_date, max_points, file_path) VALUES (?, ?, ?, ?, ?, ?)',
        [$course_id, $title, $description, $due_date, $max_points, $file_path]
    );
    flash('success', 'Assignment created successfully!');
    redirect('course_detail.php?id=' . $course_id);
}

// ===== SUBMIT ASSIGNMENT (Student Only) =====
if ($action === 'submit') {
    $assignment_id = (int) ($_POST['assignment_id'] ?? 0);

    if ($current['role'] !== 'student') {
        flash('error', 'Only students can submit assignments.');
        redirect('assignment.php?id=' . $assignment_id);
    }

    $assignment = query('SELECT id, course_id FROM assignments WHERE id = ?', [$assignment_id])->fetch();
    if (!$assignment) {
        flash('error', 'Assignment not found.');
        redirect('student_dashboard.php');
    }

    if (!is_enrolled($current['id'], (int) $assignment['course_id'])) {
        flash('error', 'You need to be enrolled in this course to submit.');
        redirect('course_detail.php?id=' . $assignment['course_id']);
    }

    $existing = query(
        'SELECT id, file_path, grade FROM submissions WHERE assignment_id = ? AND student_id = ?',
        [$assignment_id, $current['id']]
    )->fetch();

    if ($existing && $existing['grade'] !== null) {
        flash('error', 'This submission has already been graded.');
        redirect('assignment.php?id=' . $assignment_id);
    }

    try {
        $stored = store_upload($_FILES['submission_file'] ?? ['error' => UPLOAD_ERR_NO_FILE], 'submission');
    } catch (UploadError $e) {
        flash('error', $e->getMessage());
        redirect('assignment.php?id=' . $assignment_id);
    }

    $now = date('Y-m-d H:i:s');
    if ($existing) {
        query('UPDATE submissions SET file_path = ?, submitted_at = ? WHERE id = ?', [$stored['key'], $now, $existing['id']]);
        delete_upload($existing['file_path']);
        flash('success', 'Your submission was updated.');
    } else {
        query(
            'INSERT INTO submissions (assignment_id, student_id, file_path, submitted_at) VALUES (?, ?, ?, ?)',
            [$assignment_id, $current['id'], $stored['key'], $now]
        );
        flash('success', 'Assignment submitted successfully!');
    }
    redirect('assignment.php?id=' . $assignment_id);
}

// ===== GRADE SUBMISSION (Instructor Only) =====
if ($action === 'grade') {
    $submission_id = (int) ($_POST['submission_id'] ?? 0);
    $assignment_id = (int) ($_POST['assignment_id'] ?? 0);
    $grade_input = trim((string) ($_POST['grade'] ?? ''));
    $feedback = trim((string) ($_POST['feedback'] ?? ''));

    // The grader must own the course AND the submission must belong to this assignment
    $assignment = query('SELECT course_id, max_points FROM assignments WHERE id = ?', [$assignment_id])->fetch();
    $can_grade = $assignment
        && owns_course($current['id'], (int) $assignment['course_id'])
        && find_submission_for_assignment($submission_id, $assignment_id) !== null;

    if (!$can_grade) {
        flash('error', "You don't have permission to grade this submission.");
        redirect('assignment.php?id=' . $assignment_id);
    }

    $max = (int) $assignment['max_points'];
    if (!preg_match('/^\d+$/', $grade_input) || (int) $grade_input > $max) {
        flash('error', "Grade must be between 0 and {$max}.");
        redirect('assignment.php?id=' . $assignment_id);
    }

    query(
        'UPDATE submissions SET grade = ?, feedback = ?, graded_at = ? WHERE id = ? AND assignment_id = ?',
        [(int) $grade_input, $feedback, date('Y-m-d H:i:s'), $submission_id, $assignment_id]
    );
    flash('success', 'Submission graded successfully!');
    redirect('assignment.php?id=' . $assignment_id);
}

// ===== EDIT ASSIGNMENT =====
if ($action === 'update') {
    $assignment = query(
        'SELECT a.* FROM assignments a JOIN courses c ON c.id = a.course_id WHERE a.id = ? AND c.instructor_id = ?',
        [(int) ($_POST['assignment_id'] ?? 0), $current['id']]
    )->fetch();
    if (!$assignment) {
        flash('error', "You don't have permission to modify this assignment.");
        redirect('index.php');
    }

    $title = trim((string) ($_POST['title'] ?? ''));
    $description = trim((string) ($_POST['description'] ?? ''));
    $due_date = parse_due_date(trim((string) ($_POST['due_date'] ?? '')));
    $max_points = (int) ($_POST['max_points'] ?? 0);
    $highest = (int) query('SELECT COALESCE(MAX(grade), 0) FROM submissions WHERE assignment_id = ?', [$assignment['id']])->fetchColumn();

    $errors = [];
    if ($title === '' || $description === '') {
        $errors[] = 'Title and instructions are required.';
    }
    if ($due_date === null) {
        $errors[] = 'Please choose a valid due date.';
    }
    if ($max_points < 1 || $max_points > 1000) {
        $errors[] = 'Max points must be between 1 and 1000.';
    } elseif ($max_points < $highest) {
        $errors[] = "Max points can't be lower than the highest grade already given ({$highest}).";
    }

    $file_path = $assignment['file_path'];
    $has_attachment = isset($_FILES['assignment_file']) && (int) $_FILES['assignment_file']['error'] !== UPLOAD_ERR_NO_FILE;
    if (!$errors && $has_attachment) {
        try {
            $file_path = store_upload($_FILES['assignment_file'], 'assignment')['key'];
        } catch (UploadError $e) {
            $errors[] = $e->getMessage();
        }
    }

    if ($errors) {
        flash('error', $errors);
        redirect('assignment.php?id=' . $assignment['id']);
    }

    query(
        'UPDATE assignments SET title = ?, description = ?, due_date = ?, max_points = ?, file_path = ? WHERE id = ?',
        [$title, $description, $due_date, $max_points, $file_path, $assignment['id']]
    );
    if ($file_path !== $assignment['file_path']) {
        delete_upload($assignment['file_path']);
    }
    flash('success', 'Assignment updated.');
    redirect('assignment.php?id=' . $assignment['id']);
}

// ===== DELETE ASSIGNMENT =====
if ($action === 'delete') {
    $assignment = query(
        'SELECT a.id, a.course_id FROM assignments a JOIN courses c ON c.id = a.course_id WHERE a.id = ? AND c.instructor_id = ?',
        [(int) ($_POST['assignment_id'] ?? 0), $current['id']]
    )->fetch();
    if (!$assignment) {
        flash('error', "You don't have permission to modify this assignment.");
        redirect('index.php');
    }
    $keys = assignment_file_keys((int) $assignment['id']);
    query('DELETE FROM assignments WHERE id = ?', [$assignment['id']]);
    delete_uploads($keys);
    flash('success', 'Assignment deleted.');
    redirect('course_detail.php?id=' . $assignment['course_id']);
}

redirect('index.php');

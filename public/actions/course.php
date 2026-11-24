<?php
require __DIR__ . '/../../includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('index.php');
}
csrf_verify();

$current = require_login();
$action = $_POST['action'] ?? '';

/** Validate course form fields; returns [fields, errors]. */
function course_input(?int $courseId): array
{
    $fields = [
        'title' => trim((string) ($_POST['title'] ?? '')),
        'course_code' => strtoupper(trim((string) ($_POST['course_code'] ?? ''))),
        'category' => trim((string) ($_POST['category'] ?? '')),
        'description' => trim((string) ($_POST['description'] ?? '')),
        'enrollment_limit' => (int) ($_POST['enrollment_limit'] ?? 0),
    ];

    $errors = [];
    if ($fields['title'] === '' || $fields['course_code'] === '' || $fields['description'] === '') {
        $errors[] = 'All fields are required.';
    }
    if (!in_array($fields['category'], course_categories(), true)) {
        $errors[] = 'Please choose a category.';
    }
    if ($fields['enrollment_limit'] < 1 || $fields['enrollment_limit'] > 500) {
        $errors[] = 'Enrollment limit must be between 1 and 500.';
    }
    if ($fields['course_code'] !== '') {
        $taken = query('SELECT id FROM courses WHERE course_code = ? AND id <> ?', [$fields['course_code'], $courseId ?? 0])->fetchColumn();
        if ($taken) {
            $errors[] = 'Course code already exists.';
        }
    }
    return [$fields, $errors];
}

/** The course if the current user owns it, otherwise flash and redirect. */
function owned_course_or_fail(array $user, int $courseId): array
{
    $course = query('SELECT * FROM courses WHERE id = ? AND instructor_id = ?', [$courseId, $user['id']])->fetch();
    if (!$course) {
        flash('error', "You don't have permission to modify this course.");
        redirect($user['role'] === 'instructor' ? 'instructor_dashboard.php' : 'student_dashboard.php');
    }
    return $course;
}

// ===== CREATE COURSE (Instructor Only) =====
if ($action === 'create') {
    if ($current['role'] !== 'instructor') {
        flash('error', 'Only instructors can create courses.');
        redirect('index.php');
    }
    [$fields, $errors] = course_input(null);
    if ($errors) {
        flash('error', $errors);
        redirect('instructor_dashboard.php');
    }
    query(
        'INSERT INTO courses (title, course_code, category, description, instructor_id, enrollment_limit) VALUES (?, ?, ?, ?, ?, ?)',
        [$fields['title'], $fields['course_code'], $fields['category'], $fields['description'], $current['id'], $fields['enrollment_limit']]
    );
    flash('success', 'Course created successfully!');
    redirect('instructor_dashboard.php');
}

// ===== EDIT COURSE =====
if ($action === 'update') {
    $course = owned_course_or_fail($current, (int) ($_POST['course_id'] ?? 0));
    [$fields, $errors] = course_input((int) $course['id']);

    $enrolled = (int) query('SELECT COUNT(*) FROM enrollments WHERE course_id = ?', [$course['id']])->fetchColumn();
    if ($fields['enrollment_limit'] >= 1 && $fields['enrollment_limit'] < $enrolled) {
        $errors[] = "Limit can't be lower than the {$enrolled} students already enrolled.";
    }
    if ($errors) {
        flash('error', $errors);
        redirect('course_detail.php?id=' . $course['id']);
    }
    query(
        'UPDATE courses SET title = ?, course_code = ?, category = ?, description = ?, enrollment_limit = ? WHERE id = ?',
        [$fields['title'], $fields['course_code'], $fields['category'], $fields['description'], $fields['enrollment_limit'], $course['id']]
    );
    flash('success', 'Course updated.');
    redirect('course_detail.php?id=' . $course['id']);
}

// ===== DELETE COURSE =====
if ($action === 'delete') {
    $course = owned_course_or_fail($current, (int) ($_POST['course_id'] ?? 0));
    $keys = course_file_keys((int) $course['id']);
    query('DELETE FROM courses WHERE id = ?', [$course['id']]);
    delete_uploads($keys);
    flash('success', 'Course deleted successfully!');
    redirect('instructor_dashboard.php');
}

// ===== ENROLL IN COURSE (Student Only) =====
if ($action === 'enroll') {
    $course_id = (int) ($_POST['course_id'] ?? 0);

    if ($current['role'] !== 'student') {
        flash('error', 'Only students can enroll in courses.');
        redirect('index.php');
    }

    $course = query(
        'SELECT c.id, c.enrollment_limit, (SELECT COUNT(*) FROM enrollments e WHERE e.course_id = c.id) AS enrolled
         FROM courses c WHERE c.id = ?',
        [$course_id]
    )->fetch();
    if (!$course) {
        flash('error', 'Course not found.');
        redirect('student_dashboard.php');
    }
    if (is_enrolled($current['id'], $course_id)) {
        flash('error', 'You are already enrolled in this course.');
        redirect('course_detail.php?id=' . $course_id);
    }
    if ((int) $course['enrolled'] >= (int) $course['enrollment_limit']) {
        flash('error', 'This course has reached its enrollment limit.');
        redirect('course_detail.php?id=' . $course_id);
    }

    query('INSERT INTO enrollments (user_id, course_id) VALUES (?, ?)', [$current['id'], $course_id]);
    flash('success', 'Successfully enrolled in course!');
    redirect('course_detail.php?id=' . $course_id);
}

// ===== ADD MODULE =====
if ($action === 'add_module') {
    $course = owned_course_or_fail($current, (int) ($_POST['course_id'] ?? 0));
    $module_title = trim((string) ($_POST['module_title'] ?? ''));

    // Validate every file before saving anything
    $files = uploaded_files('materials');
    $errors = [];
    if ($module_title === '') {
        $errors[] = 'Module title is required.';
    }
    foreach ($files as $file) {
        try {
            validate_upload($file, 'material');
        } catch (UploadError $e) {
            $errors[] = upload_original_name((string) $file['name']) . ': ' . $e->getMessage();
        }
    }
    if ($errors) {
        flash('error', $errors);
        redirect('course_detail.php?id=' . $course['id']);
    }

    $next_order = (int) query('SELECT COALESCE(MAX(module_order), 0) + 1 FROM modules WHERE course_id = ?', [$course['id']])->fetchColumn();
    query('INSERT INTO modules (course_id, title, module_order) VALUES (?, ?, ?)', [$course['id'], $module_title, $next_order]);
    $module_id = (int) db()->lastInsertId();

    foreach ($files as $file) {
        $stored = store_upload($file, 'material');
        query(
            'INSERT INTO materials (module_id, file_name, file_path, file_type) VALUES (?, ?, ?, ?)',
            [$module_id, $stored['original'], $stored['key'], $stored['mime']]
        );
    }

    flash('success', 'Module added successfully!');
    redirect('course_detail.php?id=' . $course['id']);
}

// ===== DELETE MODULE =====
if ($action === 'delete_module') {
    $module = query(
        'SELECT mo.id, mo.course_id FROM modules mo JOIN courses c ON c.id = mo.course_id WHERE mo.id = ? AND c.instructor_id = ?',
        [(int) ($_POST['module_id'] ?? 0), $current['id']]
    )->fetch();
    if (!$module) {
        flash('error', "You don't have permission to modify this course.");
        redirect('index.php');
    }
    $keys = module_file_keys((int) $module['id']);
    query('DELETE FROM modules WHERE id = ?', [$module['id']]);
    delete_uploads($keys);
    flash('success', 'Module deleted.');
    redirect('course_detail.php?id=' . $module['course_id']);
}

// ===== DELETE MATERIAL =====
if ($action === 'delete_material') {
    $material = query(
        'SELECT m.id, m.file_path, mo.course_id
         FROM materials m JOIN modules mo ON mo.id = m.module_id JOIN courses c ON c.id = mo.course_id
         WHERE m.id = ? AND c.instructor_id = ?',
        [(int) ($_POST['material_id'] ?? 0), $current['id']]
    )->fetch();
    if (!$material) {
        flash('error', "You don't have permission to modify this course.");
        redirect('index.php');
    }
    query('DELETE FROM materials WHERE id = ?', [$material['id']]);
    delete_upload($material['file_path']);
    flash('success', 'Material deleted.');
    redirect('course_detail.php?id=' . $material['course_id']);
}

redirect('index.php');

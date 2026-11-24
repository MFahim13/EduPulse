<?php

require __DIR__ . '/../../includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('index.php');
}
csrf_verify();

$current = require_login();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    $user_id = $_SESSION['user_id'];
    
    // ===== CREATE ASSIGNMENT (Instructor Only) =====
    if ($action == 'create') {
        if ($_SESSION['role'] != 'instructor') {
            flash('error', "Only instructors can create assignments");
            redirect('instructor_dashboard.php');
        }
        
        $course_id = (int)$_POST['course_id'];
        $title = trim($_POST['title']);
        $description = trim($_POST['description']);
        $due_date = $_POST['due_date'];
        $max_points = (int)$_POST['max_points'];
        
        $errors = [];
        
        if (empty($title) || empty($description) || empty($due_date)) {
            $errors[] = "All fields are required";
        }
        
        // Check if user owns this course
        $stmt = query("SELECT instructor_id FROM courses WHERE id = ?", [$course_id]);
        $course = $stmt->fetch();
        
        if (!$course || $course['instructor_id'] != $user_id) {
            $errors[] = "You don't have permission to create assignments for this course";
        }
        
        // Optional attachment, stored only once everything else is valid
        $file_path = null;
        $has_attachment = isset($_FILES['assignment_file']) && (int) $_FILES['assignment_file']['error'] !== UPLOAD_ERR_NO_FILE;
        if (empty($errors) && $has_attachment) {
            try {
                $file_path = store_upload($_FILES['assignment_file'], 'assignment')['key'];
            } catch (UploadError $e) {
                $errors[] = $e->getMessage();
            }
        }

        if (empty($errors)) {
            try {
                $sql = "INSERT INTO assignments (course_id, title, description, due_date, max_points, file_path) 
                        VALUES (?, ?, ?, ?, ?, ?)";
                query($sql, [$course_id, $title, $description, $due_date, $max_points, $file_path]);
                
                flash('success', "Assignment created successfully!");
                redirect('course_detail.php?id=' . $course_id);
            } catch (Exception $e) {
                flash('error', ["Database error: " . $e->getMessage()]);
                redirect('course_detail.php?id=' . $course_id);
            }
        } else {
            flash('error', $errors);
            redirect('course_detail.php?id=' . $course_id);
        }
    }
    
    // ===== SUBMIT ASSIGNMENT (Student Only) =====
    if ($action == 'submit') {
        $assignment_id = (int) ($_POST['assignment_id'] ?? 0);
        $errors = [];

        if ($current['role'] !== 'student') {
            flash('error', 'Only students can submit assignments.');
            redirect('assignment.php?id=' . $assignment_id);
        }

        $target = query('SELECT id, course_id FROM assignments WHERE id = ?', [$assignment_id])->fetch();
        if (!$target) {
            flash('error', 'Assignment not found.');
            redirect('student_dashboard.php');
        }

        if (!is_enrolled($current['id'], (int) $target['course_id'])) {
            flash('error', 'You need to be enrolled in this course to submit.');
            redirect('course_detail.php?id=' . $target['course_id']);
        }

        // Check if already submitted
        $stmt = query("SELECT id FROM submissions WHERE assignment_id = ? AND student_id = ?", 
                      [$assignment_id, $user_id]);
        if ($stmt->rowCount() > 0) {
            $errors[] = "You have already submitted this assignment";
        }
        
        // Store the file once the request itself is valid
        $file_path = null;
        if (empty($errors)) {
            try {
                $file_path = store_upload($_FILES['submission_file'] ?? ['error' => UPLOAD_ERR_NO_FILE], 'submission')['key'];
            } catch (UploadError $e) {
                $errors[] = $e->getMessage();
            }
        }

        
        if (empty($errors)) {
            try {
                $sql = "INSERT INTO submissions (assignment_id, student_id, file_path, submitted_at) 
                        VALUES (?, ?, ?, NOW())";
                query($sql, [$assignment_id, $user_id, $file_path]);
                
                flash('success', "Assignment submitted successfully!");
                redirect('assignment.php?id=' . $assignment_id);
            } catch (Exception $e) {
                flash('error', ["Database error: " . $e->getMessage()]);
                redirect('assignment.php?id=' . $assignment_id);
            }
        } else {
            flash('error', $errors);
            redirect('assignment.php?id=' . $assignment_id);
        }
    }
    
    // ===== GRADE SUBMISSION (Instructor Only) =====
    if ($action == 'grade') {
        $submission_id = (int)$_POST['submission_id'];
        $assignment_id = (int)$_POST['assignment_id'];
        $grade = (int)$_POST['grade'];
        $feedback = trim($_POST['feedback'] ?? '');
        
        $errors = [];
        
        if ($grade < 0 || $grade > 100) {
            $errors[] = "Grade must be between 0 and 100";
        }
        
        // The grader must own the course AND the submission must belong to this assignment
        $assignment = query('SELECT course_id FROM assignments WHERE id = ?', [$assignment_id])->fetch();
        $can_grade = $assignment
            && owns_course($current['id'], (int) $assignment['course_id'])
            && find_submission_for_assignment($submission_id, $assignment_id) !== null;

        if (!$can_grade) {
            $errors = ["You don't have permission to grade this submission."];
        }

        if (empty($errors)) {
            try {
                $sql = "UPDATE submissions SET grade = ?, feedback = ?, graded_at = NOW() WHERE id = ? AND assignment_id = ?";
                query($sql, [$grade, $feedback, $submission_id, $assignment_id]);
                
                flash('success', "Submission graded successfully!");
                redirect('assignment.php?id=' . $assignment_id);
            } catch (Exception $e) {
                flash('error', ["Database error: " . $e->getMessage()]);
                redirect('assignment.php?id=' . $assignment_id);
            }
        } else {
            flash('error', $errors);
            redirect('assignment.php?id=' . $assignment_id);
        }
    }
    
} else {
    // If not POST request, redirect
    redirect('index.php');
}

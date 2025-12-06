<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config/db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../public/login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    $user_id = $_SESSION['user_id'];
    
    // ===== CREATE ASSIGNMENT (Instructor Only) =====
    if ($action == 'create') {
        if ($_SESSION['role'] != 'instructor') {
            $_SESSION['errors'] = ["Only instructors can create assignments"];
            header('Location: ../public/instructor_dashboard.php');
            exit();
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
        
        // Handle file upload
        $file_path = null;
        if (isset($_FILES['assignment_file']) && $_FILES['assignment_file']['error'] == 0) {
            $upload_dir = __DIR__ . '/../public/assets/uploads/assignments/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $file = $_FILES['assignment_file'];
            $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            $new_name = 'assignment_' . time() . '_' . rand(1000, 9999) . '.' . $ext;
            $full_path = $upload_dir . $new_name;
            
            if (move_uploaded_file($file['tmp_name'], $full_path)) {
                $file_path = 'assets/uploads/assignments/' . $new_name;
            } else {
                $errors[] = "Failed to upload assignment file";
            }
        }
        
        if (empty($errors)) {
            try {
                $sql = "INSERT INTO assignments (course_id, title, description, due_date, max_points, file_path) 
                        VALUES (?, ?, ?, ?, ?, ?)";
                query($sql, [$course_id, $title, $description, $due_date, $max_points, $file_path]);
                
                $_SESSION['success'] = "Assignment created successfully!";
                header('Location: ../public/course_detail.php?id=' . $course_id);
                exit();
            } catch (Exception $e) {
                $_SESSION['errors'] = ["Database error: " . $e->getMessage()];
                header('Location: ../public/course_detail.php?id=' . $course_id);
                exit();
            }
        } else {
            $_SESSION['errors'] = $errors;
            header('Location: ../public/course_detail.php?id=' . $course_id);
            exit();
        }
    }
    
    // ===== SUBMIT ASSIGNMENT (Student Only) =====
    if ($action == 'submit') {
        $assignment_id = (int)$_POST['assignment_id'];
        $errors = [];
        
        // Check if already submitted
        $stmt = query("SELECT id FROM submissions WHERE assignment_id = ? AND student_id = ?", 
                      [$assignment_id, $user_id]);
        if ($stmt->rowCount() > 0) {
            $errors[] = "You have already submitted this assignment";
        }
        
        // Handle file upload
        $file_path = null;
        if (isset($_FILES['submission_file']) && $_FILES['submission_file']['error'] == 0) {
            $upload_dir = __DIR__ . '/../public/assets/uploads/submissions/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $file = $_FILES['submission_file'];
            $allowed = ['pdf', 'doc', 'docx', 'zip'];
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            
            if (!in_array($ext, $allowed)) {
                $errors[] = "Invalid file type. Allowed: PDF, DOC, DOCX, ZIP";
            }
            
            if ($file['size'] > 10 * 1024 * 1024) {
                $errors[] = "File too large. Max size: 10MB";
            }
            
            if (empty($errors)) {
                $new_name = 'submission_' . $user_id . '_' . $assignment_id . '_' . time() . '.' . $ext;
                $full_path = $upload_dir . $new_name;
                
                if (move_uploaded_file($file['tmp_name'], $full_path)) {
                    $file_path = 'assets/uploads/submissions/' . $new_name;
                } else {
                    $errors[] = "Failed to upload file";
                }
            }
        } else {
            $errors[] = "Please select a file to upload";
        }
        
        if (empty($errors)) {
            try {
                $sql = "INSERT INTO submissions (assignment_id, student_id, file_path, submitted_at) 
                        VALUES (?, ?, ?, NOW())";
                query($sql, [$assignment_id, $user_id, $file_path]);
                
                $_SESSION['success'] = "Assignment submitted successfully!";
                header('Location: ../public/assignment.php?id=' . $assignment_id);
                exit();
            } catch (Exception $e) {
                $_SESSION['errors'] = ["Database error: " . $e->getMessage()];
                header('Location: ../public/assignment.php?id=' . $assignment_id);
                exit();
            }
        } else {
            $_SESSION['errors'] = $errors;
            header('Location: ../public/assignment.php?id=' . $assignment_id);
            exit();
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
        
        // Verify instructor owns this assignment
        $stmt = query("SELECT a.course_id, c.instructor_id 
                       FROM assignments a 
                       JOIN courses c ON a.course_id = c.id 
                       WHERE a.id = ?", [$assignment_id]);
        $assignment = $stmt->fetch();
        
        if (!$assignment || $assignment['instructor_id'] != $user_id) {
            $errors[] = "You don't have permission to grade this submission";
        }
        
        if (empty($errors)) {
            try {
                $sql = "UPDATE submissions SET grade = ?, feedback = ?, graded_at = NOW() WHERE id = ?";
                query($sql, [$grade, $feedback, $submission_id]);
                
                $_SESSION['success'] = "Submission graded successfully!";
                header('Location: ../public/assignment.php?id=' . $assignment_id);
                exit();
            } catch (Exception $e) {
                $_SESSION['errors'] = ["Database error: " . $e->getMessage()];
                header('Location: ../public/assignment.php?id=' . $assignment_id);
                exit();
            }
        } else {
            $_SESSION['errors'] = $errors;
            header('Location: ../public/assignment.php?id=' . $assignment_id);
            exit();
        }
    }
    
} else {
    // If not POST request, redirect
    header('Location: ../public/index.php');
    exit();
}
?>

?>

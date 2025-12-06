<?php
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
    $action = $_POST['action'] ?? '';      // ← ADD THIS LINE
    $user_id = $_SESSION['user_id'];       // ← ADD THIS LINE
    
    // ===== CREATE COURSE (Instructor Only) =====
    if ($action == 'create') {
        // Check if user is instructor
        if ($_SESSION['role'] != 'instructor') {
            $_SESSION['errors'] = ["Only instructors can create courses"];
            header('Location: ../public/instructor_dashboard.php');
            exit();
        }
        
        $title = trim($_POST['title']);
        $course_code = trim($_POST['course_code']);
        $category = trim($_POST['category']);
        $description = trim($_POST['description']);
        $enrollment_limit = (int)$_POST['enrollment_limit'];
        
        // Validation
        $errors = [];
        
        if (empty($title) || empty($course_code) || empty($category) || empty($description)) {
            $errors[] = "All fields are required";
        }
        
        // Check if course code already exists
        if (empty($errors)) {
            $stmt = query("SELECT id FROM courses WHERE course_code = ?", [$course_code]);
            if ($stmt->rowCount() > 0) {
                $errors[] = "Course code already exists";
            }
        }
        
        // Insert course if no errors
        if (empty($errors)) {
            $sql = "INSERT INTO courses (title, course_code, category, description, instructor_id, enrollment_limit) 
                    VALUES (?, ?, ?, ?, ?, ?)";
            query($sql, [$title, $course_code, $category, $description, $user_id, $enrollment_limit]);
            
            $_SESSION['success'] = "Course created successfully!";
            header('Location: ../public/instructor_dashboard.php');
            exit();
        } else {
            $_SESSION['errors'] = $errors;
            header('Location: ../public/instructor_dashboard.php');
            exit();
        }
    }
    
    // ===== DELETE COURSE (Instructor Only) =====
    if ($action == 'delete') {
        $course_id = (int)$_POST['course_id'];
        
        // Check if user owns this course
        $stmt = query("SELECT instructor_id FROM courses WHERE id = ?", [$course_id]);
        $course = $stmt->fetch();
        
        if ($course && $course['instructor_id'] == $user_id) {
            query("DELETE FROM courses WHERE id = ?", [$course_id]);
            $_SESSION['success'] = "Course deleted successfully!";
        } else {
            $_SESSION['errors'] = ["You don't have permission to delete this course"];
        }
        
        header('Location: ../public/instructor_dashboard.php');
        exit();
    }
    
    // ===== ENROLL IN COURSE (Student Only) =====
    if ($action == 'enroll') {
        $course_id = (int)$_POST['course_id'];
        
        // Check if user is student
        if ($_SESSION['role'] != 'student') {
            $_SESSION['errors'] = ["Only students can enroll in courses"];
            header('Location: ../public/student_dashboard.php');
            exit();
        }
        
        // Check if already enrolled
        $stmt = query("SELECT id FROM enrollments WHERE user_id = ? AND course_id = ?", [$user_id, $course_id]);
        if ($stmt->rowCount() > 0) {
            $_SESSION['errors'] = ["You are already enrolled in this course"];
            header('Location: ../public/student_dashboard.php');
            exit();
        }
        
        // Check enrollment limit
        $stmt = query("SELECT c.enrollment_limit, COUNT(e.id) as enrolled 
                       FROM courses c 
                       LEFT JOIN enrollments e ON c.id = e.course_id 
                       WHERE c.id = ? 
                       GROUP BY c.id", [$course_id]);
        $course = $stmt->fetch();
        
        if ($course && $course['enrolled'] >= $course['enrollment_limit']) {
            $_SESSION['errors'] = ["This course has reached its enrollment limit"];
            header('Location: ../public/student_dashboard.php');
            exit();
        }
        
        // Enroll student
        query("INSERT INTO enrollments (user_id, course_id) VALUES (?, ?)", [$user_id, $course_id]);
        $_SESSION['success'] = "Successfully enrolled in course!";
        header('Location: ../public/student_dashboard.php');
        exit();
    }
    
    // ===== ADD MODULE (Instructor Only) =====
if ($action == 'add_module') {
    $course_id = (int)$_POST['course_id'];
    $module_title = trim($_POST['module_title']);
    
    // Check if user owns this course
    $stmt = query("SELECT instructor_id FROM courses WHERE id = ?", [$course_id]);
    $course = $stmt->fetch();
    
    if (!$course || $course['instructor_id'] != $user_id) {
        $_SESSION['errors'] = ["You don't have permission to modify this course"];
        header('Location: ../public/course_detail.php?id=' . $course_id);
        exit();
    }
    
    // Get next module order
    $stmt = query("SELECT COALESCE(MAX(module_order), 0) + 1 as next_order FROM modules WHERE course_id = ?", [$course_id]);
    $next_order = $stmt->fetch()['next_order'];
    
    // Insert module
    try {
        query("INSERT INTO modules (course_id, title, module_order) VALUES (?, ?, ?)", 
              [$course_id, $module_title, $next_order]);
        
        // Get the last inserted module ID
        global $pdo;
        $module_id = $pdo->lastInsertId();
        
        // Handle file uploads
        if (isset($_FILES['materials']) && !empty($_FILES['materials']['name'][0])) {
            $upload_dir = __DIR__ . '/../public/assets/uploads/materials/';
            
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            foreach ($_FILES['materials']['tmp_name'] as $key => $tmp_name) {
                if ($_FILES['materials']['error'][$key] == 0) {
                    $file_name = $_FILES['materials']['name'][$key];
                    $file_type = $_FILES['materials']['type'][$key];
                    $file_extension = pathinfo($file_name, PATHINFO_EXTENSION);
                    $new_filename = 'material_' . time() . '_' . rand(1000, 9999) . '.' . $file_extension;
                    
                    if (move_uploaded_file($tmp_name, $upload_dir . $new_filename)) {
                        $db_path = 'assets/uploads/materials/' . $new_filename;
                        
                        // Insert into materials table
                        query("INSERT INTO materials (module_id, file_name, file_path, file_type) VALUES (?, ?, ?, ?)",
                              [$module_id, $file_name, $db_path, $file_type]);
                    }
                }
            }
        }
        
        $_SESSION['success'] = "Module added successfully!";
        header('Location: ../public/course_detail.php?id=' . $course_id);
        exit();
        
    } catch (Exception $e) {
        $_SESSION['errors'] = ["Database error: " . $e->getMessage()];
        header('Location: ../public/course_detail.php?id=' . $course_id);
        exit();
    }
}
}
?>

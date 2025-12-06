<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config/db.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';  // ← THIS WAS MISSING
    
    // ===== REGISTER =====
    if ($action == 'register') {
        $name = trim($_POST['name']);
        $email = trim($_POST['email']);
        $password = $_POST['password'];
        $confirm_password = $_POST['confirm_password'];
        $role = $_POST['role'];
        
        // Validation
        $errors = [];
        
        if (empty($name) || empty($email) || empty($password) || empty($role)) {
            $errors[] = "All fields are required";
        }
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Invalid email format";
        }
        
        if (strlen($password) < 6) {
            $errors[] = "Password must be at least 6 characters";
        }
        
        if ($password !== $confirm_password) {
            $errors[] = "Passwords do not match";
        }
        
        if (!in_array($role, ['student', 'instructor'])) {
            $errors[] = "Invalid role selected";
        }
        
        // Check if email already exists
        if (empty($errors)) {
            $stmt = query("SELECT id FROM users WHERE email = ?", [$email]);
            if ($stmt->rowCount() > 0) {
                $errors[] = "Email already registered";
            }
        }
        
        // If no errors, insert user
        if (empty($errors)) {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            $sql = "INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)";
            query($sql, [$name, $email, $hashed_password, $role]);
            
            // Set success message for login page
            $_SESSION['success'] = "Registration successful! Please login with your credentials.";
            
            // Redirect to login page (NOT auto-login)
            header('Location: ../public/login.php');
            exit();
        } else {
            // If errors, redirect back to register page
            $_SESSION['errors'] = $errors;
            header('Location: ../public/register.php');
            exit();
        }
    }
    
    // ===== LOGIN =====
    if ($action == 'login') {
        $email = trim($_POST['email']);
        $password = $_POST['password'];
        
        // Validation
        if (empty($email) || empty($password)) {
            $_SESSION['error'] = "Email and password are required";
            header('Location: ../public/login.php');
            exit();
        }
        
        // Check user in database
        $stmt = query("SELECT * FROM users WHERE email = ?", [$email]);
        
        if ($stmt->rowCount() > 0) {
            $user = $stmt->fetch();
            
            // Verify password
            if (password_verify($password, $user['password'])) {
                // Set session
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['name'] = $user['name'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['profile_picture'] = $user['profile_picture'];
                
                // Redirect based on role
                if ($user['role'] == 'student') {
                    header('Location: ../public/student_dashboard.php');
                } else {
                    header('Location: ../public/instructor_dashboard.php');
                }
                exit();
            } else {
                $_SESSION['error'] = "Invalid password";
                header('Location: ../public/login.php');
                exit();
            }
        } else {
            $_SESSION['error'] = "Email not found";
            header('Location: ../public/login.php');
            exit();
        }
    }
}
?>

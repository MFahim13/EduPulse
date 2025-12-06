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

// Only process if POST request
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    $user_id = $_SESSION['user_id'];
    
    // ===== UPDATE PROFILE =====
    if ($action == 'update_profile') {
        $name = trim($_POST['name']);
        $email = trim($_POST['email']);
        $phone = trim($_POST['phone'] ?? '');
        $dob = $_POST['dob'] ?? null;
        $bio = trim($_POST['bio'] ?? '');
        
        // Validation
        $errors = [];
        
        if (empty($name) || empty($email)) {
            $errors[] = "Name and email are required";
        }
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Invalid email format";
        }
        
        // Check if email is taken by another user
        if (empty($errors)) {
            $stmt = query("SELECT id FROM users WHERE email = ? AND id != ?", [$email, $user_id]);
            if ($stmt->rowCount() > 0) {
                $errors[] = "Email already taken by another user";
            }
        }
        
        // Update if no errors
        if (empty($errors)) {
            $sql = "UPDATE users SET name = ?, email = ?, phone = ?, dob = ?, bio = ? WHERE id = ?";
            query($sql, [$name, $email, $phone, $dob, $bio, $user_id]);
            
            // Update session
            $_SESSION['name'] = $name;
            $_SESSION['email'] = $email;
            $_SESSION['success'] = "Profile updated successfully!";
            
            header('Location: ../public/profile.php');
            exit();
        } else {
            $_SESSION['errors'] = $errors;
            header('Location: ../public/profile.php');
            exit();
        }
    }
    
    // ===== CHANGE PASSWORD =====
    if ($action == 'change_password') {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        // Validation
        $errors = [];
        
        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            $errors[] = "All password fields are required";
        }
        
        if (strlen($new_password) < 6) {
            $errors[] = "New password must be at least 6 characters";
        }
        
        if ($new_password !== $confirm_password) {
            $errors[] = "New passwords do not match";
        }
        
        // Verify current password
        if (empty($errors)) {
            $stmt = query("SELECT password FROM users WHERE id = ?", [$user_id]);
            $user = $stmt->fetch();
            
            if (!password_verify($current_password, $user['password'])) {
                $errors[] = "Current password is incorrect";
            }
        }
        
        // Update password if no errors
        if (empty($errors)) {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            query("UPDATE users SET password = ? WHERE id = ?", [$hashed_password, $user_id]);
            
            $_SESSION['success'] = "Password changed successfully!";
            header('Location: ../public/profile.php');
            exit();
        } else {
            $_SESSION['errors'] = $errors;
            header('Location: ../public/profile.php');
            exit();
        }
    }
    
    // ===== UPLOAD PROFILE PHOTO =====
    if ($action == 'upload_photo') {
        $errors = [];
        
        if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] == 0) {
            $file = $_FILES['profile_photo'];
            $allowed_types = ['image/jpeg', 'image/png', 'image/jpg'];
            $max_size = 2 * 1024 * 1024; // 2MB
            
            // Validate file type
            if (!in_array($file['type'], $allowed_types)) {
                $errors[] = "Only JPG and PNG images are allowed";
            }
            
            // Validate file size
            if ($file['size'] > $max_size) {
                $errors[] = "File size must be less than 2MB";
            }
            
            // Upload file if no errors
            if (empty($errors)) {
                $upload_dir = __DIR__ . '/../public/assets/uploads/profiles/';
                
                // Create directory if not exists
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                // Generate unique filename
                $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
                $new_filename = 'profile_' . $user_id . '_' . time() . '.' . $file_extension;
                $file_path = $upload_dir . $new_filename;
                
                // Move uploaded file
                if (move_uploaded_file($file['tmp_name'], $file_path)) {
                    // Delete old profile picture if exists
                    $stmt = query("SELECT profile_picture FROM users WHERE id = ?", [$user_id]);
                    $old_pic = $stmt->fetch()['profile_picture'] ?? null;
                    if ($old_pic && file_exists(__DIR__ . '/../public/' . $old_pic)) {
                        unlink(__DIR__ . '/../public/' . $old_pic);
                    }
                    
                    // Update database
                    $db_path = 'assets/uploads/profiles/' . $new_filename;
                    query("UPDATE users SET profile_picture = ? WHERE id = ?", [$db_path, $user_id]);
                    
                    // Update session
                    $_SESSION['profile_picture'] = $db_path;
                    $_SESSION['success'] = "Profile picture updated successfully!";
                    
                    header('Location: ../public/profile.php');
                    exit();
                } else {
                    $errors[] = "Failed to upload file";
                }
            }
        } else {
            $errors[] = "Please select a file to upload";
        }
        
        if (!empty($errors)) {
            $_SESSION['errors'] = $errors;
            header('Location: ../public/profile.php');
            exit();
        }
    }
    
} else {
    // If not POST request, redirect to profile
    header('Location: ../public/profile.php');
    exit();
}
?>

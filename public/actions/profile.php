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
    
    // ===== UPDATE PROFILE =====
    if ($action == 'update_profile') {
        $name = trim($_POST['name']);
        $email = strtolower(trim($_POST['email'] ?? ''));
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
            flash('success', "Profile updated successfully!");
            
            redirect('profile.php');
        } else {
            flash('error', $errors);
            redirect('profile.php');
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
        
        if (strlen($new_password) < 8) {
            $errors[] = "New password must be at least 8 characters.";
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
            
            flash('success', "Password changed successfully!");
            redirect('profile.php');
        } else {
            flash('error', $errors);
            redirect('profile.php');
        }
    }
    
    // ===== UPLOAD PROFILE PHOTO =====
    if ($action == 'upload_photo') {
        try {
            $stored = store_upload($_FILES['profile_photo'] ?? ['error' => UPLOAD_ERR_NO_FILE], 'profile');
        } catch (UploadError $e) {
            flash('error', $e->getMessage());
            redirect('profile.php');
        }

        $old_pic = query("SELECT profile_picture FROM users WHERE id = ?", [$user_id])->fetchColumn() ?: null;
        query("UPDATE users SET profile_picture = ? WHERE id = ?", [$stored['key'], $user_id]);
        delete_upload($old_pic);

        $_SESSION['profile_picture'] = $stored['key'];
        flash('success', "Profile picture updated successfully!");
        redirect('profile.php');
    }

} else {
    // If not POST request, redirect to profile
    redirect('profile.php');
}

<?php

require __DIR__ . '/../../includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('index.php');
}
csrf_verify();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    
    // ===== REGISTER =====
    if ($action == 'register') {
        $name = trim($_POST['name'] ?? '');
        $email = strtolower(trim($_POST['email'] ?? ''));
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
        
        if (strlen($password) < 8) {
            $errors[] = "Password must be at least 8 characters.";
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
            flash('success', "Registration successful! Please login with your credentials.");
            
            // Redirect to login page (NOT auto-login)
            redirect('login.php');
        } else {
            // If errors, redirect back to register page
            flash('error', $errors);
            redirect('register.php');
        }
    }
    
    // ===== LOGIN =====
    if ($action == 'login') {
        $email = strtolower(trim((string) ($_POST['email'] ?? '')));
        $password = (string) ($_POST['password'] ?? '');
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

        if ($email === '' || $password === '') {
            flash('error', 'Email and password are required.');
            redirect('login.php');
        }

        if (login_locked($email, $ip)) {
            flash('error', 'Too many failed attempts. Try again in 15 minutes.');
            redirect('login.php');
        }

        $user = query('SELECT * FROM users WHERE email = ?', [$email])->fetch();

        if (!$user || !password_verify($password, $user['password'])) {
            record_login_failure($email, $ip);
            flash('error', 'Invalid email or password.');
            redirect('login.php');
        }

        clear_login_failures($email, $ip);

        // New session ID on login prevents session fixation
        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['name'] = $user['name'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['profile_picture'] = $user['profile_picture'];

        redirect($user['role'] === 'student' ? 'student_dashboard.php' : 'instructor_dashboard.php');
    }

    // ===== LOGOUT =====
    if ($action == 'logout') {
        $_SESSION = [];
        $params = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires' => time() - 3600,
            'path' => $params['path'],
            'secure' => $params['secure'],
            'httponly' => $params['httponly'],
            'samesite' => $params['samesite'],
        ]);
        session_destroy();
        redirect('login.php?message=logged_out');
    }
}

redirect('index.php');

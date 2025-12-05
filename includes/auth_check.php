<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Optional: Check for specific role
if (isset($required_role)) {
    if ($_SESSION['role'] != $required_role) {
        header('Location: index.php');
        exit();
    }
}
?>

<?php
session_start();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action == 'submit') {
        // TODO: Save assignment submission
        header('Location: ../public/assignment.php?id=' . $_POST['assignment_id'] . '&message=submitted');
        exit();
    }
    
    if ($action == 'grade') {
        // TODO: Save grade to database
        header('Location: ../public/assignment.php?id=1&message=graded');
        exit();
    }
    
    if ($action == 'create') {
        // TODO: Create new assignment
        header('Location: ../public/course_detail.php?id=' . $_POST['course_id']);
        exit();
    }
}
?>

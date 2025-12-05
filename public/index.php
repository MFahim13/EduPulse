<?php
session_start();
$page_title = "Welcome";
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/navbar.php';


?>
<main>
    <section class="hero-section">
        <div class="container">
            <h1>EduPulse – Learning Management System</h1>
            <p>Manage courses, assignments, and learning progress in one centralized platform for students and instructors.</p>
            <div>
                <a href="login.php" class="btn btn-light">Login</a>
                <a href="register.php" class="btn btn-outline-light">Sign Up</a>
            </div>
        </div>
    </section>

    <section class="features-section">
        <div class="container">
            <div class="row g-4">
                <div class="col-md-4">
                    <div class="feature-card">
                        <i class="fas fa-user-graduate"></i>
                        <h3>Student Dashboard</h3>
                        <p>View enrolled courses, assignments, and progress in a clean, organized dashboard.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="feature-card">
                        <i class="fas fa-chalkboard-teacher"></i>
                        <h3>Instructor Tools</h3>
                        <p>Create courses, upload materials, and manage student submissions with ease.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="feature-card">
                        <i class="fas fa-chart-line"></i>
                        <h3>Progress Tracking</h3>
                        <p>Track grades and completion status with visual indicators for better learning.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>
<?php include '../includes/footer.php'; ?>

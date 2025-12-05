<?php
$required_role = 'student';
include __DIR__ . '/../includes/auth_check.php';
$page_title = "Student Dashboard";
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/navbar.php';
?>

<main>
    <section class="dashboard-header">
        <div class="container">
            <h1>Welcome, <?php echo htmlspecialchars($_SESSION['name'] ?? 'Student'); ?></h1>
            <p>Your courses, assignments, and progress at a glance.</p>
        </div>
    </section>

    <section class="py-4">
        <div class="container">
            
            <!-- Success/Error Messages -->
            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['errors'])): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <ul class="mb-0">
                        <?php foreach($_SESSION['errors'] as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                    <?php unset($_SESSION['errors']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Stats Cards -->
            <div class="row g-4 mb-4">
                <div class="col-md-4">
                    <div class="stat-card">
                        <i class="fas fa-book"></i>
                        <h3>0</h3>
                        <p>Enrolled Courses</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stat-card">
                        <i class="fas fa-tasks"></i>
                        <h3>0</h3>
                        <p>Pending Assignments</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stat-card">
                        <i class="fas fa-chart-line"></i>
                        <h3>0%</h3>
                        <p>Average Progress</p>
                    </div>
                </div>
            </div>

            <div class="mb-4 d-flex justify-content-between align-items-center">
                <h2 class="h4">My Courses</h2>
                <input type="text" id="courseSearch" class="form-control w-50" placeholder="Search courses...">
            </div>

            <div class="row g-4">
                <!-- Later: loop courses from DB -->
                <div class="col-md-4">
                    <div class="course-card">
                        <div class="course-card-img">
                            <i class="fas fa-laptop-code"></i>
                        </div>
                        <div class="course-card-body">
                            <h3 class="course-card-title">Sample Course</h3>
                            <p class="course-card-text">This is a placeholder course. Replace with dynamic data later.</p>
                            <div class="course-meta">
                                <span><i class="fas fa-user"></i> Instructor</span>
                                <a href="course_detail.php" class="btn btn-sm btn-primary">View</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </section>
</main>

<?php include __DIR__ . '/../includes/footer.php'; ?>

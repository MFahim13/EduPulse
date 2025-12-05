<?php
$required_role = 'instructor';
include __DIR__ . '/../includes/auth_check.php';
$page_title = "Instructor Dashboard";
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/navbar.php';
?>
<main>
    <section class="dashboard-header">
        <div class="container">
            <h1>Welcome, <?php echo htmlspecialchars($_SESSION['name'] ?? 'Instructor'); ?></h1>
            <p>Manage your courses, students, and assignments efficiently.</p>
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
                <div class="col-md-3">
                    <div class="stat-card">
                        <i class="fas fa-book-open text-primary"></i>
                        <h3>0</h3>
                        <p>Total Courses</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card">
                        <i class="fas fa-users text-success"></i>
                        <h3>0</h3>
                        <p>Total Students</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card">
                        <i class="fas fa-clipboard-list text-warning"></i>
                        <h3>0</h3>
                        <p>Pending Grading</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card">
                        <i class="fas fa-bullhorn text-info"></i>
                        <h3>0</h3>
                        <p>Announcements</p>
                    </div>
                </div>
            </div>

            <!-- Course Management Section -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 class="h4">My Courses</h2>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createCourseModal">
                    <i class="fas fa-plus"></i> Create New Course
                </button>
            </div>

            <!-- Course Cards -->
            <div class="row g-4 mb-5">
                <!-- Sample Course 1 (Replace with PHP loop from database) -->
                <div class="col-md-4">
                    <div class="course-card">
                        <div class="course-card-img">
                            <i class="fas fa-code"></i>
                        </div>
                        <div class="course-card-body">
                            <h3 class="course-card-title">Web Technologies</h3>
                            <p class="course-card-text">Complete web development course covering HTML, CSS, JavaScript, PHP, and MySQL.</p>
                            <div class="mb-3">
                                <small class="text-muted">Course Code: CS401</small>
                            </div>
                            <div class="course-meta">
                                <span><i class="fas fa-user-friends"></i> 45 Students</span>
                                <div>
                                    <a href="course_detail.php?id=1" class="btn btn-sm btn-primary">Manage</a>
                                    <button class="btn btn-sm btn-outline-danger btn-delete">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Sample Course 2 -->
                <div class="col-md-4">
                    <div class="course-card">
                        <div class="course-card-img">
                            <i class="fas fa-database"></i>
                        </div>
                        <div class="course-card-body">
                            <h3 class="course-card-title">Database Systems</h3>
                            <p class="course-card-text">Learn database design, SQL queries, and database management systems.</p>
                            <div class="mb-3">
                                <small class="text-muted">Course Code: CS302</small>
                            </div>
                            <div class="course-meta">
                                <span><i class="fas fa-user-friends"></i> 38 Students</span>
                                <div>
                                    <a href="course_detail.php?id=2" class="btn btn-sm btn-primary">Manage</a>
                                    <button class="btn btn-sm btn-outline-danger btn-delete">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Pending Submissions Section -->
            <h2 class="h4 mb-4">Pending Submissions</h2>
            <div class="table-responsive table-custom">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Assignment</th>
                            <th>Course</th>
                            <th>Student</th>
                            <th>Submitted</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>Assignment 1: HTML Basics</td>
                            <td>Web Technologies</td>
                            <td>John Doe</td>
                            <td>2 hours ago</td>
                            <td>
                                <a href="assignment.php?id=1" class="btn btn-sm btn-success">Grade</a>
                            </td>
                        </tr>
                        <tr>
                            <td>Project Proposal</td>
                            <td>Database Systems</td>
                            <td>Jane Smith</td>
                            <td>1 day ago</td>
                            <td>
                                <a href="assignment.php?id=2" class="btn btn-sm btn-success">Grade</a>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </section>
</main>

<!-- Create Course Modal -->
<div class="modal fade" id="createCourseModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Create New Course</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="../controllers/course_controller.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="create">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Course Title</label>
                        <input type="text" class="form-control" name="title" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Course Code</label>
                        <input type="text" class="form-control" name="course_code" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Category</label>
                        <select class="form-select" name="category" required>
                            <option value="">Select category</option>
                            <option value="Programming">Programming</option>
                            <option value="Database">Database</option>
                            <option value="Web Development">Web Development</option>
                            <option value="Mobile Development">Mobile Development</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" name="description" rows="4" required></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Enrollment Limit</label>
                        <input type="number" class="form-control" name="enrollment_limit" value="50">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Course</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>

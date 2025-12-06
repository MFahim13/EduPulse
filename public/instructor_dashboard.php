<?php
$required_role = 'instructor';
include __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/db.php';

// Get instructor's courses and stats
$user_id = $_SESSION['user_id'];

// Count total courses
$total_courses = query("SELECT COUNT(*) as count FROM courses WHERE instructor_id = ?", [$user_id])->fetch()['count'];

// Count total enrolled students across all courses
$total_students = query("SELECT COUNT(DISTINCT e.user_id) as count FROM enrollments e 
                         JOIN courses c ON e.course_id = c.id 
                         WHERE c.instructor_id = ?", [$user_id])->fetch()['count'];

// Count pending submissions
$pending_grading = query("SELECT COUNT(*) as count FROM submissions s 
                         JOIN assignments a ON s.assignment_id = a.id 
                         JOIN courses c ON a.course_id = c.id 
                         WHERE c.instructor_id = ? AND s.grade IS NULL", [$user_id])->fetch()['count'];

// Count announcements
$total_announcements = query("SELECT COUNT(*) as count FROM announcements WHERE instructor_id = ?", [$user_id])->fetch()['count'];

// Get all courses by this instructor
$courses_stmt = query("SELECT c.*, 
                       (SELECT COUNT(*) FROM enrollments WHERE course_id = c.id) as enrolled_count
                       FROM courses c 
                       WHERE c.instructor_id = ? 
                       ORDER BY c.created_at DESC", [$user_id]);
$courses = $courses_stmt->fetchAll();

// Get pending submissions for table
$submissions_stmt = query("SELECT s.*, a.title as assignment_title, c.title as course_title, u.name as student_name,
                          s.submitted_at
                          FROM submissions s
                          JOIN assignments a ON s.assignment_id = a.id
                          JOIN courses c ON a.course_id = c.id
                          JOIN users u ON s.student_id = u.id
                          WHERE c.instructor_id = ? AND s.grade IS NULL
                          ORDER BY s.submitted_at DESC
                          LIMIT 10", [$user_id]);
$submissions = $submissions_stmt->fetchAll();

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
                        <h3><?php echo $total_courses; ?></h3>
                        <p>Total Courses</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card">
                        <i class="fas fa-users text-success"></i>
                        <h3><?php echo $total_students; ?></h3>
                        <p>Total Students</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card">
                        <i class="fas fa-clipboard-list text-warning"></i>
                        <h3><?php echo $pending_grading; ?></h3>
                        <p>Pending Grading</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card">
                        <i class="fas fa-bullhorn text-info"></i>
                        <h3><?php echo $total_announcements; ?></h3>
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
                <?php if (empty($courses)): ?>
                    <div class="col-12">
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i> You haven't created any courses yet. Click "Create New Course" to get started!
                        </div>
                    </div>
                <?php else: ?>
                    <?php foreach ($courses as $course): ?>
                        <div class="col-md-4">
                            <div class="course-card">
                                <div class="course-card-img">
                                    <i class="fas fa-<?php 
                                        echo match($course['category']) {
                                            'Programming' => 'code',
                                            'Database' => 'database',
                                            'Web Development' => 'globe',
                                            'Mobile Development' => 'mobile-alt',
                                            default => 'book'
                                        };
                                    ?>"></i>
                                </div>
                                <div class="course-card-body">
                                    <h3 class="course-card-title"><?php echo htmlspecialchars($course['title']); ?></h3>
                                    <p class="course-card-text"><?php echo htmlspecialchars(substr($course['description'], 0, 80)) . '...'; ?></p>
                                    <div class="mb-3">
                                        <small class="text-muted">Course Code: <?php echo htmlspecialchars($course['course_code']); ?></small>
                                    </div>
                                    <div class="course-meta">
                                        <span><i class="fas fa-user-friends"></i> <?php echo $course['enrolled_count']; ?> Students</span>
                                        <div>
                                            <a href="course_detail.php?id=<?php echo $course['id']; ?>" class="btn btn-sm btn-primary">Manage</a>
                                            <form method="POST" action="../controllers/course_controller.php" style="display:inline;">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="course_id" value="<?php echo $course['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger btn-delete" onclick="return confirm('Are you sure you want to delete this course?')">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
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
                        <?php if (empty($submissions)): ?>
                            <tr>
                                <td colspan="5" class="text-center text-muted">No pending submissions</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($submissions as $submission): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($submission['assignment_title']); ?></td>
                                    <td><?php echo htmlspecialchars($submission['course_title']); ?></td>
                                    <td><?php echo htmlspecialchars($submission['student_name']); ?></td>
                                    <td><?php 
                                        $time_ago = time() - strtotime($submission['submitted_at']);
                                        if ($time_ago < 3600) echo floor($time_ago / 60) . ' minutes ago';
                                        elseif ($time_ago < 86400) echo floor($time_ago / 3600) . ' hours ago';
                                        else echo floor($time_ago / 86400) . ' days ago';
                                    ?></td>
                                    <td>
                                        <a href="assignment.php?id=<?php echo $submission['assignment_id']; ?>" class="btn btn-sm btn-success">Grade</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
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

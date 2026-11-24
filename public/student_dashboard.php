<?php
require __DIR__ . '/../includes/bootstrap.php';
require_role('student');

$user_id = $_SESSION['user_id'];

// Get enrolled courses count
$enrolled_count = query("SELECT COUNT(*) as count FROM enrollments WHERE user_id = ?", [$user_id])->fetch()['count'];

// Get pending assignments count
$pending_assignments = query("SELECT COUNT(DISTINCT a.id) as count 
                              FROM assignments a
                              JOIN enrollments e ON a.course_id = e.course_id
                              LEFT JOIN submissions s ON a.id = s.assignment_id AND s.student_id = ?
                              WHERE e.user_id = ? AND s.id IS NULL", 
                              [$user_id, $user_id])->fetch()['count'];

// Get average grade
$avg_grade = student_average_percent((int) $user_id);

// Get enrolled courses
$enrolled_courses_stmt = query("SELECT c.*, u.name as instructor_name,
                                (SELECT COUNT(*) FROM enrollments WHERE course_id = c.id) as enrolled_count
                                FROM courses c
                                JOIN users u ON c.instructor_id = u.id
                                JOIN enrollments e ON c.id = e.course_id
                                WHERE e.user_id = ?
                                ORDER BY e.enrolled_at DESC", [$user_id]);
$enrolled_courses = $enrolled_courses_stmt->fetchAll();

// Get available courses (not enrolled)
$available_courses_stmt = query("SELECT c.*, u.name as instructor_name,
                                (SELECT COUNT(*) FROM enrollments WHERE course_id = c.id) as enrolled_count
                                FROM courses c
                                JOIN users u ON c.instructor_id = u.id
                                WHERE c.id NOT IN (SELECT course_id FROM enrollments WHERE user_id = ?)
                                ORDER BY c.created_at DESC
                                LIMIT 6", [$user_id]);
$available_courses = $available_courses_stmt->fetchAll();

// Latest announcements from enrolled courses
$recent_announcements = query("SELECT a.message, a.created_at, c.id AS course_id, c.title AS course_title
                               FROM announcements a
                               JOIN courses c ON c.id = a.course_id
                               JOIN enrollments e ON e.course_id = a.course_id AND e.user_id = ?
                               ORDER BY a.created_at DESC
                               LIMIT 5", [$user_id])->fetchAll();

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
            
<?php include __DIR__ . '/../includes/flash.php'; ?>
<!-- Stats Cards -->
            <div class="row g-4 mb-4">
                <div class="col-md-4">
                    <div class="stat-card">
                        <i class="fas fa-book text-primary"></i>
                        <h3><?php echo $enrolled_count; ?></h3>
                        <p>Enrolled Courses</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stat-card">
                        <i class="fas fa-tasks text-warning"></i>
                        <h3><?php echo $pending_assignments; ?></h3>
                        <p>Pending Assignments</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stat-card">
                        <i class="fas fa-chart-line text-success"></i>
                        <h3><?= $avg_grade === null ? '—' : e(round($avg_grade) . '%') ?></h3>
                        <p>Average Grade</p>
                    </div>
                </div>
            </div>

            <!-- Recent Announcements -->
            <?php if (!empty($recent_announcements)): ?>
                <div class="bg-white rounded shadow-custom p-4 mb-4">
                    <h2 class="h5 mb-3"><i class="fas fa-bullhorn text-primary"></i> Recent announcements</h2>
                    <ul class="list-unstyled mb-0">
                        <?php foreach ($recent_announcements as $item): ?>
                            <li class="border-bottom pb-2 mb-2">
                                <a href="<?= e(url('course_detail.php?id=' . $item['course_id'])) ?>" class="fw-semibold text-decoration-none"><?= e($item['course_title']) ?></a>
                                <small class="text-muted ms-2"><?= e(date('M d', strtotime($item['created_at']))) ?></small>
                                <p class="mb-0"><?= e($item['message']) ?></p>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <!-- My Enrolled Courses -->
            <div class="mb-4">
                <h2 class="h4 mb-3">My Courses</h2>
                
                <?php if (empty($enrolled_courses)): ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> You haven't enrolled in any courses yet. Browse available courses below!
                    </div>
                <?php else: ?>
                    <div class="row g-4 mb-5">
                        <?php foreach ($enrolled_courses as $course): ?>
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
                                            <small class="text-muted">
                                                <i class="fas fa-user"></i> <?php echo htmlspecialchars($course['instructor_name']); ?>
                                            </small>
                                        </div>
                                        <?php $progress = student_course_progress((int) $user_id, (int) $course['id']); ?>
                                        <div class="mb-3">
                                            <div class="d-flex justify-content-between small text-muted mb-1">
                                                <span>Progress</span>
                                                <span><?= $progress['submitted'] ?> of <?= $progress['total'] ?> assignments submitted</span>
                                            </div>
                                            <div class="progress" style="height: 8px;" role="progressbar"
                                                 aria-label="Assignments submitted in <?= e($course['title']) ?>"
                                                 aria-valuenow="<?= $progress['percent'] ?>" aria-valuemin="0" aria-valuemax="100">
                                                <div class="progress-bar" style="width: <?= $progress['percent'] ?>%"></div>
                                            </div>
                                        </div>
                                        <div class="course-meta">
                                            <span><i class="fas fa-users"></i> <?php echo $course['enrolled_count']; ?> Students</span>
                                            <a href="course_detail.php?id=<?php echo $course['id']; ?>" class="btn btn-sm btn-primary">
                                                Open Course
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Available Courses to Enroll -->
            <div class="mb-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h2 class="h4 mb-0">Available Courses</h2>
                    <a href="<?= e(url('courses.php')) ?>" class="btn btn-sm btn-outline-primary">Browse all courses <i class="fas fa-arrow-right"></i></a>
                </div>
                
                <?php if (empty($available_courses)): ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> No new courses available at the moment.
                    </div>
                <?php else: ?>
                    <div class="row g-4">
                        <?php foreach ($available_courses as $course): ?>
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
                                        <span class="badge bg-success position-absolute top-0 end-0 m-2">New</span>
                                    </div>
                                    <div class="course-card-body">
                                        <h3 class="course-card-title"><?php echo htmlspecialchars($course['title']); ?></h3>
                                        <p class="course-card-text"><?php echo htmlspecialchars(substr($course['description'], 0, 80)) . '...'; ?></p>
                                        <div class="mb-3">
                                            <small class="text-muted">
                                                <i class="fas fa-user"></i> <?php echo htmlspecialchars($course['instructor_name']); ?><br>
                                                <i class="fas fa-tag"></i> <?php echo htmlspecialchars($course['category']); ?>
                                            </small>
                                        </div>
                                        <div class="course-meta">
                                            <span>
                                                <i class="fas fa-users"></i> 
                                                <?php echo $course['enrolled_count']; ?>/<?php echo $course['enrollment_limit']; ?>
                                            </span>
                                            <div>
                                                <a href="course_detail.php?id=<?php echo $course['id']; ?>" class="btn btn-sm btn-outline-primary me-1">
                                                    <i class="fas fa-eye"></i> Preview
                                                </a>
                                                <form method="POST" action="actions/course.php" style="display:inline;">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="action" value="enroll">
                                                    <input type="hidden" name="course_id" value="<?php echo $course['id']; ?>">
                                                    <button type="submit" class="btn btn-sm btn-success" 
                                                            <?php echo $course['enrolled_count'] >= $course['enrollment_limit'] ? 'disabled' : ''; ?>>
                                                        <i class="fas fa-plus"></i> Enroll
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

        </div>
    </section>
</main>

<?php include __DIR__ . '/../includes/footer.php'; ?>

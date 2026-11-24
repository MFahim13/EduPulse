<?php
require __DIR__ . '/../includes/bootstrap.php';
require_login();

$page_title = "Course Detail";

// Get course ID from URL
$course_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($course_id == 0) {
    redirect('index.php');
}

// Fetch course details
$stmt = query("SELECT c.*, u.name as instructor_name, 
               (SELECT COUNT(*) FROM enrollments WHERE course_id = c.id) as enrolled_count
               FROM courses c 
               JOIN users u ON c.instructor_id = u.id 
               WHERE c.id = ?", [$course_id]);
$course = $stmt->fetch();

if (!$course) {
    flash('error', 'Course not found.');
    redirect(current_user()['role'] === 'instructor' ? 'instructor_dashboard.php' : 'student_dashboard.php');
}

$is_instructor = ($_SESSION['role'] == 'instructor' && $course['instructor_id'] == $_SESSION['user_id']);
$is_student = ($_SESSION['role'] == 'student');

// Check if student is enrolled
$is_enrolled = false;
if ($is_student) {
    $stmt = query("SELECT id FROM enrollments WHERE user_id = ? AND course_id = ?", [$_SESSION['user_id'], $course_id]);
    $is_enrolled = $stmt->rowCount() > 0;
}

// Get course modules
$modules_stmt = query("SELECT * FROM modules WHERE course_id = ? ORDER BY module_order", [$course_id]);
$modules = $modules_stmt->fetchAll();

// Load every material for the course in one query, then group by module
$materials_by_module = [];
$materials = query(
    "SELECT m.* FROM materials m JOIN modules mo ON mo.id = m.module_id WHERE mo.course_id = ? ORDER BY m.id",
    [$course_id]
)->fetchAll();
foreach ($materials as $material) {
    $materials_by_module[$material['module_id']][] = $material;
}
foreach ($modules as $i => $module) {
    $modules[$i]['materials'] = $materials_by_module[$module['id']] ?? [];
}

// Get assignments
$assignments_stmt = query("SELECT a.*, 
                          (SELECT COUNT(*) FROM submissions WHERE assignment_id = a.id) as submission_count
                          FROM assignments a 
                          WHERE a.course_id = ? 
                          ORDER BY a.due_date ASC", [$course_id]);
$assignments = $assignments_stmt->fetchAll();

// Get announcements
$announcements_stmt = query("SELECT * FROM announcements WHERE course_id = ? ORDER BY created_at DESC LIMIT 5", [$course_id]);
$announcements = $announcements_stmt->fetchAll();

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/navbar.php';
?>
<main>
    <section class="py-5">
        <div class="container">
            
<?php include __DIR__ . '/../includes/flash.php'; ?>
<!-- Course Header -->
            <div class="bg-white rounded shadow-custom p-4 mb-4">
                <div class="row">
                    <div class="col-md-8">
                        <h1 class="mb-3"><?php echo htmlspecialchars($course['title']); ?></h1>
                        <p class="lead text-muted"><?php echo htmlspecialchars($course['description']); ?></p>
                        <div class="d-flex gap-3 mb-3 flex-wrap">
                            <span class="badge bg-primary"><?php echo htmlspecialchars($course['category']); ?></span>
                            <span><i class="fas fa-user"></i> <?php echo htmlspecialchars($course['instructor_name']); ?></span>
                            <span><i class="fas fa-users"></i> <?php echo $course['enrolled_count']; ?> Students</span>
                            <span><i class="fas fa-code"></i> <?php echo htmlspecialchars($course['course_code']); ?></span>
                        </div>
                    </div>
                    <div class="col-md-4 text-md-end">
                        <?php if ($is_instructor): ?>
                            <button class="btn btn-primary mb-2" data-bs-toggle="modal" data-bs-target="#addModuleModal">
                                <i class="fas fa-plus"></i> Add Module
                            </button>
                            <button class="btn btn-outline-primary mb-2" data-bs-toggle="modal" data-bs-target="#addAssignmentModal">
                                <i class="fas fa-tasks"></i> Create Assignment
                            </button>
                            <button class="btn btn-outline-secondary mb-2" data-bs-toggle="modal" data-bs-target="#editCourseModal">
                                <i class="fas fa-pen"></i> Edit Course
                            </button>
                        <?php elseif ($is_student && !$is_enrolled): ?>
                            <form method="POST" action="actions/course.php">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="enroll">
                                <input type="hidden" name="course_id" value="<?php echo $course_id; ?>">
                                <button type="submit" class="btn btn-success btn-lg">
                                    <i class="fas fa-check"></i> Enroll Now
                                </button>
                            </form>
                        <?php elseif ($is_enrolled): ?>
                            <div class="alert alert-success">
                                <i class="fas fa-check-circle"></i> You are enrolled
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="row g-4">
                <!-- Course Content -->
                <div class="col-lg-8">
                    <!-- Course Modules -->
                    <div class="bg-white rounded shadow-custom p-4 mb-4">
                        <h2 class="h4 mb-4"><i class="fas fa-book-open"></i> Course Modules</h2>
                        
                        <?php if (empty($modules)): ?>
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle"></i> No modules have been added yet.
                            </div>
                        <?php else: ?>
                            <div class="accordion" id="courseModules">
                                <?php foreach ($modules as $index => $module): ?>
                                    <div class="accordion-item mb-3">
                                        <h2 class="accordion-header">
                                            <button class="accordion-button <?php echo $index > 0 ? 'collapsed' : ''; ?>" type="button" data-bs-toggle="collapse" data-bs-target="#module<?php echo $module['id']; ?>">
                                                <i class="fas fa-folder me-2"></i> 
                                                <?php echo htmlspecialchars($module['title']); ?>
                                            </button>
                                        </h2>
                                        <div id="module<?php echo $module['id']; ?>" class="accordion-collapse collapse <?php echo $index == 0 ? 'show' : ''; ?>" data-bs-parent="#courseModules">
                                            <div class="accordion-body">
                                                <?php if ($is_instructor): ?>
                                                    <form method="POST" action="<?= e(url('actions/course.php')) ?>" class="text-end mb-2">
                                                        <?= csrf_field() ?>
                                                        <input type="hidden" name="action" value="delete_module">
                                                        <input type="hidden" name="module_id" value="<?= (int) $module['id'] ?>">
                                                        <button type="submit" class="btn btn-sm btn-outline-danger" data-confirm="Delete this module and all of its materials?">
                                                            <i class="fas fa-trash-alt"></i> Delete module
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                                <?php if (empty($module['materials'])): ?>
                                                    <p class="text-muted">No materials uploaded yet.</p>
                                                <?php else: ?>
                                                    <ul class="list-unstyled">
                                                        <?php foreach ($module['materials'] as $material): ?>
                                                            <li class="mb-2 d-flex justify-content-between align-items-center gap-2">
                                                                <a href="<?= e(url('download.php?type=material&id=' . $material['id'])) ?>">
                                                                    <i class="fas fa-file-download"></i>
                                                                    <?= e($material['file_name']) ?>
                                                                </a>
                                                                <?php if ($is_instructor): ?>
                                                                    <form method="POST" action="<?= e(url('actions/course.php')) ?>">
                                                                        <?= csrf_field() ?>
                                                                        <input type="hidden" name="action" value="delete_material">
                                                                        <input type="hidden" name="material_id" value="<?= (int) $material['id'] ?>">
                                                                        <button type="submit" class="btn btn-link btn-sm text-danger p-0" data-confirm="Delete this file?" aria-label="Delete <?= e($material['file_name']) ?>">
                                                                            <i class="fas fa-times"></i>
                                                                        </button>
                                                                    </form>
                                                                <?php endif; ?>
                                                            </li>
                                                        <?php endforeach; ?>
                                                    </ul>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Assignments -->
                    <div class="bg-white rounded shadow-custom p-4 mb-4">
                        <h2 class="h4 mb-4"><i class="fas fa-tasks"></i> Assignments</h2>
                        
                        <?php if (empty($assignments)): ?>
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle"></i> No assignments posted yet.
                            </div>
                        <?php else: ?>
                            <div class="list-group">
                                <?php foreach ($assignments as $assignment): ?>
                                    <?php
                                    $now = time();
                                    $due = strtotime($assignment['due_date']);
                                    $is_overdue = $now > $due;
                                    ?>
                                    <a href="assignment.php?id=<?php echo $assignment['id']; ?>" class="list-group-item list-group-item-action">
                                        <div class="d-flex w-100 justify-content-between">
                                            <h5 class="mb-1"><?php echo htmlspecialchars($assignment['title']); ?></h5>
                                            <small class="text-<?php echo $is_overdue ? 'danger' : 'warning'; ?>">
                                                <i class="fas fa-clock"></i> Due: <?php echo date('M d, Y', $due); ?>
                                            </small>
                                        </div>
                                        <p class="mb-1"><?php echo substr(htmlspecialchars($assignment['description']), 0, 100); ?>...</p>
                                        <?php if ($is_instructor): ?>
                                            <small class="text-muted">
                                                <i class="fas fa-users"></i> <?php echo $assignment['submission_count']; ?> submissions
                                            </small>
                                        <?php endif; ?>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Sidebar -->
                <div class="col-lg-4">
                    <!-- Announcements -->
                    <div class="bg-white rounded shadow-custom p-4 mb-4">
                        <h5 class="mb-3"><i class="fas fa-bullhorn"></i> Announcements</h5>
                        <?php if ($is_instructor): ?>
                            <form method="POST" action="<?= e(url('actions/announcement.php')) ?>" class="mb-4">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="create">
                                <input type="hidden" name="course_id" value="<?= (int) $course_id ?>">
                                <label for="announcementMessage" class="form-label visually-hidden">New announcement</label>
                                <textarea class="form-control mb-2" id="announcementMessage" name="message" rows="3" maxlength="2000" placeholder="Share an update with your students…" required></textarea>
                                <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-paper-plane"></i> Post announcement</button>
                            </form>
                        <?php endif; ?>
                        <?php if (empty($announcements)): ?>
                            <p class="text-muted">No announcements yet.</p>
                        <?php else: ?>
                            <?php foreach ($announcements as $announcement): ?>
                                <div class="border-bottom pb-3 mb-3">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <small class="text-muted"><?= e(date('M d, Y', strtotime($announcement['created_at']))) ?></small>
                                        <?php if ($is_instructor): ?>
                                            <form method="POST" action="<?= e(url('actions/announcement.php')) ?>">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="announcement_id" value="<?= (int) $announcement['id'] ?>">
                                                <button type="submit" class="btn btn-link btn-sm text-danger p-0" data-confirm="Delete this announcement?" aria-label="Delete announcement">
                                                    <i class="fas fa-trash-alt"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                    <p class="mb-0"><?= nl2br(e($announcement['message'])) ?></p>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>

<!-- Add Module Modal -->
<div class="modal fade" id="addModuleModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add Course Module</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="actions/course.php" method="POST" enctype="multipart/form-data">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="add_module">
                <input type="hidden" name="course_id" value="<?php echo $course_id; ?>">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Module Title</label>
                        <input type="text" class="form-control" name="module_title" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Upload Materials (Optional)</label>
                        <input type="file" class="form-control" name="materials[]" multiple>
                        <small class="text-muted">You can upload multiple files</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Module</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Create Assignment Modal -->
<div class="modal fade" id="addAssignmentModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Create Assignment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="actions/assignment.php" method="POST" enctype="multipart/form-data">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="create">
                <input type="hidden" name="course_id" value="<?php echo $course_id; ?>">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Assignment Title</label>
                        <input type="text" class="form-control" name="title" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description/Instructions</label>
                        <textarea class="form-control" name="description" rows="4" required></textarea>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Due Date</label>
                                <input type="datetime-local" class="form-control" name="due_date" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Max Points</label>
                                <input type="number" class="form-control" name="max_points" value="100" min="1" max="1000" required>
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Attachment (Optional)</label>
                        <input type="file" class="form-control" name="assignment_file">
                        <small class="text-muted">Upload assignment instructions file if needed</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Assignment</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php if ($is_instructor): ?>
<!-- Edit Course Modal -->
<div class="modal fade" id="editCourseModal" tabindex="-1" aria-labelledby="editCourseTitle" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editCourseTitle">Edit Course</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="<?= e(url('actions/course.php')) ?>" method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="course_id" value="<?= (int) $course_id ?>">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label" for="editCourseName">Course Title</label>
                        <input type="text" class="form-control" id="editCourseName" name="title" value="<?= e($course['title']) ?>" required>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label" for="editCourseCode">Course Code</label>
                            <input type="text" class="form-control" id="editCourseCode" name="course_code" value="<?= e($course['course_code']) ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label" for="editCourseCategory">Category</label>
                            <select class="form-select" id="editCourseCategory" name="category" required>
                                <?php foreach (course_categories() as $category): ?>
                                    <option value="<?= e($category) ?>" <?= $category === $course['category'] ? 'selected' : '' ?>><?= e($category) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="editCourseDescription">Description</label>
                        <textarea class="form-control" id="editCourseDescription" name="description" rows="4" required><?= e($course['description']) ?></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="editCourseLimit">Enrollment Limit</label>
                        <input type="number" class="form-control" id="editCourseLimit" name="enrollment_limit" min="<?= max(1, (int) $course['enrolled_count']) ?>" max="500" value="<?= (int) $course['enrollment_limit'] ?>" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save changes</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>

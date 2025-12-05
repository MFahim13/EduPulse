<?php
include '../includes/auth_check.php';
$page_title = "Course Detail";
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/navbar.php';



// Get course ID from URL (later from database)
$course_id = $_GET['id'] ?? 1;
$is_instructor = ($_SESSION['role'] == 'instructor');
?>
<main>
    <section class="py-5">
        <div class="container">
            <!-- Course Header -->
            <div class="bg-white rounded shadow-custom p-4 mb-4">
                <div class="row">
                    <div class="col-md-8">
                        <h1 class="mb-3">Web Technologies</h1>
                        <p class="lead text-muted">Complete web development course covering HTML, CSS, JavaScript, PHP, and MySQL.</p>
                        <div class="d-flex gap-3 mb-3">
                            <span class="badge bg-primary">Programming</span>
                            <span><i class="fas fa-user"></i> Dr. Sarah Ahmed</span>
                            <span><i class="fas fa-users"></i> 45 Students</span>
                            <span><i class="fas fa-code"></i> CS401</span>
                        </div>
                    </div>
                    <div class="col-md-4 text-md-end">
                        <?php if (!$is_instructor): ?>
                            <button class="btn btn-success btn-lg btn-enroll">
                                <i class="fas fa-check"></i> Enroll Now
                            </button>
                            <div class="mt-3">
                                <div class="progress progress-custom">
                                    <div class="progress-bar bg-success progress-bar-custom" style="width: 65%">65%</div>
                                </div>
                                <small class="text-muted">Course Progress</small>
                            </div>
                        <?php else: ?>
                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addModuleModal">
                                <i class="fas fa-plus"></i> Add Module
                            </button>
                            <button class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#addAssignmentModal">
                                <i class="fas fa-tasks"></i> Add Assignment
                            </button>
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
                        
                        <!-- Module 1 -->
                        <div class="accordion" id="courseModules">
                            <div class="accordion-item mb-3">
                                <h2 class="accordion-header">
                                    <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#module1">
                                        <i class="fas fa-folder-open me-2"></i> Module 1: Introduction to HTML
                                    </button>
                                </h2>
                                <div id="module1" class="accordion-collapse collapse show" data-bs-parent="#courseModules">
                                    <div class="accordion-body">
                                        <ul class="list-group list-group-flush">
                                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                                <span><i class="fas fa-file-pdf text-danger"></i> HTML Basics.pdf</span>
                                                <a href="#" class="btn btn-sm btn-outline-primary">Download</a>
                                            </li>
                                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                                <span><i class="fas fa-file-pdf text-danger"></i> HTML Forms.pdf</span>
                                                <a href="#" class="btn btn-sm btn-outline-primary">Download</a>
                                            </li>
                                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                                <span><i class="fas fa-video text-primary"></i> Video Lecture</span>
                                                <a href="#" class="btn btn-sm btn-outline-primary">Watch</a>
                                            </li>
                                        </ul>
                                    </div>
                                </div>
                            </div>

                            <!-- Module 2 -->
                            <div class="accordion-item mb-3">
                                <h2 class="accordion-header">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#module2">
                                        <i class="fas fa-folder me-2"></i> Module 2: CSS Styling
                                    </button>
                                </h2>
                                <div id="module2" class="accordion-collapse collapse" data-bs-parent="#courseModules">
                                    <div class="accordion-body">
                                        <ul class="list-group list-group-flush">
                                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                                <span><i class="fas fa-file-pdf text-danger"></i> CSS Fundamentals.pdf</span>
                                                <a href="#" class="btn btn-sm btn-outline-primary">Download</a>
                                            </li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Assignments Section -->
                    <div class="bg-white rounded shadow-custom p-4">
                        <h2 class="h4 mb-4"><i class="fas fa-tasks"></i> Assignments</h2>
                        
                        <div class="assignment-item">
                            <div class="assignment-header">
                                <h5 class="assignment-title">Assignment 1: HTML Basics</h5>
                                <span class="badge bg-warning badge-deadline">
                                    <i class="fas fa-clock"></i> Due: Dec 10, 2025
                                </span>
                            </div>
                            <p class="mb-2">Create a personal portfolio website using HTML5 semantic elements.</p>
                            <div class="d-flex justify-content-between align-items-center">
                                <small class="text-muted">Posted: Dec 1, 2025</small>
                                <a href="assignment.php?id=1" class="btn btn-sm btn-primary">
                                    <?php echo $is_instructor ? 'View Submissions' : 'Submit'; ?>
                                </a>
                            </div>
                        </div>

                        <div class="assignment-item completed">
                            <div class="assignment-header">
                                <h5 class="assignment-title">Assignment 2: CSS Layout</h5>
                                <span class="badge bg-success badge-deadline">
                                    <i class="fas fa-check"></i> Submitted
                                </span>
                            </div>
                            <p class="mb-2">Design a responsive webpage using CSS Grid and Flexbox.</p>
                            <div class="d-flex justify-content-between align-items-center">
                                <small class="text-success">Grade: 85/100</small>
                                <a href="assignment.php?id=2" class="btn btn-sm btn-outline-primary">View Details</a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Sidebar -->
                <div class="col-lg-4">
                    <!-- Announcements -->
                    <div class="bg-white rounded shadow-custom p-4 mb-4">
                        <h5 class="mb-3"><i class="fas fa-bullhorn text-primary"></i> Announcements</h5>
                        <div class="border-start border-3 border-primary ps-3 mb-3">
                            <h6 class="mb-1">Midterm Schedule</h6>
                            <p class="small mb-1">Midterm exams will be held on December 15th. Please prepare accordingly.</p>
                            <small class="text-muted">2 days ago</small>
                        </div>
                        <div class="border-start border-3 border-info ps-3 mb-3">
                            <h6 class="mb-1">New Material Uploaded</h6>
                            <p class="small mb-1">Module 3 materials are now available in the course section.</p>
                            <small class="text-muted">5 days ago</small>
                        </div>
                    </div>

                    <!-- Discussion Forum Preview -->
                    <div class="bg-white rounded shadow-custom p-4">
                        <h5 class="mb-3"><i class="fas fa-comments text-success"></i> Discussion Forum</h5>
                        <div class="mb-3">
                            <div class="d-flex gap-2 mb-2">
                                <i class="fas fa-user-circle fa-2x text-muted"></i>
                                <div>
                                    <strong class="d-block">Ahmed Khan</strong>
                                    <small class="text-muted">How to center a div in CSS?</small>
                                </div>
                            </div>
                            <small class="text-muted">3 replies • 1 hour ago</small>
                        </div>
                        <button class="btn btn-outline-success btn-sm w-100">View All Discussions</button>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>

<!-- Add Module Modal (Instructor Only) -->
<?php if ($is_instructor): ?>
<div class="modal fade" id="addModuleModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New Module</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="../controllers/course_controller.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="add_module">
                <input type="hidden" name="course_id" value="<?php echo $course_id; ?>">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Module Title</label>
                        <input type="text" class="form-control" name="module_title" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Upload Materials</label>
                        <input type="file" class="form-control" name="materials[]" multiple>
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

<div class="modal fade" id="addAssignmentModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Create Assignment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="../controllers/assignment_controller.php" method="POST">
                <input type="hidden" name="action" value="create">
                <input type="hidden" name="course_id" value="<?php echo $course_id; ?>">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Assignment Title</label>
                        <input type="text" class="form-control" name="title" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" name="description" rows="3" required></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Due Date</label>
                        <input type="date" class="form-control" name="due_date" required>
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
<?php endif; ?>

<?php include '../includes/footer.php'; ?>

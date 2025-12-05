<?php
include '../includes/auth_check.php';
$page_title = "Assignment";
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/navbar.php';



$is_instructor = ($_SESSION['role'] == 'instructor');
$assignment_id = $_GET['id'] ?? 1;
?>
<main class="py-5">
    <div class="container">
        <!-- Assignment Details -->
        <div class="bg-white rounded shadow-custom p-4 mb-4">
            <div class="d-flex justify-content-between align-items-start mb-3">
                <div>
                    <h1 class="mb-2">Assignment 1: HTML Basics</h1>
                    <p class="text-muted mb-0">Web Technologies • CS401</p>
                </div>
                <span class="badge bg-warning fs-6">
                    <i class="fas fa-clock"></i> Due: Dec 10, 2025
                </span>
            </div>
            <hr>
            <h5>Instructions:</h5>
            <p>Create a personal portfolio website using HTML5 semantic elements. Your website should include:</p>
            <ul>
                <li>Header with navigation</li>
                <li>About section with your information</li>
                <li>Skills section</li>
                <li>Projects/Portfolio section</li>
                <li>Contact form</li>
                <li>Footer with social links</li>
            </ul>
            <p><strong>Submission Format:</strong> Upload a ZIP file containing all HTML files and assets.</p>
        </div>

        <?php if (!$is_instructor): ?>
            <!-- Student View: Submission Form -->
            <div class="bg-white rounded shadow-custom p-4">
                <h3 class="mb-4"><i class="fas fa-upload"></i> Submit Assignment</h3>
                
                <!-- Check if already submitted -->
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> You have not submitted this assignment yet.
                </div>

                <form action="../controllers/assignment_controller.php" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="submit">
                    <input type="hidden" name="assignment_id" value="<?php echo $assignment_id; ?>">
                    
                    <div class="mb-4">
                        <label class="form-label">Upload File (ZIP, PDF, or DOC)</label>
                        <input type="file" class="form-control" name="submission_file" required accept=".zip,.pdf,.doc,.docx">
                        <small class="text-muted">Maximum file size: 10MB</small>
                    </div>

                    <div class="mb-4">
                        <label class="form-label">Comments (Optional)</label>
                        <textarea class="form-control" name="comments" rows="3" placeholder="Add any notes for your instructor..."></textarea>
                    </div>

                    <button type="submit" class="btn btn-primary btn-lg">
                        <i class="fas fa-paper-plane"></i> Submit Assignment
                    </button>
                </form>

                <!-- Previous Submission (if exists) -->
                <!-- 
                <div class="mt-5">
                    <h4>Your Submission</h4>
                    <div class="alert alert-success">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <i class="fas fa-check-circle"></i> Submitted on Dec 8, 2025 at 3:45 PM
                                <br><small>File: portfolio_assignment.zip</small>
                            </div>
                            <a href="#" class="btn btn-sm btn-outline-primary">Download</a>
                        </div>
                    </div>
                    
                    <div class="bg-light p-3 rounded mt-3">
                        <h5>Feedback & Grade</h5>
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <strong class="text-success fs-4">85/100</strong>
                                <p class="mb-0 mt-2">Great work! Your HTML structure is clean and semantic. Consider adding more comments in your code.</p>
                            </div>
                        </div>
                    </div>
                </div>
                -->
            </div>

        <?php else: ?>
            <!-- Instructor View: Submissions Table -->
            <div class="bg-white rounded shadow-custom p-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h3><i class="fas fa-clipboard-list"></i> Student Submissions</h3>
                    <span class="badge bg-primary fs-6">12/45 Submitted</span>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>Student Name</th>
                                <th>Submitted On</th>
                                <th>File</th>
                                <th>Status</th>
                                <th>Grade</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>
                                    <i class="fas fa-user-circle text-primary me-2"></i>
                                    John Doe
                                </td>
                                <td>Dec 8, 2025<br><small class="text-muted">3:45 PM</small></td>
                                <td>
                                    <a href="#" class="text-decoration-none">
                                        <i class="fas fa-file-archive"></i> portfolio.zip
                                    </a>
                                </td>
                                <td><span class="badge bg-warning">Pending</span></td>
                                <td>-</td>
                                <td>
                                    <button class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#gradeModal1">
                                        <i class="fas fa-edit"></i> Grade
                                    </button>
                                </td>
                            </tr>
                            <tr>
                                <td>
                                    <i class="fas fa-user-circle text-primary me-2"></i>
                                    Jane Smith
                                </td>
                                <td>Dec 7, 2025<br><small class="text-muted">11:20 AM</small></td>
                                <td>
                                    <a href="#" class="text-decoration-none">
                                        <i class="fas fa-file-archive"></i> assignment1.zip
                                    </a>
                                </td>
                                <td><span class="badge bg-success">Graded</span></td>
                                <td><strong class="text-success">92/100</strong></td>
                                <td>
                                    <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#gradeModal2">
                                        <i class="fas fa-eye"></i> View
                                    </button>
                                </td>
                            </tr>
                            <tr>
                                <td>
                                    <i class="fas fa-user-circle text-primary me-2"></i>
                                    Ahmed Khan
                                </td>
                                <td>Dec 9, 2025<br><small class="text-muted">10:15 PM</small></td>
                                <td>
                                    <a href="#" class="text-decoration-none">
                                        <i class="fas fa-file-archive"></i> html_project.zip
                                    </a>
                                </td>
                                <td><span class="badge bg-danger">Late</span></td>
                                <td>-</td>
                                <td>
                                    <button class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#gradeModal3">
                                        <i class="fas fa-edit"></i> Grade
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    </div>
</main>

<!-- Grading Modal (Instructor Only) -->
<?php if ($is_instructor): ?>
<div class="modal fade" id="gradeModal1" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Grade Submission - John Doe</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="../controllers/assignment_controller.php" method="POST">
                <input type="hidden" name="action" value="grade">
                <input type="hidden" name="submission_id" value="1">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Download Submission</label>
                        <div>
                            <a href="#" class="btn btn-outline-primary btn-sm">
                                <i class="fas fa-download"></i> Download portfolio.zip
                            </a>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Grade (out of 100)</label>
                        <input type="number" class="form-control" name="grade" min="0" max="100" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Feedback</label>
                        <textarea class="form-control" name="feedback" rows="4" placeholder="Provide detailed feedback to the student..." required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">Submit Grade</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>

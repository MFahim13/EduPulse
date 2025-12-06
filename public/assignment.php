<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/db.php';

$assignment_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($assignment_id == 0) {
    header('Location: index.php');
    exit();
}

// Fetch assignment details
$stmt = query("SELECT a.*, c.title as course_title, c.instructor_id, u.name as instructor_name
               FROM assignments a
               JOIN courses c ON a.course_id = c.id
               JOIN users u ON c.instructor_id = u.id
               WHERE a.id = ?", [$assignment_id]);
$assignment = $stmt->fetch();

if (!$assignment) {
    $_SESSION['errors'] = ["Assignment not found"];
    header('Location: index.php');
    exit();
}

$is_instructor = ($_SESSION['role'] == 'instructor' && $assignment['instructor_id'] == $_SESSION['user_id']);
$is_student = ($_SESSION['role'] == 'student');

// Check if enrolled
$is_enrolled = false;
if ($is_student) {
    $stmt = query("SELECT id FROM enrollments WHERE user_id = ? AND course_id = ?", 
                  [$_SESSION['user_id'], $assignment['course_id']]);
    $is_enrolled = $stmt->rowCount() > 0;
}

// Get student's submission if exists
$my_submission = null;
if ($is_student && $is_enrolled) {
    $stmt = query("SELECT * FROM submissions WHERE assignment_id = ? AND student_id = ?", 
                  [$assignment_id, $_SESSION['user_id']]);
    $my_submission = $stmt->fetch();
}

// Get all submissions if instructor
$submissions = [];
if ($is_instructor) {
    $stmt = query("SELECT s.*, u.name as student_name, u.email as student_email
                   FROM submissions s
                   JOIN users u ON s.student_id = u.id
                   WHERE s.assignment_id = ?
                   ORDER BY s.submitted_at DESC", [$assignment_id]);
    $submissions = $stmt->fetchAll();
}

$page_title = htmlspecialchars($assignment['title']);
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/navbar.php';
?>
<main class="py-5">
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
        
        <!-- Assignment Header -->
        <div class="bg-white rounded shadow-custom p-4 mb-4">
            <div class="d-flex justify-content-between align-items-start mb-3">
                <div>
                    <h1><?php echo htmlspecialchars($assignment['title']); ?></h1>
                    <p class="text-muted mb-0">
                        <i class="fas fa-book"></i> <?php echo htmlspecialchars($assignment['course_title']); ?> | 
                        <i class="fas fa-user"></i> <?php echo htmlspecialchars($assignment['instructor_name']); ?>
                    </p>
                </div>
                <div>
                    <?php 
                    $now = time();
                    $due = strtotime($assignment['due_date']);
                    $is_overdue = $now > $due;
                    ?>
                    <span class="badge bg-<?php echo $is_overdue ? 'danger' : 'warning'; ?> fs-6">
                        <i class="fas fa-clock"></i> Due: <?php echo date('M d, Y h:i A', $due); ?>
                    </span>
                </div>
            </div>
            
            <hr>
            
            <h5>Instructions:</h5>
            <p><?php echo nl2br(htmlspecialchars($assignment['description'])); ?></p>
            
            <?php if ($assignment['file_path']): ?>
                <div class="mt-3">
                    <a href="<?php echo htmlspecialchars($assignment['file_path']); ?>" class="btn btn-outline-primary" download>
                        <i class="fas fa-download"></i> Download Assignment File
                    </a>
                </div>
            <?php endif; ?>
        </div>
        
        <?php if ($is_student && $is_enrolled): ?>
            <!-- Student Submission Section -->
            <div class="bg-white rounded shadow-custom p-4 mb-4">
                <h3 class="mb-4"><i class="fas fa-upload"></i> Your Submission</h3>
                
                <?php if ($my_submission): ?>
                    <!-- Already Submitted -->
                    <div class="alert alert-info">
                        <i class="fas fa-check-circle"></i> You submitted this assignment on 
                        <strong><?php echo date('M d, Y h:i A', strtotime($my_submission['submitted_at'])); ?></strong>
                    </div>
                    
                    <?php if ($my_submission['file_path']): ?>
                        <p><strong>Submitted File:</strong></p>
                        <a href="<?php echo htmlspecialchars($my_submission['file_path']); ?>" class="btn btn-sm btn-outline-primary mb-3" download>
                            <i class="fas fa-file-download"></i> Download Your Submission
                        </a>
                    <?php endif; ?>
                    
                    <?php if ($my_submission['grade'] !== null): ?>
                        <div class="alert alert-success mt-3">
                            <h5><i class="fas fa-star"></i> Grade: <?php echo htmlspecialchars($my_submission['grade']); ?>%</h5>
                            <?php if ($my_submission['feedback']): ?>
                                <hr>
                                <p class="mb-0"><strong>Feedback:</strong></p>
                                <p><?php echo nl2br(htmlspecialchars($my_submission['feedback'])); ?></p>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-warning">
                            <i class="fas fa-hourglass-half"></i> Your submission is pending grading.
                        </div>
                    <?php endif; ?>
                    
                <?php else: ?>
                    <!-- Submit Assignment Form -->
                    <?php if ($is_overdue): ?>
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle"></i> This assignment is overdue. Late submissions may receive reduced credit.
                        </div>
                    <?php endif; ?>
                    
                    <form action="../controllers/assignment_controller.php" method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="submit">
                        <input type="hidden" name="assignment_id" value="<?php echo $assignment_id; ?>">
                        
                        <div class="mb-3">
                            <label class="form-label">Upload Your Work</label>
                            <input type="file" class="form-control" name="submission_file" required>
                            <small class="text-muted">Supported formats: PDF, DOC, DOCX, ZIP. Max size: 10MB</small>
                        </div>
                        
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-upload"></i> Submit Assignment
                        </button>
                    </form>
                <?php endif; ?>
            </div>
            
        <?php elseif ($is_instructor): ?>
            <!-- Instructor: View All Submissions -->
            <div class="bg-white rounded shadow-custom p-4">
                <h3 class="mb-4"><i class="fas fa-list-check"></i> Student Submissions</h3>
                
                <?php if (empty($submissions)): ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> No submissions yet.
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>Student</th>
                                    <th>Submitted</th>
                                    <th>File</th>
                                    <th>Grade</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($submissions as $sub): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($sub['student_name']); ?></strong><br>
                                            <small class="text-muted"><?php echo htmlspecialchars($sub['student_email']); ?></small>
                                        </td>
                                        <td><?php echo date('M d, Y h:i A', strtotime($sub['submitted_at'])); ?></td>
                                        <td>
                                            <?php if ($sub['file_path']): ?>
                                                <a href="<?php echo htmlspecialchars($sub['file_path']); ?>" class="btn btn-sm btn-outline-primary" download>
                                                    <i class="fas fa-download"></i> Download
                                                </a>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($sub['grade'] !== null): ?>
                                                <span class="badge bg-success"><?php echo $sub['grade']; ?>%</span>
                                            <?php else: ?>
                                                <span class="badge bg-warning">Pending</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#gradeModal<?php echo $sub['id']; ?>">
                                                <i class="fas fa-edit"></i> Grade
                                            </button>
                                        </td>
                                    </tr>
                                    
                                    <!-- Grade Modal -->
                                    <div class="modal fade" id="gradeModal<?php echo $sub['id']; ?>" tabindex="-1">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title">Grade Submission</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <form action="../controllers/assignment_controller.php" method="POST">
                                                    <input type="hidden" name="action" value="grade">
                                                    <input type="hidden" name="submission_id" value="<?php echo $sub['id']; ?>">
                                                    <input type="hidden" name="assignment_id" value="<?php echo $assignment_id; ?>">
                                                    <div class="modal-body">
                                                        <p><strong>Student:</strong> <?php echo htmlspecialchars($sub['student_name']); ?></p>
                                                        <div class="mb-3">
                                                            <label class="form-label">Grade (0-100)</label>
                                                            <input type="number" class="form-control" name="grade" min="0" max="100" 
                                                                   value="<?php echo $sub['grade'] ?? ''; ?>" required>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label class="form-label">Feedback</label>
                                                            <textarea class="form-control" name="feedback" rows="4"><?php echo htmlspecialchars($sub['feedback'] ?? ''); ?></textarea>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                        <button type="submit" class="btn btn-primary">Save Grade</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        
    </div>
</main>
<?php include __DIR__ . '/../includes/footer.php'; ?>

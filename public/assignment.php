<?php
require __DIR__ . '/../includes/bootstrap.php';
require_login();

$assignment_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($assignment_id == 0) {
    redirect('index.php');
}

// Fetch assignment details
$stmt = query("SELECT a.*, c.title as course_title, c.instructor_id, u.name as instructor_name
               FROM assignments a
               JOIN courses c ON a.course_id = c.id
               JOIN users u ON c.instructor_id = u.id
               WHERE a.id = ?", [$assignment_id]);
$assignment = $stmt->fetch();

if (!$assignment) {
    flash('error', 'Assignment not found.');
    redirect(current_user()['role'] === 'instructor' ? 'instructor_dashboard.php' : 'student_dashboard.php');
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

$page_title = $assignment['title'];
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/navbar.php';
?>
<main class="py-5">
    <div class="container">
        
<?php include __DIR__ . '/../includes/flash.php'; ?>
<!-- Assignment Header -->
        <div class="bg-white rounded shadow-custom p-4 mb-4">
            <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
                <div class="min-w-0">
                    <h1 class="h2"><?php echo htmlspecialchars($assignment['title']); ?></h1>
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
                    <div class="mt-2 text-md-end small text-muted"><?= (int) $assignment['max_points'] ?> points</div>
                    <?php if ($is_instructor): ?>
                        <div class="mt-2 d-flex gap-2 justify-content-md-end">
                            <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#editAssignmentModal">
                                <i class="fas fa-pen"></i> Edit
                            </button>
                            <form method="POST" action="<?= e(url('actions/assignment.php')) ?>">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="assignment_id" value="<?= (int) $assignment['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger" data-confirm="Delete this assignment and every submission for it?">
                                    <i class="fas fa-trash-alt"></i> Delete
                                </button>
                            </form>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <hr>
            
            <h5>Instructions:</h5>
            <p><?php echo nl2br(htmlspecialchars($assignment['description'])); ?></p>
            
            <?php if ($assignment['file_path'] && can_view_course_content(current_user(), (int) $assignment['course_id'])): ?>
                <div class="mt-3">
                    <a href="<?= e(url('download.php?type=assignment&id=' . $assignment['id'])) ?>" class="btn btn-outline-primary">
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
                        <strong><?= e(date('M d, Y h:i A', strtotime($my_submission['submitted_at']))) ?></strong>
                        <?php if (is_late($my_submission['submitted_at'], $assignment['due_date'])): ?>
                            <span class="badge bg-danger late-badge ms-1">Late</span>
                        <?php endif; ?>
                    </div>
                    
                    <?php if ($my_submission['file_path']): ?>
                        <p><strong>Submitted File:</strong></p>
                        <a href="<?= e(url('download.php?type=submission&id=' . $my_submission['id'])) ?>" class="btn btn-sm btn-outline-primary mb-3">
                            <i class="fas fa-file-download"></i> Download Your Submission
                        </a>
                    <?php endif; ?>
                    
                    <?php if ($my_submission['grade'] !== null): ?>
                        <div class="alert alert-success mt-3">
                            <h5><i class="fas fa-star"></i> Grade: <?= e(format_grade((int) $my_submission['grade'], (int) $assignment['max_points'])) ?></h5>
                            <?php if ($my_submission['feedback']): ?>
                                <hr>
                                <p class="mb-0"><strong>Feedback:</strong></p>
                                <p><?php echo nl2br(htmlspecialchars($my_submission['feedback'])); ?></p>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-warning">
                            <i class="fas fa-hourglass-half"></i> Your submission is pending grading. You can replace your file until it's graded.
                        </div>
                        <form action="<?= e(url('actions/assignment.php')) ?>" method="POST" enctype="multipart/form-data" class="mt-3">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="submit">
                            <input type="hidden" name="assignment_id" value="<?= (int) $assignment_id ?>">
                            <label class="form-label" for="replacementFile">Replace your file</label>
                            <div class="input-group">
                                <input type="file" class="form-control" id="replacementFile" name="submission_file" accept=".pdf,.doc,.docx,.zip" required>
                                <button type="submit" class="btn btn-outline-primary"><i class="fas fa-sync-alt"></i> Resubmit</button>
                            </div>
                            <small class="text-muted">PDF, DOC, DOCX or ZIP · max 10 MB</small>
                        </form>
                    <?php endif; ?>
                    
                <?php else: ?>
                    <!-- Submit Assignment Form -->
                    <?php if ($is_overdue): ?>
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle"></i> This assignment is overdue. Late submissions may receive reduced credit.
                        </div>
                    <?php endif; ?>
                    
                    <form action="actions/assignment.php" method="POST" enctype="multipart/form-data">
                        <?= csrf_field() ?>
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
                                        <td>
                                            <?= e(date('M d, Y h:i A', strtotime($sub['submitted_at']))) ?>
                                            <?php if (is_late($sub['submitted_at'], $assignment['due_date'])): ?>
                                                <span class="badge bg-danger late-badge ms-1">Late</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($sub['file_path']): ?>
                                                <a href="<?= e(url('download.php?type=submission&id=' . $sub['id'])) ?>" class="btn btn-sm btn-outline-primary">
                                                    <i class="fas fa-download"></i> Download
                                                </a>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($sub['grade'] !== null): ?>
                                                <span class="badge bg-success"><?= e(format_grade((int) $sub['grade'], (int) $assignment['max_points'])) ?></span>
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
                                                <form action="actions/assignment.php" method="POST">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="action" value="grade">
                                                    <input type="hidden" name="submission_id" value="<?php echo $sub['id']; ?>">
                                                    <input type="hidden" name="assignment_id" value="<?php echo $assignment_id; ?>">
                                                    <div class="modal-body">
                                                        <p><strong>Student:</strong> <?php echo htmlspecialchars($sub['student_name']); ?></p>
                                                        <div class="mb-3">
                                                            <label class="form-label">Grade (0–<?= (int) $assignment['max_points'] ?> points)</label>
                                                            <input type="number" class="form-control" name="grade" min="0" max="<?= (int) $assignment['max_points'] ?>" 
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
<?php if ($is_instructor): ?>
<!-- Edit Assignment Modal -->
<div class="modal fade" id="editAssignmentModal" tabindex="-1" aria-labelledby="editAssignmentTitle" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editAssignmentTitle">Edit Assignment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="<?= e(url('actions/assignment.php')) ?>" method="POST" enctype="multipart/form-data">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="assignment_id" value="<?= (int) $assignment['id'] ?>">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label" for="editAssignmentName">Title</label>
                        <input type="text" class="form-control" id="editAssignmentName" name="title" value="<?= e($assignment['title']) ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="editAssignmentDescription">Instructions</label>
                        <textarea class="form-control" id="editAssignmentDescription" name="description" rows="5" required><?= e($assignment['description']) ?></textarea>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label" for="editAssignmentDue">Due date</label>
                            <input type="datetime-local" class="form-control" id="editAssignmentDue" name="due_date" value="<?= e(date('Y-m-d\TH:i', strtotime($assignment['due_date']))) ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label" for="editAssignmentPoints">Max points</label>
                            <input type="number" class="form-control" id="editAssignmentPoints" name="max_points" min="1" max="1000" value="<?= (int) $assignment['max_points'] ?>" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="editAssignmentFile">Replace attachment (optional)</label>
                        <input type="file" class="form-control" id="editAssignmentFile" name="assignment_file" accept=".pdf,.doc,.docx,.txt,.zip">
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

<?php
require __DIR__ . '/../includes/bootstrap.php';

$user = current_user();
$dashboard = $user ? ($user['role'] === 'student' ? 'student_dashboard.php' : 'instructor_dashboard.php') : null;

$page_title = 'Learning Management System';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/navbar.php';
?>
<main>
    <section class="landing-hero">
        <div class="container">
            <?php include __DIR__ . '/../includes/flash.php'; ?>
            <div class="row g-4 g-lg-5 align-items-center">
                <div class="col-lg-6">
                    <p class="eyebrow">Learning management system</p>
                    <h1>Courses, assignments and grades in one place.</h1>
                    <p class="lead">Instructors publish course material, set assignments and grade work with feedback. Students enroll, submit their files and follow their progress in every course.</p>
                    <div class="hero-actions">
                        <?php if ($dashboard): ?>
                            <a href="<?= e(url($dashboard)) ?>" class="btn btn-primary btn-lg">Go to your dashboard</a>
                        <?php else: ?>
                            <a href="<?= e(url('login.php')) ?>" class="btn btn-primary btn-lg">Try the demo</a>
                            <a href="<?= e(url('register.php')) ?>" class="btn btn-outline-primary btn-lg">Create an account</a>
                        <?php endif; ?>
                    </div>
                    <?php if (!$user && is_demo()): ?>
                        <p class="small text-muted mt-3 mb-0">Demo accounts are on the login page, one click each for a student and an instructor.</p>
                    <?php endif; ?>
                </div>
                <div class="col-lg-6">
                    <div class="hero-preview" aria-label="Preview of a student dashboard">
                        <div class="preview-row">
                            <strong>Welcome back, Ali</strong>
                            <span class="text-muted small">3 courses · 90% average</span>
                        </div>
                        <div class="preview-course">
                            <div class="d-flex justify-content-between align-items-baseline gap-2">
                                <strong>Introduction to Programming</strong>
                                <span class="small text-muted">CS101</span>
                            </div>
                            <div class="progress" style="height: 8px;" aria-hidden="true">
                                <div class="progress-bar" style="width: 67%"></div>
                            </div>
                            <div class="meta"><span>2 of 3 assignments submitted</span><span>Dr. Ayesha Khan</span></div>
                        </div>
                        <div class="preview-row">
                            <span>Loops Challenge</span>
                            <span class="badge bg-warning">Due in 5 days</span>
                        </div>
                        <div class="preview-row">
                            <span>ER Diagram for a Library</span>
                            <span class="badge bg-success">45 / 50 (90%)</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="landing-section">
        <div class="container">
            <h2>Built for both sides of the classroom</h2>
            <p class="section-intro">Each role gets its own dashboard and only sees what it is allowed to see.</p>
            <div class="row g-4">
                <div class="col-md-6">
                    <div class="role-card">
                        <h3><i class="fas fa-user-graduate"></i> Students</h3>
                        <ul class="check-list">
                            <li><i class="fas fa-check"></i> Browse and search courses, then enroll in one click</li>
                            <li><i class="fas fa-check"></i> Download lecture notes and lab sheets by module</li>
                            <li><i class="fas fa-check"></i> Submit work and replace the file until it is graded</li>
                            <li><i class="fas fa-check"></i> See grades out of each assignment's points, with feedback</li>
                            <li><i class="fas fa-check"></i> Track progress per course and read announcements</li>
                        </ul>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="role-card">
                        <h3><i class="fas fa-chalkboard-teacher"></i> Instructors</h3>
                        <ul class="check-list">
                            <li><i class="fas fa-check"></i> Create courses with modules and upload materials</li>
                            <li><i class="fas fa-check"></i> Set assignments with due dates, points and a brief</li>
                            <li><i class="fas fa-check"></i> Grade submissions, leave feedback and spot late work</li>
                            <li><i class="fas fa-check"></i> Post announcements to everyone enrolled</li>
                            <li><i class="fas fa-check"></i> Watch submission and grading rates for each course</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="landing-section pt-0">
        <div class="container">
            <div class="row g-4 align-items-start">
                <div class="col-lg-7">
                    <h2>Secure by default</h2>
                    <p class="section-intro mb-3">Every form is protected against cross-site request forgery, logins are rate-limited, and uploaded files are checked by content and stored outside the public web folder. Course files are only served to the people who are allowed to see them.</p>
                </div>
                <div class="col-lg-5">
                    <h2 class="h5">Built with</h2>
                    <div class="built-with">
                        <span>PHP 8.2</span><span>MySQL / MariaDB</span><span>Bootstrap 5</span><span>Docker</span><span>PHPUnit</span><span>GitHub Actions</span>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>
<?php include __DIR__ . '/../includes/footer.php'; ?>

<?php
require __DIR__ . '/../includes/bootstrap.php';

$user = require_login();

$q = trim((string) ($_GET['q'] ?? ''));
$category = (string) ($_GET['category'] ?? '');
if (!in_array($category, course_categories(), true)) {
    $category = '';
}

$where = [];
$params = [$user['id']];
if ($q !== '') {
    $like = '%' . addcslashes($q, '%_\\') . '%';
    $where[] = '(c.title LIKE ? OR c.course_code LIKE ? OR c.description LIKE ?)';
    array_push($params, $like, $like, $like);
}
if ($category !== '') {
    $where[] = 'c.category = ?';
    $params[] = $category;
}

$courses = query(
    'SELECT c.*, u.name AS instructor_name,
            (SELECT COUNT(*) FROM enrollments e WHERE e.course_id = c.id) AS enrolled_count,
            EXISTS(SELECT 1 FROM enrollments e WHERE e.course_id = c.id AND e.user_id = ?) AS is_enrolled
     FROM courses c JOIN users u ON u.id = c.instructor_id'
    . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
    . ' ORDER BY c.title',
    $params
)->fetchAll();

$category_icons = [
    'Programming' => 'code',
    'Database' => 'database',
    'Web Development' => 'globe',
    'Mobile Development' => 'mobile-alt',
];

$page_title = 'Browse Courses';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/navbar.php';
?>
<main>
    <section class="dashboard-header">
        <div class="container">
            <h1>Browse courses</h1>
            <p>Find a course by name, code or topic.</p>
        </div>
    </section>

    <section class="py-4">
        <div class="container">
            <?php include __DIR__ . '/../includes/flash.php'; ?>

            <form method="GET" action="<?= e(url('courses.php')) ?>" class="row g-2 mb-4" role="search">
                <div class="col-md-7">
                    <label for="courseQuery" class="visually-hidden">Search courses</label>
                    <input type="search" class="form-control" id="courseQuery" name="q" value="<?= e($q) ?>" placeholder="Search by title, code or description">
                </div>
                <div class="col-md-3">
                    <label for="courseCategory" class="visually-hidden">Category</label>
                    <select class="form-select" id="courseCategory" name="category">
                        <option value="">All categories</option>
                        <?php foreach (course_categories() as $option): ?>
                            <option value="<?= e($option) ?>" <?= $option === $category ? 'selected' : '' ?>><?= e($option) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2 d-grid">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Search</button>
                </div>
            </form>

            <?php if (empty($courses)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-search fa-2x text-muted mb-3"></i>
                    <p class="mb-2">No courses match your search.</p>
                    <a href="<?= e(url('courses.php')) ?>" class="btn btn-outline-primary btn-sm">Clear filters</a>
                </div>
            <?php else: ?>
                <p class="text-muted small"><?= count($courses) ?> course<?= count($courses) === 1 ? '' : 's' ?></p>
                <div class="row g-4">
                    <?php foreach ($courses as $course): ?>
                        <?php $full = (int) $course['enrolled_count'] >= (int) $course['enrollment_limit']; ?>
                        <div class="col-md-6 col-lg-4">
                            <div class="course-card h-100" data-category="<?= e($course['category']) ?>">
                                <div class="course-card-img">
                                    <i class="fas fa-<?= e($category_icons[$course['category']] ?? 'book') ?>"></i>
                                </div>
                                <div class="course-card-body">
                                    <span class="badge bg-light text-dark mb-2"><?= e($course['category']) ?> · <?= e($course['course_code']) ?></span>
                                    <h2 class="course-card-title h5"><?= e($course['title']) ?></h2>
                                    <p class="course-card-text"><?= e(mb_strimwidth($course['description'], 0, 110, '…')) ?></p>
                                    <p class="small text-muted mb-3">
                                        <i class="fas fa-user"></i> <?= e($course['instructor_name']) ?>
                                        · <i class="fas fa-users"></i> <?= (int) $course['enrolled_count'] ?>/<?= (int) $course['enrollment_limit'] ?>
                                    </p>
                                    <div class="d-flex gap-2">
                                        <a href="<?= e(url('course_detail.php?id=' . $course['id'])) ?>" class="btn btn-sm btn-outline-primary">View</a>
                                        <?php if ($user['role'] === 'student'): ?>
                                            <?php if ($course['is_enrolled']): ?>
                                                <span class="badge bg-success align-self-center"><i class="fas fa-check"></i> Enrolled</span>
                                            <?php elseif ($full): ?>
                                                <span class="badge bg-secondary align-self-center">Full</span>
                                            <?php else: ?>
                                                <form method="POST" action="<?= e(url('actions/course.php')) ?>">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="action" value="enroll">
                                                    <input type="hidden" name="course_id" value="<?= (int) $course['id'] ?>">
                                                    <button type="submit" class="btn btn-sm btn-success">Enroll</button>
                                                </form>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>
</main>
<?php include __DIR__ . '/../includes/footer.php'; ?>

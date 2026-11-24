<?php
$nav_user = current_user();
$nav_page = basename($_SERVER['SCRIPT_NAME'] ?? '');
$nav_link = static function (string $file, string $icon, string $label) use ($nav_page): string {
    $active = $nav_page === $file;
    return '<li class="nav-item"><a class="nav-link' . ($active ? ' active' : '') . '" href="' . e(url($file)) . '"'
        . ($active ? ' aria-current="page"' : '') . '><i class="fas ' . $icon . '"></i> ' . e($label) . '</a></li>';
};
?>
<nav class="navbar navbar-expand-lg site-nav">
    <div class="container">
        <a class="navbar-brand" href="<?= e(url('index.php')) ?>">
            <span class="brand-mark"><i class="fas fa-graduation-cap"></i></span> EduPulse
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto align-items-lg-center gap-lg-1">
                <?php if ($nav_user): ?>
                    <?= $nav_link($nav_user['role'] === 'student' ? 'student_dashboard.php' : 'instructor_dashboard.php', 'fa-gauge', 'Dashboard') ?>
                    <?= $nav_link('courses.php', 'fa-compass', 'Browse') ?>
                    <?= $nav_link('profile.php', 'fa-user', 'Profile') ?>
                    <li class="nav-item">
                        <form method="POST" action="<?= e(url('actions/auth.php')) ?>" class="d-inline">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="logout">
                            <button type="submit" class="nav-link btn btn-link">
                                <i class="fas fa-arrow-right-from-bracket"></i> Logout
                            </button>
                        </form>
                    </li>
                <?php else: ?>
                    <?= $nav_link('index.php', 'fa-house', 'Home') ?>
                    <?= $nav_link('login.php', 'fa-right-to-bracket', 'Login') ?>
                    <li class="nav-item ms-lg-2">
                        <a class="btn btn-primary btn-sm px-3" href="<?= e(url('register.php')) ?>">Sign up</a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>

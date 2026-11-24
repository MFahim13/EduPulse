<?php
require __DIR__ . '/../includes/bootstrap.php';
$page_title = "Login";
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/navbar.php';
?>
<main class="auth-container">
    <div class="auth-card">
        <h2>Login to EduPulse</h2>
        
<?php include __DIR__ . '/../includes/flash.php'; ?>
<form id="loginForm" action="actions/auth.php" method="POST">
    <?= csrf_field() ?>
            <input type="hidden" name="action" value="login">
            <div class="mb-3">
                <label class="form-label" for="email">Email</label>
                <input class="form-control" type="email" id="email" name="email" required>
            </div>
            <div class="mb-3">
                <label class="form-label" for="password">Password</label>
                <input class="form-control" type="password" id="password" name="password" required>
            </div>
            <div class="d-grid">
                <button class="btn btn-primary" type="submit">Login</button>
            </div>
            <p class="mt-3 text-center">
                Don't have an account? <a href="register.php">Register</a>
            </p>
        </form>
    </div>
</main>
<?php include __DIR__ . '/../includes/footer.php'; ?>

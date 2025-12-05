<?php
session_start();
$page_title = "Register";
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/navbar.php';

?>
<main class="auth-container">
    <div class="auth-card">
        <?php if (isset($_SESSION['errors'])): ?>
        <div class="alert alert-danger">
        <ul class="mb-0">
                <?php foreach($_SESSION['errors'] as $error): ?>
                <li><?php echo htmlspecialchars($error); ?></li>
            <?php endforeach; ?>
        </ul>
        </div>
                <?php unset($_SESSION['errors']); ?>
             <?php endif; ?>

        <h2>Create an Account</h2>
        <form id="registerForm" action="../controllers/auth_controller.php" method="POST">
            <input type="hidden" name="action" value="register">
            <div class="mb-3">
                <label class="form-label" for="name">Full Name</label>
                <input class="form-control" type="text" id="name" name="name" required>
            </div>
            <div class="mb-3">
                <label class="form-label" for="email">Email</label>
                <input class="form-control" type="email" id="email" name="email" required>
            </div>
            <div class="mb-3">
                <label class="form-label" for="role">Role</label>
                <select class="form-select" id="role" name="role" required>
                    <option value="">Select role</option>
                    <option value="student">Student</option>
                    <option value="instructor">Instructor</option>
                </select>
            </div>
            <div class="mb-3">
                <label class="form-label" for="password">Password</label>
                <input class="form-control" type="password" id="password" name="password" required>
            </div>
            <div class="mb-3">
                <label class="form-label" for="confirm_password">Confirm Password</label>
                <input class="form-control" type="password" id="confirm_password" name="confirm_password" required>
            </div>
            <div class="d-grid">
                <button class="btn btn-primary" type="submit">Register</button>
            </div>
            <p class="mt-3 text-center">
                Already have an account? <a href="login.php">Login</a>
            </p>
        </form>
    </div>
</main>
<?php include '../includes/footer.php'; ?>

<?php
session_start();
$page_title = "Login";
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/navbar.php';
?>
<main class="auth-container">
    <div class="auth-card">
        <h2>Login to EduPulse</h2>
        
        <!-- SUCCESS MESSAGE (Add this) -->
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <!-- ERROR MESSAGE -->
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger">
                <?php 
                echo htmlspecialchars($_SESSION['error']); 
                unset($_SESSION['error']);
                ?>
            </div>
        <?php endif; ?>
        
        <form id="loginForm" action="../controllers/auth_controller.php" method="POST">
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

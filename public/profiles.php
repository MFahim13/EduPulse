<?php
include '../includes/auth_check.php';
$page_title = "Profile";
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/navbar.php';

?>
<main class="py-5">
    <div class="container">
        <div class="row g-4">
            <!-- Profile Info Card -->
            <div class="col-lg-4">
                <div class="profile-card text-center">
                    <img src="https://via.placeholder.com/150" alt="Profile" class="profile-avatar">
                    <h2><?php echo htmlspecialchars($_SESSION['name'] ?? 'User'); ?></h2>
                    <p class="text-muted text-capitalize">
                        <i class="fas fa-user-tag"></i> <?php echo htmlspecialchars($_SESSION['role'] ?? 'Student'); ?>
                    </p>
                    <button class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#uploadPhotoModal">
                        <i class="fas fa-camera"></i> Change Photo
                    </button>
                </div>

                <!-- Quick Stats -->
                <div class="bg-white rounded shadow-custom p-4 mt-4">
                    <h5 class="mb-3">Quick Stats</h5>
                    <div class="d-flex justify-content-between mb-2">
                        <span>Courses</span>
                        <strong class="text-primary">5</strong>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span>Assignments</span>
                        <strong class="text-success">12</strong>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span>Average Grade</span>
                        <strong class="text-info">87%</strong>
                    </div>
                </div>
            </div>

            <!-- Profile Details & Settings -->
            <div class="col-lg-8">
                <!-- Personal Information -->
                <div class="bg-white rounded shadow-custom p-4 mb-4">
                    <h3 class="mb-4"><i class="fas fa-user-edit"></i> Personal Information</h3>
                    <form action="../controllers/profile_controller.php" method="POST">
                        <input type="hidden" name="action" value="update_profile">
                        
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Full Name</label>
                                <input type="text" class="form-control" name="name" value="<?php echo htmlspecialchars($_SESSION['name'] ?? ''); ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Email</label>
                                <input type="email" class="form-control" name="email" value="<?php echo htmlspecialchars($_SESSION['email'] ?? ''); ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Phone Number</label>
                                <input type="tel" class="form-control" name="phone" placeholder="+92 300 1234567">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Date of Birth</label>
                                <input type="date" class="form-control" name="dob">
                            </div>
                            <div class="col-12">
                                <label class="form-label">Bio</label>
                                <textarea class="form-control" name="bio" rows="3" placeholder="Tell us about yourself..."></textarea>
                            </div>
                        </div>

                        <div class="mt-4">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Save Changes
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Change Password -->
                <div class="bg-white rounded shadow-custom p-4 mb-4">
                    <h3 class="mb-4"><i class="fas fa-lock"></i> Change Password</h3>
                    <form action="../controllers/profile_controller.php" method="POST">
                        <input type="hidden" name="action" value="change_password">
                        
                        <div class="mb-3">
                            <label class="form-label">Current Password</label>
                            <input type="password" class="form-control" name="current_password" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">New Password</label>
                            <input type="password" class="form-control" name="new_password" id="new_password" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Confirm New Password</label>
                            <input type="password" class="form-control" name="confirm_password" id="confirm_password" required>
                        </div>

                        <button type="submit" class="btn btn-warning">
                            <i class="fas fa-key"></i> Update Password
                        </button>
                    </form>
                </div>

                <!-- Account Settings -->
                <div class="bg-white rounded shadow-custom p-4">
                    <h3 class="mb-4"><i class="fas fa-cog"></i> Account Settings</h3>
                    
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" id="emailNotifications" checked>
                        <label class="form-check-label" for="emailNotifications">
                            Email Notifications
                        </label>
                    </div>
                    
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" id="assignmentReminders" checked>
                        <label class="form-check-label" for="assignmentReminders">
                            Assignment Reminders
                        </label>
                    </div>
                    
                    <div class="form-check form-switch mb-4">
                        <input class="form-check-input" type="checkbox" id="courseUpdates" checked>
                        <label class="form-check-label" for="courseUpdates">
                            Course Updates
                        </label>
                    </div>

                    <hr>

                    <button class="btn btn-danger" onclick="return confirm('Are you sure you want to delete your account? This action cannot be undone.')">
                        <i class="fas fa-trash-alt"></i> Delete Account
                    </button>
                </div>
            </div>
        </div>
    </div>
</main>

<!-- Upload Photo Modal -->
<div class="modal fade" id="uploadPhotoModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Upload Profile Photo</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="../controllers/profile_controller.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="upload_photo">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Choose Photo</label>
                        <input type="file" class="form-control" name="profile_photo" accept="image/*" required>
                        <small class="text-muted">Supported formats: JPG, PNG. Max size: 2MB</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Upload</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>

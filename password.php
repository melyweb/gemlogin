<?php
require_once 'includes/functions.php';
require_once 'includes/auth.php';

// Require login to access this page
require_login();

$page_title = 'Change Password';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    $errors = [];
    
    // Validate current password is not empty
    if (empty($current_password)) {
        $errors[] = 'Current password is required.';
    }
    
    // Validate new password is not empty and meets requirements
    if (empty($new_password)) {
        $errors[] = 'New password is required.';
    } elseif (strlen($new_password) < 8) {
        $errors[] = 'New password must be at least 8 characters long.';
    }
    
    // Validate confirm password matches
    if ($new_password !== $confirm_password) {
        $errors[] = 'Passwords do not match.';
    }
    
    // If no errors, try to change password
    if (empty($errors)) {
        $user_id = $_SESSION['user_id'];
        
        if (change_password($user_id, $current_password, $new_password)) {
            set_flash_message('Password changed successfully.', 'success');
            redirect('settings.php');
        } else {
            $errors[] = 'Current password is incorrect.';
        }
    }
}

include 'includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3"><?php echo $page_title; ?></h1>
    <a href="settings.php" class="btn btn-secondary">
        <i class="fas fa-arrow-left me-2"></i> Back to Settings
    </a>
</div>

<div class="row">
    <div class="col-lg-6">
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold">Update Your Password</h6>
            </div>
            <div class="card-body">
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            <?php foreach ($errors as $error): ?>
                                <li><?php echo $error; ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
                
                <form action="password.php" method="post" id="password-form">
                    <div class="mb-3">
                        <label for="current_password" class="form-label form-required">Current Password</label>
                        <input type="password" class="form-control" id="current_password" name="current_password" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="new_password" class="form-label form-required">New Password</label>
                        <input type="password" class="form-control" id="new_password" name="new_password" minlength="8" required>
                        <div class="form-text">Password must be at least 8 characters long.</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="confirm_password" class="form-label form-required">Confirm New Password</label>
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                    </div>
                    
                    <div class="text-end">
                        <a href="settings.php" class="btn btn-secondary me-2">Cancel</a>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-key me-2"></i> Change Password
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-lg-6">
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold">Password Guidelines</h6>
            </div>
            <div class="card-body">
                <p>A strong password helps protect your account. Please follow these guidelines when creating your password:</p>
                
                <ul>
                    <li>Use at least 8 characters</li>
                    <li>Include a combination of letters, numbers, and special characters</li>
                    <li>Avoid using easily guessable information like birthdates or names</li>
                    <li>Don't reuse passwords from other websites</li>
                </ul>
                
                <div class="alert alert-info mb-0">
                    <i class="fas fa-info-circle me-2"></i> After changing your password, you will need to use the new password the next time you log in.
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Password match validation
    const form = document.getElementById('password-form');
    const newPassword = document.getElementById('new_password');
    const confirmPassword = document.getElementById('confirm_password');
    
    form.addEventListener('submit', function(event) {
        if (newPassword.value !== confirmPassword.value) {
            confirmPassword.setCustomValidity('Passwords do not match');
        } else {
            confirmPassword.setCustomValidity('');
        }
    });
    
    confirmPassword.addEventListener('input', function() {
        if (newPassword.value !== confirmPassword.value) {
            confirmPassword.setCustomValidity('Passwords do not match');
        } else {
            confirmPassword.setCustomValidity('');
        }
    });
});
</script>

<?php include 'includes/footer.php'; ?>

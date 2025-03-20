<?php
require_once 'includes/functions.php';
require_once 'includes/auth.php';

// Require login to access this page
require_login();

$page_title = 'Settings';

// Get user information
$user = get_logged_in_user();

// Get database connection
$db = db_connect();

// Get application version and API URL
$api_url = '';
$config = json_decode(file_get_contents('config.json'), true);
if ($config && isset($config['api_url'])) {
    $api_url = $config['api_url'];
}

// Get API status
$api_status = 'unknown';
$api_message = '';

$api = get_api();
try {
    $response = $api->ping();
    
    if (isset($response['success']) && $response['success']) {
        $api_status = 'connected';
        $api_message = $response['message'] ?? 'API connection successful';
    } else {
        $api_status = 'error';
        $api_message = $response['message'] ?? 'Unknown API error';
    }
} catch (Exception $e) {
    $api_status = 'error';
    $api_message = $e->getMessage();
}

include 'includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3"><?php echo $page_title; ?></h1>
    <a href="index.php" class="btn btn-secondary">
        <i class="fas fa-arrow-left me-2"></i> Back to Dashboard
    </a>
</div>

<div class="row">
    <div class="col-lg-4">
        <!-- Account Settings Card -->
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold">Account Settings</h6>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label">Username</label>
                    <input type="text" class="form-control-plaintext" value="<?php echo htmlspecialchars($user['username'] ?? ''); ?>" readonly>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Account Created</label>
                    <input type="text" class="form-control-plaintext" value="<?php echo isset($user['created_at']) ? format_datetime($user['created_at']) : 'Unknown'; ?>" readonly>
                </div>
                
                <div class="d-grid">
                    <a href="password.php" class="btn btn-primary">
                        <i class="fas fa-key me-2"></i> Change Password
                    </a>
                </div>
            </div>
        </div>
        
        <!-- API Settings Card -->
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold">API Settings</h6>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label">API URL</label>
                    <input type="text" class="form-control-plaintext" value="<?php echo htmlspecialchars($api_url); ?>" readonly>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">API Status</label>
                    <div>
                        <?php if ($api_status === 'connected'): ?>
                            <span class="badge bg-success">Connected</span>
                        <?php elseif ($api_status === 'error'): ?>
                            <span class="badge bg-danger">Error</span>
                        <?php else: ?>
                            <span class="badge bg-warning">Unknown</span>
                        <?php endif; ?>
                        <span class="text-muted ms-2"><?php echo htmlspecialchars($api_message); ?></span>
                    </div>
                </div>
                
                <div class="d-grid gap-2">
                    <a href="sync.php" class="btn btn-primary">
                        <i class="fas fa-sync-alt me-2"></i> Sync Data
                    </a>
                    <button type="button" class="btn btn-outline-secondary" id="config-edit-btn" data-bs-toggle="modal" data-bs-target="#configModal">
                        <i class="fas fa-cog me-2"></i> Edit API Configuration
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-lg-8">
        <!-- System Information Card -->
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold">System Information</h6>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label">PHP Version</label>
                            <input type="text" class="form-control-plaintext" value="<?php echo htmlspecialchars(PHP_VERSION); ?>" readonly>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Server Software</label>
                            <input type="text" class="form-control-plaintext" value="<?php echo htmlspecialchars($_SERVER['SERVER_SOFTWARE'] ?? 'Unknown'); ?>" readonly>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Database Type</label>
                            <input type="text" class="form-control-plaintext" value="MySQL" readonly>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label">Application Path</label>
                            <input type="text" class="form-control-plaintext" value="<?php echo htmlspecialchars(dirname($_SERVER['SCRIPT_FILENAME'])); ?>" readonly>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Server Time</label>
                            <input type="text" class="form-control-plaintext" value="<?php echo date('Y-m-d H:i:s'); ?>" readonly>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Server Timezone</label>
                            <input type="text" class="form-control-plaintext" value="<?php echo htmlspecialchars(date_default_timezone_get()); ?>" readonly>
                        </div>
                    </div>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Loaded PHP Extensions</label>
                    <textarea class="form-control form-control-sm" rows="3" readonly><?php echo htmlspecialchars(implode(', ', get_loaded_extensions())); ?></textarea>
                </div>
            </div>
        </div>
        
        <!-- Database Information Card -->
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold">Database Information</h6>
            </div>
            <div class="card-body">
                <div class="row">
                    <?php
                    // Get table counts
                    $tables = [
                        'profiles' => 'Profiles',
                        'scripts' => 'Scripts',
                        'schedules' => 'Schedules',
                        'schedule_logs' => 'Schedule Logs',
                        'users' => 'Users'
                    ];
                    
                    foreach ($tables as $table => $label):
                        $query = "SELECT COUNT(*) as count FROM {$table}";
                        $result = $db->query($query);
                        $count = $result ? $result->fetch_assoc()['count'] : 'Error';
                    ?>
                        <div class="col-md-4 mb-3">
                            <div class="card h-100">
                                <div class="card-body text-center">
                                    <h3 class="h5"><?php echo htmlspecialchars($label); ?></h3>
                                    <div class="display-4"><?php echo $count; ?></div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- API Configuration Modal -->
<div class="modal fade" id="configModal" tabindex="-1" aria-labelledby="configModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="configModalLabel">Edit API Configuration</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="config-form" action="ajax/save_config.php" method="post">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="api_url" class="form-label form-required">API URL</label>
                        <input type="url" class="form-control" id="api_url" name="api_url" value="<?php echo htmlspecialchars($api_url); ?>" required>
                        <div class="form-text">Enter the GemLogin API URL (e.g., http://localhost:1010)</div>
                    </div>
                    
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i> 
                        Changing these settings might affect the application's ability to connect to the API. Make sure you have the correct information.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Config form handling
    document.getElementById('config-form').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const apiUrl = document.getElementById('api_url').value;
        
        // Show loading state
        const submitBtn = this.querySelector('button[type="submit"]');
        const originalBtnText = submitBtn.innerHTML;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Saving...';
        submitBtn.disabled = true;
        
        // Save configuration via AJAX
        fetch('ajax/save_config.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                api_url: apiUrl
            })
        })
        .then(response => response.json())
        .then(data => {
            // Reset button state
            submitBtn.innerHTML = originalBtnText;
            submitBtn.disabled = false;
            
            if (data.success) {
                // Close modal and show success message
                var modal = bootstrap.Modal.getInstance(document.getElementById('configModal'));
                modal.hide();
                
                // Show success message with toastr
                toastr.success('Configuration saved successfully! The page will now reload.');
                setTimeout(function() {
                    window.location.reload();
                }, 1000);
            } else {
                // Show error message with toastr
                toastr.error('Error saving configuration: ' + (data.message || 'Unknown error'));
            }
        })
        .catch(error => {
            // Reset button state and show error with toastr
            submitBtn.innerHTML = originalBtnText;
            submitBtn.disabled = false;
            toastr.error('Error saving configuration: ' + error);
        });
    });
});
</script>

<?php include 'includes/footer.php'; ?>

<?php
require_once 'includes/functions.php';
require_once 'includes/auth.php';

// Require login to access this page
require_login();

$page_title = 'Sync Data';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sync_profiles = isset($_POST['sync_profiles']) && $_POST['sync_profiles'] === '1';
    $sync_scripts = isset($_POST['sync_scripts']) && $_POST['sync_scripts'] === '1';
    
    $success = true;
    $errors = [];
    
    if ($sync_profiles) {
        try {
            if (sync_profiles()) {
                set_flash_message('Profiles synchronized successfully.', 'success');
            } else {
                $success = false;
                $errors[] = 'Failed to synchronize profiles.';
            }
        } catch (Exception $e) {
            $success = false;
            $errors[] = 'Error synchronizing profiles: ' . $e->getMessage();
        }
    }
    
    if ($sync_scripts) {
        try {
            if (sync_scripts()) {
                set_flash_message('Scripts synchronized successfully.', 'success');
            } else {
                $success = false;
                $errors[] = 'Failed to synchronize scripts.';
            }
        } catch (Exception $e) {
            $success = false;
            $errors[] = 'Error synchronizing scripts: ' . $e->getMessage();
        }
    }
    
    if (!$sync_profiles && !$sync_scripts) {
        set_flash_message('Please select at least one data type to synchronize.', 'warning');
    } elseif (!$success) {
        foreach ($errors as $error) {
            set_flash_message($error, 'danger');
        }
    }
    
    // Redirect to avoid resubmission
    redirect('sync.php');
}

// Get database connection
$db = db_connect();

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

// Get last sync times
$query = "SELECT MAX(last_synced) as profiles_last_synced FROM profiles";
$result = $db->query($query);
$profiles_last_synced = $result->fetch_assoc()['profiles_last_synced'] ?? null;

$query = "SELECT MAX(last_synced) as scripts_last_synced FROM scripts";
$result = $db->query($query);
$scripts_last_synced = $result->fetch_assoc()['scripts_last_synced'] ?? null;

// Get stats
$query = "SELECT COUNT(*) as profile_count FROM profiles";
$result = $db->query($query);
$profile_count = $result->fetch_assoc()['profile_count'] ?? 0;

$query = "SELECT COUNT(*) as script_count FROM scripts";
$result = $db->query($query);
$script_count = $result->fetch_assoc()['script_count'] ?? 0;

include 'includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3"><?php echo $page_title; ?></h1>
    <a href="index.php" class="btn btn-secondary">
        <i class="fas fa-arrow-left me-2"></i> Back to Dashboard
    </a>
</div>

<!-- API Status Card -->
<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold">API Connection Status</h6>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-6">
                <div class="d-flex align-items-center mb-3">
                    <div class="me-3">
                        <?php if ($api_status === 'connected'): ?>
                            <div class="bg-success text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 48px; height: 48px;">
                                <i class="fas fa-check fa-lg"></i>
                            </div>
                        <?php elseif ($api_status === 'error'): ?>
                            <div class="bg-danger text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 48px; height: 48px;">
                                <i class="fas fa-times fa-lg"></i>
                            </div>
                        <?php else: ?>
                            <div class="bg-warning text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 48px; height: 48px;">
                                <i class="fas fa-question fa-lg"></i>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div>
                        <h5 class="mb-1">
                            <?php if ($api_status === 'connected'): ?>
                                <span class="text-success">Connected</span>
                            <?php elseif ($api_status === 'error'): ?>
                                <span class="text-danger">Connection Error</span>
                            <?php else: ?>
                                <span class="text-warning">Unknown Status</span>
                            <?php endif; ?>
                        </h5>
                        <div class="text-muted"><?php echo htmlspecialchars($api_message); ?></div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="row">
                    <div class="col-6">
                        <div class="border rounded p-3 text-center h-100">
                            <h3 class="mb-0"><?php echo $profile_count; ?></h3>
                            <div class="text-muted">Profiles</div>
                            <?php if ($profiles_last_synced): ?>
                                <small class="text-muted">Last synced: <?php echo format_datetime($profiles_last_synced); ?></small>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="border rounded p-3 text-center h-100">
                            <h3 class="mb-0"><?php echo $script_count; ?></h3>
                            <div class="text-muted">Scripts</div>
                            <?php if ($scripts_last_synced): ?>
                                <small class="text-muted">Last synced: <?php echo format_datetime($scripts_last_synced); ?></small>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Sync Card -->
<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold">Sync Data from API</h6>
    </div>
    <div class="card-body">
        <p class="mb-4">Synchronize your local database with the GemLogin API. This will update all profiles and scripts with the latest data from the API.</p>
        
        <form action="sync.php" method="post">
            <div class="mb-4">
                <div class="form-check mb-2">
                    <input class="form-check-input" type="checkbox" value="1" id="sync_profiles" name="sync_profiles" checked>
                    <label class="form-check-label" for="sync_profiles">
                        <strong>Sync Profiles</strong>
                        <div class="text-muted">Update profiles from GemLogin API</div>
                    </label>
                </div>
                
                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" value="1" id="sync_scripts" name="sync_scripts" checked>
                    <label class="form-check-label" for="sync_scripts">
                        <strong>Sync Scripts</strong>
                        <div class="text-muted">Update scripts from GemLogin API</div>
                    </label>
                </div>
            </div>
            
            <div class="d-grid gap-2 d-md-flex justify-content-md-start">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-sync-alt me-2"></i> Start Synchronization
                </button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Enable tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl)
    });
});
</script>

<?php include 'includes/footer.php'; ?>

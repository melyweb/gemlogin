<?php
require_once 'includes/functions.php';
require_once 'includes/auth.php';

// Require login to access this page
require_login();

// Set page title and get database connection
$page_title = 'View Profile';
$db = db_connect();
$api = get_api();

// Check if profile ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    set_flash_message('Invalid profile ID.', 'danger');
    redirect('profiles.php');
}

$profile_id = (int)$_GET['id'];

// Get profile from database
$stmt = $db->prepare("SELECT * FROM profiles WHERE id = ?");
$stmt->bind_param("i", $profile_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    set_flash_message('Profile not found.', 'danger');
    redirect('profiles.php');
}

$profile = $result->fetch_assoc();

// Get real-time data from API if needed
$api_profile = null;
try {
    $response = $api->getProfile($profile_id);
    if (isset($response['success']) && $response['success']) {
        $api_profile = $response['data'] ?? null;
    }
} catch (Exception $e) {
    // Just continue, we'll use local data
}

// Get group information
$group_name = 'None';
if ($profile['group_id']) {
    $groups_response = $api->getGroups();
    if (isset($groups_response['success']) && $groups_response['success']) {
        $groups = $groups_response['data'] ?? [];
        foreach ($groups as $group) {
            if ((int)$group['id'] === (int)$profile['group_id']) {
                $group_name = $group['name'];
                break;
            }
        }
    }
}

include 'includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3">Profile: <?php echo htmlspecialchars($profile['name']); ?></h1>
    <div class="btn-group">
        <a href="profile_edit.php?id=<?php echo $profile['id']; ?>" class="btn btn-primary">
            <i class="fas fa-edit me-2"></i> Edit Profile
        </a>
        <a href="profiles.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left me-2"></i> Back to Profiles
        </a>
    </div>
</div>

<div class="row">
    <div class="col-lg-8">
        <!-- Profile Details Card -->
        <div class="card shadow mb-4">
            <div class="card-header py-3 d-flex justify-content-between align-items-center">
                <h6 class="m-0 font-weight-bold">Profile Details</h6>
                
                <!-- Quick action buttons -->
                <div class="btn-group">
                    <button type="button" class="btn btn-sm btn-success start-profile" data-id="<?php echo $profile['id']; ?>">
                        <i class="fas fa-play me-1"></i> Start Profile
                    </button>
                    <button type="button" class="btn btn-sm btn-warning change-fingerprint" data-id="<?php echo $profile['id']; ?>">
                        <i class="fas fa-fingerprint me-1"></i> Change Fingerprint
                    </button>
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <tbody>
                            <tr>
                                <th style="width: 200px;">ID</th>
                                <td><?php echo $profile['id']; ?></td>
                            </tr>
                            <tr>
                                <th>Name</th>
                                <td><?php echo htmlspecialchars($profile['name']); ?></td>
                            </tr>
                            <tr>
                                <th>Group</th>
                                <td><?php echo htmlspecialchars($group_name); ?></td>
                            </tr>
                            <tr>
                                <th>Browser</th>
                                <td>
                                    <?php 
                                    echo htmlspecialchars($profile['browser_type'] ?: 'Not specified'); 
                                    if (!empty($profile['browser_version'])) {
                                        echo ' (' . htmlspecialchars($profile['browser_version']) . ')';
                                    }
                                    ?>
                                </td>
                            </tr>
                            <tr>
                                <th>Proxy</th>
                                <td>
                                    <?php if (!empty($profile['proxy'])): ?>
                                        <code><?php echo htmlspecialchars($profile['proxy']); ?></code>
                                    <?php else: ?>
                                        <span class="text-muted">None</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <th>Notes</th>
                                <td>
                                    <?php if (!empty($profile['note'])): ?>
                                        <div class="notes-content"><?php echo nl2br(htmlspecialchars($profile['note'])); ?></div>
                                    <?php else: ?>
                                        <span class="text-muted">No notes</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <th>Last Synced</th>
                                <td><?php echo format_datetime($profile['last_synced']); ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- API Status Card (if data available) -->
        <?php if ($api_profile): ?>
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold">API Status</h6>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <tbody>
                            <tr>
                                <th style="width: 200px;">Status</th>
                                <td>
                                    <?php if (isset($api_profile['status'])): ?>
                                        <span class="badge bg-<?php echo $api_profile['status'] === 'active' ? 'success' : 'secondary'; ?>">
                                            <?php echo ucfirst($api_profile['status']); ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Unknown</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php if (isset($api_profile['last_used'])): ?>
                            <tr>
                                <th>Last Used</th>
                                <td><?php echo format_datetime($api_profile['last_used']); ?></td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <div class="col-lg-4">
        <!-- Quick Actions Card -->
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold">Actions</h6>
            </div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <a href="profile_edit.php?id=<?php echo $profile['id']; ?>" class="btn btn-primary">
                        <i class="fas fa-edit me-2"></i> Edit Profile
                    </a>
                    <button type="button" class="btn btn-success start-profile-custom" data-id="<?php echo $profile['id']; ?>" data-bs-toggle="modal" data-bs-target="#startProfileModal">
                        <i class="fas fa-play me-2"></i> Start with Custom URL
                    </button>
                    <a href="javascript:void(0);" class="btn btn-warning change-fingerprint" data-id="<?php echo $profile['id']; ?>">
                        <i class="fas fa-fingerprint me-2"></i> Change Fingerprint
                    </a>
                    <a href="profiles.php?delete=<?php echo $profile['id']; ?>" class="btn btn-danger" onclick="return confirm('Are you sure you want to delete this profile?');">
                        <i class="fas fa-trash-alt me-2"></i> Delete Profile
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Start Profile Modal -->
<div class="modal fade" id="startProfileModal" tabindex="-1" aria-labelledby="startProfileModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="startProfileModalLabel">Start Profile with Custom URL</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="startProfileForm">
                    <input type="hidden" id="profile_id" value="<?php echo $profile['id']; ?>">
                    <div class="mb-3">
                        <label for="url" class="form-label">URL</label>
                        <input type="text" class="form-control" id="url" value="https://google.com">
                    </div>
                    <div class="mb-3">
                        <label for="win_size" class="form-label">Window Size (width,height)</label>
                        <input type="text" class="form-control" id="win_size" value="1280,720">
                    </div>
                    <div class="mb-3">
                        <label for="win_pos" class="form-label">Window Position (x,y) (optional)</label>
                        <input type="text" class="form-control" id="win_pos" placeholder="100,100">
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="startProfileBtn">Start Profile</button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Simple start profile
    document.querySelectorAll('.start-profile').forEach(function(button) {
        button.addEventListener('click', function() {
            const profileId = this.getAttribute('data-id');
            
            // Show loading state
            this.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Starting...';
            this.disabled = true;
            
            // Make AJAX call to start the profile
            fetch('ajax/start_profile.php?profile_id=' + profileId)
                .then(response => response.json())
                .then(data => {
                    // Reset button state
                    this.innerHTML = '<i class="fas fa-play me-1"></i> Start Profile';
                    this.disabled = false;
                    
                    if (data.success) {
                        // Show success message with toastr
                        toastr.success('Profile started successfully!');
                    } else {
                        // Show error message with toastr
                        toastr.error('Error starting profile: ' + (data.message || 'Unknown error'));
                    }
                })
                .catch(error => {
                    // Reset button state and show error with toastr
                    this.innerHTML = '<i class="fas fa-play me-1"></i> Start Profile';
                    this.disabled = false;
                    toastr.error('Error starting profile: ' + error);
                });
        });
    });
    
    // Custom start profile
    document.getElementById('startProfileBtn').addEventListener('click', function() {
        const profileId = document.getElementById('profile_id').value;
        const url = document.getElementById('url').value;
        const winSize = document.getElementById('win_size').value;
        const winPos = document.getElementById('win_pos').value;
        
        // Show loading state
        this.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Starting...';
        this.disabled = true;
        
        // Build query string
        let queryString = `profile_id=${profileId}&url=${encodeURIComponent(url)}&win_size=${encodeURIComponent(winSize)}`;
        if (winPos) {
            queryString += `&win_pos=${encodeURIComponent(winPos)}`;
        }
        
        // Make AJAX call to start the profile
        fetch('ajax/start_profile.php?' + queryString)
            .then(response => response.json())
            .then(data => {
                // Reset button state
                this.innerHTML = 'Start Profile';
                this.disabled = false;
                
                if (data.success) {
                    // Close modal and show success message with toastr
                    var modal = bootstrap.Modal.getInstance(document.getElementById('startProfileModal'));
                    modal.hide();
                    toastr.success('Profile started successfully!');
                } else {
                    // Show error message with toastr
                    toastr.error('Error starting profile: ' + (data.message || 'Unknown error'));
                }
            })
            .catch(error => {
                // Reset button state and show error with toastr
                this.innerHTML = 'Start Profile';
                this.disabled = false;
                toastr.error('Error starting profile: ' + error);
            });
    });
    
    // Create the fingerprint change confirmation modal dynamically
    const modalHtml = `
    <div class="modal fade" id="fingerprintChangeModal" tabindex="-1" aria-labelledby="fingerprintChangeModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="fingerprintChangeModalLabel">Confirm Fingerprint Change</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to change the fingerprint for this profile?</p>
                    <p class="text-muted small">Changing the fingerprint will modify the browser's signature and may affect how websites identify your browser.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-warning" id="confirmFingerprintChange">Change Fingerprint</button>
                </div>
            </div>
        </div>
    </div>
    `;
    
    // Add modal to document if it doesn't exist
    if (!document.getElementById('fingerprintChangeModal')) {
        document.body.insertAdjacentHTML('beforeend', modalHtml);
    }
    
    // Change fingerprint
    document.querySelectorAll('.change-fingerprint').forEach(function(button) {
        button.addEventListener('click', function() {
            const profileId = this.getAttribute('data-id');
            const changeButton = this;
            
            // Show the modal
            const modal = new bootstrap.Modal(document.getElementById('fingerprintChangeModal'));
            modal.show();
            
            // Handle confirm button click
            document.getElementById('confirmFingerprintChange').onclick = function() {
                // Hide the modal
                modal.hide();
                
                // Show loading state
                changeButton.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Changing...';
                changeButton.disabled = true;
                
                // Make AJAX request to change fingerprint
                fetch('ajax/change_fingerprint.php?profile_id=' + profileId)
                    .then(response => response.json())
                    .then(data => {
                        // Reset button state
                        changeButton.innerHTML = '<i class="fas fa-fingerprint me-1"></i> Change Fingerprint';
                        changeButton.disabled = false;
                        
                        if (data.success) {
                            // Show success message with toastr
                            toastr.success('Fingerprint changed successfully!');
                        } else {
                            // Show error message with toastr
                            toastr.error('Error changing fingerprint: ' + (data.message || 'Unknown error'));
                        }
                    })
                    .catch(error => {
                        // Reset button state and show error with toastr
                        changeButton.innerHTML = '<i class="fas fa-fingerprint me-1"></i> Change Fingerprint';
                        changeButton.disabled = false;
                        toastr.error('Error changing fingerprint: ' + error);
                    });
            }
        });
    });
});
</script>

<?php include 'includes/footer.php'; ?>

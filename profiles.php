<?php
require_once 'includes/functions.php';
require_once 'includes/auth.php';

// Require login to access this page
require_login();

$page_title = 'Profiles Management';

// Get database connection
$db = db_connect();

// Handle profile sync request
if (isset($_GET['sync']) && $_GET['sync'] == 1) {
    if (sync_profiles()) {
        set_flash_message('Profiles synchronized successfully.', 'success');
    }
    redirect('profiles.php');
}

// Handle profile delete request
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $profile_id = (int)$_GET['delete'];
    
    // Use the API to delete the profile
    $api = get_api();
    $response = $api->deleteProfile($profile_id);
    
    if (isset($response['success']) && $response['success']) {
        // Delete from local database
        $stmt = $db->prepare("DELETE FROM profiles WHERE id = ?");
        $stmt->bind_param("i", $profile_id);
        
        if ($stmt->execute()) {
            set_flash_message('Profile deleted successfully.', 'success');
        } else {
            set_flash_message('Error deleting profile from database: ' . $stmt->error, 'danger');
        }
    } else {
        set_flash_message('Error deleting profile from API: ' . ($response['message'] ?? 'Unknown error'), 'danger');
    }
    
    redirect('profiles.php');
}

// Change fingerprint for selected profiles
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'change_fingerprint') {
    if (isset($_POST['profile_ids']) && is_array($_POST['profile_ids']) && !empty($_POST['profile_ids'])) {
        $profile_ids = array_filter($_POST['profile_ids'], 'is_numeric');
        
        if (!empty($profile_ids)) {
            $api = get_api();
            $response = $api->changeFingerprint($profile_ids);
            
            if (isset($response['success']) && $response['success']) {
                set_flash_message('Fingerprint changed successfully for selected profiles.', 'success');
            } else {
                set_flash_message('Error changing fingerprint: ' . ($response['message'] ?? 'Unknown error'), 'danger');
            }
        } else {
            set_flash_message('No valid profile IDs provided.', 'warning');
        }
    } else {
        set_flash_message('Please select at least one profile.', 'warning');
    }
    
    redirect('profiles.php');
}

// Get filters from query string
$search = isset($_GET['search']) ? clean_input($_GET['search']) : '';
$group_id = isset($_GET['group_id']) && is_numeric($_GET['group_id']) ? (int)$_GET['group_id'] : null;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$sort = isset($_GET['sort']) && in_array($_GET['sort'], [0, 1, 2, 3]) ? (int)$_GET['sort'] : 0;

// Get profiles from database
$query = "SELECT p.* FROM profiles p";
$params = [];
$types = "";

// Build WHERE clause based on filters
$conditions = [];

if (!empty($search)) {
    $conditions[] = "p.name LIKE ?";
    $params[] = "%$search%";
    $types .= "s";
}

if ($group_id !== null) {
    $conditions[] = "p.group_id = ?";
    $params[] = $group_id;
    $types .= "i";
}

if (!empty($conditions)) {
    $query .= " WHERE " . implode(" AND ", $conditions);
}

// Add ORDER BY clause
$query .= match($sort) {
    0 => " ORDER BY p.id DESC", // Newest first
    1 => " ORDER BY p.id ASC",  // Oldest first
    2 => " ORDER BY p.name ASC", // Name A-Z
    3 => " ORDER BY p.name DESC", // Name Z-A
    default => " ORDER BY p.id DESC"
};

// Count total profiles for pagination
$count_query = "SELECT COUNT(*) as count FROM profiles p";
if (!empty($conditions)) {
    $count_query .= " WHERE " . implode(" AND ", $conditions);
}

$stmt = $db->prepare($count_query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$total_profiles = $result->fetch_assoc()['count'] ?? 0;

// Pagination settings
$per_page = DEFAULT_PAGE_SIZE;
$total_pages = ceil($total_profiles / $per_page);
$page = max(1, min($page, $total_pages)); // Ensure page is within valid range
$offset = ($page - 1) * $per_page;

// Add LIMIT clause for pagination
$query .= " LIMIT ?, ?";
$params[] = $offset;
$params[] = $per_page;
$types .= "ii";

// Fetch profiles
$stmt = $db->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$profiles = $result->fetch_all(MYSQLI_ASSOC);

// Get all groups for filter dropdown
$api = get_api();
$groups_response = $api->getGroups();
$groups = [];

if (isset($groups_response['success']) && $groups_response['success']) {
    $groups = $groups_response['data'] ?? [];
}

include 'includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3"><?php echo $page_title; ?></h1>
        <div class="btn-group">
            <a href="profile_edit.php" class="btn btn-primary">
                <i class="fas fa-plus me-2"></i> New Profile
            </a>
            <a href="profiles.php?sync=1" class="btn btn-success">
                <i class="fas fa-sync-alt me-2"></i> Sync Profiles
            </a>
            <button id="refresh-status-btn" class="btn btn-info">
                <i class="fas fa-sync me-2"></i> Refresh Status
            </button>
        </div>
</div>

<style>
    /* Custom styles for profile action buttons */
    .btn-group-action {
        display: inline-block;
        margin: 0 2px;
    }
    
    .btn-group-action .btn {
        border-radius: 4px;
        margin: 0 1px;
    }
    
    /* Make the stop button more visible */
    .close-profile {
        background-color: #dc3545;
        border-color: #dc3545;
        color: white;
    }
    
    /* Add a small animation for button state changes */
    .btn-group-action .btn {
        transition: all 0.3s ease;
    }
</style>

<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold">Profile List</h6>
    </div>
    <div class="card-body">
        <form action="profiles.php" method="get" class="mb-4">
            <div class="row g-3 align-items-center">
                <div class="col-md-4">
                    <div class="input-group">
                        <input type="text" class="form-control" id="search" name="search" placeholder="Search profiles..." value="<?php echo htmlspecialchars($search); ?>">
                        <button class="btn btn-outline-secondary" type="submit">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                </div>
                <div class="col-md-3">
                    <select class="form-select" id="group_filter" name="group_id">
                        <option value="">All Groups</option>
                        <?php foreach ($groups as $group): ?>
                        <option value="<?php echo $group['id']; ?>" <?php echo ($group_id === (int)$group['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($group['name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <select class="form-select" id="sort" name="sort">
                        <option value="0" <?php echo ($sort === 0) ? 'selected' : ''; ?>>Newest First</option>
                        <option value="1" <?php echo ($sort === 1) ? 'selected' : ''; ?>>Oldest First</option>
                        <option value="2" <?php echo ($sort === 2) ? 'selected' : ''; ?>>Name A-Z</option>
                        <option value="3" <?php echo ($sort === 3) ? 'selected' : ''; ?>>Name Z-A</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100">Apply Filters</button>
                </div>
            </div>
        </form>
        
        <form action="profiles.php" method="post">
            <input type="hidden" name="action" value="change_fingerprint">
            
<div class="mb-3 btn-toolbar">
                <button type="submit" class="btn btn-warning me-2" id="change-fingerprint-btn">
                    <i class="fas fa-fingerprint me-2"></i> Change Fingerprint
                </button>
                <button type="button" class="btn btn-success me-2" id="start-selected-profiles-btn">
                    <i class="fas fa-play me-2"></i> Start Selected Profiles
                </button>
                <button type="button" class="btn btn-danger" id="close-selected-profiles-btn">
                    <i class="fas fa-stop me-2"></i> Close Selected Profiles
                </button>
            </div>
            
            <div class="table-responsive">
                <table class="table table-bordered table-hover">
                    <thead>
                        <tr>
                            <th width="1%">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="select-all-profiles">
                                </div>
                            </th>
                            <th width="5%">ID</th>
                            <th>Name</th>
                            <th>Browser</th>
                            <th>Group</th>
                            <th>Proxy</th>
                            <th width="15%">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($profiles)): ?>
                        <tr>
                            <td colspan="7" class="text-center">No profiles found.</td>
                        </tr>
                        <?php else: ?>
                            <?php foreach ($profiles as $profile): ?>
                            <tr>
                                <td>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="profile_ids[]" value="<?php echo $profile['id']; ?>">
                                    </div>
                                </td>
                                <td><?php echo $profile['id']; ?></td>
                                <td>
                                    <?php echo htmlspecialchars($profile['name']); ?>
                                    <?php if (!empty($profile['note'])): ?>
                                    <div class="text-muted small"><?php echo htmlspecialchars(substr($profile['note'], 0, 50)); ?><?php echo (strlen($profile['note']) > 50) ? '...' : ''; ?></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($profile['browser_type'] ?: 'N/A'); ?>
                                    <?php if (!empty($profile['browser_version'])): ?>
                                    <div class="text-muted small"><?php echo htmlspecialchars($profile['browser_version']); ?></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php 
                                    $group_name = 'Unknown';
                                    foreach ($groups as $group) {
                                        if ((int)$group['id'] === (int)$profile['group_id']) {
                                            $group_name = $group['name'];
                                            break;
                                        }
                                    }
                                    echo htmlspecialchars($group_name);
                                    ?>
                                </td>
                                <td>
                                    <?php if (!empty($profile['proxy'])): ?>
                                    <span class="badge bg-success">Yes</span>
                                    <?php else: ?>
                                    <span class="badge bg-secondary">No</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <a href="profile_edit.php?id=<?php echo $profile['id']; ?>" class="btn btn-primary" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="profile_view.php?id=<?php echo $profile['id']; ?>" class="btn btn-info" title="View">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="javascript:void(0);" class="btn btn-success start-profile" data-id="<?php echo $profile['id']; ?>" title="Start">
                                            <i class="fas fa-play"></i>
                                        </a>
                                        <a href="javascript:void(0);" class="btn btn-danger close-profile" data-id="<?php echo $profile['id']; ?>" title="Close">
                                            <i class="fas fa-stop"></i>
                                        </a>
                                        <a href="profiles.php?delete=<?php echo $profile['id']; ?>" class="btn btn-danger btn-delete" title="Delete" onclick="return confirm('Are you sure you want to delete this profile?');">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </form>
        
        <?php
        // Generate pagination
        $pagination_url = 'profiles.php?page={page}';
        
        // Add existing filters to pagination URL
        if (!empty($search)) {
            $pagination_url .= '&search=' . urlencode($search);
        }
        if ($group_id !== null) {
            $pagination_url .= '&group_id=' . $group_id;
        }
        if ($sort !== 0) {
            $pagination_url .= '&sort=' . $sort;
        }
        
        echo pagination($total_profiles, $per_page, $page, $pagination_url);
        ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Profile selection - Select All checkbox
    document.getElementById('select-all-profiles').addEventListener('change', function() {
        const checkboxes = document.querySelectorAll('input[name="profile_ids[]"]');
        checkboxes.forEach(function(checkbox) {
            checkbox.checked = this.checked;
        }, this);
        
        // Show notification
        if (this.checked) {
            toastr.info('Selected all profiles');
        } else {
            toastr.info('Deselected all profiles');
        }
    });
    
    // Start an individual profile
    document.querySelectorAll('.start-profile').forEach(function(button) {
        button.addEventListener('click', function() {
            const profileId = this.getAttribute('data-id');
            
            // Show loading state
            this.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
            this.classList.add('disabled');
            
            // Make AJAX call to start the profile
            fetch('ajax/start_profile.php?profile_id=' + profileId)
                .then(response => response.json())
                .then(data => {
                    // Reset button state
                    this.innerHTML = '<i class="fas fa-play"></i>';
                    this.classList.remove('disabled');
                    
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
                    this.innerHTML = '<i class="fas fa-play"></i>';
                    this.classList.remove('disabled');
                    toastr.error('Error starting profile: ' + error);
                });
        });
    });
    
    // Close an individual profile
    document.querySelectorAll('.close-profile').forEach(function(button) {
        button.addEventListener('click', function() {
            const profileId = this.getAttribute('data-id');
            
            // Show loading state
            this.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
            this.classList.add('disabled');
            
            // Make AJAX call to close the profile
            fetch('ajax/close_profile.php?profile_id=' + profileId)
                .then(response => response.json())
                .then(data => {
                    // Reset button state
                    this.innerHTML = '<i class="fas fa-stop"></i>';
                    this.classList.remove('disabled');
                    
                    if (data.success) {
                        // Show success message with toastr
                        toastr.success('Profile closed successfully!');
                    } else {
                        // Show error message with toastr
                        toastr.error('Error closing profile: ' + (data.message || 'Unknown error'));
                    }
                })
                .catch(error => {
                    // Reset button state and show error with toastr
                    this.innerHTML = '<i class="fas fa-stop"></i>';
                    this.classList.remove('disabled');
                    toastr.error('Error closing profile: ' + error);
                });
        });
    });
    
    // Start all selected profiles
    document.getElementById('start-selected-profiles-btn').addEventListener('click', function() {
        const checkboxes = document.querySelectorAll('input[name="profile_ids[]"]:checked');
        
        if (checkboxes.length === 0) {
            toastr.warning('Please select at least one profile to start');
            return;
        }
        
        // Show loading state
        const originalText = this.innerHTML;
        this.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Starting...';
        this.disabled = true;
        
        // Get selected profile IDs
        const profileIds = Array.from(checkboxes).map(checkbox => checkbox.value);
        let completedCount = 0;
        let successCount = 0;
        
        // Start each profile sequentially
        profileIds.forEach(function(profileId) {
            fetch('ajax/start_profile.php?profile_id=' + profileId)
                .then(response => response.json())
                .then(data => {
                    completedCount++;
                    
                    if (data.success) {
                        successCount++;
                    }
                    
                    // If this is the last profile, reset the button state and show notification
                    if (completedCount === profileIds.length) {
                        // Reset button state
                        document.getElementById('start-selected-profiles-btn').innerHTML = originalText;
                        document.getElementById('start-selected-profiles-btn').disabled = false;
                        
                        // Show notification
                        toastr.success('Started ' + successCount + ' of ' + profileIds.length + ' selected profiles');
                    }
                })
                .catch(() => {
                    completedCount++;
                    
                    // If this is the last profile, reset the button state
                    if (completedCount === profileIds.length) {
                        document.getElementById('start-selected-profiles-btn').innerHTML = originalText;
                        document.getElementById('start-selected-profiles-btn').disabled = false;
                        toastr.success('Started ' + successCount + ' of ' + profileIds.length + ' selected profiles');
                    }
                });
        });
    });
    
    // Close all selected profiles
    document.getElementById('close-selected-profiles-btn').addEventListener('click', function() {
        const checkboxes = document.querySelectorAll('input[name="profile_ids[]"]:checked');
        
        if (checkboxes.length === 0) {
            toastr.warning('Please select at least one profile to close');
            return;
        }
        
        // Show loading state
        const originalText = this.innerHTML;
        this.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Closing...';
        this.disabled = true;
        
        // Get selected profile IDs
        const profileIds = Array.from(checkboxes).map(checkbox => checkbox.value);
        let completedCount = 0;
        let successCount = 0;
        
        // Close each profile sequentially
        profileIds.forEach(function(profileId) {
            fetch('ajax/close_profile.php?profile_id=' + profileId)
                .then(response => response.json())
                .then(data => {
                    completedCount++;
                    
                    if (data.success) {
                        successCount++;
                    }
                    
                    // If this is the last profile, reset the button state and show notification
                    if (completedCount === profileIds.length) {
                        // Reset button state
                        document.getElementById('close-selected-profiles-btn').innerHTML = originalText;
                        document.getElementById('close-selected-profiles-btn').disabled = false;
                        
                        // Show notification
                        toastr.success('Closed ' + successCount + ' of ' + profileIds.length + ' selected profiles');
                    }
                })
                .catch(() => {
                    completedCount++;
                    
                    // If this is the last profile, reset the button state
                    if (completedCount === profileIds.length) {
                        document.getElementById('close-selected-profiles-btn').innerHTML = originalText;
                        document.getElementById('close-selected-profiles-btn').disabled = false;
                        toastr.success('Closed ' + successCount + ' of ' + profileIds.length + ' selected profiles');
                    }
                });
        });
    });
    
    // Refresh button now just shows a notification since we don't need to check status
    document.getElementById('refresh-status-btn').addEventListener('click', function() {
        toastr.info('All profiles listed with start and close buttons');
    });
});
</script>

<?php include 'includes/footer.php'; ?>

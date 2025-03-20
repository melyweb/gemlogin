<?php
require_once 'includes/functions.php';
require_once 'includes/auth.php';

// Require login to access this page
require_login();

// Set default title
$page_title = 'Create Profile';
$is_edit = false;
$profile = null;

// Get database connection
$db = db_connect();
$api = get_api();

// Check if editing existing profile
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $profile_id = (int)$_GET['id'];
    $is_edit = true;
    $page_title = 'Edit Profile';
    
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
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate input
    $errors = [];
    
    // Get form data
    $profile_data = [
        'name' => trim($_POST['name'] ?? ''),
        'browser_type' => trim($_POST['browser_type'] ?? ''),
        'browser_version' => trim($_POST['browser_version'] ?? ''),
        'proxy' => trim($_POST['proxy'] ?? ''),
        'group_id' => (int)($_POST['group_id'] ?? 0),
        'note' => trim($_POST['note'] ?? '')
    ];
    
    // Validate required fields
    if (empty($profile_data['name'])) {
        $errors[] = 'Profile name is required.';
    }
    
    if (empty($errors)) {
        if ($is_edit) {
            // Update existing profile via API
            $response = $api->updateProfile($profile_id, $profile_data);
            
            if (isset($response['success']) && $response['success']) {
                // Update local database
                $stmt = $db->prepare("UPDATE profiles SET 
                    name = ?, 
                    browser_type = ?, 
                    browser_version = ?, 
                    proxy = ?, 
                    group_id = ?, 
                    note = ?, 
                    last_synced = NOW() 
                    WHERE id = ?");
                $stmt->bind_param("ssssssi", 
                    $profile_data['name'], 
                    $profile_data['browser_type'], 
                    $profile_data['browser_version'], 
                    $profile_data['proxy'], 
                    $profile_data['group_id'], 
                    $profile_data['note'], 
                    $profile_id
                );
                
                if ($stmt->execute()) {
                    set_flash_message('Profile updated successfully.', 'success');
                    redirect('profiles.php');
                } else {
                    $errors[] = 'Failed to update profile in database: ' . $stmt->error;
                }
            } else {
                $errors[] = 'Failed to update profile via API: ' . ($response['message'] ?? 'Unknown error');
            }
        } else {
            // Create new profile via API
            $response = $api->createProfile($profile_data);
            
            if (isset($response['success']) && $response['success']) {
                $new_profile_id = $response['data']['id'] ?? null;
                
                if ($new_profile_id) {
                    // Insert into local database
                    $stmt = $db->prepare("INSERT INTO profiles (id, name, browser_type, browser_version, proxy, group_id, note, last_synced) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
                    $stmt->bind_param("issssss", 
                        $new_profile_id, 
                        $profile_data['name'], 
                        $profile_data['browser_type'], 
                        $profile_data['browser_version'], 
                        $profile_data['proxy'], 
                        $profile_data['group_id'], 
                        $profile_data['note']
                    );
                    
                    if ($stmt->execute()) {
                        set_flash_message('Profile created successfully.', 'success');
                        redirect('profiles.php');
                    } else {
                        $errors[] = 'Failed to save profile to database: ' . $stmt->error;
                    }
                } else {
                    $errors[] = 'Failed to get new profile ID from API.';
                }
            } else {
                $errors[] = 'Failed to create profile via API: ' . ($response['message'] ?? 'Unknown error');
            }
        }
    }
    
    // If we get here, there were errors, update the profile variable to include form data
    if ($errors) {
        $profile = $profile_data;
        if ($is_edit) {
            $profile['id'] = $profile_id;
        }
    }
}

// Get available browser options
$browsers = [
    'chrome' => 'Chrome',
    'firefox' => 'Firefox',
    'edge' => 'Edge',
    'chromium' => 'Chromium'
];

// Get all groups
$groups_response = $api->getGroups();
$groups = [];

if (isset($groups_response['success']) && $groups_response['success']) {
    $groups = $groups_response['data'] ?? [];
}

include 'includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3"><?php echo $page_title; ?></h1>
    <a href="profiles.php" class="btn btn-secondary">
        <i class="fas fa-arrow-left me-2"></i> Back to Profiles
    </a>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
        <ul class="mb-0">
            <?php foreach ($errors as $error): ?>
                <li><?php echo $error; ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold">Profile Information</h6>
    </div>
    <div class="card-body">
        <form action="<?php echo $is_edit ? "profile_edit.php?id={$profile_id}" : 'profile_edit.php'; ?>" method="post">
            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="name" class="form-label form-required">Profile Name</label>
                        <input type="text" class="form-control" id="name" name="name" value="<?php echo htmlspecialchars($profile['name'] ?? ''); ?>" required>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="group_id" class="form-label">Group</label>
                        <select class="form-select" id="group_id" name="group_id">
                            <option value="0">-- No Group --</option>
                            <?php foreach ($groups as $group): ?>
                                <option value="<?php echo $group['id']; ?>" <?php echo (isset($profile['group_id']) && (int)$profile['group_id'] === (int)$group['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($group['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="browser_type" class="form-label">Browser Type</label>
                        <select class="form-select" id="browser_type" name="browser_type">
                            <option value="">-- Select Browser --</option>
                            <?php foreach ($browsers as $value => $label): ?>
                                <option value="<?php echo $value; ?>" <?php echo (isset($profile['browser_type']) && $profile['browser_type'] === $value) ? 'selected' : ''; ?>>
                                    <?php echo $label; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="browser_version" class="form-label">Browser Version</label>
                        <input type="text" class="form-control" id="browser_version" name="browser_version" value="<?php echo htmlspecialchars($profile['browser_version'] ?? ''); ?>">
                    </div>
                </div>
            </div>
            
            <div class="mb-3">
                <label for="proxy" class="form-label">Proxy (Format: type://user:pass@host:port)</label>
                <input type="text" class="form-control" id="proxy" name="proxy" value="<?php echo htmlspecialchars($profile['proxy'] ?? ''); ?>">
                <div class="form-text">Examples: socks5://127.0.0.1:1080, http://user:pass@proxy.example.com:8080</div>
            </div>
            
            <div class="mb-3">
                <label for="note" class="form-label">Notes</label>
                <textarea class="form-control" id="note" name="note" rows="3"><?php echo htmlspecialchars($profile['note'] ?? ''); ?></textarea>
            </div>
            
            <div class="text-end">
                <a href="profiles.php" class="btn btn-secondary me-2">Cancel</a>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save me-2"></i> <?php echo $is_edit ? 'Update Profile' : 'Create Profile'; ?>
                </button>
            </div>
        </form>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

<?php
require_once 'includes/functions.php';
require_once 'includes/auth.php';

// Require login to access this page
require_login();

$page_title = 'Edit Schedule';

// Get database connection
$db = db_connect();

// Check if schedule ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    set_flash_message('Invalid schedule ID.', 'danger');
    redirect('schedules.php');
}

$schedule_id = (int) $_GET['id'];

// Get schedule data
$stmt = $db->prepare("SELECT * FROM schedules WHERE id = ?");
$stmt->bind_param("i", $schedule_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    set_flash_message('Schedule not found.', 'danger');
    redirect('schedules.php');
}

$schedule = $result->fetch_assoc();

// Get selected profiles for this schedule
$stmt = $db->prepare("SELECT profile_id FROM schedule_profiles WHERE schedule_id = ?");
$stmt->bind_param("i", $schedule_id);
$stmt->execute();
$result = $stmt->get_result();
$selected_profile_ids = [];
while ($row = $result->fetch_assoc()) {
    $selected_profile_ids[] = $row['profile_id'];
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate required fields
    $errors = [];
    
    // Validate name
    $name = trim($_POST['name'] ?? '');
    if (empty($name)) {
        $errors[] = 'Schedule name is required.';
    }
    
    // Validate script
    $script_id = trim($_POST['script_id'] ?? '');
    if (empty($script_id)) {
        $errors[] = 'Script is required.';
    } else {
        // Check if script exists
        $stmt = $db->prepare("SELECT id FROM scripts WHERE id = ?");
        $stmt->bind_param("s", $script_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows === 0) {
            $errors[] = 'Selected script does not exist.';
        }
    }
    
    // Validate start time
    $start_time = trim($_POST['start_time'] ?? '');
    if (empty($start_time)) {
        $errors[] = 'Start time is required.';
    } else {
        $start_timestamp = strtotime($start_time);
        if ($start_timestamp === false) {
            $errors[] = 'Invalid start time format.';
        }
    }
    
    // Validate end time
    $end_time = trim($_POST['end_time'] ?? '');
    if (empty($end_time)) {
        $errors[] = 'End time is required.';
    } else {
        $end_timestamp = strtotime($end_time);
        if ($end_timestamp === false) {
            $errors[] = 'Invalid end time format.';
        } elseif ($end_timestamp <= $start_timestamp) {
            $errors[] = 'End time must be after start time.';
        }
    }
    
    // Validate profile delay
    $profile_delay = (int)($_POST['profile_delay'] ?? 0);
    if ($profile_delay < 0) {
        $errors[] = 'Profile delay must be a non-negative integer.';
    }
    
    // Validate loop delay
    $loop_delay = (int)($_POST['loop_delay'] ?? 0);
    if ($loop_delay < 0) {
        $errors[] = 'Loop delay must be a non-negative integer.';
    }
    
    // Validate selected profiles
    $profile_ids = isset($_POST['profile_ids']) && is_array($_POST['profile_ids']) ? array_filter($_POST['profile_ids'], 'is_numeric') : [];
    if (empty($profile_ids)) {
        $errors[] = 'At least one profile must be selected.';
    }
    
    // No need to process script parameters as they are stored in the script
    
    // If no errors, update the schedule
    if (empty($errors)) {
        $db->begin_transaction();
        
        try {
            // Check if updated_at column exists in the schedules table
            $check_column = $db->query("SHOW COLUMNS FROM schedules LIKE 'updated_at'");
            
            if ($check_column !== false && $check_column->num_rows > 0) {
                // Update schedule with updated_at field
                $stmt = $db->prepare("UPDATE schedules SET name = ?, script_id = ?, start_time = ?, end_time = ?, profile_delay = ?, loop_delay = ?, updated_at = NOW() WHERE id = ?");
                if ($stmt === false) {
                    throw new Exception("Failed to prepare update statement: " . $db->error);
                }
                $stmt->bind_param("ssssiis", $name, $script_id, $start_time, $end_time, $profile_delay, $loop_delay, $schedule_id);
            } else {
                // Update schedule without updated_at field
                $stmt = $db->prepare("UPDATE schedules SET name = ?, script_id = ?, start_time = ?, end_time = ?, profile_delay = ?, loop_delay = ? WHERE id = ?");
                if ($stmt === false) {
                    throw new Exception("Failed to prepare update statement: " . $db->error);
                }
                $stmt->bind_param("ssssiis", $name, $script_id, $start_time, $end_time, $profile_delay, $loop_delay, $schedule_id);
            }
            
            if (!$stmt->execute()) {
                throw new Exception("Failed to update schedule: " . $stmt->error);
            }
            
            // No parameters to update since they are stored in the script itself
            
            // Delete existing profile associations
            $stmt = $db->prepare("DELETE FROM schedule_profiles WHERE schedule_id = ?");
            if ($stmt === false) {
                throw new Exception("Failed to prepare delete statement: " . $db->error);
            }
            $stmt->bind_param("i", $schedule_id);
            
            if (!$stmt->execute()) {
                throw new Exception("Failed to update profile associations: " . $stmt->error);
            }
            
            // Insert new profile associations
            $insert_stmt = $db->prepare("INSERT INTO schedule_profiles (schedule_id, profile_id) VALUES (?, ?)");
            if ($insert_stmt === false) {
                throw new Exception("Failed to prepare insert statement: " . $db->error);
            }
            $insert_stmt->bind_param("ii", $schedule_id, $profile_id);
            
            foreach ($profile_ids as $profile_id) {
                if (!$insert_stmt->execute()) {
                    throw new Exception("Failed to associate profile {$profile_id}: " . $insert_stmt->error);
                }
            }
            
            $db->commit();
            
            set_flash_message('Schedule updated successfully.', 'success');
            redirect('schedules.php');
        } catch (Exception $e) {
            $db->rollback();
            $errors[] = $e->getMessage();
        }
    }
}

// If form was submitted but had errors, use the submitted values
// Otherwise, use the values from the database
$name = $_POST['name'] ?? $schedule['name'];
$script_id = $_POST['script_id'] ?? $schedule['script_id'];
$start_time = $_POST['start_time'] ?? $schedule['start_time'];
$end_time = $_POST['end_time'] ?? $schedule['end_time'];
$profile_delay = $_POST['profile_delay'] ?? $schedule['profile_delay'];
$loop_delay = $_POST['loop_delay'] ?? $schedule['loop_delay'];

// Get parameters
$parameters = [];
if (!empty($schedule['parameters'])) {
    $parameters = json_decode($schedule['parameters'], true) ?: [];
}

// Get all scripts
$query = "SELECT * FROM scripts ORDER BY name ASC";
$result = $db->query($query);
$scripts = $result->fetch_all(MYSQLI_ASSOC);

// Get script parameters if a script is selected
$script_parameters = [];
if (!empty($script_id)) {
    $stmt = $db->prepare("SELECT parameters FROM scripts WHERE id = ?");
    $stmt->bind_param("s", $script_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows === 1) {
        $params_json = $result->fetch_assoc()['parameters'];
        $script_parameters = json_decode($params_json, true) ?: [];
    }
}

// Get all profiles
$query = "SELECT p.* FROM profiles p ORDER BY p.group_id ASC, p.name ASC";
$result = $db->query($query);

// Check if query was successful
if ($result === false) {
    $profiles = [];
} else {
    $profiles = $result->fetch_all(MYSQLI_ASSOC);
}

// Get all groups for group names
$api = get_api();
$groups_response = $api->getGroups();
$groups = [];
if (isset($groups_response['success']) && $groups_response['success']) {
    $groups = $groups_response['data'] ?? [];
}

// Group profiles by group
$grouped_profiles = [];
foreach ($profiles as $profile) {
    $group_id = $profile['group_id'] ?? 0;
    
    // Get group name
    $group_name = 'Uncategorized';
    if ($group_id > 0) {
        foreach ($groups as $group) {
            if ((int)$group['id'] === (int)$group_id) {
                $group_name = $group['name'];
                break;
            }
        }
    }
    
    if (!isset($grouped_profiles[$group_id])) {
        $grouped_profiles[$group_id] = [
            'name' => $group_name,
            'profiles' => []
        ];
    }
    
    $grouped_profiles[$group_id]['profiles'][] = $profile;
}

include 'includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3"><?php echo $page_title; ?></h1>
    <a href="schedules.php" class="btn btn-secondary">
        <i class="fas fa-arrow-left me-2"></i> Back to Schedules
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

<form action="schedule_edit.php?id=<?php echo $schedule_id; ?>" method="post">
    <div class="row">
        <div class="col-md-8">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold">Schedule Details</h6>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label for="name" class="form-label form-required">Schedule Name</label>
                        <input type="text" class="form-control" id="name" name="name" value="<?php echo htmlspecialchars($name); ?>" required>
                        <div class="form-text">Enter a descriptive name for this schedule.</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="script_id" class="form-label form-required">Script</label>
                        <select class="form-select" id="script_id" name="script_id" required>
                            <option value="">Select a script</option>
                            <?php foreach ($scripts as $script): ?>
                                <option value="<?php echo $script['id']; ?>" <?php echo ($script_id === $script['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($script['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">Select the script to run on the profiles.</div>
                    </div>
                    
                    <!-- Bootstrap alert for displaying script parameters -->
                    <div id="script-parameters-alert" class="alert alert-info mt-3" style="display: none;">
                        <h6 class="alert-heading mb-2"><i class="fas fa-info-circle"></i> Script Parameters</h6>
                        <p class="mb-0">This script contains the following parameters that are predefined in GemLogin:</p>
                        <div id="parameters-list" class="mt-2"></div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="start_time" class="form-label form-required">Start Time</label>
                                <input type="text" class="form-control datetimepicker" id="start_time" name="start_time" value="<?php echo htmlspecialchars($start_time); ?>" required>
                                <div class="form-text">When to start running the schedule. Format: YYYY-MM-DD HH:MM</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="end_time" class="form-label form-required">End Time</label>
                                <input type="text" class="form-control datetimepicker" id="end_time" name="end_time" value="<?php echo htmlspecialchars($end_time); ?>" required>
                                <div class="form-text">When to stop running the schedule. Format: YYYY-MM-DD HH:MM</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="profile_delay" class="form-label form-required">Profile Delay (seconds)</label>
                                <input type="number" class="form-control" id="profile_delay" name="profile_delay" value="<?php echo (int)$profile_delay; ?>" min="0" required>
                                <div class="form-text">Delay between running the script on each profile.</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="loop_delay" class="form-label form-required">Loop Delay (seconds)</label>
                                <input type="number" class="form-control" id="loop_delay" name="loop_delay" value="<?php echo (int)$loop_delay; ?>" min="0" required>
                                <div class="form-text">Delay between loops (after processing all profiles).</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold">Select Profiles</h6>
                    <div>
                        <button type="button" class="btn btn-sm btn-primary" id="select-all-profiles">Select All</button>
                        <button type="button" class="btn btn-sm btn-secondary" id="deselect-all-profiles">Deselect All</button>
                    </div>
                </div>
                <div class="card-body">
                    <div class="form-text mb-3">Select the profiles to run the script on.</div>
                    
                    <?php if (empty($grouped_profiles)): ?>
                        <div class="alert alert-warning">
                            No profiles found. <a href="profiles.php" class="alert-link">Create profiles</a> first.
                        </div>
                    <?php else: ?>
                        <div class="profiles-list">
                            <?php foreach ($grouped_profiles as $group_id => $group): ?>
                                <div class="mb-3">
                                    <h6><?php echo htmlspecialchars($group['name']); ?></h6>
                                    <div class="list-group">
                                        <?php foreach ($group['profiles'] as $profile): ?>
                                            <div class="list-group-item">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" name="profile_ids[]" value="<?php echo $profile['id']; ?>" id="profile_<?php echo $profile['id']; ?>" <?php echo in_array($profile['id'], $selected_profile_ids) ? 'checked' : ''; ?>>
                                                    <label class="form-check-label" for="profile_<?php echo $profile['id']; ?>">
                                                        <?php echo htmlspecialchars($profile['name']); ?>
                                                        <?php if (!empty($profile['note'])): ?>
                                                            <span class="text-muted small"><?php echo htmlspecialchars(substr($profile['note'], 0, 30)); ?><?php echo (strlen($profile['note']) > 30) ? '...' : ''; ?></span>
                                                        <?php endif; ?>
                                                    </label>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <div class="text-end">
        <a href="schedules.php" class="btn btn-secondary me-2">Cancel</a>
        <button type="submit" class="btn btn-primary">Update Schedule</button>
    </div>
</form>

<!-- Include moment.js and tempus-dominus for datetime picker -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.1/moment.min.js"></script>
<link href="https://cdn.jsdelivr.net/npm/tempusdominus-bootstrap-4@5.39.0/build/css/tempusdominus-bootstrap-4.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/tempusdominus-bootstrap-4@5.39.0/build/js/tempusdominus-bootstrap-4.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize datetimepicker with Vietnam timezone (UTC+7)
    $('.datetimepicker').datetimepicker({
        format: 'YYYY-MM-DD HH:mm',
        timeZone: 'Asia/Saigon',
        icons: {
            time: 'far fa-clock',
            date: 'far fa-calendar',
            up: 'fas fa-arrow-up',
            down: 'fas fa-arrow-down',
            previous: 'fas fa-chevron-left',
            next: 'fas fa-chevron-right',
            today: 'fas fa-calendar-check',
            clear: 'far fa-trash-alt',
            close: 'fas fa-times'
        }
    });
    
    // Display timezone info
    var timezoneNotice = document.createElement('div');
    timezoneNotice.className = 'alert alert-info mt-3';
    timezoneNotice.innerHTML = '<i class="fas fa-info-circle me-2"></i> All times are in Vietnam timezone (GMT+7)';
    document.querySelector('.card-body').appendChild(timezoneNotice);
    
    // Profile selection
    document.getElementById('select-all-profiles').addEventListener('click', function(e) {
        e.preventDefault();
        document.querySelectorAll('input[name="profile_ids[]"]').forEach(function(checkbox) {
            checkbox.checked = true;
        });
    });
    
    document.getElementById('deselect-all-profiles').addEventListener('click', function(e) {
        e.preventDefault();
        document.querySelectorAll('input[name="profile_ids[]"]').forEach(function(checkbox) {
            checkbox.checked = false;
        });
    });
    
    // Script parameters display
    document.getElementById('script_id').addEventListener('change', function() {
        const scriptId = this.value;
        const parametersAlert = document.getElementById('script-parameters-alert');
        const parametersList = document.getElementById('parameters-list');
        
        if (!scriptId) {
            parametersAlert.style.display = 'none';
            return;
        }
        
        // Fetch script parameters via AJAX (for display only)
        fetch(`ajax/get_script_parameters.php?script_id=${scriptId}`)
            .then(response => response.json())
            .then(data => {
                // Clear previous content
                parametersList.innerHTML = '';
                
                if (data.success && data.parameters.length > 0) {
                    // Create a table to display parameters
                    const table = document.createElement('table');
                    table.className = 'table table-sm table-borderless mb-0';
                    
                    // Create table header
                    const thead = document.createElement('thead');
                    const headerRow = document.createElement('tr');
                    ['Name', 'Type', 'Default Value', 'Description'].forEach(text => {
                        const th = document.createElement('th');
                        th.textContent = text;
                        th.style.fontSize = '0.9rem';
                        headerRow.appendChild(th);
                    });
                    thead.appendChild(headerRow);
                    table.appendChild(thead);
                    
                    // Create table body
                    const tbody = document.createElement('tbody');
                    
                    data.parameters.forEach(param => {
                        const row = document.createElement('tr');
                        
                        // Parameter name
                        const nameCell = document.createElement('td');
                        nameCell.innerHTML = `<strong>${param.label || param.name}</strong>`;
                        row.appendChild(nameCell);
                        
                        // Parameter type
                        const typeCell = document.createElement('td');
                        typeCell.textContent = param.type || 'text';
                        row.appendChild(typeCell);
                        
                        // Default value
                        const valueCell = document.createElement('td');
                        valueCell.textContent = param.defaultValue || '-';
                        row.appendChild(valueCell);
                        
                        // Description
                        const descCell = document.createElement('td');
                        descCell.textContent = param.description || '-';
                        row.appendChild(descCell);
                        
                        tbody.appendChild(row);
                    });
                    
                    table.appendChild(tbody);
                    parametersList.appendChild(table);
                    parametersAlert.style.display = 'block';
                } else {
                    parametersAlert.style.display = 'none';
                }
            })
            .catch(error => {
                console.error('Error fetching script parameters:', error);
                parametersAlert.style.display = 'none';
            });
    });
    
    // Immediately trigger parameters loading if a script is selected
    const initialScriptId = document.getElementById('script_id').value;
    if (initialScriptId) {
        // Add a small delay to ensure DOM is fully loaded
        setTimeout(() => {
            document.getElementById('script_id').dispatchEvent(new Event('change'));
        }, 100);
    }
});
</script>

<?php include 'includes/footer.php'; ?>

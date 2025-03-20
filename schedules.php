<?php
require_once 'includes/functions.php';
require_once 'includes/auth.php';

// Require login to access this page
require_login();

$page_title = 'Schedules Management';

// Get database connection
$db = db_connect();

// Handle status change requests
if (isset($_GET['action']) && isset($_GET['id']) && is_numeric($_GET['id'])) {
    $schedule_id = (int)$_GET['id'];
    
    // Check if schedule exists
    $stmt = $db->prepare("SELECT id, status FROM schedules WHERE id = ?");
    $stmt->bind_param("i", $schedule_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $schedule = $result->fetch_assoc();
        
        switch ($_GET['action']) {
            case 'start':
                // Get the schedule details
                $stmt = $db->prepare("SELECT script_id FROM schedules WHERE id = ?");
                $stmt->bind_param("i", $schedule_id);
                $stmt->execute();
                $schedule_detail = $stmt->get_result()->fetch_assoc();
                $script_id = $schedule_detail['script_id'];
                
                // Get the profiles associated with this schedule
                $stmt = $db->prepare("SELECT p.id, p.name FROM profiles p 
                                     JOIN schedule_profiles sp ON p.id = sp.profile_id 
                                     WHERE sp.schedule_id = ?");
                $stmt->bind_param("i", $schedule_id);
                $stmt->execute();
                $profiles_result = $stmt->get_result();
                $profiles = [];
                while ($profile = $profiles_result->fetch_assoc()) {
                    $profiles[] = $profile;
                }
                
                if (empty($profiles)) {
                    set_flash_message('No profiles found for this schedule.', 'warning');
                    redirect('schedules.php');
                }
                
                // Get API instance
                $api = get_api();
                
                // Start each profile and execute the script immediately
                $success_count = 0;
                $error_messages = [];
                
                foreach ($profiles as $profile) {
                    // First, start the profile
                    try {
                        $start_result = $api->startProfile($profile['id'], "about:blank", "1280,720");
                        
                        if (!isset($start_result['success']) || !$start_result['success']) {
                            $error_messages[] = "Failed to start profile {$profile['name']}: " . ($start_result['message'] ?? 'Unknown error');
                            continue;
                        }
                        
                        // Get script parameters
                        $param_stmt = $db->prepare("SELECT parameters FROM scripts WHERE id = ?");
                        $param_stmt->bind_param("s", $script_id);
                        $param_stmt->execute();
                        $param_result = $param_stmt->get_result();
                        $script_params = [];
                        
                        if ($param_result->num_rows === 1) {
                            $script_data = $param_result->fetch_assoc();
                            $script_params = json_decode($script_data['parameters'], true) ?: [];
                            
                            // Convert parameters to the format expected by the API
                            $param_values = [];
                            foreach ($script_params as $param) {
                                if (isset($param['name']) && isset($param['defaultValue'])) {
                                    $param_values[$param['name']] = $param['defaultValue'];
                                }
                            }
                        }
                        
                        // Let's try a different approach based on what works in test_run_profiles.php
                        // First, ensure we have the exact profile ID as string
                        $profileId = trim($profile['id']);
                        error_log("Executing script {$script_id} with profile ID: {$profileId}");
                        
                        // Try running the script exactly like test_run_profiles.php does (no array, just direct ID)
                        $script_result = $api->executeScript($script_id, $profileId, $param_values, true);
                        
                        if (isset($script_result['success']) && $script_result['success']) {
                            $success_count++;
                        } else {
                            $error_messages[] = "Started profile {$profile['name']} but failed to run script: " . ($script_result['message'] ?? 'Unknown error');
                        }
                    } catch (Exception $e) {
                        $error_messages[] = "Error with profile {$profile['name']}: " . $e->getMessage();
                    }
                }
                
                // Update status to running
                $stmt = $db->prepare("UPDATE schedules SET status = 'running', last_run = NOW() WHERE id = ?");
                $stmt->bind_param("i", $schedule_id);
                $stmt->execute();
                
                if ($success_count > 0) {
                    set_flash_message("Schedule started successfully. {$success_count} of " . count($profiles) . " profiles launched with script execution.", 'success');
                    if (!empty($error_messages)) {
                        set_flash_message("Some profiles had errors: " . implode(" ", $error_messages), 'warning');
                    }
                } else {
                    set_flash_message('Failed to start any profiles. Errors: ' . implode(" ", $error_messages), 'danger');
                }
                break;
                
            case 'stop':
                // Get the profiles associated with this schedule
                $stmt = $db->prepare("SELECT p.id, p.name FROM profiles p 
                                     JOIN schedule_profiles sp ON p.id = sp.profile_id 
                                     WHERE sp.schedule_id = ?");
                $stmt->bind_param("i", $schedule_id);
                $stmt->execute();
                $profiles_result = $stmt->get_result();
                $profiles = [];
                while ($profile = $profiles_result->fetch_assoc()) {
                    $profiles[] = $profile;
                }
                
                if (empty($profiles)) {
                    set_flash_message('No profiles found for this schedule.', 'warning');
                    redirect('schedules.php');
                }
                
                // Get API instance
                $api = get_api();
                
                // Close each profile 
                $success_count = 0;
                $error_messages = [];
                
                foreach ($profiles as $profile) {
                    try {
                        $result = $api->closeProfile($profile['id']);
                        
                        if (isset($result['success']) && $result['success']) {
                            $success_count++;
                        } else {
                            $error_messages[] = "Failed to close profile {$profile['name']}: " . ($result['message'] ?? 'Unknown error');
                        }
                    } catch (Exception $e) {
                        $error_messages[] = "Error closing profile {$profile['name']}: " . $e->getMessage();
                    }
                }
                
                // Update status to stopped
                $stmt = $db->prepare("UPDATE schedules SET status = 'stopped' WHERE id = ?");
                $stmt->bind_param("i", $schedule_id);
                $stmt->execute();
                
                if ($success_count > 0) {
                    set_flash_message("Schedule stopped successfully. {$success_count} of " . count($profiles) . " profiles closed.", 'success');
                    if (!empty($error_messages)) {
                        set_flash_message("Some profiles had errors: " . implode(" ", $error_messages), 'warning');
                    }
                } else {
                    set_flash_message('Failed to stop any profiles. Errors: ' . implode(" ", $error_messages), 'danger');
                }
                break;
                
            case 'delete':
                // Delete schedule
                $stmt = $db->prepare("DELETE FROM schedules WHERE id = ?");
                $stmt->bind_param("i", $schedule_id);
                
                if ($stmt->execute()) {
                    set_flash_message('Schedule deleted successfully.', 'success');
                } else {
                    set_flash_message('Failed to delete schedule: ' . $stmt->error, 'danger');
                }
                break;
        }
    } else {
        set_flash_message('Schedule not found.', 'danger');
    }
    
    redirect('schedules.php');
}

// Get filters from query string
$search = isset($_GET['search']) ? clean_input($_GET['search']) : '';
$status = isset($_GET['status']) && in_array($_GET['status'], ['pending', 'running', 'completed', 'stopped']) ? $_GET['status'] : '';
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;

// Get schedules from database
$query = "SELECT 
            s.id, s.name, s.script_id, s.start_time, s.end_time, s.profile_delay, s.loop_delay, s.status, s.created_at, s.last_run,
            u.username as created_by,
            sc.name as script_name,
            (SELECT COUNT(*) FROM schedule_profiles WHERE schedule_id = s.id) as profile_count
          FROM schedules s
          LEFT JOIN users u ON s.created_by = u.id
          LEFT JOIN scripts sc ON s.script_id = sc.id";
$params = [];
$types = "";

// Build WHERE clause based on filters
$conditions = [];

if (!empty($search)) {
    $conditions[] = "(s.name LIKE ? OR sc.name LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $types .= "ss";
}

if (!empty($status)) {
    $conditions[] = "s.status = ?";
    $params[] = $status;
    $types .= "s";
}

if (!empty($conditions)) {
    $query .= " WHERE " . implode(" AND ", $conditions);
}

// Add ORDER BY clause
$query .= " ORDER BY s.created_at DESC";

// Count total schedules for pagination
$count_query = "SELECT COUNT(*) as count 
                FROM schedules s 
                LEFT JOIN scripts sc ON s.script_id = sc.id";
                
if (!empty($conditions)) {
    $count_query .= " WHERE " . implode(" AND ", $conditions);
}

$stmt = $db->prepare($count_query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$total_schedules = $result->fetch_assoc()['count'] ?? 0;

// Pagination settings
$per_page = DEFAULT_PAGE_SIZE;
$total_pages = ceil($total_schedules / $per_page);
$page = max(1, min($page, $total_pages)); // Ensure page is within valid range
$offset = ($page - 1) * $per_page;

// Add LIMIT clause for pagination
$query .= " LIMIT ?, ?";
$params[] = $offset;
$params[] = $per_page;
$types .= "ii";

// Fetch schedules
$stmt = $db->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$schedules = $result->fetch_all(MYSQLI_ASSOC);

include 'includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3"><?php echo $page_title; ?></h1>
    <a href="create_schedule.php" class="btn btn-primary">
        <i class="fas fa-plus me-2"></i> New Schedule
    </a>
</div>

<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold">Schedule List</h6>
    </div>
    <div class="card-body">
        <form action="schedules.php" method="get" class="mb-4">
            <div class="row g-3 align-items-center">
                <div class="col-md-5">
                    <div class="input-group">
                        <input type="text" class="form-control" id="search" name="search" placeholder="Search schedules..." value="<?php echo htmlspecialchars($search); ?>">
                        <button class="btn btn-outline-secondary" type="submit">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                </div>
                <div class="col-md-4">
                    <select class="form-select" id="status" name="status">
                        <option value="">All Statuses</option>
                        <option value="pending" <?php echo ($status === 'pending') ? 'selected' : ''; ?>>Pending</option>
                        <option value="running" <?php echo ($status === 'running') ? 'selected' : ''; ?>>Running</option>
                        <option value="completed" <?php echo ($status === 'completed') ? 'selected' : ''; ?>>Completed</option>
                        <option value="stopped" <?php echo ($status === 'stopped') ? 'selected' : ''; ?>>Stopped</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-primary w-100">Apply Filters</button>
                </div>
            </div>
        </form>
        
        <?php if (empty($schedules)): ?>
            <div class="alert alert-info">
                No schedules found. <a href="create_schedule.php" class="alert-link">Create a new schedule</a> to get started.
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-bordered table-hover">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Script</th>
                            <th>Profiles</th>
                            <th>Schedule Time</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($schedules as $schedule): ?>
                            <tr>
                                <td>
                                    <a href="schedule_view.php?id=<?php echo $schedule['id']; ?>"><?php echo htmlspecialchars($schedule['name']); ?></a>
                                    <div class="small text-muted">Created by: <?php echo htmlspecialchars($schedule['created_by']); ?></div>
                                </td>
                                <td><?php echo htmlspecialchars($schedule['script_name']); ?></td>
                                <td><?php echo $schedule['profile_count']; ?></td>
                                <td>
                                    <div>
                                        <i class="far fa-calendar-alt"></i> 
                                        Start: <?php echo format_datetime($schedule['start_time'], 'M d, Y H:i'); ?>
                                    </div>
                                    <div>
                                        <i class="far fa-calendar-check"></i>
                                        End: <?php echo format_datetime($schedule['end_time'], 'M d, Y H:i'); ?>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge bg-<?php 
                                        echo match($schedule['status']) {
                                            'running' => 'success',
                                            'pending' => 'warning',
                                            'completed' => 'info',
                                            'stopped' => 'danger',
                                            default => 'secondary'
                                        };
                                    ?>"><?php echo ucfirst($schedule['status']); ?></span>
                                    <?php if (!empty($schedule['last_run'])): ?>
                                        <div class="small text-muted">Last run: <?php echo format_datetime($schedule['last_run'], 'M d, H:i'); ?></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <a href="schedule_view.php?id=<?php echo $schedule['id']; ?>" class="btn btn-info" title="View">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="schedule_edit.php?id=<?php echo $schedule['id']; ?>" class="btn btn-primary" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        
                                        <?php if ($schedule['status'] !== 'running'): ?>
                                            <a href="schedules.php?action=start&id=<?php echo $schedule['id']; ?>" class="btn btn-success" title="Start">
                                                <i class="fas fa-play"></i>
                                            </a>
                                        <?php else: ?>
                                            <a href="schedules.php?action=stop&id=<?php echo $schedule['id']; ?>" class="btn btn-warning" title="Stop">
                                                <i class="fas fa-stop"></i>
                                            </a>
                                        <?php endif; ?>
                                        
                                        <a href="schedules.php?action=delete&id=<?php echo $schedule['id']; ?>" class="btn btn-danger btn-delete" title="Delete" onclick="return confirm('Are you sure you want to delete this schedule?');">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <?php
            // Generate pagination
            $pagination_url = 'schedules.php?page={page}';
            
            // Add existing filters to pagination URL
            if (!empty($search)) {
                $pagination_url .= '&search=' . urlencode($search);
            }
            if (!empty($status)) {
                $pagination_url .= '&status=' . urlencode($status);
            }
            
            echo pagination($total_schedules, $per_page, $page, $pagination_url);
            ?>
        <?php endif; ?>
    </div>
</div>

<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold">Cronjob Setup Instructions</h6>
    </div>
    <div class="card-body">
        <p>For schedules to run automatically, you need to set up a cronjob on your server. The cronjob should run the scheduler script every minute:</p>
        
        <div class="alert alert-secondary">
            <code>* * * * * php <?php echo realpath(__DIR__); ?>/cron/run_scheduler.php</code>
        </div>
        
        <p>Add this line to your crontab by running:</p>
        
        <div class="alert alert-secondary">
            <code>crontab -e</code>
        </div>
        
        <p>This will ensure that pending schedules are started on time and completed schedules are properly finished.</p>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

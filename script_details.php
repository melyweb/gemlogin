<?php
require_once 'includes/functions.php';
require_once 'includes/auth.php';

// Require login to access this page
require_login();

// Set default title
$page_title = 'Script Details';

// Get database connection
$db = db_connect();
$api = get_api();

// Check if script ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    set_flash_message('Invalid script ID.', 'danger');
    redirect('scripts.php');
}

$script_id = clean_input($_GET['id']);

// Get script from database
$stmt = $db->prepare("SELECT * FROM scripts WHERE id = ?");
$stmt->bind_param("s", $script_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    set_flash_message('Script not found.', 'danger');
    redirect('scripts.php');
}

$script = $result->fetch_assoc();

// Get parameters from JSON
$parameters = [];
if (!empty($script['parameters'])) {
    $parameters = json_decode($script['parameters'], true) ?: [];
}

// Get schedules that use this script
$stmt = $db->prepare("
    SELECT s.*, COUNT(sp.profile_id) as profile_count
    FROM schedules s
    LEFT JOIN schedule_profiles sp ON s.id = sp.schedule_id
    WHERE s.script_id = ?
    GROUP BY s.id
    ORDER BY s.created_at DESC
");
$stmt->bind_param("s", $script_id);
$stmt->execute();
$result = $stmt->get_result();
$schedules = $result->fetch_all(MYSQLI_ASSOC);

include 'includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3">Script: <?php echo htmlspecialchars($script['name']); ?></h1>
    <div class="btn-group">
        <a href="create_schedule.php?script_id=<?php echo urlencode($script_id); ?>" class="btn btn-primary">
            <i class="fas fa-calendar-plus me-2"></i> Schedule This Script
        </a>
        <a href="scripts.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left me-2"></i> Back to Scripts
        </a>
    </div>
</div>

<div class="row">
    <div class="col-lg-8">
        <!-- Script Details Card -->
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold">Script Information</h6>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <tbody>
                            <tr>
                                <th style="width: 200px;">ID</th>
                                <td><?php echo htmlspecialchars($script['id']); ?></td>
                            </tr>
                            <tr>
                                <th>Name</th>
                                <td><?php echo htmlspecialchars($script['name']); ?></td>
                            </tr>
                            <tr>
                                <th>Description</th>
                                <td>
                                    <?php if (!empty($script['description'])): ?>
                                        <?php echo nl2br(htmlspecialchars($script['description'])); ?>
                                    <?php else: ?>
                                        <span class="text-muted">No description available</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <th>Last Updated</th>
                                <td><?php echo format_datetime($script['last_synced']); ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Parameters Card -->
        <?php if (!empty($parameters)): ?>
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold">Script Parameters</h6>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Label</th>
                                <th>Type</th>
                                <th>Required</th>
                                <th>Default Value</th>
                                <th>Description</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($parameters as $param): ?>
                            <tr>
                                <td><code><?php echo htmlspecialchars($param['name'] ?? ''); ?></code></td>
                                <td><?php echo htmlspecialchars($param['label'] ?? $param['name'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($param['type'] ?? 'text'); ?></td>
                                <td>
                                    <?php if (isset($param['required']) && $param['required']): ?>
                                        <span class="badge bg-primary">Required</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Optional</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (isset($param['defaultValue']) && $param['defaultValue'] !== ''): ?>
                                        <code><?php echo htmlspecialchars($param['defaultValue']); ?></code>
                                    <?php else: ?>
                                        <span class="text-muted">None</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($param['description'])): ?>
                                        <?php echo htmlspecialchars($param['description']); ?>
                                    <?php else: ?>
                                        <span class="text-muted">No description</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Schedules Card -->
        <?php if (!empty($schedules)): ?>
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold">Schedules Using This Script</h6>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-hover">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Status</th>
                                <th>Profiles</th>
                                <th>Start Time</th>
                                <th>End Time</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($schedules as $schedule): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($schedule['name']); ?></td>
                                <td>
                                    <?php
                                    $status_class = match($schedule['status']) {
                                        'active' => 'success',
                                        'pending' => 'warning',
                                        'completed' => 'info',
                                        'error' => 'danger',
                                        default => 'secondary'
                                    };
                                    ?>
                                    <span class="badge bg-<?php echo $status_class; ?>">
                                        <?php echo ucfirst($schedule['status']); ?>
                                    </span>
                                </td>
                                <td><?php echo (int)$schedule['profile_count']; ?> profiles</td>
                                <td><?php echo format_datetime($schedule['start_time']); ?></td>
                                <td><?php echo format_datetime($schedule['end_time']); ?></td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <a href="schedule_view.php?id=<?php echo $schedule['id']; ?>" class="btn btn-info" title="View">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
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
                    <a href="create_schedule.php?script_id=<?php echo urlencode($script_id); ?>" class="btn btn-primary">
                        <i class="fas fa-calendar-plus me-2"></i> Schedule This Script
                    </a>
                    <button type="button" class="btn btn-success" id="run-now-btn" data-bs-toggle="modal" data-bs-target="#runScriptModal">
                        <i class="fas fa-play me-2"></i> Run Now
                    </button>
                </div>
            </div>
        </div>
        
        <!-- Script JSON Card -->
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold">Script JSON</h6>
            </div>
            <div class="card-body">
                <pre class="mb-0"><code><?php echo htmlspecialchars(json_encode([
                    'id' => $script['id'],
                    'name' => $script['name'],
                    'description' => $script['description'],
                    'parameters' => $parameters
                ], JSON_PRETTY_PRINT)); ?></code></pre>
            </div>
        </div>
    </div>
</div>

<!-- Run Script Modal -->
<div class="modal fade" id="runScriptModal" tabindex="-1" aria-labelledby="runScriptModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="runScriptModalLabel">Run Script Now</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="runScriptForm">
                    <input type="hidden" id="script_id" value="<?php echo htmlspecialchars($script_id); ?>">
                    
                    <div class="mb-3">
                        <label for="profile_select" class="form-label">Select Profile</label>
                        <select class="form-select" id="profile_select" required>
                            <option value="">-- Select a profile --</option>
                            <?php
                            // Get all profiles
                            $query = "SELECT id, name FROM profiles ORDER BY name ASC";
                            $result = $db->query($query);
                            $all_profiles = $result->fetch_all(MYSQLI_ASSOC);
                            
                            foreach ($all_profiles as $profile):
                            ?>
                                <option value="<?php echo $profile['id']; ?>"><?php echo htmlspecialchars($profile['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <?php if (!empty($parameters)): ?>
                        <h6 class="mb-3">Script Parameters</h6>
                        
                        <?php foreach ($parameters as $param): ?>
                            <div class="mb-3">
                                <label for="param_<?php echo $param['name']; ?>" class="form-label <?php echo isset($param['required']) && $param['required'] ? 'form-required' : ''; ?>">
                                    <?php echo htmlspecialchars($param['label'] ?? $param['name']); ?>
                                </label>
                                <input 
                                    type="<?php echo ($param['type'] ?? 'text') === 'number' ? 'number' : 'text'; ?>" 
                                    class="form-control" 
                                    id="param_<?php echo $param['name']; ?>" 
                                    name="parameters[<?php echo $param['name']; ?>]" 
                                    value="<?php echo htmlspecialchars($param['defaultValue'] ?? ''); ?>"
                                    <?php echo isset($param['required']) && $param['required'] ? 'required' : ''; ?>
                                >
                                <?php if (isset($param['description'])): ?>
                                    <div class="form-text"><?php echo htmlspecialchars($param['description']); ?></div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="runScriptBtn">Run Script</button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Run script immediately functionality
    document.getElementById('runScriptBtn').addEventListener('click', function() {
        const scriptId = document.getElementById('script_id').value;
        const profileId = document.getElementById('profile_select').value;
        
        if (!profileId) {
            alert('Please select a profile');
            return;
        }
        
        // Get parameter values if any
        const parameters = {};
        document.querySelectorAll('#runScriptForm input[name^="parameters["]').forEach(input => {
            const paramName = input.name.match(/parameters\[(.*?)\]/)[1];
            parameters[paramName] = input.value;
        });
        
        // Show loading state
        this.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Running...';
        this.disabled = true;
        
        // Make AJAX call to run the script
        fetch('ajax/run_script.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                script_id: scriptId,
                profile_id: profileId,
                parameters: parameters
            })
        })
        .then(response => response.json())
        .then(data => {
            // Reset button state
            this.innerHTML = 'Run Script';
            this.disabled = false;
            
            if (data.success) {
                // Close modal and show success message
                var modal = bootstrap.Modal.getInstance(document.getElementById('runScriptModal'));
                modal.hide();
                alert('Script started successfully!');
            } else {
                // Show error message
                alert('Error running script: ' + (data.message || 'Unknown error'));
            }
        })
        .catch(error => {
            // Reset button state and show error
            this.innerHTML = 'Run Script';
            this.disabled = false;
            alert('Error running script: ' + error);
        });
    });
});
</script>

<?php include 'includes/footer.php'; ?>

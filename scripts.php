<?php
require_once 'includes/functions.php';
require_once 'includes/auth.php';

// Require login to access this page
require_login();

$page_title = 'Scripts Management';

// Get database connection
$db = db_connect();

// Handle script sync request
if (isset($_GET['sync']) && $_GET['sync'] == 1) {
    if (sync_scripts()) {
        set_flash_message('Scripts synchronized successfully.', 'success');
    }
    redirect('scripts.php');
}

// Get scripts from database
$query = "SELECT * FROM scripts ORDER BY name ASC";
$result = $db->query($query);
$scripts = $result->fetch_all(MYSQLI_ASSOC);

include 'includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3"><?php echo $page_title; ?></h1>
    <a href="scripts.php?sync=1" class="btn btn-success">
        <i class="fas fa-sync-alt me-2"></i> Sync Scripts
    </a>
</div>

<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold">Available Scripts</h6>
    </div>
    <div class="card-body">
        <?php if (empty($scripts)): ?>
            <div class="alert alert-info">
                No scripts found. Click the "Sync Scripts" button to fetch scripts from the GemLogin API.
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-bordered table-hover">
                    <thead>
                        <tr>
                            <th width="20%">ID</th>
                            <th width="30%">Name</th>
                            <th width="20%">Parameters</th>
                            <th width="15%">Last Synced</th>
                            <th width="15%">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($scripts as $script): ?>
                            <?php 
                            $parameters = json_decode($script['parameters'], true);
                            $param_count = is_array($parameters) ? count($parameters) : 0;
                            ?>
                            <tr>
                                <td><code><?php echo htmlspecialchars($script['id']); ?></code></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($script['name']); ?></strong>
                                    <?php if (!empty($script['description'])): ?>
                                        <div class="small text-muted"><?php echo htmlspecialchars($script['description']); ?></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($param_count > 0): ?>
                                        <span class="badge bg-info"><?php echo $param_count; ?> parameter<?php echo $param_count != 1 ? 's' : ''; ?></span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">No parameters</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo format_datetime($script['last_synced'], 'M d, Y H:i'); ?></td>
                                <td>
                                    <a href="script_details.php?id=<?php echo $script['id']; ?>" class="btn btn-sm btn-info">
                                        <i class="fas fa-info-circle"></i> Details
                                    </a>
                                    <a href="create_schedule.php?script_id=<?php echo $script['id']; ?>" class="btn btn-sm btn-primary mt-1">
                                        <i class="fas fa-calendar-plus"></i> Schedule
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold">How to Use Scripts</h6>
    </div>
    <div class="card-body">
        <p>Scripts are predefined operations that can be executed on profiles. To use scripts:</p>
        <ol>
            <li>Sync scripts to fetch the latest available scripts from the GemLogin API</li>
            <li>View script details to understand its parameters and functionality</li>
            <li>Create a schedule to run a script on one or more profiles</li>
            <li>Monitor the schedule execution from the Schedules page</li>
        </ol>
        <div class="alert alert-info">
            <i class="fas fa-info-circle me-2"></i> Scripts are managed through the GemLogin API. This interface allows you to view and use existing scripts, but not create or modify them.
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

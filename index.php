<?php
require_once 'includes/functions.php';
require_once 'includes/auth.php';

// Require login to access this page
require_login();

$page_title = 'Dashboard';

// Get counts from database
$db = db_connect();

// Total profiles count
$result = $db->query("SELECT COUNT(*) as count FROM profiles");
$profile_count = $result->fetch_assoc()['count'] ?? 0;

// Active schedules count
$result = $db->query("SELECT COUNT(*) as count FROM schedules WHERE status = 'running'");
$active_schedule_count = $result->fetch_assoc()['count'] ?? 0;

// Total schedules count
$result = $db->query("SELECT COUNT(*) as count FROM schedules");
$total_schedule_count = $result->fetch_assoc()['count'] ?? 0;

// Success rate calculation
$result = $db->query("SELECT 
    COUNT(*) as total_logs,
    SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) as success_logs
    FROM schedule_logs
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)");
$log_stats = $result->fetch_assoc();
$success_rate = $log_stats['total_logs'] > 0 
    ? round(($log_stats['success_logs'] / $log_stats['total_logs']) * 100) 
    : 0;

// Recent schedules
$result = $db->query("SELECT 
    s.id, s.name, s.status, s.start_time, s.end_time, s.last_run,
    u.username as created_by,
    sc.name as script_name,
    (SELECT COUNT(*) FROM schedule_profiles WHERE schedule_id = s.id) as profile_count
    FROM schedules s
    LEFT JOIN users u ON s.created_by = u.id
    LEFT JOIN scripts sc ON s.script_id = sc.id
    ORDER BY s.created_at DESC
    LIMIT 5");
$recent_schedules = $result->fetch_all(MYSQLI_ASSOC);

// Recent activity logs
$result = $db->query("SELECT 
    l.id, l.status, l.message, l.created_at,
    s.name as schedule_name,
    p.name as profile_name
    FROM schedule_logs l
    JOIN schedules s ON l.schedule_id = s.id
    JOIN profiles p ON l.profile_id = p.id
    ORDER BY l.created_at DESC
    LIMIT 10");
$recent_logs = $result->fetch_all(MYSQLI_ASSOC);

include 'includes/header.php';
?>

<div class="row mb-4">
    <div class="col-md-6 col-xl-3 mb-4">
        <div class="card shadow h-100">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total Profiles</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $profile_count; ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-user-circle fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-6 col-xl-3 mb-4">
        <div class="card shadow h-100">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Active Schedules</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $active_schedule_count; ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-calendar-check fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-6 col-xl-3 mb-4">
        <div class="card shadow h-100">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Total Schedules</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $total_schedule_count; ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-calendar-alt fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-6 col-xl-3 mb-4">
        <div class="card shadow h-100">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">24h Success Rate</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $success_rate; ?>%</div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-percentage fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-lg-6">
        <div class="card shadow mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="m-0 font-weight-bold">Recent Schedules</h6>
                <a href="schedules.php" class="btn btn-sm btn-primary">View All</a>
            </div>
            <div class="card-body">
                <?php if (empty($recent_schedules)): ?>
                    <p class="text-center text-muted my-4">No schedules found.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Status</th>
                                    <th>Profiles</th>
                                    <th>Created By</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_schedules as $schedule): ?>
                                <tr>
                                    <td>
                                        <a href="schedule_view.php?id=<?php echo $schedule['id']; ?>"><?php echo htmlspecialchars($schedule['name']); ?></a>
                                        <div class="small text-muted"><?php echo htmlspecialchars($schedule['script_name']); ?></div>
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
                                    </td>
                                    <td><?php echo $schedule['profile_count']; ?></td>
                                    <td><?php echo htmlspecialchars($schedule['created_by']); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card shadow mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="m-0 font-weight-bold">Recent Activity</h6>
            </div>
            <div class="card-body">
                <?php if (empty($recent_logs)): ?>
                    <p class="text-center text-muted my-4">No recent activity found.</p>
                <?php else: ?>
                    <div class="timeline">
                        <?php foreach ($recent_logs as $log): ?>
                        <div class="timeline-item <?php echo $log['status'] === 'success' ? 'success' : 'danger'; ?>">
                            <div class="timeline-content">
                                <div class="d-flex justify-content-between">
                                    <h6 class="fw-bold mb-1"><?php echo htmlspecialchars($log['schedule_name']); ?></h6>
                                    <span class="timeline-time"><?php echo format_datetime($log['created_at'], 'M d, H:i'); ?></span>
                                </div>
                                <p class="mb-0">
                                    <span class="badge bg-<?php echo $log['status'] === 'success' ? 'success' : 'danger'; ?>">
                                        <?php echo ucfirst($log['status']); ?>
                                    </span>
                                    <?php echo htmlspecialchars($log['profile_name']); ?>
                                </p>
                                <?php if (!empty($log['message'])): ?>
                                <p class="small text-muted mb-0"><?php echo htmlspecialchars($log['message']); ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

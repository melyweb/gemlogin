<?php 
$current_page = get_current_page();
?>
<nav id="sidebarMenu" class="col-md-3 col-lg-3 d-md-block bg-dark sidebar collapse">
    <div class="position-sticky pt-3">
        <div class="text-center mb-4">
            <h2 class="text-white"><?php echo APP_NAME; ?></h2>
        </div>
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page == 'index.php' || $current_page == 'dashboard.php' ? 'active' : ''; ?>" href="index.php">
                    <i class="fas fa-tachometer-alt me-2"></i>
                    Dashboard
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page == 'profiles.php' ? 'active' : ''; ?>" href="profiles.php">
                    <i class="fas fa-user-circle me-2"></i>
                    Profiles
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page == 'scripts.php' ? 'active' : ''; ?>" href="scripts.php">
                    <i class="fas fa-code me-2"></i>
                    Scripts
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page == 'schedules.php' ? 'active' : ''; ?>" href="schedules.php">
                    <i class="fas fa-calendar-alt me-2"></i>
                    Schedules
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page == 'create_schedule.php' ? 'active' : ''; ?>" href="create_schedule.php">
                    <i class="fas fa-plus-circle me-2"></i>
                    Create Schedule
                </a>
            </li>
        </ul>

        <h6 class="sidebar-heading d-flex justify-content-between align-items-center px-3 mt-4 mb-1 text-muted">
            <span>System</span>
        </h6>
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page == 'sync.php' ? 'active' : ''; ?>" href="sync.php">
                    <i class="fas fa-sync-alt me-2"></i>
                    Sync Data
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page == 'settings.php' ? 'active' : ''; ?>" href="settings.php">
                    <i class="fas fa-cog me-2"></i>
                    Settings
                </a>
            </li>
            <?php if (is_logged_in()): ?>
            <li class="nav-item">
                <a class="nav-link" href="logout.php">
                    <i class="fas fa-sign-out-alt me-2"></i>
                    Logout
                </a>
            </li>
            <?php endif; ?>
        </ul>
    </div>
</nav>

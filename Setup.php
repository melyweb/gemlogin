<?php
/**
 * GemLogin Scheduler Setup Script
 * 
 * This script initializes the application by:
 * 1. Creating the necessary database tables
 * 2. Creating an admin user if it doesn't exist
 * 3. Syncing profiles and scripts from the GemLogin API
 */

// Include functions file
require_once 'includes/functions.php';

// Check if running from browser or command line
$is_cli = php_sapi_name() === 'cli';

// CLI output styling function
function cli_output($message, $type = 'info') {
    global $is_cli;
    
    if ($is_cli) {
        $prefix = match($type) {
            'success' => "\033[32m[SUCCESS]\033[0m ",
            'error' => "\033[31m[ERROR]\033[0m ",
            'warning' => "\033[33m[WARNING]\033[0m ",
            'info' => "\033[36m[INFO]\033[0m ",
            default => "[INFO] "
        };
        
        echo $prefix . $message . PHP_EOL;
    } else {
        $class = match($type) {
            'success' => 'alert-success',
            'error' => 'alert-danger',
            'warning' => 'alert-warning',
            'info' => 'alert-info',
            default => 'alert-info'
        };
        
        echo "<div class='alert {$class}'>{$message}</div>";
    }
}

// Function to check dependencies
function check_dependencies() {
    cli_output("Checking dependencies...", 'info');
    
    // Check if required extensions are loaded
    $required_extensions = ['mysqli', 'json', 'curl'];
    $missing_extensions = [];
    
    foreach ($required_extensions as $ext) {
        if (!extension_loaded($ext)) {
            $missing_extensions[] = $ext;
        }
    }
    
    if (!empty($missing_extensions)) {
        cli_output("Missing required PHP extensions: " . implode(', ', $missing_extensions), 'error');
        return false;
    }
    
    // Check if composer dependencies are installed
    if (!file_exists('vendor/autoload.php')) {
        cli_output("Composer dependencies not found. Please run 'composer install' first.", 'error');
        return false;
    }
    
    // Check if GemLogin API is accessible
    try {
        $api = get_api();
        $response = $api->getProfiles();
        
        if (!isset($response['success'])) {
            cli_output("GemLogin API response format is unexpected. Make sure the API is running and configured correctly.", 'warning');
        } elseif (!$response['success']) {
            cli_output("GemLogin API returned error: " . ($response['message'] ?? 'Unknown error'), 'warning');
        } else {
            cli_output("GemLogin API is accessible.", 'success');
        }
    } catch (Exception $e) {
        cli_output("Failed to connect to GemLogin API: " . $e->getMessage(), 'error');
        return false;
    }
    
    cli_output("All dependencies are satisfied.", 'success');
    return true;
}

// Function to set up database tables
function setup_database() {
    cli_output("Setting up database...", 'info');
    
    try {
        // Include database setup script
        require_once 'db/setup.php';
        cli_output("Database setup completed successfully.", 'success');
        return true;
    } catch (Exception $e) {
        cli_output("Database setup failed: " . $e->getMessage(), 'error');
        return false;
    }
}

// Function to sync profiles and scripts
function sync_data() {
    cli_output("Syncing profiles and scripts from GemLogin API...", 'info');
    
    // Sync profiles
    if (sync_profiles()) {
        cli_output("Profiles synced successfully.", 'success');
    } else {
        cli_output("Failed to sync profiles.", 'error');
        return false;
    }
    
    // Sync scripts
    if (sync_scripts()) {
        cli_output("Scripts synced successfully.", 'success');
    } else {
        cli_output("Failed to sync scripts.", 'error');
        return false;
    }
    
    return true;
}

// Function to set up cron job (only for CLI)
function setup_cron() {
    global $is_cli;
    
    if (!$is_cli) {
        return false;
    }
    
    cli_output("Setting up cron job...", 'info');
    
    // Get absolute path to scheduler script
    $scheduler_path = realpath(__DIR__ . '/cron/run_scheduler.php');
    $cron_line = "* * * * * php {$scheduler_path}";
    
    cli_output("To set up the cron job, run 'crontab -e' and add the following line:", 'info');
    cli_output($cron_line, 'info');
    
    // Prompt user to add cron job
    cli_output("Do you want to attempt to add the cron job automatically? (y/n)", 'info');
    $handle = fopen("php://stdin", "r");
    $line = trim(fgets($handle));
    
    if (strtolower($line) === 'y') {
        // Get existing cron jobs
        $output = [];
        exec('crontab -l 2>/dev/null', $output);
        
        // Check if cron job already exists
        $cron_job_exists = false;
        foreach ($output as $line) {
            if (strpos($line, $scheduler_path) !== false) {
                $cron_job_exists = true;
                break;
            }
        }
        
        if ($cron_job_exists) {
            cli_output("Cron job already exists.", 'info');
            return true;
        }
        
        // Add new cron job
        $output[] = $cron_line;
        $temp_file = tempnam(sys_get_temp_dir(), 'cron');
        file_put_contents($temp_file, implode(PHP_EOL, $output) . PHP_EOL);
        
        exec('crontab ' . $temp_file, $output, $return_var);
        unlink($temp_file);
        
        if ($return_var === 0) {
            cli_output("Cron job added successfully.", 'success');
            return true;
        } else {
            cli_output("Failed to add cron job. Please add it manually.", 'error');
            return false;
        }
    } else {
        cli_output("Skipping automatic cron job setup. Please add it manually.", 'info');
        return true;
    }
}

// Main setup function
function run_setup() {
    global $is_cli;
    
    // Display header
    if (!$is_cli) {
        echo "<!DOCTYPE html>
        <html lang='en'>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>GemLogin Scheduler Setup</title>
            <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css' rel='stylesheet'>
        </head>
        <body>
            <div class='container mt-5'>
                <h1 class='mb-4'>GemLogin Scheduler Setup</h1>
                <div class='card shadow'>
                    <div class='card-body'>";
    } else {
        echo "==============================================\n";
        echo "         GemLogin Scheduler Setup           \n";
        echo "==============================================\n\n";
    }
    
    // Run setup steps
    $success = true;
    
    // Step 1: Check dependencies
    if (!check_dependencies()) {
        $success = false;
    }
    
    // Step 2: Setup database
    if ($success && !setup_database()) {
        $success = false;
    }
    
    // Step 3: Sync data
    if ($success && !sync_data()) {
        $success = false;
    }
    
    // Step 4: Setup cron job (CLI only)
    if ($success && $is_cli) {
        setup_cron();
    }
    
    // Display footer
    if ($success) {
        cli_output("Setup completed successfully! You can now use the GemLogin Scheduler.", 'success');
        
        if (!$is_cli) {
            echo "<div class='mt-3'>
                <a href='index.php' class='btn btn-primary'>Go to Dashboard</a>
            </div>";
        }
    } else {
        cli_output("Setup failed. Please fix the errors and try again.", 'error');
    }
    
    if (!$is_cli) {
        echo "       </div>
                </div>
            </div>
            <script src='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js'></script>
        </body>
        </html>";
    }
}

// Run the setup
run_setup();
?>

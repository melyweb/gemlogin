<?php
/**
 * Scheduler Script
 * 
 * This script is meant to be run via cron job every minute to:
 * 1. Start pending schedules when their start time is reached
 * 2. Stop running schedules when their end time is reached
 * 3. Execute scripts on profiles based on schedule parameters
 */

// Set script execution time to unlimited
set_time_limit(0);

// Ensure this script is only run from the command line
if (php_sapi_name() !== 'cli') {
    die("This script can only be run from the command line.");
}

// Change to the parent directory
chdir(dirname(__DIR__));

// Load required files
require_once 'includes/functions.php';

// Get database connection
$db = db_connect();

// Get API instance
$api = get_api();

// Log function
function log_message($message, $schedule_id = null, $profile_id = null, $status = null) {
    global $db;
    
    echo date('Y-m-d H:i:s') . " - {$message}\n";
    
    // If schedule ID is provided, log to database
    if ($schedule_id !== null && $profile_id !== null && $status !== null) {
        $stmt = $db->prepare("INSERT INTO schedule_logs (schedule_id, profile_id, status, message, created_at) VALUES (?, ?, ?, ?, NOW())");
        $stmt->bind_param("iiss", $schedule_id, $profile_id, $status, $message);
        $stmt->execute();
    }
}

// Start pending schedules
function start_pending_schedules() {
    global $db;
    
    $now = date('Y-m-d H:i:s');
    $query = "UPDATE schedules SET status = 'running' WHERE status = 'pending' AND start_time <= ?";
    $stmt = $db->prepare($query);
    $stmt->bind_param("s", $now);
    $stmt->execute();
    
    $count = $stmt->affected_rows;
    if ($count > 0) {
        log_message("Started {$count} pending schedule(s)");
    }
}

// Stop completed schedules
function stop_completed_schedules() {
    global $db;
    
    $now = date('Y-m-d H:i:s');
    $query = "UPDATE schedules SET status = 'completed' WHERE status = 'running' AND end_time <= ?";
    $stmt = $db->prepare($query);
    $stmt->bind_param("s", $now);
    $stmt->execute();
    
    $count = $stmt->affected_rows;
    if ($count > 0) {
        log_message("Completed {$count} schedule(s)");
    }
}

// Process running schedules
function process_running_schedules() {
    global $db, $api;
    
    try {
        // Get running schedules that need to be processed
        $query = "SELECT 
                    s.id, s.name, s.script_id, s.profile_delay, s.loop_delay, s.parameters, s.last_run,
                    UNIX_TIMESTAMP(NOW()) - UNIX_TIMESTAMP(COALESCE(s.last_run, '1970-01-01')) as seconds_since_last_run
                  FROM 
                    schedules s
                  WHERE 
                    s.status = 'running'";
        
        $result = $db->query($query);
        
        // Check if query failed
        if ($result === false) {
            log_message("Error executing query: " . $db->error);
            return;
        }
        
        // Check if no running schedules
        if ($result->num_rows === 0) {
            log_message("No running schedules found");
            return;
        }
        
        log_message("Processing " . $result->num_rows . " running schedule(s)");
        
        while ($schedule = $result->fetch_assoc()) {
            $schedule_id = $schedule['id'];
            $script_id = $schedule['script_id'];
            $last_run = $schedule['last_run'];
            $seconds_since_last_run = $schedule['seconds_since_last_run'];
            $loop_delay = $schedule['loop_delay'];
            
            // If schedule has never run or it's time for next run
            if ($last_run === null || $seconds_since_last_run >= $loop_delay) {
                log_message("Processing schedule: {$schedule['name']} (ID: {$schedule_id})");
                
                // Get profiles for this schedule
                $profile_query = "SELECT p.id, p.name FROM profiles p
                                JOIN schedule_profiles sp ON p.id = sp.profile_id
                                WHERE sp.schedule_id = ?";
                $stmt = $db->prepare($profile_query);
                $stmt->bind_param("i", $schedule_id);
                $stmt->execute();
                $profile_result = $stmt->get_result();
                
                if ($profile_result === false) {
                    log_message("Error getting profiles for schedule ID {$schedule_id}: " . $stmt->error);
                    continue;
                }
                
                if ($profile_result->num_rows === 0) {
                    log_message("No profiles found for schedule ID {$schedule_id}");
                    continue;
                }
                
                // Update last run time
                $update_query = "UPDATE schedules SET last_run = NOW() WHERE id = ?";
                $stmt = $db->prepare($update_query);
                $stmt->bind_param("i", $schedule_id);
                $stmt->execute();
                
                // Process each profile
                $profile_delay = $schedule['profile_delay'];
                
                // Get script parameters from the script directly, not from the schedule
                $script_params_query = "SELECT parameters FROM scripts WHERE id = ?";
                $stmt = $db->prepare($script_params_query);
                $stmt->bind_param("s", $script_id);
                $stmt->execute();
                $script_params_result = $stmt->get_result();
                
                $param_values = [];
                if ($script_params_result->num_rows === 1) {
                    $script_data = $script_params_result->fetch_assoc();
                    $script_params = json_decode($script_data['parameters'], true) ?: [];
                    
                    // Convert parameters to the format expected by the API
                    foreach ($script_params as $param) {
                        if (isset($param['name']) && isset($param['defaultValue'])) {
                            $param_values[$param['name']] = $param['defaultValue'];
                        }
                    }
                }
                
                while ($profile = $profile_result->fetch_assoc()) {
                    $profile_id = (int)$profile['id']; // Ensure it's an integer
                    $profile_name = $profile['name'];
                    
                    log_message("Executing script on profile: {$profile_name} (ID: {$profile_id})", $schedule_id, $profile_id, 'success');
                    
                    try {
                        // Try passing the profile ID directly without wrapping in array
                        $profileId = trim($profile_id);
                        error_log("Cron executing script {$script_id} on profile {$profileId}");
                        
                        // Use the approach that works in schedules.php
                        $result = $api->executeScript($script_id, $profileId, $param_values, true);
                        
                        // Debug log
                        error_log("Cron job - Script {$script_id} on profile {$profileId} with params: " . json_encode($param_values));
                        
                        if (isset($result['success']) && $result['success']) {
                            log_message("Script executed successfully on profile {$profile_name}", $schedule_id, $profile_id, 'success');
                        } else {
                            log_message("Script execution failed on profile {$profile_name}: " . ($result['message'] ?? 'Unknown error'), $schedule_id, $profile_id, 'failed');
                        }
                    } catch (Exception $e) {
                        log_message("Exception during script execution on profile {$profile_name}: " . $e->getMessage(), $schedule_id, $profile_id, 'failed');
                    }
                    
                    // Sleep for profile delay if there are more profiles to process
                    if ($profile_result->num_rows > 1) {
                        sleep($profile_delay);
                    }
                }
            }
        }
    } catch (Exception $e) {
        log_message("Error processing running schedules: " . $e->getMessage());
    }
}

// Main execution
try {
    log_message("Scheduler started");
    
    // Step 1: Start pending schedules
    start_pending_schedules();
    
    // Step 2: Stop completed schedules
    stop_completed_schedules();
    
    // Step 3: Process running schedules
    process_running_schedules();
    
    log_message("Scheduler finished");
} catch (Exception $e) {
    log_message("Error: " . $e->getMessage());
}
?>

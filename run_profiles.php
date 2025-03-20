<?php

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/GemLoginAPI.php';
require_once __DIR__ . '/ProfileManager.php';

// Configuration file path
$configFilePath = __DIR__ . '/config.json';

// Load configuration
if (!file_exists($configFilePath)) {
    echo "ERROR: Configuration file not found.\n";
    exit(1);
}
$config = json_decode(file_get_contents($configFilePath), true);

// Validate configuration
if (empty($config['script_id']) || empty($config['start_time']) || empty($config['profile_delay']) || empty($config['loop_delay']) || empty($config['end_time'])) {
    echo "ERROR: Incomplete configuration.\n";
    exit(1);
}

// API Base URL
$baseUrl = 'http://localhost:1010'; // Replace with your actual base URL
$api = new GemLoginAPI($baseUrl);
$profileManager = new ProfileManager($api);

// Get all profiles
$profilesResponse = $api->getProfiles();
$profiles = [];
if ($profilesResponse['success'] && isset($profilesResponse['data'])) {
    $profiles = $profilesResponse['data'];
} else {
    echo "ERROR: Failed to get profiles: " . ($profilesResponse['message'] ?? 'Unknown error') . "\n";
    exit(1);
}

if (empty($profiles)) {
    echo "INFO: No profiles found.\n";
    exit(0); // Exit gracefully if no profiles are found
}

// Convert start and end times to timestamps
$startTime = strtotime($config['start_time']);
$endTime = strtotime($config['end_time']);

// Check if current time is within the start and end times
if (time() < $startTime) {
    echo "INFO: Script not yet started. Waiting until " . date('Y-m-d H:i:s', $startTime) . "\n";
    sleep($startTime - time()); // Wait until start time
}

if (time() > $endTime) {
    echo "INFO: Script has already ended.\n";
    exit(0);
}

// Main loop
echo "INFO: Starting main loop...\n";
$isRunning = true;
while ($isRunning) {
    $loopStartTime = time();
    echo "INFO: Starting a new loop...\n";

    foreach ($profiles as $profile) {
        echo "INFO: Processing profile {$profile['id']} ({$profile['name']})...\n";

        // Execute the script on each profile
        echo "INFO: Executing script {$config['script_id']} on profile {$profile['id']} ({$profile['name']})...\n";
        // Pass an empty array for parameters.  The user will need to modify this if the script requires parameters.
        $result = $profileManager->executeScript($config['script_id'], [$profile['id']], []);

        // Log the result
        if ($result['success']) {
            echo "INFO: Script executed successfully.\n";
        } else {
            echo "ERROR: Script execution failed: " . ($result['message'] ?? 'Unknown error') . "\n";
        }

        // Sleep for the profile delay
        echo "INFO: Sleeping for " . $config['profile_delay'] . " seconds...\n";
        sleep($config['profile_delay']);
    }

    // Calculate the time elapsed for the loop
    $loopEndTime = time();
    $loopDuration = $loopEndTime - $loopStartTime;

    // Check if the end time has been reached
    if (time() >= $endTime) {
        echo "INFO: End time reached. Exiting.\n";
        $isRunning = false;
        break;
    }

    // Sleep for the loop delay, taking into account the loop duration
    $sleepTime = $config['loop_delay'] - $loopDuration;
    if ($sleepTime > 0) {
        echo "INFO: Sleeping for " . $sleepTime . " seconds before next loop...\n";
        sleep($sleepTime);
    } else {
        echo "INFO: Loop duration exceeded loop delay. Continuing immediately.\n";
    }
}

echo "INFO: Script finished.\n";
exit(0);
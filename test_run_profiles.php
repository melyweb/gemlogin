<?php

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/GemLoginAPI.php';

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

// API Base URL from config or default
$config = json_decode(file_get_contents(__DIR__ . '/config.json'), true);
$baseUrl = $config['api_url'] ?? 'http://localhost:1010';

// Create a direct API client
$client = new Client(['base_uri' => $baseUrl]);

// Create GemLoginAPI instance
$api = new GemLoginAPI($baseUrl);

// Check if specific profile IDs were passed as arguments
$specificProfiles = isset($_GET['profiles']) ? explode(',', $_GET['profiles']) : (isset($argv[1]) ? explode(',', $argv[1]) : []);
$customUrl = $_GET['url'] ?? ($argv[2] ?? 'https://google.com');
$windowSize = $_GET['size'] ?? ($argv[3] ?? '1280,720');

// Handle action parameter 
$action = $_GET['action'] ?? ($argv[4] ?? 'start');

// Make sure the URL is valid and has a protocol
if ($action === 'start' && $customUrl !== 'about:blank' && !preg_match('~^(?:f|ht)tps?://~i', $customUrl)) {
    $customUrl = 'https://' . $customUrl;
}

// Check if running from web or CLI
$isWeb = isset($_SERVER['REQUEST_METHOD']);

// Output function based on environment
function output($message, $type = 'info') {
    global $isWeb;
    
    if ($isWeb) {
        $class = match($type) {
            'success' => 'text-success',
            'error' => 'text-danger',
            'warning' => 'text-warning',
            'info' => 'text-info',
            default => 'text-dark'
        };
        echo "<div class='{$class}'>{$message}</div>";
    } else {
        $prefix = match($type) {
            'success' => "\033[32m[SUCCESS]\033[0m ",
            'error' => "\033[31m[ERROR]\033[0m ",
            'warning' => "\033[33m[WARNING]\033[0m ",
            'info' => "\033[36m[INFO]\033[0m ",
            default => "[INFO] "
        };
        echo $prefix . $message . PHP_EOL;
    }
}

if ($isWeb) {
    // Web interface header
    echo '<!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GemLogin Profile Tester</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
        <link href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css" rel="stylesheet">
        <style>
            body { padding: 20px; }
            .profile-list { max-height: 400px; overflow-y: auto; }
        </style>
    </head>
    <body>
        <div class="container">
            <h2 class="mb-4">GemLogin Profile Tester</h2>';
}

try {
    // Get all profiles
    $profilesResponse = $api->getProfiles();
    
    if (!isset($profilesResponse['success']) || !$profilesResponse['success']) {
        output("Failed to get profiles: " . ($profilesResponse['message'] ?? 'Unknown error'), 'error');
        goto end_script;
    }
    
    $profiles = $profilesResponse['data'] ?? [];
    
    if (empty($profiles)) {
        output("No profiles found.", 'warning');
        goto end_script;
    }
    
    output("Found " . count($profiles) . " profiles.", 'success');
    
    // Display profiles for selection in web mode
    if ($isWeb && empty($specificProfiles)) {
        echo '<div class="card mb-4">
            <div class="card-header">Available Profiles</div>
            <div class="card-body profile-list">
                <form action="test_run_profiles.php" method="get">
                    <div class="mb-3">
                        <div class="d-flex justify-content-between mb-2">
                            <div>
                                <button type="button" class="btn btn-sm btn-primary" id="select-all">Select All</button>
                                <button type="button" class="btn btn-sm btn-secondary" id="deselect-all">Deselect All</button>
                            </div>
                        </div>';
        
        foreach ($profiles as $profile) {
            echo '<div class="form-check">
                <input class="form-check-input profile-checkbox" type="checkbox" name="profile_ids[]" value="' . $profile['id'] . '" id="profile_' . $profile['id'] . '">
                <label class="form-check-label" for="profile_' . $profile['id'] . '">
                    <strong>' . htmlspecialchars($profile['name']) . '</strong> (ID: ' . $profile['id'] . ')
                    <small class="text-muted">' . htmlspecialchars($profile['browser_type'] ?? 'Unknown browser') . '</small>
                </label>
            </div>';
        }
        
        echo '</div>
                <div class="mb-3">
                    <label for="url" class="form-label">URL to Open</label>
                    <input type="text" class="form-control" id="url" name="url" value="https://google.com">
                </div>
                <div class="mb-3">
                    <label for="size" class="form-label">Window Size (width,height)</label>
                    <input type="text" class="form-control" id="size" name="size" value="1280,720">
                </div>
                <div class="mb-3">
                    <label class="form-label">Action</label>
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-success" name="action" value="start">Start Selected Profiles</button>
                        <button type="submit" class="btn btn-danger" name="action" value="close">Close Selected Profiles</button>
                    </div>
                </div>
                <input type="hidden" name="profiles" id="selected-profiles-input">
            </form>
        </div>
    </div>
    
    <script>
        document.getElementById("select-all").addEventListener("click", function() {
            const checkboxes = document.querySelectorAll(".profile-checkbox");
            checkboxes.forEach(function(checkbox) {
                checkbox.checked = true;
            });
            toastr.info("Selected all " + checkboxes.length + " profiles");
        });
        
        document.getElementById("deselect-all").addEventListener("click", function() {
            const checkboxes = document.querySelectorAll(".profile-checkbox");
            checkboxes.forEach(function(checkbox) {
                checkbox.checked = false;
            });
            toastr.info("Deselected all profiles");
        });
        
        document.querySelector("form").addEventListener("submit", function(e) {
            e.preventDefault();
            const checkedProfiles = Array.from(document.querySelectorAll(".profile-checkbox:checked")).map(cb => cb.value);
            
            if (checkedProfiles.length === 0) {
                toastr.warning("Please select at least one profile");
                return;
            }
            
            document.getElementById("selected-profiles-input").value = checkedProfiles.join(",");
            this.submit();
        });
    </script>';
    } else {
        // Process profiles
        $profilesToProcess = [];
        
        if (!empty($specificProfiles)) {
            // Filter profiles by the specific IDs provided
            foreach ($profiles as $profile) {
                if (in_array((string)$profile['id'], $specificProfiles)) {
                    $profilesToProcess[] = $profile;
                }
            }
        } else {
            // In CLI mode with no specific profiles, use all profiles
            $profilesToProcess = $profiles;
        }
        
        if (empty($profilesToProcess)) {
            output("No matching profiles found to process.", 'warning');
            goto end_script;
        }
        
        // Display list of profiles being processed
        if ($isWeb) {
            $actionText = $action === 'close' ? 'Closing' : 'Opening';
            echo '<div class="card mb-4">
                <div class="card-header">' . $actionText . ' Profiles</div>
                <div class="card-body" id="results">';
        }
        
        if ($action === 'close') {
            output("Closing " . count($profilesToProcess) . " profiles");
            
            // Close each selected profile
            foreach ($profilesToProcess as $profile) {
                output("Closing profile {$profile['id']} ({$profile['name']})...");
                
                try {
                    // Call the API to close the profile
                    $response = $api->closeProfile($profile['id']);
                    
                    if (isset($response['success']) && $response['success']) {
                        output("Profile {$profile['id']} ({$profile['name']}) closed successfully.", 'success');
                    } else {
                        output("Failed to close profile {$profile['id']}: " . ($response['message'] ?? 'Unknown error'), 'error');
                    }
                } catch (Exception $e) {
                    output("Error closing profile {$profile['id']}: " . $e->getMessage(), 'error');
                }
            }
        } else {
            // Default action is to open profiles
            output("Opening " . count($profilesToProcess) . " profiles with URL: " . $customUrl . " and window size: " . $windowSize);
            
            // Open each selected profile
            foreach ($profilesToProcess as $profile) {
                output("Opening profile {$profile['id']} ({$profile['name']})...");
                
                try {
                    // Call the API to open the profile with the specified URL
                    $response = $api->startProfile(
                        $profile['id'],
                        $customUrl,  // Use the customUrl with protocol
                        $windowSize
                    );
                    
                    if (isset($response['success']) && $response['success']) {
                        output("Profile {$profile['id']} ({$profile['name']}) opened successfully with URL: {$customUrl}", 'success');
                    } else {
                        output("Failed to open profile {$profile['id']}: " . ($response['message'] ?? 'Unknown error'), 'error');
                    }
                } catch (Exception $e) {
                    output("Error opening profile {$profile['id']}: " . $e->getMessage(), 'error');
                }
            }
        }
        
        if ($isWeb) {
            echo '</div></div>';
        }
        
        output("All requested profiles have been processed.", 'success');
    }
    
} catch (Exception $e) {
    output("Error: " . $e->getMessage(), 'error');
}

end_script:
if ($isWeb) {
    // Web interface footer
    echo '</div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
    <script>
        // Configure toastr defaults
        toastr.options = {
            "closeButton": true,
            "progressBar": true,
            "positionClass": "toast-top-right",
            "timeOut": "5000"
        };
    </script>
    </body>
    </html>';
}

exit(0);

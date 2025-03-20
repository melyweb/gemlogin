<?php
// CLI script for syncing profiles and scripts

// Check if running from command line
if (php_sapi_name() !== 'cli') {
    die("This script must be run from the command line.");
}

// Include necessary files
require_once __DIR__ . '/includes/functions.php';

// Parse command line arguments
$options = getopt("ps", ["profiles", "scripts", "help", "verbose"]);

// Show help if requested or no options provided
if (isset($options['help']) || (empty($options) && empty($argv[1]))) {
    echo "GemLogin Sync CLI Tool\n";
    echo "Usage: php cli_sync.php [options]\n\n";
    echo "Options:\n";
    echo "  -p, --profiles    Sync profiles from API\n";
    echo "  -s, --scripts     Sync scripts from API\n";
    echo "  --verbose         Show detailed output\n";
    echo "  --help            Show this help message\n";
    echo "\nExamples:\n";
    echo "  php cli_sync.php -p               # Sync only profiles\n";
    echo "  php cli_sync.php -s               # Sync only scripts\n";
    echo "  php cli_sync.php -p -s            # Sync both profiles and scripts\n";
    echo "  php cli_sync.php --profiles       # Sync only profiles (long option)\n";
    echo "  php cli_sync.php -p -s --verbose  # Sync both with verbose output\n";
    exit(0);
}

// Process command
$sync_profiles = isset($options['p']) || isset($options['profiles']);
$sync_scripts = isset($options['s']) || isset($options['scripts']);
$verbose = isset($options['verbose']);

// Default to both if no specific sync option is provided
if (!$sync_profiles && !$sync_scripts && isset($argv[1])) {
    $sync_profiles = true;
    $sync_scripts = true;
}

// Track success
$success = true;

// Get API status
if ($verbose) {
    echo "Checking API connection...\n";
}

$api = get_api();
try {
    $response = $api->ping();
    
    if (isset($response['success']) && $response['success']) {
        if ($verbose) {
            echo "✓ API connected: " . ($response['message'] ?? 'API is online') . "\n";
        }
    } else {
        echo "✗ API connection error: " . ($response['message'] ?? 'Unknown API error') . "\n";
        exit(1);
    }
} catch (Exception $e) {
    echo "✗ API connection error: " . $e->getMessage() . "\n";
    exit(1);
}

// Sync profiles if requested
if ($sync_profiles) {
    echo "Syncing profiles...\n";
    
    try {
        if (sync_profiles()) {
            echo "✓ Profiles synchronized successfully.\n";
        } else {
            echo "✗ Failed to synchronize profiles.\n";
            $success = false;
        }
    } catch (Exception $e) {
        echo "✗ Error synchronizing profiles: " . $e->getMessage() . "\n";
        $success = false;
    }
}

// Sync scripts if requested
if ($sync_scripts) {
    echo "Syncing scripts...\n";
    
    try {
        if (sync_scripts()) {
            echo "✓ Scripts synchronized successfully.\n";
        } else {
            echo "✗ Failed to synchronize scripts.\n";
            $success = false;
        }
    } catch (Exception $e) {
        echo "✗ Error synchronizing scripts: " . $e->getMessage() . "\n";
        $success = false;
    }
}

// Show summary
if ($verbose) {
    // Get database connection
    $db = db_connect();
    
    // Get profile count
    $query = "SELECT COUNT(*) as count FROM profiles";
    $result = $db->query($query);
    $profile_count = $result->fetch_assoc()['count'] ?? 0;
    
    // Get script count
    $query = "SELECT COUNT(*) as count FROM scripts";
    $result = $db->query($query);
    $script_count = $result->fetch_assoc()['count'] ?? 0;
    
    echo "\nSummary:\n";
    echo "- Total profiles in database: $profile_count\n";
    echo "- Total scripts in database: $script_count\n";
}

// Exit with appropriate status code
exit($success ? 0 : 1);

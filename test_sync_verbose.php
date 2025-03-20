<?php
// Detailed test file with better error reporting
require_once 'includes/functions.php';

echo "===== DETAILED SYNC TEST =====\n\n";

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Test API connection
echo "Testing API connection:\n";
try {
    $api = get_api();
    $profiles_response = $api->getProfiles();
    
    if (isset($profiles_response['success'])) {
        echo "✓ API connection successful\n";
        echo "  Response: " . json_encode($profiles_response['success']) . "\n";
        echo "  Message: " . ($profiles_response['message'] ?? 'No message') . "\n";
        
        if (isset($profiles_response['data'])) {
            echo "  Found " . count($profiles_response['data']) . " profiles\n";
        }
    } else {
        echo "✗ API response format unexpected: " . json_encode($profiles_response) . "\n";
    }
} catch (Exception $e) {
    echo "✗ API connection failed: " . $e->getMessage() . "\n";
}

echo "\n";

// Test database connection
echo "Testing database connection:\n";
try {
    $db = db_connect();
    if ($db instanceof mysqli) {
        echo "✓ Database connection successful\n";
        
        // Check if tables exist
        $result = $db->query("SHOW TABLES");
        $tables = [];
        while ($row = $result->fetch_array()) {
            $tables[] = $row[0];
        }
        
        echo "  Found tables: " . implode(", ", $tables) . "\n";
    } else {
        echo "✗ Database connection failed\n";
    }
} catch (Exception $e) {
    echo "✗ Database connection failed: " . $e->getMessage() . "\n";
}

echo "\n";

// Now test the sync functions with detailed output
echo "1. Testing profile synchronization:\n";
try {
    $profiles_result = sync_profiles();
    if ($profiles_result) {
        echo "✓ Profile synchronization successful\n";
    } else {
        echo "✗ Profile synchronization failed\n";
    }
} catch (Exception $e) {
    echo "✗ Profile synchronization exception: " . $e->getMessage() . "\n";
    echo "  Line: " . $e->getLine() . " in " . $e->getFile() . "\n";
}

echo "\n2. Testing script synchronization:\n";
try {
    $scripts_result = sync_scripts();
    if ($scripts_result) {
        echo "✓ Script synchronization successful\n";
    } else {
        echo "✗ Script synchronization failed\n";
    }
} catch (Exception $e) {
    echo "✗ Script synchronization exception: " . $e->getMessage() . "\n";
    echo "  Line: " . $e->getLine() . " in " . $e->getFile() . "\n";
}

echo "\nTest completed.\n";
?>

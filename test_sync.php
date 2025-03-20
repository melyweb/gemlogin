<?php
// Simple test file to test syncing profiles and scripts
require_once 'includes/functions.php';

echo "Testing profile and script synchronization...\n\n";

echo "1. Testing profile synchronization:\n";
$profiles_result = sync_profiles();
if ($profiles_result) {
    echo "✓ Profile synchronization successful\n";
} else {
    echo "✗ Profile synchronization failed\n";
}

echo "\n2. Testing script synchronization:\n";
$scripts_result = sync_scripts();
if ($scripts_result) {
    echo "✓ Script synchronization successful\n";
} else {
    echo "✗ Script synchronization failed\n";
}

echo "\nTest completed.\n";
?>

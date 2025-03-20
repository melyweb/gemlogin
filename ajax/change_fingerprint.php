<?php
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// Require login for this action
if (!is_logged_in()) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Authentication required'
    ]);
    exit;
}

// Check if profile ID is provided
if (!isset($_GET['profile_id']) || !is_numeric($_GET['profile_id'])) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Profile ID is required'
    ]);
    exit;
}

$profile_id = (int)$_GET['profile_id'];

// Get API instance
$api = get_api();

// Change fingerprint for the profile
$profile_ids = [$profile_id]; // Create an array with the single profile ID
$response = $api->changeFingerprint($profile_ids);

// Return the API response
header('Content-Type: application/json');
echo json_encode($response);
?>

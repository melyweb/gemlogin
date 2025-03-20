<?php
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// Set content type to JSON
header('Content-Type: application/json');

// Require login for this action
if (!is_logged_in()) {
    echo json_encode([
        'success' => false,
        'message' => 'Authentication required'
    ]);
    exit;
}

// Validate profile ID
if (!isset($_GET['profile_id']) || !is_numeric($_GET['profile_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid profile ID'
    ]);
    exit;
}

$profile_id = (int)$_GET['profile_id'];

// Get API instance
$api = get_api();

// Call API to get profile details
try {
    $response = $api->getProfile($profile_id);
    
    // Return the full response for debugging
    echo json_encode([
        'success' => true,
        'profileId' => $profile_id,
        'rawResponse' => $response,
        'apiBaseUrl' => $api->getBaseUrl() 
    ], JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}

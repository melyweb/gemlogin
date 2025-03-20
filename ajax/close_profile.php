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

// Call API to close the profile
try {
    $response = $api->closeProfile($profile_id);
    
    if (isset($response['success']) && $response['success']) {
        echo json_encode([
            'success' => true,
            'message' => 'Profile closed successfully'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => $response['message'] ?? 'Failed to close profile'
        ]);
    }
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}

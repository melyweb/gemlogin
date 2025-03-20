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

// Call the new specialized method to check if profile is running
try {
    $response = $api->isProfileRunning($profile_id);
    
    if (isset($response['success']) && $response['success']) {
        $status = $response['status'] ?? 'unknown';
        $is_running = $response['running'] ?? false;
        
        echo json_encode([
            'success' => true,
            'profileId' => $profile_id,
            'status' => $status,
            'isRunning' => $is_running,
            'debug' => $response
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => $response['message'] ?? 'Failed to get profile status'
        ]);
    }
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}

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

// Get optional parameters
$url = isset($_GET['url']) ? clean_input($_GET['url']) : 'https://google.com';
$win_size = isset($_GET['win_size']) ? clean_input($_GET['win_size']) : '1280,720';
$win_pos = isset($_GET['win_pos']) ? clean_input($_GET['win_pos']) : null;
$additional_args = isset($_GET['additional_args']) ? clean_input($_GET['additional_args']) : null;

// Get API instance
$api = get_api();

// Start the profile
$response = $api->startProfile($profile_id, $url, $win_size, $win_pos, $additional_args);

// Return the API response
header('Content-Type: application/json');
echo json_encode($response);
?>

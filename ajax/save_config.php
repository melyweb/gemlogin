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

// Get JSON payload from POST
$json = file_get_contents('php://input');
$data = json_decode($json, true);

// Validate API URL
if (!isset($data['api_url']) || empty($data['api_url'])) {
    echo json_encode([
        'success' => false,
        'message' => 'API URL is required'
    ]);
    exit;
}

$api_url = trim($data['api_url']);

// Validate URL format
if (!filter_var($api_url, FILTER_VALIDATE_URL)) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid URL format'
    ]);
    exit;
}

// Load current configuration
$config_file = '../config.json';
if (!file_exists($config_file)) {
    $current_config = [];
} else {
    $current_config = json_decode(file_get_contents($config_file), true) ?: [];
}

// Update API URL
$current_config['api_url'] = $api_url;

// Save updated configuration
try {
    $result = file_put_contents($config_file, json_encode($current_config, JSON_PRETTY_PRINT));
    
    if ($result === false) {
        echo json_encode([
            'success' => false,
            'message' => 'Failed to save configuration file'
        ]);
        exit;
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Configuration saved successfully'
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}

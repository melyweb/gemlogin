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

// Validate required fields
if (!isset($data['script_id']) || empty($data['script_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Script ID is required'
    ]);
    exit;
}

if (!isset($data['profile_id']) || empty($data['profile_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Profile ID is required'
    ]);
    exit;
}

$script_id = $data['script_id'];
$profile_id = $data['profile_id'];
$parameters = $data['parameters'] ?? [];

// Get database connection
$db = db_connect();

// Check if script exists
$stmt = $db->prepare("SELECT * FROM scripts WHERE id = ?");
$stmt->bind_param("s", $script_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Script not found'
    ]);
    exit;
}

$script = $result->fetch_assoc();

// Check if profile exists
$stmt = $db->prepare("SELECT * FROM profiles WHERE id = ?");
$stmt->bind_param("i", $profile_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Profile not found'
    ]);
    exit;
}

$profile = $result->fetch_assoc();

// Get API instance
$api = get_api();

// Run the script on the profile
try {
    $response = $api->runScript($script_id, $profile_id, $parameters);
    
    if (isset($response['success']) && $response['success']) {
        // Log the execution
        $stmt = $db->prepare("INSERT INTO schedule_logs (profile_id, script_id, parameters, status, message, created_at) VALUES (?, ?, ?, 'success', ?, NOW())");
        $params_json = json_encode($parameters);
        $message = 'Script executed manually by user';
        $stmt->bind_param("isss", $profile_id, $script_id, $params_json, $message);
        $stmt->execute();
        
        echo json_encode([
            'success' => true,
            'message' => 'Script executed successfully',
            'data' => $response['data'] ?? null
        ]);
    } else {
        // Log the error
        $stmt = $db->prepare("INSERT INTO schedule_logs (profile_id, script_id, parameters, status, message, created_at) VALUES (?, ?, ?, 'error', ?, NOW())");
        $params_json = json_encode($parameters);
        $message = $response['message'] ?? 'Unknown API error';
        $stmt->bind_param("isss", $profile_id, $script_id, $params_json, $message);
        $stmt->execute();
        
        echo json_encode([
            'success' => false,
            'message' => $message
        ]);
    }
} catch (Exception $e) {
    // Log the exception
    $stmt = $db->prepare("INSERT INTO schedule_logs (profile_id, script_id, parameters, status, message, created_at) VALUES (?, ?, ?, 'error', ?, NOW())");
    $params_json = json_encode($parameters);
    $message = 'Exception: ' . $e->getMessage();
    $stmt->bind_param("isss", $profile_id, $script_id, $params_json, $message);
    $stmt->execute();
    
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

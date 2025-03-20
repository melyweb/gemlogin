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

// Check if script ID is provided
if (!isset($_GET['script_id']) || empty($_GET['script_id'])) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Script ID is required'
    ]);
    exit;
}

$script_id = clean_input($_GET['script_id']);

// Get database connection
$db = db_connect();

// Get script parameters
$stmt = $db->prepare("SELECT parameters FROM scripts WHERE id = ?");
$stmt->bind_param("s", $script_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Script not found'
    ]);
    exit;
}

$script = $result->fetch_assoc();
$parameters = json_decode($script['parameters'], true) ?: [];

// Return parameters
header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'parameters' => $parameters
]);
?>

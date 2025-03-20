<?php
require_once __DIR__ . '/functions.php';

// Login function
function login($username, $password) {
    $db = db_connect();
    $stmt = $db->prepare("SELECT id, username, password FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        if (password_verify($password, $user['password'])) {
            // Password is correct, start a new session
            session_regenerate_id();
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['last_activity'] = time();
            return true;
        }
    }
    
    return false;
}

// Logout function
function logout() {
    // Unset all session variables
    $_SESSION = [];
    
    // Delete the session cookie
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    
    // Destroy the session
    session_destroy();
}

// Require login for a page
function require_login() {
    if (!is_logged_in()) {
        set_flash_message('You must be logged in to access that page.', 'warning');
        redirect('login.php');
    }
    
    // Check for session timeout (30 minutes)
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 1800)) {
        logout();
        set_flash_message('Your session has expired. Please log in again.', 'warning');
        redirect('login.php');
    }
    
    // Update last activity time
    $_SESSION['last_activity'] = time();
}

// Get currently logged in user
function get_logged_in_user() {
    if (!is_logged_in()) {
        return null;
    }
    
    $db = db_connect();
    $stmt = $db->prepare("SELECT id, username, created_at FROM users WHERE id = ?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        return $result->fetch_assoc();
    }
    
    return null;
}

// Change password
function change_password($user_id, $current_password, $new_password) {
    $db = db_connect();
    
    // Verify current password
    $stmt = $db->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows !== 1) {
        return false;
    }
    
    $user = $result->fetch_assoc();
    if (!password_verify($current_password, $user['password'])) {
        return false;
    }
    
    // Update password
    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
    $stmt = $db->prepare("UPDATE users SET password = ? WHERE id = ?");
    $stmt->bind_param("si", $hashed_password, $user_id);
    
    return $stmt->execute();
}

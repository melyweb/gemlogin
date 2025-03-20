<?php
session_start();

// Include necessary files
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../vendor/autoload.php';

// Database connection
function db_connect() {
    static $mysqli = null;
    if ($mysqli === null) {
        $mysqli = require __DIR__ . '/../config/database.php';
    }
    return $mysqli;
}

// Check if user is logged in
function is_logged_in() {
    return isset($_SESSION['user_id']);
}

// Redirect to a URL
function redirect($url) {
    header("Location: $url");
    exit;
}

// Display flash message
function flash_message() {
    if (isset($_SESSION['flash_message'])) {
        $message = $_SESSION['flash_message'];
        $type = $_SESSION['flash_type'] ?? 'info';
        unset($_SESSION['flash_message']);
        unset($_SESSION['flash_type']);
        return "<div class='alert alert-{$type} alert-dismissible fade show' role='alert'>
                    {$message}
                    <button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button>
                </div>";
    }
    return '';
}

// Set flash message
function set_flash_message($message, $type = 'info') {
    $_SESSION['flash_message'] = $message;
    $_SESSION['flash_type'] = $type;
}

// Format date and time
function format_datetime($datetime, $format = 'Y-m-d H:i:s') {
    $dt = new DateTime($datetime);
    return $dt->format($format);
}

// Clean input data
function clean_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

// Generate random string
function generate_random_string($length = 10) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $randomString = '';
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[rand(0, strlen($characters) - 1)];
    }
    return $randomString;
}

// Get current page name
function get_current_page() {
    $path = $_SERVER['PHP_SELF'];
    $filename = basename($path);
    return $filename;
}

// Check if a page is active (for navigation highlighting)
function is_page_active($page) {
    $current_page = get_current_page();
    return $current_page == $page;
}

// Generate pagination HTML
function pagination($total_items, $items_per_page, $current_page, $url_pattern) {
    $total_pages = ceil($total_items / $items_per_page);
    
    if ($total_pages <= 1) {
        return '';
    }
    
    $html = '<nav aria-label="Page navigation"><ul class="pagination">';
    
    // Previous button
    if ($current_page > 1) {
        $prev_url = str_replace('{page}', $current_page - 1, $url_pattern);
        $html .= '<li class="page-item"><a class="page-link" href="' . $prev_url . '">&laquo;</a></li>';
    } else {
        $html .= '<li class="page-item disabled"><span class="page-link">&laquo;</span></li>';
    }
    
    // Page numbers
    $start_page = max(1, $current_page - 2);
    $end_page = min($total_pages, $current_page + 2);
    
    if ($start_page > 1) {
        $html .= '<li class="page-item"><a class="page-link" href="' . str_replace('{page}', 1, $url_pattern) . '">1</a></li>';
        if ($start_page > 2) {
            $html .= '<li class="page-item disabled"><span class="page-link">...</span></li>';
        }
    }
    
    for ($i = $start_page; $i <= $end_page; $i++) {
        if ($i == $current_page) {
            $html .= '<li class="page-item active"><span class="page-link">' . $i . '</span></li>';
        } else {
            $page_url = str_replace('{page}', $i, $url_pattern);
            $html .= '<li class="page-item"><a class="page-link" href="' . $page_url . '">' . $i . '</a></li>';
        }
    }
    
    if ($end_page < $total_pages) {
        if ($end_page < $total_pages - 1) {
            $html .= '<li class="page-item disabled"><span class="page-link">...</span></li>';
        }
        $html .= '<li class="page-item"><a class="page-link" href="' . str_replace('{page}', $total_pages, $url_pattern) . '">' . $total_pages . '</a></li>';
    }
    
    // Next button
    if ($current_page < $total_pages) {
        $next_url = str_replace('{page}', $current_page + 1, $url_pattern);
        $html .= '<li class="page-item"><a class="page-link" href="' . $next_url . '">&raquo;</a></li>';
    } else {
        $html .= '<li class="page-item disabled"><span class="page-link">&raquo;</span></li>';
    }
    
    $html .= '</ul></nav>';
    
    return $html;
}

// Get API instance
function get_api() {
    static $api = null;
    if ($api === null) {
        require_once __DIR__ . '/../lib/GemLoginAPI.php';
        $api = new GemLoginAPI();
    }
    return $api;
}

// Get all profiles from API and sync with database
function sync_profiles() {
    $api = get_api();
    $response = $api->getProfiles(null, 1, 1000); // Get up to 1000 profiles
    
    if (!isset($response['success']) || !$response['success']) {
        set_flash_message('Failed to fetch profiles from API: ' . ($response['message'] ?? 'Unknown error'), 'danger');
        return false;
    }
    
    $profiles = $response['data'] ?? [];
    $db = db_connect();
    
    // Begin transaction
    $db->begin_transaction();
    
    try {
        foreach ($profiles as $profile) {
            // Prepare values for bind_param to avoid passing expressions by reference
            $id = $profile['id'];
            $name = $profile['name'];
            $proxy = $profile['raw_proxy'] ?? '';
            $browser_type = $profile['browser_type'] ?? '';
            $browser_version = $profile['browser_version'] ?? '';
            $group_id = $profile['group_id'] ?? 0;
            $note = $profile['note'] ?? '';
            
            // Use the ON DUPLICATE KEY UPDATE with VALUES() syntax to simplify
            $stmt = $db->prepare("INSERT INTO profiles (id, name, proxy, browser_type, browser_version, group_id, note, last_synced) 
                               VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
                               ON DUPLICATE KEY UPDATE 
                               name = VALUES(name), 
                               proxy = VALUES(proxy), 
                               browser_type = VALUES(browser_type), 
                               browser_version = VALUES(browser_version), 
                               group_id = VALUES(group_id), 
                               note = VALUES(note), 
                               last_synced = NOW()");
            
            $stmt->bind_param("issssis", 
                $id, 
                $name, 
                $proxy, 
                $browser_type, 
                $browser_version, 
                $group_id, 
                $note
            );
            
            if (!$stmt->execute()) {
                throw new Exception("Failed to sync profile {$id}: " . $stmt->error);
            }
        }
        
        $db->commit();
        return true;
    } catch (Exception $e) {
        $db->rollback();
        set_flash_message('Error syncing profiles: ' . $e->getMessage(), 'danger');
        return false;
    }
}

// Get all scripts from API and sync with database
function sync_scripts() {
    $api = get_api();
    $response = $api->getScripts();
    
    if (!isset($response['success']) || !$response['success']) {
        set_flash_message('Failed to fetch scripts from API: ' . ($response['message'] ?? 'Unknown error'), 'danger');
        return false;
    }
    
    $scripts = $response['data'] ?? [];
    $db = db_connect();
    
    // Begin transaction
    $db->begin_transaction();
    
    try {
        foreach ($scripts as $script) {
            // Prepare values for bind_param to avoid passing expressions by reference
            $id = $script['id'];
            $name = $script['name'];
            $description = $script['description'] ?? '';
            $parameters = json_encode($script['parameters'] ?? []);
            
            $stmt = $db->prepare("INSERT INTO scripts (id, name, description, parameters, last_synced) 
                                VALUES (?, ?, ?, ?, NOW()) 
                                ON DUPLICATE KEY UPDATE 
                                name = VALUES(name),
                                description = VALUES(description), 
                                parameters = VALUES(parameters), 
                                last_synced = NOW()");
            
            $stmt->bind_param("ssss", 
                $id,
                $name,
                $description,
                $parameters
            );
            
            if (!$stmt->execute()) {
                throw new Exception("Failed to sync script {$script['id']}: " . $stmt->error);
            }
        }
        
        $db->commit();
        return true;
    } catch (Exception $e) {
        $db->rollback();
        set_flash_message('Error syncing scripts: ' . $e->getMessage(), 'danger');
        return false;
    }
}
?>

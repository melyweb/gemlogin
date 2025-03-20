<?php
// Include database configuration
$mysqli = require_once __DIR__ . '/../config/database.php';

// Create users table
$sql = "CREATE TABLE IF NOT EXISTS users (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

if ($mysqli->query($sql) === FALSE) {
    die("Error creating users table: " . $mysqli->error);
}

// Create profiles table (this will store profile metadata from GemLogin)
$sql = "CREATE TABLE IF NOT EXISTS profiles (
    id INT NOT NULL PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    proxy VARCHAR(255),
    browser_type VARCHAR(50),
    browser_version VARCHAR(50),
    group_id INT,
    note TEXT,
    status INT DEFAULT 0,
    last_synced TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

if ($mysqli->query($sql) === FALSE) {
    die("Error creating profiles table: " . $mysqli->error);
}

// Create scripts table
$sql = "CREATE TABLE IF NOT EXISTS scripts (
    id VARCHAR(50) NOT NULL PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    parameters TEXT,
    last_synced TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

if ($mysqli->query($sql) === FALSE) {
    die("Error creating scripts table: " . $mysqli->error);
}

// Create schedules table
$sql = "CREATE TABLE IF NOT EXISTS schedules (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    script_id VARCHAR(50) NOT NULL,
    start_time DATETIME NOT NULL,
    end_time DATETIME NOT NULL,
    profile_delay INT NOT NULL DEFAULT 300,
    loop_delay INT NOT NULL DEFAULT 600,
    status ENUM('pending', 'running', 'completed', 'stopped') DEFAULT 'pending',
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_run TIMESTAMP NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (script_id) REFERENCES scripts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

if ($mysqli->query($sql) === FALSE) {
    die("Error creating schedules table: " . $mysqli->error);
}

// Create schedule_profiles table (links schedules to profiles)
$sql = "CREATE TABLE IF NOT EXISTS schedule_profiles (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    schedule_id INT NOT NULL,
    profile_id INT NOT NULL,
    FOREIGN KEY (schedule_id) REFERENCES schedules(id) ON DELETE CASCADE,
    FOREIGN KEY (profile_id) REFERENCES profiles(id) ON DELETE CASCADE,
    UNIQUE KEY (schedule_id, profile_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

if ($mysqli->query($sql) === FALSE) {
    die("Error creating schedule_profiles table: " . $mysqli->error);
}

// Create schedule_logs table
$sql = "CREATE TABLE IF NOT EXISTS schedule_logs (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    schedule_id INT NOT NULL,
    profile_id INT NOT NULL,
    status ENUM('success', 'failed') NOT NULL,
    message TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (schedule_id) REFERENCES schedules(id) ON DELETE CASCADE,
    FOREIGN KEY (profile_id) REFERENCES profiles(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

if ($mysqli->query($sql) === FALSE) {
    die("Error creating schedule_logs table: " . $mysqli->error);
}

// Create a default admin user (username: admin, password: admin123)
$default_username = "admin";
$default_password = password_hash("admin123", PASSWORD_DEFAULT);

// Check if admin user already exists
$stmt = $mysqli->prepare("SELECT id FROM users WHERE username = ?");
$stmt->bind_param("s", $default_username);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    $stmt = $mysqli->prepare("INSERT INTO users (username, password) VALUES (?, ?)");
    $stmt->bind_param("ss", $default_username, $default_password);
    
    if ($stmt->execute() === FALSE) {
        echo "Error creating default user: " . $stmt->error;
    } else {
        echo "Default admin user created. Username: admin, Password: admin123\n";
    }
} else {
    echo "Admin user already exists.\n";
}

echo "Database setup completed successfully!\n";

$mysqli->close();
?>

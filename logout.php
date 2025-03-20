<?php
require_once 'includes/functions.php';
require_once 'includes/auth.php';

// Perform logout
logout();

// Redirect to login page with success message
set_flash_message('You have been successfully logged out.', 'success');
redirect('login.php');
?>

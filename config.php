<?php
// Resgrow CRM - Database Configuration
// Phase 1: Project Setup & Auth

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'u345095192_despearluser');
define('DB_PASS', 'Despearl@788');
define('DB_NAME', 'u345095192_despearldb');

// Application Configuration
define('APP_NAME', 'Resgrow CRM');
define('BASE_URL', 'https://despearl.in');
define('TIMEZONE', 'Asia/Qatar');

// Security Configuration
define('SESSION_TIMEOUT', 3600); // 1 hour
define('PASSWORD_MIN_LENGTH', 6);

// Set timezone
date_default_timezone_set(TIMEZONE);

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
?>
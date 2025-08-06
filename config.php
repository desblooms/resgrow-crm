<?php
// Resgrow CRM - Database Configuration (FIXED)
// Phase 1: Project Setup & Auth

// Enable error reporting for development
define('DEVELOPMENT', true); // Set to false in production

if (DEVELOPMENT) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    ini_set('log_errors', 1);
    
    // Create logs directory if it doesn't exist
    $log_dir = __DIR__ . '/data/logs';
    if (!is_dir($log_dir)) {
        mkdir($log_dir, 0755, true);
    }
    ini_set('error_log', $log_dir . '/php_errors.log');
}

// Database Configuration - DEFINE THESE FIRST
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

// Test database connection (only in development mode)
if (DEVELOPMENT) {
    try {
        $test_conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        if ($test_conn->connect_error) {
            error_log("Database connection failed: " . $test_conn->connect_error);
            // Don't die here, just log the error
        } else {
            // Connection successful
            $test_conn->close();
        }
    } catch (Exception $e) {
        error_log("Database connection error: " . $e->getMessage());
    }
}

// NOTE: Database class should be instantiated in individual files, not here
?>
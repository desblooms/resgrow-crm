<?php
// Resgrow CRM - Logout Handler
// Phase 1: Project Setup & Auth

require_once '../includes/session.php';
require_once '../includes/functions.php';

// Check if user is logged in
if (SessionManager::isLoggedIn()) {
    // Log the logout activity
    log_activity(SessionManager::getUserId(), 'logout', 'User logged out');
    
    // Destroy session
    SessionManager::destroy();
}

// Redirect to login page with success message
header('Location: login.php?logout=success');
exit();
?>
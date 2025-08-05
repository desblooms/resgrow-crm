<?php
// Resgrow CRM - Main Entry Point
// Phase 1: Project Setup & Auth

require_once '../includes/session.php';

// Check if user is logged in
if (SessionManager::isLoggedIn()) {
    // Redirect to dashboard
    header('Location: dashboard.php');
} else {
    // Redirect to login
    header('Location: login.php');
}

exit();
?>
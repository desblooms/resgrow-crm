<?php
// Resgrow CRM - Session Keep-Alive Endpoint
// Phase 1: Project Setup & Auth

require_once '../includes/session.php';

header('Content-Type: application/json');

// Check if user is logged in
if (SessionManager::isLoggedIn()) {
    // Update last activity
    $_SESSION['last_activity'] = time();
    
    echo json_encode([
        'success' => true,
        'message' => 'Session extended',
        'timestamp' => time(),
        'user' => SessionManager::getUsername()
    ]);
} else {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Session expired',
        'redirect' => 'login.php'
    ]);
}
?>
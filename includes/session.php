<?php
// Resgrow CRM - Session Management
// Phase 1: Project Setup & Auth

require_once '../config.php';

class SessionManager {
    
    public static function start() {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
        
        // Check session timeout
        if (isset($_SESSION['last_activity']) && 
            (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT)) {
            self::destroy();
            return false;
        }
        
        $_SESSION['last_activity'] = time();
        return true;
    }
    
    public static function login($user_id, $username, $role) {
        $_SESSION['user_id'] = $user_id;
        $_SESSION['username'] = $username;
        $_SESSION['role'] = $role;
        $_SESSION['login_time'] = time();
        $_SESSION['last_activity'] = time();
        
        // Regenerate session ID for security
        session_regenerate_id(true);
    }
    
    public static function logout() {
        self::destroy();
        header('Location: login.php');
        exit();
    }
    
    public static function destroy() {
        $_SESSION = array();
        
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        
        session_destroy();
    }
    
    public static function isLoggedIn() {
        self::start();
        return isset($_SESSION['user_id']) && isset($_SESSION['role']);
    }
    
    public static function getUserId() {
        return isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
    }
    
    public static function getUsername() {
        return isset($_SESSION['username']) ? $_SESSION['username'] : null;
    }
    
    public static function getRole() {
        return isset($_SESSION['role']) ? $_SESSION['role'] : null;
    }
    
    public static function hasRole($required_role) {
        $user_role = self::getRole();
        
        if (!$user_role) return false;
        
        // Admin has access to everything
        if ($user_role === 'admin') {
            return true;
        }
        
        // Check specific role
        return $user_role === $required_role;
    }
    
    public static function requireLogin() {
        if (!self::isLoggedIn()) {
            header('Location: login.php');
            exit();
        }
    }
    
    public static function requireRole($required_role) {
        self::requireLogin();
        
        if (!self::hasRole($required_role)) {
            header('Location: dashboard.php?error=access_denied');
            exit();
        }
    }
}
?>
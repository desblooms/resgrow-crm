<?php
// Resgrow CRM - Authentication Functions
// Phase 1: Project Setup & Auth

require_once 'db.php';
require_once 'session.php';

class Auth {
    private $db;
    
    public function __construct() {
        global $db;
        $this->db = $db;
    }
    
    public function login($email, $password) {
        $stmt = $this->db->prepare("SELECT id, name, email, password, role, status FROM users WHERE email = ? AND status = 'active'");
        
        if (!$stmt) {
            return ['success' => false, 'message' => 'Database error occurred'];
        }
        
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            
            if (password_verify($password, $user['password'])) {
                // Login successful
                SessionManager::login($user['id'], $user['name'], $user['role']);
                
                // Log login attempt
                $this->logActivity($user['id'], 'login', 'User logged in successfully');
                
                return [
                    'success' => true, 
                    'message' => 'Login successful',
                    'role' => $user['role']
                ];
            } else {
                // Wrong password
                $this->logActivity(null, 'login_failed', "Failed login attempt for email: $email");
                return ['success' => false, 'message' => 'Invalid email or password'];
            }
        } else {
            // User not found
            $this->logActivity(null, 'login_failed', "Failed login attempt for email: $email");
            return ['success' => false, 'message' => 'Invalid email or password'];
        }
    }
    
    public function register($name, $email, $password, $role = 'sales') {
        // Check if email already exists
        $stmt = $this->db->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            return ['success' => false, 'message' => 'Email already exists'];
        }
        
        // Validate password length
        if (strlen($password) < PASSWORD_MIN_LENGTH) {
            return ['success' => false, 'message' => 'Password must be at least ' . PASSWORD_MIN_LENGTH . ' characters'];
        }
        
        // Hash password
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        // Insert new user
        $stmt = $this->db->prepare("INSERT INTO users (name, email, password, role, status, created_at) VALUES (?, ?, ?, ?, 'active', NOW())");
        $stmt->bind_param("ssss", $name, $email, $hashed_password, $role);
        
        if ($stmt->execute()) {
            $user_id = $this->db->lastInsertId();
            $this->logActivity($user_id, 'register', 'User registered successfully');
            
            return [
                'success' => true, 
                'message' => 'User registered successfully',
                'user_id' => $user_id
            ];
        } else {
            return ['success' => false, 'message' => 'Registration failed'];
        }
    }
    
    public function changePassword($user_id, $current_password, $new_password) {
        // Get current password hash
        $stmt = $this->db->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            return ['success' => false, 'message' => 'User not found'];
        }
        
        $user = $result->fetch_assoc();
        
        // Verify current password
        if (!password_verify($current_password, $user['password'])) {
            return ['success' => false, 'message' => 'Current password is incorrect'];
        }
        
        // Validate new password
        if (strlen($new_password) < PASSWORD_MIN_LENGTH) {
            return ['success' => false, 'message' => 'New password must be at least ' . PASSWORD_MIN_LENGTH . ' characters'];
        }
        
        // Update password
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt = $this->db->prepare("UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?");
        $stmt->bind_param("si", $hashed_password, $user_id);
        
        if ($stmt->execute()) {
            $this->logActivity($user_id, 'password_change', 'Password changed successfully');
            return ['success' => true, 'message' => 'Password changed successfully'];
        } else {
            return ['success' => false, 'message' => 'Password change failed'];
        }
    }
    
    public function getUserById($user_id) {
        $stmt = $this->db->prepare("SELECT id, name, email, role, status, created_at FROM users WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        return $result->num_rows > 0 ? $result->fetch_assoc() : null;
    }
    
    public function updateUserStatus($user_id, $status) {
        $stmt = $this->db->prepare("UPDATE users SET status = ?, updated_at = NOW() WHERE id = ?");
        $stmt->bind_param("si", $status, $user_id);
        
        if ($stmt->execute()) {
            $this->logActivity($user_id, 'status_change', "User status changed to: $status");
            return ['success' => true, 'message' => 'User status updated'];
        } else {
            return ['success' => false, 'message' => 'Status update failed'];
        }
    }
    
    private function logActivity($user_id, $action, $description) {
        $stmt = $this->db->prepare("INSERT INTO activity_log (user_id, action, description, ip_address, created_at) VALUES (?, ?, ?, ?, NOW())");
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $stmt->bind_param("isss", $user_id, $action, $description, $ip_address);
        $stmt->execute();
    }
}
?>
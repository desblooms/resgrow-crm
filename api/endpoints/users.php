<?php
// Resgrow CRM - Users API Endpoint
// User management operations

class UsersAPI {
    private $db;
    
    public function __construct($database) {
        $this->db = $database;
    }
    
    public function handle($method, $id, $input) {
        session_start();
        $user_id = $_SESSION['user_id'];
        $user_role = $_SESSION['user_role'];
        
        switch ($method) {
            case 'GET':
                if ($id) {
                    $this->getUser($id, $user_role, $user_id);
                } else {
                    $this->getUsers($user_role, $user_id, $_GET);
                }
                break;
            case 'POST':
                $this->createUser($input, $user_role);
                break;
            case 'PUT':
                $this->updateUser($id, $input, $user_role, $user_id);
                break;
            case 'DELETE':
                $this->deleteUser($id, $user_role);
                break;
            default:
                $this->sendResponse(405, ['error' => 'Method not allowed']);
        }
    }
    
    private function getUsers($user_role, $user_id, $params) {
        // Only admin can view all users, others can only see themselves
        if ($user_role !== 'admin') {
            $this->getUser($user_id, $user_role, $user_id);
            return;
        }
        
        // Apply filters
        $where_conditions = [];
        $bind_params = [];
        
        if (!empty($params['role'])) {
            $where_conditions[] = "role = ?";
            $bind_params[] = $params['role'];
        }
        
        if (!empty($params['status'])) {
            $where_conditions[] = "status = ?";
            $bind_params[] = $params['status'];
        }
        
        if (!empty($params['search'])) {
            $where_conditions[] = "(name LIKE ? OR email LIKE ?)";
            $search_term = "%{$params['search']}%";
            $bind_params[] = $search_term;
            $bind_params[] = $search_term;
        }
        
        // Pagination
        $page = intval($params['page'] ?? 1);
        $limit = intval($params['limit'] ?? 20);
        $offset = ($page - 1) * $limit;
        
        // Build query
        $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
        
        $sql = "SELECT id, name, email, role, status, phone, last_login, created_at, updated_at 
                FROM users 
                {$where_clause}
                ORDER BY created_at DESC 
                LIMIT ? OFFSET ?";
        
        $bind_params[] = $limit;
        $bind_params[] = $offset;
        
        $stmt = $this->db->prepare($sql);
        if (!empty($bind_params)) {
            $types = str_repeat('s', count($bind_params) - 2) . 'ii';
            $stmt->bind_param($types, ...$bind_params);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        $users = [];
        
        while ($row = $result->fetch_assoc()) {
            $users[] = $this->formatUser($row);
        }
        
        // Get total count
        $count_sql = str_replace('SELECT id, name, email, role, status, phone, last_login, created_at, updated_at', 'SELECT COUNT(*)', $sql);
        $count_sql = str_replace('ORDER BY created_at DESC LIMIT ? OFFSET ?', '', $count_sql);
        
        $count_stmt = $this->db->prepare($count_sql);
        if (!empty($bind_params)) {
            $count_params = array_slice($bind_params, 0, -2);
            if (!empty($count_params)) {
                $count_types = str_repeat('s', count($count_params));
                $count_stmt->bind_param($count_types, ...$count_params);
            }
        }
        
        $count_stmt->execute();
        $total_count = $count_stmt->get_result()->fetch_row()[0];
        
        $this->sendResponse(200, [
            'users' => $users,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total_count,
                'total_pages' => ceil($total_count / $limit)
            ]
        ]);
    }
    
    private function getUser($id, $user_role, $current_user_id) {
        // Users can only view themselves unless they're admin
        if ($user_role !== 'admin' && $id != $current_user_id) {
            $this->sendResponse(403, ['error' => 'Access denied']);
            return;
        }
        
        $sql = "SELECT id, name, email, role, status, phone, last_login, created_at, updated_at 
                FROM users WHERE id = ?";
        
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($user = $result->fetch_assoc()) {
            // Get additional stats if admin is viewing
            if ($user_role === 'admin') {
                $stats = $this->getUserStats($id, $user['role']);
                $user_data = $this->formatUser($user);
                $user_data['stats'] = $stats;
                $this->sendResponse(200, $user_data);
            } else {
                $this->sendResponse(200, $this->formatUser($user));
            }
        } else {
            $this->sendResponse(404, ['error' => 'User not found']);
        }
    }
    
    private function createUser($input, $user_role) {
        // Only admin can create users
        if ($user_role !== 'admin') {
            $this->sendResponse(403, ['error' => 'Only administrators can create users']);
            return;
        }
        
        // Validate required fields
        $required = ['name', 'email', 'password', 'role'];
        foreach ($required as $field) {
            if (empty($input[$field])) {
                $this->sendResponse(400, ['error' => "Field '{$field}' is required"]);
                return;
            }
        }
        
        // Validate email
        if (!validate_email($input['email'])) {
            $this->sendResponse(400, ['error' => 'Invalid email format']);
            return;
        }
        
        // Check if email already exists
        $check_stmt = $this->db->prepare("SELECT id FROM users WHERE email = ?");
        $check_stmt->bind_param("s", $input['email']);
        $check_stmt->execute();
        if ($check_stmt->get_result()->num_rows > 0) {
            $this->sendResponse(400, ['error' => 'Email already exists']);
            return;
        }
        
        // Validate role
        $valid_roles = ['admin', 'marketing', 'sales'];
        if (!in_array($input['role'], $valid_roles)) {
            $this->sendResponse(400, ['error' => 'Invalid role']);
            return;
        }
        
        // Hash password
        $hashed_password = password_hash($input['password'], PASSWORD_DEFAULT);
        
        $sql = "INSERT INTO users (name, email, password, role, status, phone) VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("ssssss", 
            $input['name'],
            $input['email'],
            $hashed_password,
            $input['role'],
            $input['status'] ?? 'active',
            $input['phone'] ?? null
        );
        
        if ($stmt->execute()) {
            $new_user_id = $this->db->lastInsertId();
            
            // Log activity
            log_activity($_SESSION['user_id'], 'user_created', "Created user ID: {$new_user_id}");
            
            // Return created user
            $this->getUser($new_user_id, $user_role, $_SESSION['user_id']);
        } else {
            $this->sendResponse(500, ['error' => 'Failed to create user']);
        }
    }
    
    private function updateUser($id, $input, $user_role, $current_user_id) {
        // Users can only update themselves unless they're admin
        if ($user_role !== 'admin' && $id != $current_user_id) {
            $this->sendResponse(403, ['error' => 'Access denied']);
            return;
        }
        
        // Check if user exists
        $check_stmt = $this->db->prepare("SELECT id, role FROM users WHERE id = ?");
        $check_stmt->bind_param("i", $id);
        $check_stmt->execute();
        $existing_user = $check_stmt->get_result()->fetch_assoc();
        
        if (!$existing_user) {
            $this->sendResponse(404, ['error' => 'User not found']);
            return;
        }
        
        // Build update query
        $update_fields = [];
        $bind_params = [];
        $types = '';
        
        // Fields that regular users can update
        $user_fields = ['name', 'phone'];
        // Additional fields that admin can update
        $admin_fields = ['email', 'role', 'status'];
        
        $allowed_fields = ($user_role === 'admin') ? array_merge($user_fields, $admin_fields) : $user_fields;
        
        foreach ($allowed_fields as $field) {
            if (array_key_exists($field, $input)) {
                if ($field === 'email' && !validate_email($input[$field])) {
                    $this->sendResponse(400, ['error' => 'Invalid email format']);
                    return;
                }
                
                if ($field === 'role') {
                    $valid_roles = ['admin', 'marketing', 'sales'];
                    if (!in_array($input[$field], $valid_roles)) {
                        $this->sendResponse(400, ['error' => 'Invalid role']);
                        return;
                    }
                }
                
                $update_fields[] = "{$field} = ?";
                $bind_params[] = $input[$field];
                $types .= 's';
            }
        }
        
        // Handle password separately
        if (array_key_exists('password', $input) && !empty($input['password'])) {
            if (strlen($input['password']) < PASSWORD_MIN_LENGTH) {
                $this->sendResponse(400, ['error' => "Password must be at least " . PASSWORD_MIN_LENGTH . " characters"]);
                return;
            }
            
            $update_fields[] = "password = ?";
            $bind_params[] = password_hash($input['password'], PASSWORD_DEFAULT);
            $types .= 's';
        }
        
        if (empty($update_fields)) {
            $this->sendResponse(400, ['error' => 'No valid fields to update']);
            return;
        }
        
        $sql = "UPDATE users SET " . implode(', ', $update_fields) . " WHERE id = ?";
        $bind_params[] = $id;
        $types .= 'i';
        
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param($types, ...$bind_params);
        
        if ($stmt->execute()) {
            // Log activity
            log_activity($current_user_id, 'user_updated', "Updated user ID: {$id}");
            
            // Return updated user
            $this->getUser($id, $user_role, $current_user_id);
        } else {
            $this->sendResponse(500, ['error' => 'Failed to update user']);
        }
    }
    
    private function deleteUser($id, $user_role) {
        // Only admin can delete users
        if ($user_role !== 'admin') {
            $this->sendResponse(403, ['error' => 'Only administrators can delete users']);
            return;
        }
        
        // Prevent deleting yourself
        if ($id == $_SESSION['user_id']) {
            $this->sendResponse(400, ['error' => 'Cannot delete your own account']);
            return;
        }
        
        $stmt = $this->db->prepare("DELETE FROM users WHERE id = ?");
        $stmt->bind_param("i", $id);
        
        if ($stmt->execute()) {
            if ($stmt->affected_rows > 0) {
                log_activity($_SESSION['user_id'], 'user_deleted', "Deleted user ID: {$id}");
                $this->sendResponse(200, ['message' => 'User deleted successfully']);
            } else {
                $this->sendResponse(404, ['error' => 'User not found']);
            }
        } else {
            $this->sendResponse(500, ['error' => 'Failed to delete user']);
        }
    }
    
    private function getUserStats($user_id, $role) {
        $stats = [];
        
        if ($role === 'sales') {
            // Sales statistics
            $sql = "SELECT 
                        COUNT(*) as total_leads,
                        COUNT(CASE WHEN status = 'closed-won' THEN 1 END) as won_leads,
                        SUM(CASE WHEN status = 'closed-won' THEN sale_value_qr ELSE 0 END) as total_revenue,
                        AVG(CASE WHEN status = 'closed-won' THEN sale_value_qr END) as avg_deal_size
                    FROM leads WHERE assigned_to = ?";
            
            $stmt = $this->db->prepare($sql);
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_assoc();
            
            $stats = [
                'total_leads' => intval($result['total_leads']),
                'won_leads' => intval($result['won_leads']),
                'total_revenue' => floatval($result['total_revenue'] ?? 0),
                'avg_deal_size' => floatval($result['avg_deal_size'] ?? 0),
                'conversion_rate' => $result['total_leads'] > 0 ? 
                    round(($result['won_leads'] / $result['total_leads']) * 100, 2) : 0
            ];
            
        } elseif ($role === 'marketing') {
            // Marketing statistics
            $sql = "SELECT 
                        COUNT(DISTINCT c.id) as total_campaigns,
                        COUNT(l.id) as total_leads,
                        COUNT(CASE WHEN l.status = 'closed-won' THEN 1 END) as won_leads,
                        SUM(CASE WHEN l.status = 'closed-won' THEN l.sale_value_qr ELSE 0 END) as total_revenue
                    FROM campaigns c
                    LEFT JOIN leads l ON c.id = l.campaign_id
                    WHERE c.created_by = ?";
            
            $stmt = $this->db->prepare($sql);
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_assoc();
            
            $stats = [
                'total_campaigns' => intval($result['total_campaigns']),
                'total_leads' => intval($result['total_leads']),
                'won_leads' => intval($result['won_leads']),
                'total_revenue' => floatval($result['total_revenue'] ?? 0),
                'conversion_rate' => $result['total_leads'] > 0 ? 
                    round(($result['won_leads'] / $result['total_leads']) * 100, 2) : 0
            ];
        }
        
        return $stats;
    }
    
    private function formatUser($user) {
        return [
            'id' => intval($user['id']),
            'name' => $user['name'],
            'email' => $user['email'],
            'role' => $user['role'],
            'status' => $user['status'],
            'phone' => $user['phone'],
            'last_login' => $user['last_login'],
            'created_at' => $user['created_at'],
            'updated_at' => $user['updated_at']
        ];
    }
    
    private function sendResponse($status_code, $data) {
        http_response_code($status_code);
        echo json_encode($data, JSON_PRETTY_PRINT);
        exit();
    }
}
<<<<<<< HEAD
?>
=======
?>
>>>>>>> e981dd606dbc3d13315396933ec31366209c7f6d

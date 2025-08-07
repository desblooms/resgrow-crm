<?php
// Resgrow CRM - Leads API Endpoint
// Complete CRUD operations for leads management

class LeadsAPI {
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
                    $this->getLead($id, $user_role, $user_id);
                } else {
                    $this->getLeads($user_role, $user_id, $_GET);
                }
                break;
            case 'POST':
                $this->createLead($input, $user_id);
                break;
            case 'PUT':
                $this->updateLead($id, $input, $user_role, $user_id);
                break;
            case 'DELETE':
                $this->deleteLead($id, $user_role, $user_id);
                break;
            default:
                $this->sendResponse(405, ['error' => 'Method not allowed']);
        }
    }
    
    private function getLeads($user_role, $user_id, $params) {
        // Build query based on role
        $where_conditions = [];
        $bind_params = [];
        
        if ($user_role === 'sales') {
            $where_conditions[] = "l.assigned_to = ?";
            $bind_params[] = $user_id;
        } elseif ($user_role === 'marketing') {
            $where_conditions[] = "c.created_by = ?";
            $bind_params[] = $user_id;
        }
        
        // Apply filters
        if (!empty($params['status'])) {
            $where_conditions[] = "l.status = ?";
            $bind_params[] = $params['status'];
        }
        
        if (!empty($params['platform'])) {
            $where_conditions[] = "l.platform = ?";
            $bind_params[] = $params['platform'];
        }
        
        if (!empty($params['campaign_id'])) {
            $where_conditions[] = "l.campaign_id = ?";
            $bind_params[] = $params['campaign_id'];
        }
        
        if (!empty($params['search'])) {
            $where_conditions[] = "(l.full_name LIKE ? OR l.phone LIKE ? OR l.email LIKE ?)";
            $search_term = "%{$params['search']}%";
            $bind_params[] = $search_term;
            $bind_params[] = $search_term;
            $bind_params[] = $search_term;
        }
        
        // Pagination
        $page = intval($params['page'] ?? 1);
        $limit = intval($params['limit'] ?? 20);
        $offset = ($page - 1) * $limit;
        
        // Build query
        $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
        
        $sql = "SELECT l.*, c.title as campaign_title, u.name as assigned_to_name 
                FROM leads l 
                LEFT JOIN campaigns c ON l.campaign_id = c.id 
                LEFT JOIN users u ON l.assigned_to = u.id 
                {$where_clause}
                ORDER BY l.created_at DESC 
                LIMIT ? OFFSET ?";
        
        $bind_params[] = $limit;
        $bind_params[] = $offset;
        
        $stmt = $this->db->prepare($sql);
        if (!empty($bind_params)) {
            $types = str_repeat('s', count($bind_params) - 2) . 'ii'; // Last two are integers
            $stmt->bind_param($types, ...$bind_params);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        $leads = [];
        
        while ($row = $result->fetch_assoc()) {
            $leads[] = $this->formatLead($row);
        }
        
        // Get total count
        $count_sql = str_replace('SELECT l.*, c.title as campaign_title, u.name as assigned_to_name', 'SELECT COUNT(*)', $sql);
        $count_sql = str_replace('ORDER BY l.created_at DESC LIMIT ? OFFSET ?', '', $count_sql);
        
        $count_stmt = $this->db->prepare($count_sql);
        if (!empty($bind_params)) {
            $count_params = array_slice($bind_params, 0, -2); // Remove limit and offset
            if (!empty($count_params)) {
                $count_types = str_repeat('s', count($count_params));
                $count_stmt->bind_param($count_types, ...$count_params);
            }
        }
        
        $count_stmt->execute();
        $total_count = $count_stmt->get_result()->fetch_row()[0];
        
        $this->sendResponse(200, [
            'leads' => $leads,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total_count,
                'total_pages' => ceil($total_count / $limit)
            ]
        ]);
    }
    
    private function getLead($id, $user_role, $user_id) {
        $sql = "SELECT l.*, c.title as campaign_title, u.name as assigned_to_name 
                FROM leads l 
                LEFT JOIN campaigns c ON l.campaign_id = c.id 
                LEFT JOIN users u ON l.assigned_to = u.id 
                WHERE l.id = ?";
        
        // Add role-based access control
        if ($user_role === 'sales') {
            $sql .= " AND l.assigned_to = ?";
        } elseif ($user_role === 'marketing') {
            $sql .= " AND c.created_by = ?";
        }
        
        $stmt = $this->db->prepare($sql);
        if ($user_role !== 'admin') {
            $stmt->bind_param("ii", $id, $user_id);
        } else {
            $stmt->bind_param("i", $id);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($lead = $result->fetch_assoc()) {
            // Get lead interactions
            $interactions_sql = "SELECT li.*, u.name as user_name 
                               FROM lead_interactions li 
                               JOIN users u ON li.user_id = u.id 
                               WHERE li.lead_id = ? 
                               ORDER BY li.created_at DESC";
            
            $interactions_stmt = $this->db->prepare($interactions_sql);
            $interactions_stmt->bind_param("i", $id);
            $interactions_stmt->execute();
            $interactions_result = $interactions_stmt->get_result();
            
            $interactions = [];
            while ($interaction = $interactions_result->fetch_assoc()) {
                $interactions[] = $interaction;
            }
            
            $lead_data = $this->formatLead($lead);
            $lead_data['interactions'] = $interactions;
            
            $this->sendResponse(200, $lead_data);
        } else {
            $this->sendResponse(404, ['error' => 'Lead not found']);
        }
    }
    
    private function createLead($input, $user_id) {
        // Validate required fields
        $required = ['full_name', 'phone', 'platform'];
        foreach ($required as $field) {
            if (empty($input[$field])) {
                $this->sendResponse(400, ['error' => "Field '{$field}' is required"]);
                return;
            }
        }
        
        // Validate phone format
        if (!validate_phone($input['phone'])) {
            $this->sendResponse(400, ['error' => 'Invalid phone number format']);
            return;
        }
        
        // Validate email if provided
        if (!empty($input['email']) && !validate_email($input['email'])) {
            $this->sendResponse(400, ['error' => 'Invalid email format']);
            return;
        }
        
        $sql = "INSERT INTO leads (full_name, phone, email, campaign_id, platform, product, notes, assigned_to, status, sale_value_qr, lead_source, lead_quality, next_follow_up) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("sssississssss", 
            $input['full_name'],
            $input['phone'],
            $input['email'] ?? null,
            $input['campaign_id'] ?? null,
            $input['platform'],
            $input['product'] ?? null,
            $input['notes'] ?? null,
            $input['assigned_to'] ?? null,
            $input['status'] ?? 'new',
            $input['sale_value_qr'] ?? null,
            $input['lead_source'] ?? null,
            $input['lead_quality'] ?? 'warm',
            $input['next_follow_up'] ?? null
        );
        
        if ($stmt->execute()) {
            $lead_id = $this->db->lastInsertId();
            
            // Log activity
            log_activity($user_id, 'lead_created', "Created lead ID: {$lead_id}");
            
            // Get the created lead
            $this->getLead($lead_id, $_SESSION['user_role'], $user_id);
        } else {
            $this->sendResponse(500, ['error' => 'Failed to create lead']);
        }
    }
    
    private function updateLead($id, $input, $user_role, $user_id) {
        // Check if lead exists and user has permission
        $check_sql = "SELECT l.id, l.assigned_to, c.created_by 
                      FROM leads l 
                      LEFT JOIN campaigns c ON l.campaign_id = c.id 
                      WHERE l.id = ?";
        
        $check_stmt = $this->db->prepare($check_sql);
        $check_stmt->bind_param("i", $id);
        $check_stmt->execute();
        $lead = $check_stmt->get_result()->fetch_assoc();
        
        if (!$lead) {
            $this->sendResponse(404, ['error' => 'Lead not found']);
            return;
        }
        
        // Check permissions
        if ($user_role === 'sales' && $lead['assigned_to'] != $user_id) {
            $this->sendResponse(403, ['error' => 'Access denied']);
            return;
        } elseif ($user_role === 'marketing' && $lead['created_by'] != $user_id) {
            $this->sendResponse(403, ['error' => 'Access denied']);
            return;
        }
        
        // Build update query
        $update_fields = [];
        $bind_params = [];
        $types = '';
        
        $allowed_fields = ['full_name', 'phone', 'email', 'campaign_id', 'platform', 'product', 'notes', 'assigned_to', 'status', 'sale_value_qr', 'lead_source', 'lead_quality', 'next_follow_up'];
        
        foreach ($allowed_fields as $field) {
            if (array_key_exists($field, $input)) {
                $update_fields[] = "{$field} = ?";
                $bind_params[] = $input[$field];
                $types .= 's';
            }
        }
        
        if (empty($update_fields)) {
            $this->sendResponse(400, ['error' => 'No valid fields to update']);
            return;
        }
        
        $sql = "UPDATE leads SET " . implode(', ', $update_fields) . " WHERE id = ?";
        $bind_params[] = $id;
        $types .= 'i';
        
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param($types, ...$bind_params);
        
        if ($stmt->execute()) {
            // Log activity
            log_activity($user_id, 'lead_updated', "Updated lead ID: {$id}");
            
            // Return updated lead
            $this->getLead($id, $user_role, $user_id);
        } else {
            $this->sendResponse(500, ['error' => 'Failed to update lead']);
        }
    }
    
    private function deleteLead($id, $user_role, $user_id) {
        // Only admin can delete leads
        if ($user_role !== 'admin') {
            $this->sendResponse(403, ['error' => 'Only administrators can delete leads']);
            return;
        }
        
        $stmt = $this->db->prepare("DELETE FROM leads WHERE id = ?");
        $stmt->bind_param("i", $id);
        
        if ($stmt->execute()) {
            if ($stmt->affected_rows > 0) {
                log_activity($user_id, 'lead_deleted', "Deleted lead ID: {$id}");
                $this->sendResponse(200, ['message' => 'Lead deleted successfully']);
            } else {
                $this->sendResponse(404, ['error' => 'Lead not found']);
            }
        } else {
            $this->sendResponse(500, ['error' => 'Failed to delete lead']);
        }
    }
    
    private function formatLead($lead) {
        return [
            'id' => intval($lead['id']),
            'full_name' => $lead['full_name'],
            'phone' => $lead['phone'],
            'email' => $lead['email'],
            'campaign_id' => $lead['campaign_id'] ? intval($lead['campaign_id']) : null,
            'campaign_title' => $lead['campaign_title'],
            'platform' => $lead['platform'],
            'product' => $lead['product'],
            'notes' => $lead['notes'],
            'assigned_to' => $lead['assigned_to'] ? intval($lead['assigned_to']) : null,
            'assigned_to_name' => $lead['assigned_to_name'],
            'status' => $lead['status'],
            'sale_value_qr' => $lead['sale_value_qr'] ? floatval($lead['sale_value_qr']) : null,
            'lead_source' => $lead['lead_source'],
            'lead_quality' => $lead['lead_quality'],
            'last_contact_date' => $lead['last_contact_date'],
            'next_follow_up' => $lead['next_follow_up'],
            'created_at' => $lead['created_at'],
            'updated_at' => $lead['updated_at']
        ];
    }
    
    private function sendResponse($status_code, $data) {
        http_response_code($status_code);
        echo json_encode($data, JSON_PRETTY_PRINT);
        exit();
    }
}
?>
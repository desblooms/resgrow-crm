<?php
// Resgrow CRM - Campaigns API Endpoint
// Complete CRUD operations for campaigns management

class CampaignsAPI {
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
                    $this->getCampaign($id, $user_role, $user_id);
                } else {
                    $this->getCampaigns($user_role, $user_id, $_GET);
                }
                break;
            case 'POST':
                $this->createCampaign($input, $user_id, $user_role);
                break;
            case 'PUT':
                $this->updateCampaign($id, $input, $user_role, $user_id);
                break;
            case 'DELETE':
                $this->deleteCampaign($id, $user_role, $user_id);
                break;
            default:
                $this->sendResponse(405, ['error' => 'Method not allowed']);
        }
    }
    
    private function getCampaigns($user_role, $user_id, $params) {
        // Build query based on role
        $where_conditions = [];
        $bind_params = [];
        
        if ($user_role === 'marketing') {
            $where_conditions[] = "c.created_by = ?";
            $bind_params[] = $user_id;
        } elseif ($user_role === 'sales') {
            // Sales can see campaigns they have leads assigned to
            $where_conditions[] = "c.id IN (SELECT DISTINCT campaign_id FROM leads WHERE assigned_to = ?)";
            $bind_params[] = $user_id;
        }
        
        // Apply filters
        if (!empty($params['status'])) {
            $where_conditions[] = "c.status = ?";
            $bind_params[] = $params['status'];
        }
        
        if (!empty($params['search'])) {
            $where_conditions[] = "(c.title LIKE ? OR c.product_name LIKE ?)";
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
        
        $sql = "SELECT c.*, 
                       u1.name as created_by_name,
                       u2.name as assigned_to_name,
                       COUNT(l.id) as total_leads,
                       COUNT(CASE WHEN l.status = 'closed-won' THEN 1 END) as won_leads,
                       SUM(CASE WHEN l.status = 'closed-won' THEN l.sale_value_qr ELSE 0 END) as total_revenue
                FROM campaigns c 
                LEFT JOIN users u1 ON c.created_by = u1.id 
                LEFT JOIN users u2 ON c.assigned_to = u2.id 
                LEFT JOIN leads l ON c.id = l.campaign_id
                {$where_clause}
                GROUP BY c.id
                ORDER BY c.created_at DESC 
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
        $campaigns = [];
        
        while ($row = $result->fetch_assoc()) {
            $campaigns[] = $this->formatCampaign($row);
        }
        
        // Get total count
        $count_sql = "SELECT COUNT(DISTINCT c.id) FROM campaigns c 
                      LEFT JOIN leads l ON c.id = l.campaign_id 
                      {$where_clause}";
        
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
            'campaigns' => $campaigns,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total_count,
                'total_pages' => ceil($total_count / $limit)
            ]
        ]);
    }
    
    private function getCampaign($id, $user_role, $user_id) {
        $sql = "SELECT c.*, 
                       u1.name as created_by_name,
                       u2.name as assigned_to_name,
                       COUNT(l.id) as total_leads,
                       COUNT(CASE WHEN l.status = 'closed-won' THEN 1 END) as won_leads,
                       SUM(CASE WHEN l.status = 'closed-won' THEN l.sale_value_qr ELSE 0 END) as total_revenue
                FROM campaigns c 
                LEFT JOIN users u1 ON c.created_by = u1.id 
                LEFT JOIN users u2 ON c.assigned_to = u2.id 
                LEFT JOIN leads l ON c.id = l.campaign_id
                WHERE c.id = ?";
        
        // Add role-based access control
        if ($user_role === 'marketing') {
            $sql .= " AND c.created_by = ?";
        } elseif ($user_role === 'sales') {
            $sql .= " AND c.id IN (SELECT DISTINCT campaign_id FROM leads WHERE assigned_to = ?)";
        }
        
        $sql .= " GROUP BY c.id";
        
        $stmt = $this->db->prepare($sql);
        if ($user_role !== 'admin') {
            $stmt->bind_param("ii", $id, $user_id);
        } else {
            $stmt->bind_param("i", $id);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($campaign = $result->fetch_assoc()) {
            // Get campaign performance data
            $performance_sql = "SELECT * FROM campaign_performance WHERE campaign_id = ? ORDER BY date DESC LIMIT 30";
            $performance_stmt = $this->db->prepare($performance_sql);
            $performance_stmt->bind_param("i", $id);
            $performance_stmt->execute();
            $performance_result = $performance_stmt->get_result();
            
            $performance = [];
            while ($perf = $performance_result->fetch_assoc()) {
                $performance[] = $perf;
            }
            
            $campaign_data = $this->formatCampaign($campaign);
            $campaign_data['performance'] = $performance;
            
            $this->sendResponse(200, $campaign_data);
        } else {
            $this->sendResponse(404, ['error' => 'Campaign not found']);
        }
    }
    
    private function createCampaign($input, $user_id, $user_role) {
        // Only marketing and admin can create campaigns
        if ($user_role === 'sales') {
            $this->sendResponse(403, ['error' => 'Sales users cannot create campaigns']);
            return;
        }
        
        // Validate required fields
        $required = ['title', 'product_name', 'start_date', 'end_date'];
        foreach ($required as $field) {
            if (empty($input[$field])) {
                $this->sendResponse(400, ['error' => "Field '{$field}' is required"]);
                return;
            }
        }
        
        // Validate dates
        if (strtotime($input['start_date']) >= strtotime($input['end_date'])) {
            $this->sendResponse(400, ['error' => 'End date must be after start date']);
            return;
        }
        
        // Prepare platforms JSON
        $platforms_json = null;
        if (!empty($input['platforms']) && is_array($input['platforms'])) {
            $platforms_json = json_encode($input['platforms']);
        }
        
        $sql = "INSERT INTO campaigns (title, product_name, description, platforms, budget_qr, target_audience, objectives, created_by, assigned_to, start_date, end_date, status) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("ssssdssiisss", 
            $input['title'],
            $input['product_name'],
            $input['description'] ?? null,
            $platforms_json,
            $input['budget_qr'] ?? null,
            $input['target_audience'] ?? null,
            $input['objectives'] ?? null,
            $user_id,
            $input['assigned_to'] ?? null,
            $input['start_date'],
            $input['end_date'],
            $input['status'] ?? 'draft'
        );
        
        if ($stmt->execute()) {
            $campaign_id = $this->db->lastInsertId();
            
            // Log activity
            log_activity($user_id, 'campaign_created', "Created campaign ID: {$campaign_id}");
            
            // Get the created campaign
            $this->getCampaign($campaign_id, $user_role, $user_id);
        } else {
            $this->sendResponse(500, ['error' => 'Failed to create campaign']);
        }
    }
    
    private function updateCampaign($id, $input, $user_role, $user_id) {
        // Check if campaign exists and user has permission
        $check_sql = "SELECT created_by FROM campaigns WHERE id = ?";
        $check_stmt = $this->db->prepare($check_sql);
        $check_stmt->bind_param("i", $id);
        $check_stmt->execute();
        $result = $check_stmt->get_result();
        
        if (!$campaign = $result->fetch_assoc()) {
            $this->sendResponse(404, ['error' => 'Campaign not found']);
            return;
        }
        
        // Check permissions
        if ($user_role === 'marketing' && $campaign['created_by'] != $user_id) {
            $this->sendResponse(403, ['error' => 'Access denied']);
            return;
        } elseif ($user_role === 'sales') {
            $this->sendResponse(403, ['error' => 'Sales users cannot edit campaigns']);
            return;
        }
        
        // Build update query
        $update_fields = [];
        $bind_params = [];
        $types = '';
        
        $allowed_fields = ['title', 'product_name', 'description', 'budget_qr', 'target_audience', 'objectives', 'assigned_to', 'start_date', 'end_date', 'status'];
        
        foreach ($allowed_fields as $field) {
            if (array_key_exists($field, $input)) {
                $update_fields[] = "{$field} = ?";
                $bind_params[] = $input[$field];
                $types .= 's';
            }
        }
        
        // Handle platforms separately
        if (array_key_exists('platforms', $input)) {
            $update_fields[] = "platforms = ?";
            $platforms_json = is_array($input['platforms']) ? json_encode($input['platforms']) : $input['platforms'];
            $bind_params[] = $platforms_json;
            $types .= 's';
        }
        
        if (empty($update_fields)) {
            $this->sendResponse(400, ['error' => 'No valid fields to update']);
            return;
        }
        
        $sql = "UPDATE campaigns SET " . implode(', ', $update_fields) . " WHERE id = ?";
        $bind_params[] = $id;
        $types .= 'i';
        
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param($types, ...$bind_params);
        
        if ($stmt->execute()) {
            // Log activity
            log_activity($user_id, 'campaign_updated', "Updated campaign ID: {$id}");
            
            // Return updated campaign
            $this->getCampaign($id, $user_role, $user_id);
        } else {
            $this->sendResponse(500, ['error' => 'Failed to update campaign']);
        }
    }
    
    private function deleteCampaign($id, $user_role, $user_id) {
        // Only admin and campaign creator can delete
        if ($user_role === 'sales') {
            $this->sendResponse(403, ['error' => 'Sales users cannot delete campaigns']);
            return;
        }
        
        $check_sql = "SELECT created_by FROM campaigns WHERE id = ?";
        $check_stmt = $this->db->prepare($check_sql);
        $check_stmt->bind_param("i", $id);
        $check_stmt->execute();
        $result = $check_stmt->get_result();
        
        if (!$campaign = $result->fetch_assoc()) {
            $this->sendResponse(404, ['error' => 'Campaign not found']);
            return;
        }
        
        if ($user_role === 'marketing' && $campaign['created_by'] != $user_id) {
            $this->sendResponse(403, ['error' => 'Access denied']);
            return;
        }
        
        $stmt = $this->db->prepare("DELETE FROM campaigns WHERE id = ?");
        $stmt->bind_param("i", $id);
        
        if ($stmt->execute()) {
            if ($stmt->affected_rows > 0) {
                log_activity($user_id, 'campaign_deleted', "Deleted campaign ID: {$id}");
                $this->sendResponse(200, ['message' => 'Campaign deleted successfully']);
            } else {
                $this->sendResponse(404, ['error' => 'Campaign not found']);
            }
        } else {
            $this->sendResponse(500, ['error' => 'Failed to delete campaign']);
        }
    }
    
    private function formatCampaign($campaign) {
        $platforms = null;
        if ($campaign['platforms']) {
            $platforms = json_decode($campaign['platforms'], true);
        }
        
        return [
            'id' => intval($campaign['id']),
            'title' => $campaign['title'],
            'product_name' => $campaign['product_name'],
            'description' => $campaign['description'],
            'platforms' => $platforms,
            'budget_qr' => $campaign['budget_qr'] ? floatval($campaign['budget_qr']) : null,
            'target_audience' => $campaign['target_audience'],
            'objectives' => $campaign['objectives'],
            'created_by' => intval($campaign['created_by']),
            'created_by_name' => $campaign['created_by_name'],
            'assigned_to' => $campaign['assigned_to'] ? intval($campaign['assigned_to']) : null,
            'assigned_to_name' => $campaign['assigned_to_name'],
            'start_date' => $campaign['start_date'],
            'end_date' => $campaign['end_date'],
            'status' => $campaign['status'],
            'total_leads' => intval($campaign['total_leads'] ?? 0),
            'won_leads' => intval($campaign['won_leads'] ?? 0),
            'total_revenue' => floatval($campaign['total_revenue'] ?? 0),
            'created_at' => $campaign['created_at'],
            'updated_at' => $campaign['updated_at']
        ];
    }
    
    private function sendResponse($status_code, $data) {
        http_response_code($status_code);
        echo json_encode($data, JSON_PRETTY_PRINT);
        exit();
    }
}
?>
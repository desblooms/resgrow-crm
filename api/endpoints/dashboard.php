<?php
// Resgrow CRM - Dashboard API Endpoint
// Dashboard statistics and metrics

class DashboardAPI {
    private $db;
    
    public function __construct($database) {
        $this->db = $database;
    }
    
    public function handle($method, $id, $input) {
        session_start();
        $user_id = $_SESSION['user_id'];
        $user_role = $_SESSION['user_role'];
        
        if ($method !== 'GET') {
            $this->sendResponse(405, ['error' => 'Method not allowed']);
            return;
        }
        
        $this->getDashboardData($user_role, $user_id, $_GET);
    }
    
    private function getDashboardData($user_role, $user_id, $params) {
        $date_range = $params['range'] ?? '30'; // days
        $start_date = date('Y-m-d', strtotime("-{$date_range} days"));
        
        $dashboard_data = [
            'summary' => $this->getSummaryStats($user_role, $user_id),
            'recent_leads' => $this->getRecentLeads($user_role, $user_id, 5),
            'chart_data' => $this->getChartData($user_role, $user_id, $start_date),
            'performance' => $this->getPerformanceMetrics($user_role, $user_id, $start_date),
            'recent_activity' => $this->getRecentActivity($user_role, $user_id, 10)
        ];
        
        $this->sendResponse(200, $dashboard_data);
    }
    
    private function getSummaryStats($user_role, $user_id) {
        $stats = [];
        
        if ($user_role === 'admin') {
            // Admin sees everything
            $sql = "SELECT 
                        COUNT(DISTINCT l.id) as total_leads,
                        COUNT(DISTINCT CASE WHEN l.status = 'new' THEN l.id END) as new_leads,
                        COUNT(DISTINCT CASE WHEN l.status = 'closed-won' THEN l.id END) as won_leads,
                        SUM(CASE WHEN l.status = 'closed-won' THEN l.sale_value_qr ELSE 0 END) as total_revenue,
                        COUNT(DISTINCT c.id) as total_campaigns,
                        COUNT(DISTINCT u.id) as total_users
                    FROM leads l
                    LEFT JOIN campaigns c ON l.campaign_id = c.id
                    LEFT JOIN users u ON u.status = 'active'";
            
            $result = $this->db->query($sql)->fetch_assoc();
            
            $stats = [
                'total_leads' => intval($result['total_leads']),
                'new_leads' => intval($result['new_leads']),
                'won_leads' => intval($result['won_leads']),
                'total_revenue' => floatval($result['total_revenue'] ?? 0),
                'total_campaigns' => intval($result['total_campaigns']),
                'total_users' => intval($result['total_users']),
                'conversion_rate' => $result['total_leads'] > 0 ? 
                    round(($result['won_leads'] / $result['total_leads']) * 100, 2) : 0
            ];
            
        } elseif ($user_role === 'sales') {
            // Sales sees their own leads
            $sql = "SELECT 
                        COUNT(*) as total_leads,
                        COUNT(CASE WHEN status = 'new' THEN 1 END) as new_leads,
                        COUNT(CASE WHEN status = 'closed-won' THEN 1 END) as won_leads,
                        SUM(CASE WHEN status = 'closed-won' THEN sale_value_qr ELSE 0 END) as total_revenue,
                        COUNT(CASE WHEN status IN ('interested', 'follow-up') THEN 1 END) as pending_leads
                    FROM leads WHERE assigned_to = ?";
            
            $stmt = $this->db->prepare($sql);
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_assoc();
            
            $stats = [
                'total_leads' => intval($result['total_leads']),
                'new_leads' => intval($result['new_leads']),
                'won_leads' => intval($result['won_leads']),
                'total_revenue' => floatval($result['total_revenue'] ?? 0),
                'pending_leads' => intval($result['pending_leads']),
                'conversion_rate' => $result['total_leads'] > 0 ? 
                    round(($result['won_leads'] / $result['total_leads']) * 100, 2) : 0
            ];
            
        } elseif ($user_role === 'marketing') {
            // Marketing sees their campaigns and related leads
            $sql = "SELECT 
                        COUNT(DISTINCT c.id) as total_campaigns,
                        COUNT(l.id) as total_leads,
                        COUNT(CASE WHEN l.status = 'new' THEN 1 END) as new_leads,
                        COUNT(CASE WHEN l.status = 'closed-won' THEN 1 END) as won_leads,
                        SUM(CASE WHEN l.status = 'closed-won' THEN l.sale_value_qr ELSE 0 END) as total_revenue,
                        COUNT(CASE WHEN c.status = 'active' THEN 1 END) as active_campaigns
                    FROM campaigns c
                    LEFT JOIN leads l ON c.id = l.campaign_id
                    WHERE c.created_by = ?";
            
            $stmt = $this->db->prepare($sql);
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_assoc();
            
            $stats = [
                'total_campaigns' => intval($result['total_campaigns']),
                'active_campaigns' => intval($result['active_campaigns']),
                'total_leads' => intval($result['total_leads']),
                'new_leads' => intval($result['new_leads']),
                'won_leads' => intval($result['won_leads']),
                'total_revenue' => floatval($result['total_revenue'] ?? 0),
                'conversion_rate' => $result['total_leads'] > 0 ? 
                    round(($result['won_leads'] / $result['total_leads']) * 100, 2) : 0
            ];
        }
        
        return $stats;
    }
    
    private function getRecentLeads($user_role, $user_id, $limit) {
        $where_conditions = [];
        $bind_params = [];
        
        if ($user_role === 'sales') {
            $where_conditions[] = "l.assigned_to = ?";
            $bind_params[] = $user_id;
        } elseif ($user_role === 'marketing') {
            $where_conditions[] = "c.created_by = ?";
            $bind_params[] = $user_id;
        }
        
        $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
        
        $sql = "SELECT l.id, l.full_name, l.phone, l.platform, l.status, l.created_at, c.title as campaign_title
                FROM leads l 
                LEFT JOIN campaigns c ON l.campaign_id = c.id 
                {$where_clause}
                ORDER BY l.created_at DESC 
                LIMIT ?";
        
        $bind_params[] = $limit;
        
        $stmt = $this->db->prepare($sql);
        if (!empty($bind_params)) {
            $types = str_repeat('s', count($bind_params) - 1) . 'i';
            $stmt->bind_param($types, ...$bind_params);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        $leads = [];
        
        while ($row = $result->fetch_assoc()) {
            $leads[] = [
                'id' => intval($row['id']),
                'full_name' => $row['full_name'],
                'phone' => $row['phone'],
                'platform' => $row['platform'],
                'status' => $row['status'],
                'campaign_title' => $row['campaign_title'],
                'created_at' => $row['created_at']
            ];
        }
        
        return $leads;
    }
    
    private function getChartData($user_role, $user_id, $start_date) {
        $where_conditions = [];
        $bind_params = [];
        
        if ($user_role === 'sales') {
            $where_conditions[] = "l.assigned_to = ?";
            $bind_params[] = $user_id;
        } elseif ($user_role === 'marketing') {
            $where_conditions[] = "c.created_by = ?";
            $bind_params[] = $user_id;
        }
        
        $where_conditions[] = "l.created_at >= ?";
        $bind_params[] = $start_date;
        
        $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
        
        // Daily leads chart
        $sql = "SELECT 
                    DATE(l.created_at) as date,
                    COUNT(*) as total_leads,
                    COUNT(CASE WHEN l.status = 'closed-won' THEN 1 END) as won_leads,
                    SUM(CASE WHEN l.status = 'closed-won' THEN l.sale_value_qr ELSE 0 END) as revenue
                FROM leads l 
                LEFT JOIN campaigns c ON l.campaign_id = c.id 
                {$where_clause}
                GROUP BY DATE(l.created_at)
                ORDER BY date ASC";
        
        $stmt = $this->db->prepare($sql);
        if (!empty($bind_params)) {
            $types = str_repeat('s', count($bind_params));
            $stmt->bind_param($types, ...$bind_params);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        
        $chart_data = [
            'daily_leads' => [],
            'platforms' => $this->getPlatformBreakdown($user_role, $user_id, $start_date),
            'status_breakdown' => $this->getStatusBreakdown($user_role, $user_id)
        ];
        
        while ($row = $result->fetch_assoc()) {
            $chart_data['daily_leads'][] = [
                'date' => $row['date'],
                'leads' => intval($row['total_leads']),
                'won' => intval($row['won_leads']),
                'revenue' => floatval($row['revenue'])
            ];
        }
        
        return $chart_data;
    }
    
    private function getPlatformBreakdown($user_role, $user_id, $start_date) {
        $where_conditions = [];
        $bind_params = [];
        
        if ($user_role === 'sales') {
            $where_conditions[] = "l.assigned_to = ?";
            $bind_params[] = $user_id;
        } elseif ($user_role === 'marketing') {
            $where_conditions[] = "c.created_by = ?";
            $bind_params[] = $user_id;
        }
        
        $where_conditions[] = "l.created_at >= ?";
        $bind_params[] = $start_date;
        
        $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
        
        $sql = "SELECT l.platform, COUNT(*) as count
                FROM leads l 
                LEFT JOIN campaigns c ON l.campaign_id = c.id 
                {$where_clause}
                GROUP BY l.platform
                ORDER BY count DESC";
        
        $stmt = $this->db->prepare($sql);
        if (!empty($bind_params)) {
            $types = str_repeat('s', count($bind_params));
            $stmt->bind_param($types, ...$bind_params);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        $platforms = [];
        
        while ($row = $result->fetch_assoc()) {
            $platforms[] = [
                'platform' => $row['platform'],
                'count' => intval($row['count'])
            ];
        }
        
        return $platforms;
    }
    
    private function getStatusBreakdown($user_role, $user_id) {
        $where_conditions = [];
        $bind_params = [];
        
        if ($user_role === 'sales') {
            $where_conditions[] = "l.assigned_to = ?";
            $bind_params[] = $user_id;
        } elseif ($user_role === 'marketing') {
            $where_conditions[] = "c.created_by = ?";
            $bind_params[] = $user_id;
        }
        
        $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
        
        $sql = "SELECT l.status, COUNT(*) as count
                FROM leads l 
                LEFT JOIN campaigns c ON l.campaign_id = c.id 
                {$where_clause}
                GROUP BY l.status
                ORDER BY count DESC";
        
        $stmt = $this->db->prepare($sql);
        if (!empty($bind_params)) {
            $types = str_repeat('s', count($bind_params));
            $stmt->bind_param($types, ...$bind_params);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        $statuses = [];
        
        while ($row = $result->fetch_assoc()) {
            $statuses[] = [
                'status' => $row['status'],
                'count' => intval($row['count'])
            ];
        }
        
        return $statuses;
    }
    
    private function getPerformanceMetrics($user_role, $user_id, $start_date) {
        // This week vs last week comparison
        $this_week_start = date('Y-m-d', strtotime('monday this week'));
        $last_week_start = date('Y-m-d', strtotime('monday last week'));
        $last_week_end = date('Y-m-d', strtotime('sunday last week'));
        
        $where_base = '';
        $bind_params = [];
        
        if ($user_role === 'sales') {
            $where_base = "AND l.assigned_to = ?";
            $bind_params[] = $user_id;
        } elseif ($user_role === 'marketing') {
            $where_base = "AND c.created_by = ?";
            $bind_params[] = $user_id;
        }
        
        // This week
        $sql_this_week = "SELECT 
                            COUNT(*) as leads,
                            COUNT(CASE WHEN l.status = 'closed-won' THEN 1 END) as won,
                            SUM(CASE WHEN l.status = 'closed-won' THEN l.sale_value_qr ELSE 0 END) as revenue
                          FROM leads l 
                          LEFT JOIN campaigns c ON l.campaign_id = c.id 
                          WHERE l.created_at >= ? {$where_base}";
        
        $params_this_week = array_merge([$this_week_start], $bind_params);
        $stmt = $this->db->prepare($sql_this_week);
        $types = str_repeat('s', count($params_this_week));
        $stmt->bind_param($types, ...$params_this_week);
        $stmt->execute();
        $this_week = $stmt->get_result()->fetch_assoc();
        
        // Last week
        $sql_last_week = "SELECT 
                            COUNT(*) as leads,
                            COUNT(CASE WHEN l.status = 'closed-won' THEN 1 END) as won,
                            SUM(CASE WHEN l.status = 'closed-won' THEN l.sale_value_qr ELSE 0 END) as revenue
                          FROM leads l 
                          LEFT JOIN campaigns c ON l.campaign_id = c.id 
                          WHERE l.created_at >= ? AND l.created_at <= ? {$where_base}";
        
        $params_last_week = array_merge([$last_week_start, $last_week_end], $bind_params);
        $stmt = $this->db->prepare($sql_last_week);
        $types = str_repeat('s', count($params_last_week));
        $stmt->bind_param($types, ...$params_last_week);
        $stmt->execute();
        $last_week = $stmt->get_result()->fetch_assoc();
        
        return [
            'this_week' => [
                'leads' => intval($this_week['leads']),
                'won' => intval($this_week['won']),
                'revenue' => floatval($this_week['revenue'] ?? 0)
            ],
            'last_week' => [
                'leads' => intval($last_week['leads']),
                'won' => intval($last_week['won']),
                'revenue' => floatval($last_week['revenue'] ?? 0)
            ],
            'changes' => [
                'leads' => $last_week['leads'] > 0 ? 
                    round((($this_week['leads'] - $last_week['leads']) / $last_week['leads']) * 100, 1) : 0,
                'won' => $last_week['won'] > 0 ? 
                    round((($this_week['won'] - $last_week['won']) / $last_week['won']) * 100, 1) : 0,
                'revenue' => $last_week['revenue'] > 0 ? 
                    round((($this_week['revenue'] - $last_week['revenue']) / $last_week['revenue']) * 100, 1) : 0
            ]
        ];
    }
    
    private function getRecentActivity($user_role, $user_id, $limit) {
        $where_conditions = [];
        $bind_params = [];
        
        if ($user_role !== 'admin') {
            $where_conditions[] = "user_id = ?";
            $bind_params[] = $user_id;
        }
        
        $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
        
        $sql = "SELECT al.action, al.description, al.created_at, u.name as user_name
                FROM activity_log al
                LEFT JOIN users u ON al.user_id = u.id
                {$where_clause}
                ORDER BY al.created_at DESC 
                LIMIT ?";
        
        $bind_params[] = $limit;
        
        $stmt = $this->db->prepare($sql);
        if (!empty($bind_params)) {
            $types = str_repeat('s', count($bind_params) - 1) . 'i';
            $stmt->bind_param($types, ...$bind_params);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        $activities = [];
        
        while ($row = $result->fetch_assoc()) {
            $activities[] = [
                'action' => $row['action'],
                'description' => $row['description'],
                'user_name' => $row['user_name'],
                'created_at' => $row['created_at']
            ];
        }
        
        return $activities;
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

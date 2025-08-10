<?php
// Resgrow CRM - Analytics API Endpoint
// Advanced analytics and reporting

class AnalyticsAPI {
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
        
        // Parse analytics type from URL
        $analytics_type = $_GET['type'] ?? 'overview';
        
        switch ($analytics_type) {
            case 'overview':
                $this->getOverviewAnalytics($user_role, $user_id, $_GET);
                break;
            case 'campaigns':
                $this->getCampaignAnalytics($user_role, $user_id, $_GET);
                break;
            case 'sales':
                $this->getSalesAnalytics($user_role, $user_id, $_GET);
                break;
            case 'performance':
                $this->getPerformanceAnalytics($user_role, $user_id, $_GET);
                break;
            case 'trends':
                $this->getTrendAnalytics($user_role, $user_id, $_GET);
                break;
            default:
                $this->sendResponse(400, ['error' => 'Invalid analytics type']);
        }
    }
    
    private function getOverviewAnalytics($user_role, $user_id, $params) {
        $date_range = $this->getDateRange($params);
        
        $analytics = [
            'summary' => $this->getOverviewSummary($user_role, $user_id, $date_range),
            'lead_funnel' => $this->getLeadFunnel($user_role, $user_id, $date_range),
            'conversion_rates' => $this->getConversionRates($user_role, $user_id, $date_range),
            'top_platforms' => $this->getTopPlatforms($user_role, $user_id, $date_range),
            'revenue_trends' => $this->getRevenueTrends($user_role, $user_id, $date_range)
        ];
        
        $this->sendResponse(200, $analytics);
    }
    
    private function getCampaignAnalytics($user_role, $user_id, $params) {
        $date_range = $this->getDateRange($params);
        
        $analytics = [
            'campaign_performance' => $this->getCampaignPerformance($user_role, $user_id, $date_range),
            'roi_analysis' => $this->getROIAnalysis($user_role, $user_id, $date_range),
            'platform_comparison' => $this->getPlatformComparison($user_role, $user_id, $date_range),
            'campaign_lifecycle' => $this->getCampaignLifecycle($user_role, $user_id, $date_range)
        ];
        
        $this->sendResponse(200, $analytics);
    }
    
    private function getSalesAnalytics($user_role, $user_id, $params) {
        $date_range = $this->getDateRange($params);
        
        $analytics = [
            'sales_performance' => $this->getSalesPerformance($user_role, $user_id, $date_range),
            'agent_comparison' => $this->getAgentComparison($user_role, $user_id, $date_range),
            'lead_quality' => $this->getLeadQualityAnalysis($user_role, $user_id, $date_range),
            'follow_up_effectiveness' => $this->getFollowUpEffectiveness($user_role, $user_id, $date_range)
        ];
        
        $this->sendResponse(200, $analytics);
    }
    
    private function getPerformanceAnalytics($user_role, $user_id, $params) {
        $date_range = $this->getDateRange($params);
        
        $analytics = [
            'kpi_dashboard' => $this->getKPIDashboard($user_role, $user_id, $date_range),
            'goal_tracking' => $this->getGoalTracking($user_role, $user_id, $date_range),
            'productivity_metrics' => $this->getProductivityMetrics($user_role, $user_id, $date_range),
            'forecasting' => $this->getForecasting($user_role, $user_id, $date_range)
        ];
        
        $this->sendResponse(200, $analytics);
    }
    
    private function getTrendAnalytics($user_role, $user_id, $params) {
        $date_range = $this->getDateRange($params);
        
        $analytics = [
            'monthly_trends' => $this->getMonthlyTrends($user_role, $user_id, $date_range),
            'seasonal_patterns' => $this->getSeasonalPatterns($user_role, $user_id),
            'growth_analysis' => $this->getGrowthAnalysis($user_role, $user_id, $date_range),
            'predictive_insights' => $this->getPredictiveInsights($user_role, $user_id, $date_range)
        ];
        
        $this->sendResponse(200, $analytics);
    }
    
    private function getDateRange($params) {
        $start_date = $params['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
        $end_date = $params['end_date'] ?? date('Y-m-d');
        
        return ['start' => $start_date, 'end' => $end_date];
    }
    
    private function buildRoleFilter($user_role, $user_id) {
        if ($user_role === 'admin') {
            return ['condition' => '', 'params' => []];
        } elseif ($user_role === 'sales') {
            return ['condition' => 'AND l.assigned_to = ?', 'params' => [$user_id]];
        } elseif ($user_role === 'marketing') {
            return ['condition' => 'AND c.created_by = ?', 'params' => [$user_id]];
        }
        
        return ['condition' => '', 'params' => []];
    }
    
    private function getOverviewSummary($user_role, $user_id, $date_range) {
        $filter = $this->buildRoleFilter($user_role, $user_id);
        
        $sql = "SELECT 
                    COUNT(DISTINCT l.id) as total_leads,
                    COUNT(DISTINCT CASE WHEN l.status = 'closed-won' THEN l.id END) as won_deals,
                    SUM(CASE WHEN l.status = 'closed-won' THEN l.sale_value_qr ELSE 0 END) as total_revenue,
                    AVG(CASE WHEN l.status = 'closed-won' THEN l.sale_value_qr END) as avg_deal_size,
                    COUNT(DISTINCT c.id) as active_campaigns
                FROM leads l
                LEFT JOIN campaigns c ON l.campaign_id = c.id
                WHERE l.created_at >= ? AND l.created_at <= ?
                {$filter['condition']}";
        
        $params = array_merge([$date_range['start'], $date_range['end']], $filter['params']);
        
        $stmt = $this->db->prepare($sql);
        $types = str_repeat('s', count($params));
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        
        return [
            'total_leads' => intval($result['total_leads']),
            'won_deals' => intval($result['won_deals']),
            'total_revenue' => floatval($result['total_revenue'] ?? 0),
            'avg_deal_size' => floatval($result['avg_deal_size'] ?? 0),
            'active_campaigns' => intval($result['active_campaigns']),
            'conversion_rate' => $result['total_leads'] > 0 ? 
                round(($result['won_deals'] / $result['total_leads']) * 100, 2) : 0
        ];
    }
    
    private function getLeadFunnel($user_role, $user_id, $date_range) {
        $filter = $this->buildRoleFilter($user_role, $user_id);
        
        $sql = "SELECT 
                    l.status,
                    COUNT(*) as count,
                    SUM(CASE WHEN l.status = 'closed-won' THEN l.sale_value_qr ELSE 0 END) as revenue
                FROM leads l
                LEFT JOIN campaigns c ON l.campaign_id = c.id
                WHERE l.created_at >= ? AND l.created_at <= ?
                {$filter['condition']}
                GROUP BY l.status
                ORDER BY 
                    CASE l.status
                        WHEN 'new' THEN 1
                        WHEN 'contacted' THEN 2
                        WHEN 'interested' THEN 3
                        WHEN 'follow-up' THEN 4
                        WHEN 'closed-won' THEN 5
                        WHEN 'closed-lost' THEN 6
                        ELSE 7
                    END";
        
        $params = array_merge([$date_range['start'], $date_range['end']], $filter['params']);
        
        $stmt = $this->db->prepare($sql);
        $types = str_repeat('s', count($params));
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $funnel = [];
        while ($row = $result->fetch_assoc()) {
            $funnel[] = [
                'status' => $row['status'],
                'count' => intval($row['count']),
                'revenue' => floatval($row['revenue'])
            ];
        }
        
        return $funnel;
    }
    
    private function getConversionRates($user_role, $user_id, $date_range) {
        $filter = $this->buildRoleFilter($user_role, $user_id);
        
        $sql = "SELECT 
                    l.platform,
                    COUNT(*) as total_leads,
                    COUNT(CASE WHEN l.status = 'closed-won' THEN 1 END) as won_leads,
                    SUM(CASE WHEN l.status = 'closed-won' THEN l.sale_value_qr ELSE 0 END) as revenue
                FROM leads l
                LEFT JOIN campaigns c ON l.campaign_id = c.id
                WHERE l.created_at >= ? AND l.created_at <= ?
                {$filter['condition']}
                GROUP BY l.platform
                ORDER BY won_leads DESC";
        
        $params = array_merge([$date_range['start'], $date_range['end']], $filter['params']);
        
        $stmt = $this->db->prepare($sql);
        $types = str_repeat('s', count($params));
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $rates = [];
        while ($row = $result->fetch_assoc()) {
            $rates[] = [
                'platform' => $row['platform'],
                'total_leads' => intval($row['total_leads']),
                'won_leads' => intval($row['won_leads']),
                'conversion_rate' => $row['total_leads'] > 0 ? 
                    round(($row['won_leads'] / $row['total_leads']) * 100, 2) : 0,
                'revenue' => floatval($row['revenue'])
            ];
        }
        
        return $rates;
    }
    
    private function getTopPlatforms($user_role, $user_id, $date_range) {
        $filter = $this->buildRoleFilter($user_role, $user_id);
        
        $sql = "SELECT 
                    l.platform,
                    COUNT(*) as lead_count,
                    SUM(CASE WHEN l.status = 'closed-won' THEN l.sale_value_qr ELSE 0 END) as revenue,
                    AVG(CASE WHEN l.status = 'closed-won' THEN l.sale_value_qr END) as avg_deal_size
                FROM leads l
                LEFT JOIN campaigns c ON l.campaign_id = c.id
                WHERE l.created_at >= ? AND l.created_at <= ?
                {$filter['condition']}
                GROUP BY l.platform
                ORDER BY revenue DESC
                LIMIT 10";
        
        $params = array_merge([$date_range['start'], $date_range['end']], $filter['params']);
        
        $stmt = $this->db->prepare($sql);
        $types = str_repeat('s', count($params));
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $platforms = [];
        while ($row = $result->fetch_assoc()) {
            $platforms[] = [
                'platform' => $row['platform'],
                'lead_count' => intval($row['lead_count']),
                'revenue' => floatval($row['revenue']),
                'avg_deal_size' => floatval($row['avg_deal_size'] ?? 0)
            ];
        }
        
        return $platforms;
    }
    
    private function getRevenueTrends($user_role, $user_id, $date_range) {
        $filter = $this->buildRoleFilter($user_role, $user_id);
        
        $sql = "SELECT 
                    DATE(l.created_at) as date,
                    COUNT(*) as leads,
                    COUNT(CASE WHEN l.status = 'closed-won' THEN 1 END) as won_deals,
                    SUM(CASE WHEN l.status = 'closed-won' THEN l.sale_value_qr ELSE 0 END) as revenue
                FROM leads l
                LEFT JOIN campaigns c ON l.campaign_id = c.id
                WHERE l.created_at >= ? AND l.created_at <= ?
                {$filter['condition']}
                GROUP BY DATE(l.created_at)
                ORDER BY date ASC";
        
        $params = array_merge([$date_range['start'], $date_range['end']], $filter['params']);
        
        $stmt = $this->db->prepare($sql);
        $types = str_repeat('s', count($params));
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $trends = [];
        while ($row = $result->fetch_assoc()) {
            $trends[] = [
                'date' => $row['date'],
                'leads' => intval($row['leads']),
                'won_deals' => intval($row['won_deals']),
                'revenue' => floatval($row['revenue'])
            ];
        }
        
        return $trends;
    }
    
    private function getCampaignPerformance($user_role, $user_id, $date_range) {
        $filter = $this->buildRoleFilter($user_role, $user_id);
        
        $sql = "SELECT 
                    c.id,
                    c.title,
                    c.budget_qr,
                    COUNT(l.id) as total_leads,
                    COUNT(CASE WHEN l.status = 'closed-won' THEN 1 END) as won_leads,
                    SUM(CASE WHEN l.status = 'closed-won' THEN l.sale_value_qr ELSE 0 END) as revenue,
                    (SUM(CASE WHEN l.status = 'closed-won' THEN l.sale_value_qr ELSE 0 END) - COALESCE(c.budget_qr, 0)) as profit
                FROM campaigns c
                LEFT JOIN leads l ON c.id = l.campaign_id AND l.created_at >= ? AND l.created_at <= ?
                WHERE 1=1 {$filter['condition']}
                GROUP BY c.id, c.title, c.budget_qr
                ORDER BY revenue DESC";
        
        $params = array_merge([$date_range['start'], $date_range['end']], $filter['params']);
        
        $stmt = $this->db->prepare($sql);
        $types = str_repeat('s', count($params));
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $performance = [];
        while ($row = $result->fetch_assoc()) {
            $roi = $row['budget_qr'] > 0 ? (($row['revenue'] - $row['budget_qr']) / $row['budget_qr']) * 100 : 0;
            
            $performance[] = [
                'campaign_id' => intval($row['id']),
                'title' => $row['title'],
                'budget' => floatval($row['budget_qr'] ?? 0),
                'total_leads' => intval($row['total_leads']),
                'won_leads' => intval($row['won_leads']),
                'revenue' => floatval($row['revenue']),
                'profit' => floatval($row['profit']),
                'roi' => round($roi, 2),
                'conversion_rate' => $row['total_leads'] > 0 ? 
                    round(($row['won_leads'] / $row['total_leads']) * 100, 2) : 0
            ];
        }
        
        return $performance;
    }
    
    private function getROIAnalysis($user_role, $user_id, $date_range) {
        $filter = $this->buildRoleFilter($user_role, $user_id);
        
        $sql = "SELECT 
                    AVG(CASE WHEN c.budget_qr > 0 THEN 
                        ((SUM(CASE WHEN l.status = 'closed-won' THEN l.sale_value_qr ELSE 0 END) - c.budget_qr) / c.budget_qr) * 100 
                        ELSE 0 END) as avg_roi,
                    SUM(c.budget_qr) as total_budget,
                    SUM(CASE WHEN l.status = 'closed-won' THEN l.sale_value_qr ELSE 0 END) as total_revenue
                FROM campaigns c
                LEFT JOIN leads l ON c.id = l.campaign_id AND l.created_at >= ? AND l.created_at <= ?
                WHERE c.budget_qr > 0 {$filter['condition']}";
        
        $params = array_merge([$date_range['start'], $date_range['end']], $filter['params']);
        
        $stmt = $this->db->prepare($sql);
        $types = str_repeat('s', count($params));
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        
        return [
            'avg_roi' => round(floatval($result['avg_roi'] ?? 0), 2),
            'total_budget' => floatval($result['total_budget'] ?? 0),
            'total_revenue' => floatval($result['total_revenue'] ?? 0),
            'total_profit' => floatval($result['total_revenue'] ?? 0) - floatval($result['total_budget'] ?? 0)
        ];
    }
    
    private function getSalesPerformance($user_role, $user_id, $date_range) {
        if ($user_role === 'sales') {
            // For sales users, show only their performance
            $where_condition = "WHERE u.id = ? AND l.created_at >= ? AND l.created_at <= ?";
            $params = [$user_id, $date_range['start'], $date_range['end']];
        } else {
            // For admin/marketing, show all sales agents
            $where_condition = "WHERE u.role = 'sales' AND l.created_at >= ? AND l.created_at <= ?";
            $params = [$date_range['start'], $date_range['end']];
        }
        
        $sql = "SELECT 
                    u.id,
                    u.name,
                    COUNT(l.id) as total_leads,
                    COUNT(CASE WHEN l.status = 'closed-won' THEN 1 END) as won_leads,
                    SUM(CASE WHEN l.status = 'closed-won' THEN l.sale_value_qr ELSE 0 END) as revenue,
                    AVG(CASE WHEN l.status = 'closed-won' THEN l.sale_value_qr END) as avg_deal_size
                FROM users u
                LEFT JOIN leads l ON u.id = l.assigned_to
                {$where_condition}
                GROUP BY u.id, u.name
                ORDER BY revenue DESC";
        
        $stmt = $this->db->prepare($sql);
        $types = str_repeat('s', count($params));
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $performance = [];
        while ($row = $result->fetch_assoc()) {
            $performance[] = [
                'agent_id' => intval($row['id']),
                'name' => $row['name'],
                'total_leads' => intval($row['total_leads']),
                'won_leads' => intval($row['won_leads']),
                'revenue' => floatval($row['revenue']),
                'avg_deal_size' => floatval($row['avg_deal_size'] ?? 0),
                'conversion_rate' => $row['total_leads'] > 0 ? 
                    round(($row['won_leads'] / $row['total_leads']) * 100, 2) : 0
            ];
        }
        
        return $performance;
    }
    
    private function getMonthlyTrends($user_role, $user_id, $date_range) {
        $filter = $this->buildRoleFilter($user_role, $user_id);
        
        $sql = "SELECT 
                    YEAR(l.created_at) as year,
                    MONTH(l.created_at) as month,
                    COUNT(*) as total_leads,
                    COUNT(CASE WHEN l.status = 'closed-won' THEN 1 END) as won_leads,
                    SUM(CASE WHEN l.status = 'closed-won' THEN l.sale_value_qr ELSE 0 END) as revenue
                FROM leads l
                LEFT JOIN campaigns c ON l.campaign_id = c.id
                WHERE l.created_at >= ? AND l.created_at <= ?
                {$filter['condition']}
                GROUP BY YEAR(l.created_at), MONTH(l.created_at)
                ORDER BY year, month";
        
        $params = array_merge([$date_range['start'], $date_range['end']], $filter['params']);
        
        $stmt = $this->db->prepare($sql);
        $types = str_repeat('s', count($params));
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $trends = [];
        while ($row = $result->fetch_assoc()) {
            $trends[] = [
                'period' => $row['year'] . '-' . str_pad($row['month'], 2, '0', STR_PAD_LEFT),
                'total_leads' => intval($row['total_leads']),
                'won_leads' => intval($row['won_leads']),
                'revenue' => floatval($row['revenue']),
                'conversion_rate' => $row['total_leads'] > 0 ? 
                    round(($row['won_leads'] / $row['total_leads']) * 100, 2) : 0
            ];
        }
        
        return $trends;
    }
    
    // Additional helper methods would go here for other analytics functions
    // (getPlatformComparison, getLeadQualityAnalysis, etc.)
    
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

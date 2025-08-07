<?php
// Resgrow CRM - Marketing Dashboard (FIXED VERSION)
// Phase 4: Campaign Creation Module

require_once '../includes/session.php';
require_once '../includes/functions.php';
require_once '../includes/db.php';

// Require marketing or admin role
if (!SessionManager::hasRole('marketing') && !SessionManager::hasRole('admin')) {
    header('Location: ../public/dashboard.php');
    exit();
}

$page_title = 'Marketing Dashboard';

// Initialize database connection
global $db;
if (!isset($db) || !$db) {
    try {
        $db = new Database();
    } catch (Exception $e) {
        die("Database connection failed: " . $e->getMessage());
    }
}

// Get marketing dashboard statistics
try {
    $user_id = SessionManager::getUserId();
    $user_role = SessionManager::getRole();
    
    // Get campaign stats (all campaigns for admin, own campaigns for marketing)
    if ($user_role === 'admin') {
        $campaign_stats_query = "
            SELECT 
                COUNT(*) as total_campaigns,
                COUNT(CASE WHEN status = 'active' THEN 1 END) as active_campaigns,
                COUNT(CASE WHEN status = 'draft' THEN 1 END) as draft_campaigns,
                COALESCE(SUM(budget_qr), 0) as total_budget,
                COALESCE(AVG(budget_qr), 0) as avg_budget
            FROM campaigns
        ";
        $campaign_stmt = $db->query($campaign_stats_query);
    } else {
        $campaign_stats_query = "
            SELECT 
                COUNT(*) as total_campaigns,
                COUNT(CASE WHEN status = 'active' THEN 1 END) as active_campaigns,
                COUNT(CASE WHEN status = 'draft' THEN 1 END) as draft_campaigns,
                COALESCE(SUM(budget_qr), 0) as total_budget,
                COALESCE(AVG(budget_qr), 0) as avg_budget
            FROM campaigns 
            WHERE created_by = ? OR assigned_to = ?
        ";
        $campaign_stmt = $db->prepare($campaign_stats_query);
        $campaign_stmt->bind_param("ii", $user_id, $user_id);
        $campaign_stmt->execute();
        $campaign_stmt = $campaign_stmt->get_result();
    }
    
    $campaign_stats = $campaign_stmt->fetch_assoc();
    
    // Get lead stats from campaigns
    if ($user_role === 'admin') {
        $lead_stats_query = "
            SELECT 
                COUNT(l.id) as total_leads,
                COUNT(CASE WHEN l.status = 'new' THEN 1 END) as new_leads,
                COUNT(CASE WHEN l.status = 'closed-won' THEN 1 END) as closed_deals,
                COALESCE(SUM(CASE WHEN l.status = 'closed-won' THEN l.sale_value_qr END), 0) as total_revenue,
                COUNT(CASE WHEN DATE(l.created_at) = CURDATE() THEN 1 END) as today_leads
            FROM leads l
            LEFT JOIN campaigns c ON l.campaign_id = c.id
        ";
        $lead_stmt = $db->query($lead_stats_query);
    } else {
        $lead_stats_query = "
            SELECT 
                COUNT(l.id) as total_leads,
                COUNT(CASE WHEN l.status = 'new' THEN 1 END) as new_leads,
                COUNT(CASE WHEN l.status = 'closed-won' THEN 1 END) as closed_deals,
                COALESCE(SUM(CASE WHEN l.status = 'closed-won' THEN l.sale_value_qr END), 0) as total_revenue,
                COUNT(CASE WHEN DATE(l.created_at) = CURDATE() THEN 1 END) as today_leads
            FROM leads l
            LEFT JOIN campaigns c ON l.campaign_id = c.id
            WHERE c.created_by = ? OR c.assigned_to = ?
        ";
        $lead_stmt = $db->prepare($lead_stats_query);
        $lead_stmt->bind_param("ii", $user_id, $user_id);
        $lead_stmt->execute();
        $lead_stmt = $lead_stmt->get_result();
    }
    
    $lead_stats = $lead_stmt->fetch_assoc();
    
    // Calculate conversion rate
    $conversion_rate = $lead_stats['total_leads'] > 0 ? 
        round(($lead_stats['closed_deals'] / $lead_stats['total_leads']) * 100, 2) : 0;
    
    // FIXED: Get recent campaigns with proper GROUP BY
    if ($user_role === 'admin') {
        $recent_campaigns_query = "
            SELECT c.id, c.title, c.product_name, c.status, c.budget_qr, 
                   c.start_date, c.end_date, c.created_at,
                   u.name as created_by_name,
                   COALESCE(lead_stats.leads_count, 0) as leads_count,
                   COALESCE(lead_stats.deals_count, 0) as deals_count,
                   COALESCE(lead_stats.revenue, 0) as revenue
            FROM campaigns c
            LEFT JOIN users u ON c.created_by = u.id
            LEFT JOIN (
                SELECT campaign_id, 
                       COUNT(*) as leads_count,
                       COUNT(CASE WHEN status = 'closed-won' THEN 1 END) as deals_count,
                       COALESCE(SUM(CASE WHEN status = 'closed-won' THEN sale_value_qr END), 0) as revenue
                FROM leads 
                GROUP BY campaign_id
            ) lead_stats ON c.id = lead_stats.campaign_id
            ORDER BY c.created_at DESC
            LIMIT 5
        ";
        $recent_campaigns = $db->query($recent_campaigns_query);
    } else {
        $recent_campaigns_query = "
            SELECT c.id, c.title, c.product_name, c.status, c.budget_qr, 
                   c.start_date, c.end_date, c.created_at,
                   u.name as created_by_name,
                   COALESCE(lead_stats.leads_count, 0) as leads_count,
                   COALESCE(lead_stats.deals_count, 0) as deals_count,
                   COALESCE(lead_stats.revenue, 0) as revenue
            FROM campaigns c
            LEFT JOIN users u ON c.created_by = u.id
            LEFT JOIN (
                SELECT campaign_id, 
                       COUNT(*) as leads_count,
                       COUNT(CASE WHEN status = 'closed-won' THEN 1 END) as deals_count,
                       COALESCE(SUM(CASE WHEN status = 'closed-won' THEN sale_value_qr END), 0) as revenue
                FROM leads 
                GROUP BY campaign_id
            ) lead_stats ON c.id = lead_stats.campaign_id
            WHERE c.created_by = ? OR c.assigned_to = ?
            ORDER BY c.created_at DESC
            LIMIT 5
        ";
        $recent_campaigns_stmt = $db->prepare($recent_campaigns_query);
        $recent_campaigns_stmt->bind_param("ii", $user_id, $user_id);
        $recent_campaigns_stmt->execute();
        $recent_campaigns = $recent_campaigns_stmt->get_result();
    }
    
    // Get platform performance
    if ($user_role === 'admin') {
        $platform_performance_query = "
            SELECT 
                l.platform,
                COUNT(*) as lead_count,
                COUNT(CASE WHEN l.status = 'closed-won' THEN 1 END) as conversions,
                COALESCE(SUM(CASE WHEN l.status = 'closed-won' THEN l.sale_value_qr END), 0) as revenue
            FROM leads l
            LEFT JOIN campaigns c ON l.campaign_id = c.id
            GROUP BY l.platform
            ORDER BY revenue DESC
            LIMIT 6
        ";
        $platform_performance = $db->query($platform_performance_query);
    } else {
        $platform_performance_query = "
            SELECT 
                l.platform,
                COUNT(*) as lead_count,
                COUNT(CASE WHEN l.status = 'closed-won' THEN 1 END) as conversions,
                COALESCE(SUM(CASE WHEN l.status = 'closed-won' THEN l.sale_value_qr END), 0) as revenue
            FROM leads l
            LEFT JOIN campaigns c ON l.campaign_id = c.id
            WHERE c.created_by = ? OR c.assigned_to = ?
            GROUP BY l.platform
            ORDER BY revenue DESC
            LIMIT 6
        ";
        $platform_performance_stmt = $db->prepare($platform_performance_query);
        $platform_performance_stmt->bind_param("ii", $user_id, $user_id);
        $platform_performance_stmt->execute();
        $platform_performance = $platform_performance_stmt->get_result();
    }
    
} catch (Exception $e) {
    log_error("Marketing dashboard query error: " . $e->getMessage());
    set_flash_message('error', 'Error loading dashboard data');
    
    // Set default values
    $campaign_stats = [
        'total_campaigns' => 0, 'active_campaigns' => 0, 'draft_campaigns' => 0,
        'total_budget' => 0, 'avg_budget' => 0
    ];
    $lead_stats = [
        'total_leads' => 0, 'new_leads' => 0, 'closed_deals' => 0,
        'total_revenue' => 0, 'today_leads' => 0
    ];
    $conversion_rate = 0;
    $recent_campaigns = null;
    $platform_performance = null;
}

include '../templates/header.php';
?>

<div class="min-h-screen bg-gray-50">
    <!-- Marketing Navigation -->
    <nav class="bg-white shadow border-b border-gray-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                
                <!-- Logo and Primary Navigation -->
                <div class="flex">
                    <div class="flex-shrink-0 flex items-center">
                        <a href="dashboard.php" class="flex items-center">
                            <div class="h-8 w-8 bg-primary-600 rounded-lg flex items-center justify-center">
                                <svg class="h-5 w-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                                </svg>
                            </div>
                            <span class="ml-2 text-xl font-semibold text-gray-900"><?php echo APP_NAME; ?></span>
                        </a>
                    </div>
                    
                    <!-- Desktop Navigation Links -->
                    <div class="hidden sm:ml-6 sm:flex sm:space-x-8">
                        <a href="dashboard.php" class="border-primary-500 text-gray-900 inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium">
                            Dashboard
                        </a>
                        <a href="campaigns.php" class="border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium">
                            Campaigns
                        </a>
                        <a href="assign-leads.php" class="border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium">
                            Lead Assignment
                        </a>
                        <?php if (SessionManager::hasRole('admin')): ?>
                        <a href="../admin/analytics.php" class="border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium">
                            Analytics
                        </a>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Right Side Navigation -->
                <div class="hidden sm:ml-6 sm:flex sm:items-center">
                    <!-- Profile dropdown -->
                    <div class="ml-3 relative">
                        <div>
                            <button type="button" class="bg-white flex text-sm rounded-full focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500" id="user-menu-button">
                                <span class="sr-only">Open user menu</span>
                                <div class="h-8 w-8 bg-primary-600 rounded-full flex items-center justify-center">
                                    <span class="text-white text-sm font-medium">
                                        <?php echo strtoupper(substr(SessionManager::getUsername(), 0, 1)); ?>
                                    </span>
                                </div>
                            </button>
                        </div>

                        <!-- Dropdown menu -->
                        <div class="hidden origin-top-right absolute right-0 mt-2 w-48 rounded-md shadow-lg py-1 bg-white ring-1 ring-black ring-opacity-5 focus:outline-none z-50" id="user-menu">
                            <div class="px-4 py-2 text-sm text-gray-500 border-b border-gray-100">
                                Signed in as<br>
                                <strong><?php echo htmlspecialchars(SessionManager::getUsername()); ?></strong>
                            </div>
                            <a href="../public/logout.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                Sign out
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Mobile menu button -->
                <div class="flex items-center sm:hidden">
                    <button type="button" class="inline-flex items-center justify-center p-2 rounded-md text-gray-400 hover:text-gray-500 hover:bg-gray-100" id="mobile-menu-button">
                        <span class="sr-only">Open main menu</span>
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                        </svg>
                    </button>
                </div>
            </div>
        </div>

        <!-- Mobile menu -->
        <div class="hidden sm:hidden" id="mobile-menu">
            <div class="pt-2 pb-3 space-y-1">
                <a href="dashboard.php" class="bg-primary-50 border-primary-500 text-primary-700 block pl-3 pr-4 py-2 border-l-4 text-base font-medium">
                    Dashboard
                </a>
                <a href="campaigns.php" class="border-transparent text-gray-600 hover:bg-gray-50 hover:border-gray-300 hover:text-gray-800 block pl-3 pr-4 py-2 border-l-4 text-base font-medium">
                    Campaigns
                </a>
                <a href="assign-leads.php" class="border-transparent text-gray-600 hover:bg-gray-50 hover:border-gray-300 hover:text-gray-800 block pl-3 pr-4 py-2 border-l-4 text-base font-medium">
                    Lead Assignment
                </a>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
        <div class="px-4 py-6 sm:px-0">
            
            <!-- Header -->
            <div class="md:flex md:items-center md:justify-between mb-8">
                <div class="flex-1 min-w-0">
                    <h2 class="text-2xl font-bold leading-7 text-gray-900 sm:text-3xl sm:truncate">
                        Marketing Dashboard
                    </h2>
                    <p class="mt-1 text-sm text-gray-500">
                        Welcome back, <?php echo htmlspecialchars(SessionManager::getUsername()); ?> • Campaign Management & Performance
                    </p>
                </div>
                <div class="mt-4 flex md:mt-0 md:ml-4 space-x-3">
                    <a href="new-campaign.php" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-primary-600 hover:bg-primary-700">
                        <svg class="-ml-1 mr-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                        </svg>
                        New Campaign
                    </a>
                </div>
            </div>

            <!-- Stats Overview -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                
                <!-- Total Campaigns -->
                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="p-5">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <div class="w-8 h-8 bg-blue-100 rounded-md flex items-center justify-center">
                                    <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
                                    </svg>
                                </div>
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 truncate">Total Campaigns</dt>
                                    <dd class="text-lg font-medium text-gray-900">
                                        <?php echo number_format($campaign_stats['total_campaigns']); ?>
                                    </dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                    <div class="bg-gray-50 px-5 py-3">
                        <div class="text-sm">
                            <span class="text-green-600 font-medium"><?php echo $campaign_stats['active_campaigns']; ?></span>
                            <span class="text-gray-500">active</span>
                            <span class="mx-2">•</span>
                            <span class="text-yellow-600 font-medium"><?php echo $campaign_stats['draft_campaigns']; ?></span>
                            <span class="text-gray-500">draft</span>
                        </div>
                    </div>
                </div>

                <!-- Total Budget -->
                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="p-5">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <div class="w-8 h-8 bg-green-100 rounded-md flex items-center justify-center">
                                    <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"></path>
                                    </svg>
                                </div>
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 truncate">Total Budget</dt>
                                    <dd class="text-lg font-medium text-gray-900">
                                        <?php echo format_currency($campaign_stats['total_budget']); ?>
                                    </dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                    <div class="bg-gray-50 px-5 py-3">
                        <div class="text-sm">
                            <span class="text-green-600 font-medium"><?php echo format_currency($campaign_stats['avg_budget']); ?></span>
                            <span class="text-gray-500">average per campaign</span>
                        </div>
                    </div>
                </div>

                <!-- Total Leads -->
                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="p-5">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <div class="w-8 h-8 bg-purple-100 rounded-md flex items-center justify-center">
                                    <svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                                    </svg>
                                </div>
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 truncate">Total Leads</dt>
                                    <dd class="text-lg font-medium text-gray-900">
                                        <?php echo number_format($lead_stats['total_leads']); ?>
                                    </dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                    <div class="bg-gray-50 px-5 py-3">
                        <div class="text-sm">
                            <span class="text-blue-600 font-medium"><?php echo $lead_stats['today_leads']; ?></span>
                            <span class="text-gray-500">new today</span>
                            <span class="mx-2">•</span>
                            <span class="text-orange-600 font-medium"><?php echo $lead_stats['new_leads']; ?></span>
                            <span class="text-gray-500">unassigned</span>
                        </div>
                    </div>
                </div>

                <!-- Performance -->
                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="p-5">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <div class="w-8 h-8 bg-yellow-100 rounded-md flex items-center justify-center">
                                    <svg class="w-5 h-5 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
                                    </svg>
                                </div>
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 truncate">Conversion Rate</dt>
                                    <dd class="text-lg font-medium text-gray-900">
                                        <?php echo $conversion_rate; ?>%
                                    </dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                    <div class="bg-gray-50 px-5 py-3">
                        <div class="text-sm">
                            <span class="text-green-600 font-medium"><?php echo $lead_stats['closed_deals']; ?></span>
                            <span class="text-gray-500">closed deals</span>
                            <span class="mx-2">•</span>
                            <span class="text-green-600 font-medium"><?php echo format_currency($lead_stats['total_revenue']); ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Charts and Performance Section -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
                
                <!-- Platform Performance -->
                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="px-4 py-5 sm:p-6">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-lg leading-6 font-medium text-gray-900">Platform Performance</h3>
                            <a href="campaigns.php?platform=Meta" class="text-sm font-medium text-primary-600 hover:text-primary-500">View Details</a>
                        </div>
                        
                        <?php if ($platform_performance && $platform_performance->num_rows > 0): ?>
                            <div class="space-y-4">
                                <?php while ($platform = $platform_performance->fetch_assoc()): ?>
                                    <?php 
                                    $platform_conversion = $platform['lead_count'] > 0 ? 
                                        round(($platform['conversions'] / $platform['lead_count']) * 100, 1) : 0;
                                    ?>
                                    <div class="flex items-center justify-between p-3 border border-gray-200 rounded-lg hover:bg-gray-50">
                                        <div class="flex items-center">
                                            <div class="flex-shrink-0 w-3 h-3 bg-primary-600 rounded-full"></div>
                                            <div class="ml-3">
                                                <p class="text-sm font-medium text-gray-900"><?php echo $platform['platform']; ?></p>
                                                <p class="text-xs text-gray-500">
                                                    <?php echo $platform['lead_count']; ?> leads • <?php echo $platform_conversion; ?>% conversion
                                                </p>
                                            </div>
                                        </div>
                                        <div class="text-right">
                                            <p class="text-sm font-medium text-gray-900">
                                                <?php echo format_currency($platform['revenue']); ?>
                                            </p>
                                            <p class="text-xs text-gray-500"><?php echo $platform['conversions']; ?> deals</p>
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                            </div>
                        <?php else: ?>
                            <div class="flex items-center justify-center h-32 text-gray-500">
                                <div class="text-center">
                                    <svg class="mx-auto h-8 w-8 text-gray-400 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                                    </svg>
                                    <p>No platform data available yet</p>
                                    <a href="campaigns.php" class="text-primary-600 hover:text-primary-500 text-sm">Create a campaign to get started</a>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="px-4 py-5 sm:p-6">
                        <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">Quick Actions</h3>
                        
                        <div class="space-y-3">
                            <a href="new-campaign.php" class="flex items-center p-3 border-2 border-dashed border-primary-300 rounded-lg hover:border-primary-400 hover:bg-primary-50 transition-colors">
                                <div class="flex-shrink-0">
                                    <div class="w-8 h-8 bg-primary-100 rounded-md flex items-center justify-center">
                                        <svg class="w-5 h-5 text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                                        </svg>
                                    </div>
                                </div>
                                <div class="ml-3">
                                    <p class="text-sm font-medium text-gray-900">Create New Campaign</p>
                                    <p class="text-xs text-gray-500">Set up a new marketing campaign</p>
                                </div>
                            </a>
                            
                            <a href="assign-leads.php" class="flex items-center p-3 border border-gray-200 rounded-lg hover:bg-gray-50">
                                <div class="flex-shrink-0">
                                    <div class="w-8 h-8 bg-blue-100 rounded-md flex items-center justify-center">
                                        <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                                        </svg>
                                    </div>
                                </div>
                                <div class="ml-3">
                                    <p class="text-sm font-medium text-gray-900">Assign Leads</p>
                                    <p class="text-xs text-gray-500">Distribute leads to sales team</p>
                                </div>
                                <?php if ($lead_stats['new_leads'] > 0): ?>
                                    <div class="ml-auto">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                            <?php echo $lead_stats['new_leads']; ?> pending
                                        </span>
                                    </div>
                                <?php endif; ?>
                            </a>
                            
                            <a href="campaigns.php" class="flex items-center p-3 border border-gray-200 rounded-lg hover:bg-gray-50">
                                <div class="flex-shrink-0">
                                    <div class="w-8 h-8 bg-green-100 rounded-md flex items-center justify-center">
                                        <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                                        </svg>
                                    </div>
                                </div>
                                <div class="ml-3">
                                    <p class="text-sm font-medium text-gray-900">View Analytics</p>
                                    <p class="text-xs text-gray-500">Campaign performance insights</p>
                                </div>
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Campaigns -->
            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="px-4 py-5 sm:p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg leading-6 font-medium text-gray-900">Recent Campaigns</h3>
                        <a href="campaigns.php" class="text-sm font-medium text-primary-600 hover:text-primary-500">View All</a>
                    </div>
                    
                    <?php if ($recent_campaigns && $recent_campaigns->num_rows > 0): ?>
                        <div class="space-y-4">
                            <?php while ($campaign = $recent_campaigns->fetch_assoc()): ?>
                                <?php 
                                $roi = $campaign['budget_qr'] > 0 ? 
                                    round((($campaign['revenue'] - $campaign['budget_qr']) / $campaign['budget_qr']) * 100, 1) : 0;
                                $days_remaining = (strtotime($campaign['end_date']) - time()) / (60 * 60 * 24);
                                ?>
                                <div class="border border-gray-200 rounded-lg p-4 hover:shadow-md transition-shadow">
                                    <div class="flex items-center justify-between mb-3">
                                        <div class="flex items-center">
                                            <h4 class="text-sm font-medium text-gray-900">
                                                <?php echo htmlspecialchars($campaign['title']); ?>
                                            </h4>
                                            <div class="ml-2">
                                                <?php echo get_status_badge($campaign['status']); ?>
                                            </div>
                                        </div>
                                        <div class="flex items-center space-x-2">
                                            <a href="edit-campaign.php?id=<?php echo $campaign['id']; ?>" class="text-primary-600 hover:text-primary-900 text-xs">
                                                Edit
                                            </a>
                                            <span class="text-gray-300">|</span>
                                            <a href="campaigns.php?search=<?php echo urlencode($campaign['title']); ?>" class="text-gray-600 hover:text-gray-900 text-xs">
                                                View Details
                                            </a>
                                        </div>
                                    </div>
                                    
                                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                                        <div>
                                            <p class="text-gray-500">Budget</p>
                                            <p class="font-medium"><?php echo format_currency($campaign['budget_qr']); ?></p>
                                        </div>
                                        <div>
                                            <p class="text-gray-500">Leads</p>
                                            <p class="font-medium"><?php echo $campaign['leads_count']; ?></p>
                                        </div>
                                        <div>
                                            <p class="text-gray-500">Deals</p>
                                            <p class="font-medium"><?php echo $campaign['deals_count']; ?></p>
                                        </div>
                                        <div>
                                            <p class="text-gray-500">Revenue</p>
                                            <p class="font-medium"><?php echo format_currency($campaign['revenue']); ?></p>
                                        </div>
                                    </div>
                                    
                                    <div class="mt-3 flex items-center justify-between text-xs text-gray-500">
                                        <div>
                                            <?php echo format_date($campaign['start_date'], 'M j'); ?> - <?php echo format_date($campaign['end_date'], 'M j, Y'); ?>
                                        </div>
                                        <div class="flex items-center space-x-4">
                                            <span>ROI: <span class="<?php echo $roi >= 0 ? 'text-green-600' : 'text-red-600'; ?> font-medium"><?php echo $roi; ?>%</span></span>
                                            <span>
                                                <?php if ($days_remaining > 0): ?>
                                                    <?php echo ceil($days_remaining); ?> days left
                                                <?php else: ?>
                                                    Ended <?php echo abs(floor($days_remaining)); ?> days ago
                                                <?php endif; ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-8">
                            <svg class="mx-auto h-12 w-12 text-gray-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
                            </svg>
                            <p class="text-gray-500 text-lg font-medium mb-2">No campaigns yet</p>
                            <p class="text-gray-400 mb-4">Create your first campaign to start generating leads</p>
                            <a href="new-campaign.php" class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-primary-600 hover:bg-primary-700">
                                <svg class="-ml-1 mr-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                                </svg>
                                Create Campaign
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>
</div>

<script>
// Dashboard functionality
document.addEventListener('DOMContentLoaded', function() {
    // User menu toggle
    const userMenuButton = document.getElementById('user-menu-button');
    const userMenu = document.getElementById('user-menu');
    
    if (userMenuButton && userMenu) {
        userMenuButton.addEventListener('click', function(e) {
            e.stopPropagation();
            userMenu.classList.toggle('hidden');
        });
        
        // Close user menu when clicking outside
        document.addEventListener('click', function(e) {
            if (!userMenuButton.contains(e.target) && !userMenu.contains(e.target)) {
                userMenu.classList.add('hidden');
            }
        });
    }
    
    // Mobile menu toggle
    const mobileMenuButton = document.getElementById('mobile-menu-button');
    const mobileMenu = document.getElementById('mobile-menu');
    
    if (mobileMenuButton && mobileMenu) {
        mobileMenuButton.addEventListener('click', function() {
            mobileMenu.classList.toggle('hidden');
        });
    }
    
    // Auto-refresh dashboard every 5 minutes
    setTimeout(function() {
        if (confirm('Dashboard data is 5 minutes old. Refresh now?')) {
            location.reload();
        }
    }, 300000);
});

// Add click tracking for quick actions
document.querySelectorAll('[href^="new-campaign"], [href^="assign-leads"], [href^="campaigns"]').forEach(link => {
    link.addEventListener('click', function() {
        console.log('Marketing action clicked:', this.href);
    });
});
</script>

<?php include '../templates/footer.php'; ?>
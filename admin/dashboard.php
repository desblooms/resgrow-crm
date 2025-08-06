<?php
// Resgrow CRM - Admin Dashboard
// Phase 3: Admin Dashboard

require_once '../includes/session.php';
require_once '../includes/functions.php';

// Require admin access
SessionManager::requireRole('admin');

$page_title = 'Admin Dashboard';

// Get dashboard statistics
try {
    global $db;
    
    // Get overall stats
    $stats_query = "
        SELECT 
            (SELECT COUNT(*) FROM users WHERE status = 'active') as active_users,
            (SELECT COUNT(*) FROM campaigns) as total_campaigns,
            (SELECT COUNT(*) FROM leads) as total_leads,
            (SELECT COUNT(*) FROM leads WHERE status = 'new') as new_leads,
            (SELECT COUNT(*) FROM leads WHERE status = 'closed-won') as closed_deals,
            (SELECT COALESCE(SUM(sale_value_qr), 0) FROM leads WHERE status = 'closed-won') as total_revenue,
            (SELECT COUNT(*) FROM leads WHERE DATE(created_at) = CURDATE()) as today_leads
    ";
    
    $stats_result = $db->query($stats_query);
    $stats = $stats_result->fetch_assoc();
    
    // Get recent activity
    $recent_activity = $db->query("
        SELECT al.*, u.name as user_name 
        FROM activity_log al 
        LEFT JOIN users u ON al.user_id = u.id 
        ORDER BY al.created_at DESC 
        LIMIT 10
    ");
    
    // Get top performing sales agents
    $top_sales = $db->query("
        SELECT 
            u.name,
            COUNT(l.id) as total_leads,
            COUNT(CASE WHEN l.status = 'closed-won' THEN 1 END) as closed_deals,
            COALESCE(SUM(CASE WHEN l.status = 'closed-won' THEN l.sale_value_qr END), 0) as total_revenue,
            ROUND(
                (COUNT(CASE WHEN l.status = 'closed-won' THEN 1 END) * 100.0 / NULLIF(COUNT(l.id), 0)), 2
            ) as conversion_rate
        FROM users u
        LEFT JOIN leads l ON u.id = l.assigned_to
        WHERE u.role = 'sales' AND u.status = 'active'
        GROUP BY u.id, u.name
        ORDER BY total_revenue DESC
        LIMIT 5
    ");
    
    // Get campaign performance
    $campaign_performance = $db->query("
        SELECT 
            c.title,
            c.budget_qr,
            COUNT(l.id) as leads_generated,
            COUNT(CASE WHEN l.status = 'closed-won' THEN 1 END) as deals_closed,
            COALESCE(SUM(CASE WHEN l.status = 'closed-won' THEN l.sale_value_qr END), 0) as revenue
        FROM campaigns c
        LEFT JOIN leads l ON c.id = l.campaign_id
        WHERE c.status = 'active'
        GROUP BY c.id, c.title, c.budget_qr
        ORDER BY revenue DESC
        LIMIT 5
    ");
    
    // Get platform performance
    $platform_stats = $db->query("
        SELECT 
            platform,
            COUNT(*) as lead_count,
            COUNT(CASE WHEN status = 'closed-won' THEN 1 END) as conversions,
            COALESCE(SUM(CASE WHEN status = 'closed-won' THEN sale_value_qr END), 0) as revenue
        FROM leads 
        GROUP BY platform
        ORDER BY revenue DESC
    ");
    
    // Calculate conversion rate
    $conversion_rate = $stats['total_leads'] > 0 ? 
        round(($stats['closed_deals'] / $stats['total_leads']) * 100, 2) : 0;
    
} catch (Exception $e) {
    log_error("Dashboard query error: " . $e->getMessage());
    set_flash_message('error', 'Error loading dashboard data');
    $stats = [
        'active_users' => 0, 'total_campaigns' => 0, 'total_leads' => 0,
        'new_leads' => 0, 'closed_deals' => 0, 'total_revenue' => 0, 'today_leads' => 0
    ];
    $conversion_rate = 0;
}

include '../templates/header.php';
?>

<div class="min-h-screen bg-gray-50">
    <!-- Navigation -->
    <?php include '../templates/nav-admin.php'; ?>

    <!-- Main Content -->
    <main class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
        <div class="px-4 py-6 sm:px-0">
            
            <!-- Header -->
            <div class="md:flex md:items-center md:justify-between mb-8">
                <div class="flex-1 min-w-0">
                    <h2 class="text-2xl font-bold leading-7 text-gray-900 sm:text-3xl sm:truncate">
                        Admin Dashboard
                    </h2>
                    <p class="mt-1 text-sm text-gray-500">
                        Welcome back, <?php echo htmlspecialchars(SessionManager::getUsername()); ?>
                    </p>
                </div>
                <div class="mt-4 flex md:mt-0 md:ml-4">
                    <button type="button" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500">
                        <svg class="-ml-1 mr-2 h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                        Export Report
                    </button>
                    <button type="button" class="ml-3 inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-primary-600 hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500">
                        <svg class="-ml-1 mr-2 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                        </svg>
                        Quick Actions
                    </button>
                </div>
            </div>

            <!-- Stats Overview -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                
                <!-- Total Revenue -->
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
                                    <dt class="text-sm font-medium text-gray-500 truncate">Total Revenue</dt>
                                    <dd class="text-lg font-medium text-gray-900">
                                        <?php echo format_currency($stats['total_revenue']); ?>
                                    </dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                    <div class="bg-gray-50 px-5 py-3">
                        <div class="text-sm">
                            <span class="text-green-600 font-medium">+12.5%</span>
                            <span class="text-gray-500">from last month</span>
                        </div>
                    </div>
                </div>

                <!-- Total Leads -->
                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="p-5">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <div class="w-8 h-8 bg-blue-100 rounded-md flex items-center justify-center">
                                    <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                                    </svg>
                                </div>
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 truncate">Total Leads</dt>
                                    <dd class="text-lg font-medium text-gray-900">
                                        <?php echo number_format($stats['total_leads']); ?>
                                    </dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                    <div class="bg-gray-50 px-5 py-3">
                        <div class="text-sm">
                            <span class="text-blue-600 font-medium"><?php echo $stats['today_leads']; ?></span>
                            <span class="text-gray-500">new today</span>
                        </div>
                    </div>
                </div>

                <!-- Conversion Rate -->
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
                            <span class="text-yellow-600 font-medium"><?php echo $stats['closed_deals']; ?></span>
                            <span class="text-gray-500">deals closed</span>
                        </div>
                    </div>
                </div>

                <!-- Active Users -->
                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="p-5">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <div class="w-8 h-8 bg-purple-100 rounded-md flex items-center justify-center">
                                    <svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z"></path>
                                    </svg>
                                </div>
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 truncate">Active Users</dt>
                                    <dd class="text-lg font-medium text-gray-900">
                                        <?php echo $stats['active_users']; ?>
                                    </dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                    <div class="bg-gray-50 px-5 py-3">
                        <div class="text-sm">
                            <span class="text-purple-600 font-medium"><?php echo $stats['total_campaigns']; ?></span>
                            <span class="text-gray-500">active campaigns</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Charts and Performance Section -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
                
                <!-- Platform Performance -->
                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="px-4 py-5 sm:p-6">
                        <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">Platform Performance</h3>
                        
                        <?php if ($platform_stats && $platform_stats->num_rows > 0): ?>
                            <div class="space-y-4">
                                <?php while ($platform = $platform_stats->fetch_assoc()): ?>
                                    <?php 
                                    $platform_conversion = $platform['lead_count'] > 0 ? 
                                        round(($platform['conversions'] / $platform['lead_count']) * 100, 1) : 0;
                                    ?>
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center">
                                            <div class="flex-shrink-0 w-2 h-2 bg-primary-600 rounded-full"></div>
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
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                            </div>
                        <?php else: ?>
                            <p class="text-gray-500 text-center py-4">No platform data available yet.</p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Top Sales Performers -->
                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="px-4 py-5 sm:p-6">
                        <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">Top Sales Performers</h3>
                        
                        <?php if ($top_sales && $top_sales->num_rows > 0): ?>
                            <div class="space-y-4">
                                <?php $rank = 1; while ($agent = $top_sales->fetch_assoc()): ?>
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center">
                                            <div class="flex-shrink-0 w-8 h-8 bg-gray-100 rounded-full flex items-center justify-center">
                                                <span class="text-sm font-medium text-gray-600"><?php echo $rank++; ?></span>
                                            </div>
                                            <div class="ml-3">
                                                <p class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($agent['name']); ?></p>
                                                <p class="text-xs text-gray-500">
                                                    <?php echo $agent['closed_deals']; ?> deals • <?php echo $agent['conversion_rate']; ?>% rate
                                                </p>
                                            </div>
                                        </div>
                                        <div class="text-right">
                                            <p class="text-sm font-medium text-gray-900">
                                                <?php echo format_currency($agent['total_revenue']); ?>
                                            </p>
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                            </div>
                        <?php else: ?>
                            <p class="text-gray-500 text-center py-4">No sales data available yet.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Recent Activity and Campaign Performance -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                
                <!-- Recent Activity -->
                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="px-4 py-5 sm:p-6">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-lg leading-6 font-medium text-gray-900">Recent Activity</h3>
                            <a href="activity-log.php" class="text-sm font-medium text-primary-600 hover:text-primary-500">View all</a>
                        </div>
                        
                        <?php if ($recent_activity && $recent_activity->num_rows > 0): ?>
                            <div class="flow-root">
                                <ul class="-mb-8">
                                    <?php $activity_count = 0; while ($activity = $recent_activity->fetch_assoc()): ?>
                                        <?php if ($activity_count >= 5) break; $activity_count++; ?>
                                        <li>
                                            <div class="relative pb-8">
                                                <?php if ($activity_count < 5): ?>
                                                    <span class="absolute top-4 left-4 -ml-px h-full w-0.5 bg-gray-200"></span>
                                                <?php endif; ?>
                                                <div class="relative flex space-x-3">
                                                    <div class="h-8 w-8 bg-primary-500 rounded-full flex items-center justify-center">
                                                        <svg class="h-4 w-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                                        </svg>
                                                    </div>
                                                    <div class="min-w-0 flex-1 pt-1.5 flex justify-between space-x-4">
                                                        <div>
                                                            <p class="text-sm text-gray-500">
                                                                <span class="font-medium text-gray-900">
                                                                    <?php echo htmlspecialchars($activity['user_name'] ?? 'System'); ?>
                                                                </span>
                                                                <?php echo htmlspecialchars($activity['description']); ?>
                                                            </p>
                                                        </div>
                                                        <div class="text-right text-sm whitespace-nowrap text-gray-500">
                                                            <?php echo time_ago($activity['created_at']); ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </li>
                                    <?php endwhile; ?>
                                </ul>
                            </div>
                        <?php else: ?>
                            <p class="text-gray-500 text-center py-4">No recent activity to display.</p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Campaign Performance -->
                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="px-4 py-5 sm:p-6">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-lg leading-6 font-medium text-gray-900">Active Campaigns</h3>
                            <a href="../marketing/campaigns.php" class="text-sm font-medium text-primary-600 hover:text-primary-500">View all</a>
                        </div>
                        
                        <?php if ($campaign_performance && $campaign_performance->num_rows > 0): ?>
                            <div class="space-y-4">
                                <?php while ($campaign = $campaign_performance->fetch_assoc()): ?>
                                    <?php 
                                    $roi = $campaign['budget_qr'] > 0 ? 
                                        round((($campaign['revenue'] - $campaign['budget_qr']) / $campaign['budget_qr']) * 100, 1) : 0;
                                    ?>
                                    <div class="border border-gray-200 rounded-lg p-4">
                                        <div class="flex items-center justify-between mb-2">
                                            <h4 class="text-sm font-medium text-gray-900">
                                                <?php echo htmlspecialchars($campaign['title']); ?>
                                            </h4>
                                            <span class="text-xs px-2 py-1 bg-green-100 text-green-800 rounded-full">
                                                Active
                                            </span>
                                        </div>
                                        <div class="grid grid-cols-3 gap-4 text-sm">
                                            <div>
                                                <p class="text-gray-500">Leads</p>
                                                <p class="font-medium"><?php echo $campaign['leads_generated']; ?></p>
                                            </div>
                                            <div>
                                                <p class="text-gray-500">Deals</p>
                                                <p class="font-medium"><?php echo $campaign['deals_closed']; ?></p>
                                            </div>
                                            <div>
                                                <p class="text-gray-500">Revenue</p>
                                                <p class="font-medium"><?php echo format_currency($campaign['revenue']); ?></p>
                                            </div>
                                        </div>
                                        <div class="mt-2 text-xs text-gray-500">
                                            Budget: <?php echo format_currency($campaign['budget_qr']); ?> • 
                                            ROI: <span class="<?php echo $roi >= 0 ? 'text-green-600' : 'text-red-600'; ?> font-medium">
                                                <?php echo $roi; ?>%
                                            </span>
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                            </div>
                        <?php else: ?>
                            <p class="text-gray-500 text-center py-4">No active campaigns found.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<script>
// Dashboard functionality
document.addEventListener('DOMContentLoaded', function() {
    // Auto-refresh dashboard every 5 minutes
    setInterval(function() {
        location.reload();
    }, 300000);
    
    // Add click handlers for quick actions
    const quickActionsBtn = document.querySelector('button[aria-label="Quick Actions"]');
    if (quickActionsBtn) {
        quickActionsBtn.addEventListener('click', function() {
            // Show quick actions dropdown/modal
            console.log('Quick actions clicked');
        });
    }
});
</script>

<?php include '../templates/footer.php'; ?>
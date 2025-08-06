<?php
// Resgrow CRM - Analytics Dashboard
// Phase 3: Admin Dashboard

require_once '../includes/session.php';
require_once '../includes/functions.php';

// Require admin access
SessionManager::requireRole('admin');

$page_title = 'Analytics Dashboard';

// Get date range from query parameters
$date_from = $_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
$date_to = $_GET['date_to'] ?? date('Y-m-d');
$period = $_GET['period'] ?? '30days';

try {
    global $db;
    
    // Get comprehensive analytics data
    $analytics_query = "
        SELECT 
            DATE(l.created_at) as date,
            COUNT(*) as leads_count,
            COUNT(CASE WHEN l.status = 'closed-won' THEN 1 END) as conversions,
            COUNT(CASE WHEN l.status = 'closed-lost' THEN 1 END) as lost_leads,
            COALESCE(SUM(CASE WHEN l.status = 'closed-won' THEN l.sale_value_qr END), 0) as revenue,
            l.platform,
            c.title as campaign_title
        FROM leads l
        LEFT JOIN campaigns c ON l.campaign_id = c.id
        WHERE DATE(l.created_at) BETWEEN ? AND ?
        GROUP BY DATE(l.created_at), l.platform, c.title
        ORDER BY l.created_at DESC
    ";
    
    $stmt = $db->prepare($analytics_query);
    $stmt->bind_param("ss", $date_from, $date_to);
    $stmt->execute();
    $analytics_result = $stmt->get_result();
    
    // Process data for charts
    $daily_stats = [];
    $platform_stats = [];
    $campaign_stats = [];
    
    while ($row = $analytics_result->fetch_assoc()) {
        $date = $row['date'];
        $platform = $row['platform'];
        $campaign = $row['campaign_title'] ?? 'Direct';
        
        // Daily stats
        if (!isset($daily_stats[$date])) {
            $daily_stats[$date] = [
                'date' => $date,
                'leads' => 0,
                'conversions' => 0,
                'revenue' => 0
            ];
        }
        $daily_stats[$date]['leads'] += $row['leads_count'];
        $daily_stats[$date]['conversions'] += $row['conversions'];
        $daily_stats[$date]['revenue'] += $row['revenue'];
        
        // Platform stats
        if (!isset($platform_stats[$platform])) {
            $platform_stats[$platform] = [
                'platform' => $platform,
                'leads' => 0,
                'conversions' => 0,
                'revenue' => 0
            ];
        }
        $platform_stats[$platform]['leads'] += $row['leads_count'];
        $platform_stats[$platform]['conversions'] += $row['conversions'];
        $platform_stats[$platform]['revenue'] += $row['revenue'];
        
        // Campaign stats
        if (!isset($campaign_stats[$campaign])) {
            $campaign_stats[$campaign] = [
                'campaign' => $campaign,
                'leads' => 0,
                'conversions' => 0,
                'revenue' => 0
            ];
        }
        $campaign_stats[$campaign]['leads'] += $row['leads_count'];
        $campaign_stats[$campaign]['conversions'] += $row['conversions'];
        $campaign_stats[$campaign]['revenue'] += $row['revenue'];
    }
    
    // Get summary statistics
    $summary_query = "
        SELECT 
            COUNT(*) as total_leads,
            COUNT(CASE WHEN status = 'closed-won' THEN 1 END) as total_conversions,
            COALESCE(SUM(CASE WHEN status = 'closed-won' THEN sale_value_qr END), 0) as total_revenue,
            COALESCE(AVG(CASE WHEN status = 'closed-won' THEN sale_value_qr END), 0) as avg_deal_value
        FROM leads 
        WHERE DATE(created_at) BETWEEN ? AND ?
    ";
    
    $stmt = $db->prepare($summary_query);
    $stmt->bind_param("ss", $date_from, $date_to);
    $stmt->execute();
    $summary = $stmt->get_result()->fetch_assoc();
    
    $conversion_rate = $summary['total_leads'] > 0 ? 
        round(($summary['total_conversions'] / $summary['total_leads']) * 100, 2) : 0;
    
    // Get top performing sales agents
    $sales_performance_query = "
        SELECT 
            u.name,
            COUNT(l.id) as total_leads,
            COUNT(CASE WHEN l.status = 'closed-won' THEN 1 END) as conversions,
            COALESCE(SUM(CASE WHEN l.status = 'closed-won' THEN l.sale_value_qr END), 0) as revenue
        FROM users u
        LEFT JOIN leads l ON u.id = l.assigned_to AND DATE(l.created_at) BETWEEN ? AND ?
        WHERE u.role = 'sales' AND u.status = 'active'
        GROUP BY u.id, u.name
        ORDER BY revenue DESC
        LIMIT 10
    ";
    
    $stmt = $db->prepare($sales_performance_query);
    $stmt->bind_param("ss", $date_from, $date_to);
    $stmt->execute();
    $sales_performance = $stmt->get_result();
    
} catch (Exception $e) {
    log_error("Analytics query error: " . $e->getMessage());
    set_flash_message('error', 'Error loading analytics data');
    $daily_stats = [];
    $platform_stats = [];
    $campaign_stats = [];
    $summary = ['total_leads' => 0, 'total_conversions' => 0, 'total_revenue' => 0, 'avg_deal_value' => 0];
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
                        Analytics Dashboard
                    </h2>
                    <p class="mt-1 text-sm text-gray-500">
                        Performance insights and detailed reporting
                    </p>
                </div>
            </div>

            <!-- Date Range Filter -->
            <div class="bg-white shadow rounded-lg mb-6">
                <div class="px-4 py-5 sm:p-6">
                    <form method="GET" class="flex flex-wrap items-end gap-4">
                        <div>
                            <label for="date_from" class="block text-sm font-medium text-gray-700">From Date</label>
                            <input type="date" name="date_from" id="date_from" value="<?php echo $date_from; ?>" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-primary-500 focus:border-primary-500 sm:text-sm">
                        </div>
                        
                        <div>
                            <label for="date_to" class="block text-sm font-medium text-gray-700">To Date</label>
                            <input type="date" name="date_to" id="date_to" value="<?php echo $date_to; ?>" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-primary-500 focus:border-primary-500 sm:text-sm">
                        </div>
                        
                        <div>
                            <label for="period" class="block text-sm font-medium text-gray-700">Quick Select</label>
                            <select name="period" id="period" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-primary-500 focus:border-primary-500 sm:text-sm">
                                <option value="7days" <?php echo $period === '7days' ? 'selected' : ''; ?>>Last 7 Days</option>
                                <option value="30days" <?php echo $period === '30days' ? 'selected' : ''; ?>>Last 30 Days</option>
                                <option value="90days" <?php echo $period === '90days' ? 'selected' : ''; ?>>Last 90 Days</option>
                                <option value="custom">Custom Range</option>
                            </select>
                        </div>
                        
                        <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-primary-600 hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500">
                            Apply Filter
                        </button>
                    </form>
                </div>
            </div>

            <!-- Summary Stats -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
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
                                        <?php echo number_format($summary['total_leads']); ?>
                                    </dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="p-5">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <div class="w-8 h-8 bg-green-100 rounded-md flex items-center justify-center">
                                    <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"></path>
                                    </svg>
                                </div>
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 truncate">Conversions</dt>
                                    <dd class="text-lg font-medium text-gray-900">
                                        <?php echo number_format($summary['total_conversions']); ?>
                                    </dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                </div>

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
                </div>

                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="p-5">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <div class="w-8 h-8 bg-purple-100 rounded-md flex items-center justify-center">
                                    <svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"></path>
                                    </svg>
                                </div>
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 truncate">Total Revenue</dt>
                                    <dd class="text-lg font-medium text-gray-900">
                                        <?php echo format_currency($summary['total_revenue']); ?>
                                    </dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Charts Section -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
                
                <!-- Daily Performance Chart -->
                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="px-4 py-5 sm:p-6">
                        <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">Daily Performance</h3>
                        <div id="daily-chart" class="h-64">
                            <?php if (!empty($daily_stats)): ?>
                                <!-- Simple bar chart representation -->
                                <div class="space-y-2">
                                    <?php 
                                    $max_leads = max(array_column($daily_stats, 'leads'));
                                    foreach (array_slice(array_reverse($daily_stats), 0, 7) as $day): 
                                        $width = $max_leads > 0 ? ($day['leads'] / $max_leads) * 100 : 0;
                                    ?>
                                        <div class="flex items-center text-sm">
                                            <div class="w-20 text-gray-500"><?php echo date('M d', strtotime($day['date'])); ?></div>
                                            <div class="flex-1 ml-4">
                                                <div class="bg-gray-200 rounded-full h-4">
                                                    <div class="bg-primary-600 h-4 rounded-full" style="width: <?php echo $width; ?>%"></div>
                                                </div>
                                            </div>
                                            <div class="w-16 text-right text-gray-700"><?php echo $day['leads']; ?> leads</div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="flex items-center justify-center h-full text-gray-500">
                                    No data available for selected period
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Platform Performance -->
                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="px-4 py-5 sm:p-6">
                        <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">Platform Performance</h3>
                        <?php if (!empty($platform_stats)): ?>
                            <div class="space-y-4">
                                <?php 
                                arsort($platform_stats);
                                foreach (array_slice($platform_stats, 0, 5) as $platform): 
                                    $conversion_rate = $platform['leads'] > 0 ? 
                                        round(($platform['conversions'] / $platform['leads']) * 100, 1) : 0;
                                ?>
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center">
                                            <div class="flex-shrink-0 w-3 h-3 bg-primary-600 rounded-full"></div>
                                            <div class="ml-3">
                                                <p class="text-sm font-medium text-gray-900"><?php echo $platform['platform']; ?></p>
                                                <p class="text-xs text-gray-500">
                                                    <?php echo $platform['leads']; ?> leads • <?php echo $conversion_rate; ?>% conversion
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
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="flex items-center justify-center h-32 text-gray-500">
                                No platform data available
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Detailed Tables Section -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                
                <!-- Sales Performance Table -->
                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="px-4 py-5 sm:p-6">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-lg leading-6 font-medium text-gray-900">Sales Team Performance</h3>
                            <a href="../sales/dashboard.php" class="text-sm font-medium text-primary-600 hover:text-primary-500">View Details</a>
                        </div>
                        
                        <?php if ($sales_performance && $sales_performance->num_rows > 0): ?>
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Agent</th>
                                            <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Leads</th>
                                            <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Deals</th>
                                            <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Revenue</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        <?php while ($agent = $sales_performance->fetch_assoc()): ?>
                                            <?php 
                                            $agent_conversion = $agent['total_leads'] > 0 ? 
                                                round(($agent['conversions'] / $agent['total_leads']) * 100, 1) : 0;
                                            ?>
                                            <tr>
                                                <td class="px-3 py-4 whitespace-nowrap">
                                                    <div class="flex items-center">
                                                        <div class="flex-shrink-0 h-8 w-8">
                                                            <div class="h-8 w-8 bg-primary-100 rounded-full flex items-center justify-center">
                                                                <span class="text-primary-600 text-xs font-medium">
                                                                    <?php echo strtoupper(substr($agent['name'], 0, 2)); ?>
                                                                </span>
                                                            </div>
                                                        </div>
                                                        <div class="ml-3">
                                                            <div class="text-sm font-medium text-gray-900">
                                                                <?php echo htmlspecialchars($agent['name']); ?>
                                                            </div>
                                                            <div class="text-xs text-gray-500">
                                                                <?php echo $agent_conversion; ?>% conversion
                                                            </div>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class="px-3 py-4 whitespace-nowrap text-sm text-gray-900">
                                                    <?php echo $agent['total_leads']; ?>
                                                </td>
                                                <td class="px-3 py-4 whitespace-nowrap text-sm text-gray-900">
                                                    <?php echo $agent['conversions']; ?>
                                                </td>
                                                <td class="px-3 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                                    <?php echo format_currency($agent['revenue']); ?>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-8 text-gray-500">
                                No sales performance data available for selected period
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Campaign Performance Table -->
                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="px-4 py-5 sm:p-6">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-lg leading-6 font-medium text-gray-900">Campaign Performance</h3>
                            <a href="../marketing/campaigns.php" class="text-sm font-medium text-primary-600 hover:text-primary-500">View All</a>
                        </div>
                        
                        <?php if (!empty($campaign_stats)): ?>
                            <div class="space-y-4">
                                <?php 
                                // Sort campaigns by revenue
                                uasort($campaign_stats, function($a, $b) {
                                    return $b['revenue'] - $a['revenue'];
                                });
                                
                                foreach (array_slice($campaign_stats, 0, 8) as $campaign): 
                                    $campaign_conversion = $campaign['leads'] > 0 ? 
                                        round(($campaign['conversions'] / $campaign['leads']) * 100, 1) : 0;
                                ?>
                                    <div class="flex items-center justify-between p-3 border border-gray-200 rounded-lg">
                                        <div class="flex-1">
                                            <h4 class="text-sm font-medium text-gray-900">
                                                <?php echo htmlspecialchars($campaign['campaign']); ?>
                                            </h4>
                                            <div class="mt-1 flex items-center text-xs text-gray-500">
                                                <span><?php echo $campaign['leads']; ?> leads</span>
                                                <span class="mx-2">•</span>
                                                <span><?php echo $campaign_conversion; ?>% conversion</span>
                                            </div>
                                        </div>
                                        <div class="text-right">
                                            <div class="text-sm font-medium text-gray-900">
                                                <?php echo format_currency($campaign['revenue']); ?>
                                            </div>
                                            <div class="text-xs text-gray-500">
                                                <?php echo $campaign['conversions']; ?> deals
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-8 text-gray-500">
                                No campaign performance data available
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Export Section -->
            <div class="mt-8 bg-white shadow rounded-lg">
                <div class="px-4 py-5 sm:p-6">
                    <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">Export Analytics Data</h3>
                    <div class="flex flex-wrap gap-4">
                        <a href="export.php?type=analytics&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>&format=csv" 
                           class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500">
                            <svg class="-ml-1 mr-2 h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                            </svg>
                            Export as CSV
                        </a>
                        
                        <a href="export.php?type=analytics&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>&format=pdf" 
                           class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500">
                            <svg class="-ml-1 mr-2 h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                            </svg>
                            Export as PDF
                        </a>
                        
                        <button type="button" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-primary-600 hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500" onclick="window.print()">
                            <svg class="-ml-1 mr-2 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path>
                            </svg>
                            Print Report
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<script>
// Analytics page functionality
document.addEventListener('DOMContentLoaded', function() {
    // Handle period quick select
    const periodSelect = document.getElementById('period');
    const dateFromInput = document.getElementById('date_from');
    const dateToInput = document.getElementById('date_to');
    
    periodSelect.addEventListener('change', function() {
        const period = this.value;
        const today = new Date();
        const toDate = today.toISOString().split('T')[0];
        
        let fromDate;
        switch (period) {
            case '7days':
                fromDate = new Date(today.setDate(today.getDate() - 7)).toISOString().split('T')[0];
                break;
            case '30days':
                fromDate = new Date(today.setDate(today.getDate() - 30)).toISOString().split('T')[0];
                break;
            case '90days':
                fromDate = new Date(today.setDate(today.getDate() - 90)).toISOString().split('T')[0];
                break;
            case 'custom':
                return; // Don't auto-set dates for custom
        }
        
        if (fromDate) {
            dateFromInput.value = fromDate;
            dateToInput.value = toDate;
        }
    });
    
    // Auto-submit form when dates change (with debounce)
    let submitTimeout;
    function scheduleSubmit() {
        clearTimeout(submitTimeout);
        submitTimeout = setTimeout(() => {
            document.querySelector('form').submit();
        }, 1000);
    }
    
    dateFromInput.addEventListener('change', scheduleSubmit);
    dateToInput.addEventListener('change', scheduleSubmit);
    
    // Add loading states for export buttons
    document.querySelectorAll('a[href*="export.php"]').forEach(button => {
        button.addEventListener('click', function() {
            const originalText = this.innerHTML;
            this.innerHTML = '<div class="loading inline-block mr-2"></div>Generating...';
            this.style.pointerEvents = 'none';
            
            // Reset after 5 seconds
            setTimeout(() => {
                this.innerHTML = originalText;
                this.style.pointerEvents = '';
            }, 5000);
        });
    });
});

// Print styles
const printStyles = `
    @media print {
        .no-print { display: none !important; }
        body { background: white !important; }
        .bg-gray-50 { background: white !important; }
        .shadow { box-shadow: none !important; }
        .border { border: 1px solid #ddd !important; }
    }
`;

// Add print styles to head
const styleSheet = document.createElement('style');
styleSheet.textContent = printStyles;
document.head.appendChild(styleSheet);
</script>

<?php include '../templates/footer.php'; ?>
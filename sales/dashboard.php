<?php
// Resgrow CRM - Sales Dashboard
// Main dashboard for sales team members

require_once '../config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Check authentication and sales role
check_login();
if ($_SESSION['user_role'] !== 'sales' && $_SESSION['user_role'] !== 'admin') {
    header('Location: ../public/dashboard.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'];

// Get dashboard statistics
$stats_sql = "SELECT 
                COUNT(*) as total_leads,
                COUNT(CASE WHEN status = 'new' THEN 1 END) as new_leads,
                COUNT(CASE WHEN status = 'contacted' THEN 1 END) as contacted_leads,
                COUNT(CASE WHEN status = 'interested' THEN 1 END) as interested_leads,
                COUNT(CASE WHEN status = 'follow-up' THEN 1 END) as follow_up_leads,
                COUNT(CASE WHEN status = 'closed-won' THEN 1 END) as won_leads,
                COUNT(CASE WHEN status = 'closed-lost' THEN 1 END) as lost_leads,
                SUM(CASE WHEN status = 'closed-won' THEN sale_value_qr ELSE 0 END) as total_revenue,
                AVG(CASE WHEN status = 'closed-won' THEN sale_value_qr END) as avg_deal_size
              FROM leads";

if ($user_role === 'sales') {
    $stats_sql .= " WHERE assigned_to = ?";
}

$stmt = $db->prepare($stats_sql);
if ($user_role === 'sales') {
    $stmt->bind_param("i", $user_id);
}
$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc();

// Calculate conversion rate
$conversion_rate = $stats['total_leads'] > 0 ? 
    round(($stats['won_leads'] / $stats['total_leads']) * 100, 2) : 0;

// Get recent leads
$recent_leads_sql = "SELECT l.*, c.title as campaign_title 
                     FROM leads l 
                     LEFT JOIN campaigns c ON l.campaign_id = c.id";

if ($user_role === 'sales') {
    $recent_leads_sql .= " WHERE l.assigned_to = ?";
}

$recent_leads_sql .= " ORDER BY l.created_at DESC LIMIT 10";

$stmt = $db->prepare($recent_leads_sql);
if ($user_role === 'sales') {
    $stmt->bind_param("i", $user_id);
}
$stmt->execute();
$recent_leads = $stmt->get_result();

// Get follow-up tasks
$follow_up_sql = "SELECT l.*, c.title as campaign_title 
                  FROM leads l 
                  LEFT JOIN campaigns c ON l.campaign_id = c.id 
                  WHERE l.next_follow_up <= DATE_ADD(NOW(), INTERVAL 7 DAY) 
                  AND l.next_follow_up IS NOT NULL 
                  AND l.status IN ('contacted', 'interested', 'follow-up')";

if ($user_role === 'sales') {
    $follow_up_sql .= " AND l.assigned_to = ?";
}

$follow_up_sql .= " ORDER BY l.next_follow_up ASC";

$stmt = $db->prepare($follow_up_sql);
if ($user_role === 'sales') {
    $stmt->bind_param("i", $user_id);
}
$stmt->execute();
$follow_ups = $stmt->get_result();

// Get activity summary for the week
$week_activity_sql = "SELECT 
                        DATE(created_at) as date,
                        COUNT(*) as leads_count
                      FROM leads 
                      WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";

if ($user_role === 'sales') {
    $week_activity_sql .= " AND assigned_to = ?";
}

$week_activity_sql .= " GROUP BY DATE(created_at) ORDER BY date ASC";

$stmt = $db->prepare($week_activity_sql);
if ($user_role === 'sales') {
    $stmt->bind_param("i", $user_id);
}
$stmt->execute();
$week_activity = $stmt->get_result();

// Get platforms breakdown
$platforms_sql = "SELECT platform, COUNT(*) as count 
                   FROM leads";

if ($user_role === 'sales') {
    $platforms_sql .= " WHERE assigned_to = ?";
}

$platforms_sql .= " GROUP BY platform ORDER BY count DESC";

$stmt = $db->prepare($platforms_sql);
if ($user_role === 'sales') {
    $stmt->bind_param("i", $user_id);
}
$stmt->execute();
$platforms = $stmt->get_result();

include_once '../templates/header.php';
?>

<div class="min-h-screen bg-gray-50">
    <?php include_once '../templates/nav-sales.php'; ?>
    
    <div class="py-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <!-- Header -->
            <div class="mb-8">
                <h1 class="text-3xl font-bold text-gray-900">Sales Dashboard</h1>
                <p class="mt-2 text-gray-600">
                    Welcome back, <?php echo htmlspecialchars($_SESSION['user_name']); ?>! 
                    Here's your sales overview.
                </p>
            </div>

            <!-- Statistics Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="p-5">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <div class="w-8 h-8 bg-blue-500 rounded-md flex items-center justify-center">
                                    <svg class="w-5 h-5 text-white" fill="currentColor" viewBox="0 0 20 20">
                                        <path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                </div>
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 truncate">Total Leads</dt>
                                    <dd class="text-lg font-medium text-gray-900"><?php echo number_format($stats['total_leads']); ?></dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="p-5">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <div class="w-8 h-8 bg-green-500 rounded-md flex items-center justify-center">
                                    <svg class="w-5 h-5 text-white" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"></path>
                                    </svg>
                                </div>
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 truncate">Won Deals</dt>
                                    <dd class="text-lg font-medium text-gray-900"><?php echo number_format($stats['won_leads']); ?></dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="p-5">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <div class="w-8 h-8 bg-yellow-500 rounded-md flex items-center justify-center">
                                    <svg class="w-5 h-5 text-white" fill="currentColor" viewBox="0 0 20 20">
                                        <path d="M8.433 7.418c.155-.103.346-.196.567-.267v1.698a2.305 2.305 0 01-.567-.267C8.07 8.34 8 8.114 8 8c0-.114.07-.34.433-.582zM11 12.849v-1.698c.22.071.412.164.567.267.364.243.433.468.433.582 0 .114-.07.34-.433.582a2.305 2.305 0 01-.567.267z"></path>
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-13a1 1 0 10-2 0v.092a4.535 4.535 0 00-1.676.662C6.602 6.234 6 7.009 6 8c0 .99.602 1.765 1.324 2.246.48.32 1.054.545 1.676.662v1.941c-.391-.127-.68-.317-.843-.504a1 1 0 10-1.51 1.31c.562.649 1.413 1.076 2.353 1.253V15a1 1 0 102 0v-.092a4.535 4.535 0 001.676-.662C13.398 13.766 14 12.991 14 12c0-.99-.602-1.765-1.324-2.246A4.535 4.535 0 0011 9.092V7.151c.391.127.68.317.843.504a1 1 0 101.511-1.31c-.563-.649-1.413-1.076-2.354-1.253V5z"></path>
                                    </svg>
                                </div>
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 truncate">Total Revenue</dt>
                                    <dd class="text-lg font-medium text-gray-900"><?php echo format_currency($stats['total_revenue']); ?></dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="p-5">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <div class="w-8 h-8 bg-purple-500 rounded-md flex items-center justify-center">
                                    <svg class="w-5 h-5 text-white" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M3 3a1 1 0 000 2v8a2 2 0 002 2h2.586l-1.293 1.293a1 1 0 101.414 1.414L10 15.414l2.293 2.293a1 1 0 001.414-1.414L12.414 15H15a2 2 0 002-2V5a1 1 0 100-2H3zm11.707 4.707a1 1 0 00-1.414-1.414L10 9.586 8.707 8.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"></path>
                                    </svg>
                                </div>
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 truncate">Conversion Rate</dt>
                                    <dd class="text-lg font-medium text-gray-900"><?php echo $conversion_rate; ?>%</dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Main Content Grid -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                <!-- Lead Pipeline -->
                <div class="lg:col-span-2">
                    <div class="bg-white shadow rounded-lg">
                        <div class="px-4 py-5 sm:p-6">
                            <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">Lead Pipeline</h3>
                            <div class="space-y-4">
                                <!-- New Leads -->
                                <div class="flex items-center justify-between p-4 bg-blue-50 rounded-lg">
                                    <div class="flex items-center">
                                        <div class="w-8 h-8 bg-blue-500 rounded-full flex items-center justify-center">
                                            <span class="text-white text-sm font-medium"><?php echo $stats['new_leads']; ?></span>
                                        </div>
                                        <span class="ml-3 text-sm font-medium text-gray-900">New Leads</span>
                                    </div>
                                    <a href="leads.php?status=new" class="text-blue-600 hover:text-blue-800 text-sm">View All</a>
                                </div>

                                <!-- Contacted -->
                                <div class="flex items-center justify-between p-4 bg-yellow-50 rounded-lg">
                                    <div class="flex items-center">
                                        <div class="w-8 h-8 bg-yellow-500 rounded-full flex items-center justify-center">
                                            <span class="text-white text-sm font-medium"><?php echo $stats['contacted_leads']; ?></span>
                                        </div>
                                        <span class="ml-3 text-sm font-medium text-gray-900">Contacted</span>
                                    </div>
                                    <a href="leads.php?status=contacted" class="text-yellow-600 hover:text-yellow-800 text-sm">View All</a>
                                </div>

                                <!-- Interested -->
                                <div class="flex items-center justify-between p-4 bg-green-50 rounded-lg">
                                    <div class="flex items-center">
                                        <div class="w-8 h-8 bg-green-500 rounded-full flex items-center justify-center">
                                            <span class="text-white text-sm font-medium"><?php echo $stats['interested_leads']; ?></span>
                                        </div>
                                        <span class="ml-3 text-sm font-medium text-gray-900">Interested</span>
                                    </div>
                                    <a href="leads.php?status=interested" class="text-green-600 hover:text-green-800 text-sm">View All</a>
                                </div>

                                <!-- Follow-up -->
                                <div class="flex items-center justify-between p-4 bg-purple-50 rounded-lg">
                                    <div class="flex items-center">
                                        <div class="w-8 h-8 bg-purple-500 rounded-full flex items-center justify-center">
                                            <span class="text-white text-sm font-medium"><?php echo $stats['follow_up_leads']; ?></span>
                                        </div>
                                        <span class="ml-3 text-sm font-medium text-gray-900">Follow-up Required</span>
                                    </div>
                                    <a href="leads.php?status=follow-up" class="text-purple-600 hover:text-purple-800 text-sm">View All</a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="space-y-6">
                    <!-- Follow-up Tasks -->
                    <div class="bg-white shadow rounded-lg">
                        <div class="px-4 py-5 sm:p-6">
                            <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">Upcoming Follow-ups</h3>
                            <div class="space-y-3">
                                <?php while ($follow_up = $follow_ups->fetch_assoc()): ?>
                                <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                                    <div>
                                        <p class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($follow_up['full_name']); ?></p>
                                        <p class="text-xs text-gray-500">
                                            <?php echo date('M j, Y', strtotime($follow_up['next_follow_up'])); ?>
                                        </p>
                                    </div>
                                    <a href="lead-detail.php?id=<?php echo $follow_up['id']; ?>" 
                                       class="text-blue-600 hover:text-blue-800 text-xs">
                                        View
                                    </a>
                                </div>
                                <?php endwhile; ?>
                                
                                <?php if ($follow_ups->num_rows === 0): ?>
                                <p class="text-sm text-gray-500 text-center py-4">No upcoming follow-ups</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Quick Actions -->
                    <div class="bg-white shadow rounded-lg">
                        <div class="px-4 py-5 sm:p-6">
                            <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">Quick Actions</h3>
                            <div class="space-y-3">
                                <a href="leads.php?status=new" 
                                   class="w-full bg-blue-600 text-white text-center py-2 px-4 rounded-md hover:bg-blue-700 transition duration-200 block">
                                    View New Leads
                                </a>
                                <a href="lead-detail.php?action=new" 
                                   class="w-full bg-green-600 text-white text-center py-2 px-4 rounded-md hover:bg-green-700 transition duration-200 block">
                                    Add New Lead
                                </a>
                                <a href="reports.php" 
                                   class="w-full bg-purple-600 text-white text-center py-2 px-4 rounded-md hover:bg-purple-700 transition duration-200 block">
                                    View Reports
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Activity -->
            <div class="mt-8">
                <div class="bg-white shadow rounded-lg">
                    <div class="px-4 py-5 sm:p-6">
                        <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">Recent Leads</h3>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Phone</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Platform</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Created</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php while ($lead = $recent_leads->fetch_assoc()): ?>
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($lead['full_name']); ?></div>
                                            <?php if ($lead['campaign_title']): ?>
                                            <div class="text-sm text-gray-500"><?php echo htmlspecialchars($lead['campaign_title']); ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            <?php echo htmlspecialchars($lead['phone']); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            <?php echo htmlspecialchars($lead['platform']); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <?php echo get_status_badge($lead['status']); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <?php echo time_ago($lead['created_at']); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                            <a href="lead-detail.php?id=<?php echo $lead['id']; ?>" 
                                               class="text-blue-600 hover:text-blue-900">View</a>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Auto-refresh dashboard every 5 minutes
setTimeout(function() {
    window.location.reload();
}, 300000);

// Add click tracking for pipeline items
document.querySelectorAll('[href*="leads.php"]').forEach(function(link) {
    link.addEventListener('click', function() {
        // Track pipeline navigation (could integrate with analytics)
        console.log('Pipeline navigation:', this.href);
    });
});
</script>

<?php include_once '../templates/footer.php'; ?>

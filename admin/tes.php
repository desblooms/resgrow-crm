<?php
// Resgrow CRM - Simple Test Dashboard
// Phase 3: Admin Dashboard - Testing

require_once '../includes/session.php';
require_once '../includes/functions.php';

// Require admin access
SessionManager::requireRole('admin');

$page_title = 'Admin Dashboard (Test)';

// Manual database connection (avoiding global issues)
try {
    require_once '../config.php';
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }
    
    $conn->set_charset("utf8mb4");
    
    // Get basic stats
    $stats = [];
    
    $result = $conn->query("SELECT COUNT(*) as count FROM users WHERE status = 'active'");
    $stats['active_users'] = $result ? $result->fetch_assoc()['count'] : 0;
    
    $result = $conn->query("SELECT COUNT(*) as count FROM campaigns");
    $stats['total_campaigns'] = $result ? $result->fetch_assoc()['count'] : 0;
    
    $result = $conn->query("SELECT COUNT(*) as count FROM leads");
    $stats['total_leads'] = $result ? $result->fetch_assoc()['count'] : 0;
    
    $result = $conn->query("SELECT COUNT(*) as count FROM leads WHERE status = 'closed-won'");
    $stats['closed_deals'] = $result ? $result->fetch_assoc()['count'] : 0;
    
    $result = $conn->query("SELECT COALESCE(SUM(sale_value_qr), 0) as revenue FROM leads WHERE status = 'closed-won'");
    $stats['total_revenue'] = $result ? $result->fetch_assoc()['revenue'] : 0;
    
    // Calculate conversion rate
    $conversion_rate = $stats['total_leads'] > 0 ? 
        round(($stats['closed_deals'] / $stats['total_leads']) * 100, 2) : 0;
    
    // Get platform stats
    $platform_stats = [];
    $platform_result = $conn->query("
        SELECT 
            platform,
            COUNT(*) as lead_count,
            COUNT(CASE WHEN status = 'closed-won' THEN 1 END) as conversions,
            COALESCE(SUM(CASE WHEN status = 'closed-won' THEN sale_value_qr END), 0) as revenue
        FROM leads 
        GROUP BY platform
        ORDER BY revenue DESC
    ");
    
    if ($platform_result) {
        while ($row = $platform_result->fetch_assoc()) {
            $platform_stats[] = $row;
        }
    }
    
    $database_status = "✅ Connected successfully";
    
} catch (Exception $e) {
    $database_status = "❌ Error: " . $e->getMessage();
    $stats = [
        'active_users' => 0, 'total_campaigns' => 0, 'total_leads' => 0,
        'closed_deals' => 0, 'total_revenue' => 0
    ];
    $conversion_rate = 0;
    $platform_stats = [];
}

include '../templates/header.php';
?>

<div class="min-h-screen bg-gray-50">
    <!-- Simple Navigation -->
    <nav class="bg-white shadow border-b border-gray-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <h1 class="text-xl font-semibold text-gray-900"><?php echo APP_NAME; ?> - Test Dashboard</h1>
                </div>
                <div class="flex items-center space-x-4">
                    <span class="text-sm text-gray-700">
                        Welcome, <?php echo htmlspecialchars(SessionManager::getUsername()); ?>
                    </span>
                    <a href="../public/logout.php" class="text-sm text-primary-600 hover:text-primary-900">Logout</a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
        <div class="px-4 py-6 sm:px-0">
            
            <!-- Database Status -->
            <div class="mb-6 p-4 rounded-lg <?php echo strpos($database_status, '✅') !== false ? 'bg-green-100' : 'bg-red-100'; ?>">
                <h3 class="font-medium">Database Status: <?php echo $database_status; ?></h3>
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
                                        <?php echo number_format($stats['total_revenue'], 2); ?> QAR
                                    </dd>
                                </dl>
                            </div>
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
                            <span class="text-gray-500">campaigns</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Platform Performance -->
            <?php if (!empty($platform_stats)): ?>
            <div class="bg-white shadow rounded-lg">
                <div class="px-4 py-5 sm:p-6">
                    <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">Platform Performance</h3>
                    <div class="space-y-4">
                        <?php foreach ($platform_stats as $platform): ?>
                            <?php 
                            $platform_conversion = $platform['lead_count'] > 0 ? 
                                round(($platform['conversions'] / $platform['lead_count']) * 100, 1) : 0;
                            ?>
                            <div class="flex items-center justify-between p-3 border border-gray-200 rounded-lg">
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
                                        <?php echo number_format($platform['revenue'], 2); ?> QAR
                                    </p>
                                    <p class="text-xs text-gray-500"><?php echo $platform['conversions']; ?> deals</p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php else: ?>
            <div class="bg-white shadow rounded-lg">
                <div class="px-4 py-5 sm:p-6">
                    <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">Platform Performance</h3>
                    <p class="text-gray-500 text-center py-4">No platform data available yet.</p>
                </div>
            </div>
            <?php endif; ?>

            <!-- Quick Actions -->
            <div class="mt-8 bg-white shadow rounded-lg">
                <div class="px-4 py-5 sm:p-6">
                    <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">Quick Actions</h3>
                    <div class="flex flex-wrap gap-4">
                        <a href="users.php" class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                            👥 Manage Users
                        </a>
                        <a href="debug.php" class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                            🔧 System Debug
                        </a>
                        <a href="../public/login.php" class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                            🏠 Back to Login
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<?php 
if (isset($conn)) {
    $conn->close();
}
include '../templates/footer.php'; 
?>
<?php
// Resgrow CRM - Activity Log
// Admin functionality to view system activity logs

require_once '../includes/session.php';
require_once '../includes/functions.php';
require_once '../includes/db.php';

// Require admin access
SessionManager::requireRole('admin');

$page_title = 'Activity Log';

// Get filter parameters
$user_filter = $_GET['user'] ?? '';
$action_filter = $_GET['action'] ?? '';
$date_from = $_GET['date_from'] ?? date('Y-m-d', strtotime('-7 days'));
$date_to = $_GET['date_to'] ?? date('Y-m-d');
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 50;
$offset = ($page - 1) * $per_page;

try {
    global $db;
    
    // Build query conditions
    $where_conditions = [];
    $bind_params = [];
    $bind_types = '';
    
    // Date filter
    if ($date_from && $date_to) {
        $where_conditions[] = "DATE(al.created_at) BETWEEN ? AND ?";
        $bind_params[] = $date_from;
        $bind_params[] = $date_to;
        $bind_types .= 'ss';
    }
    
    // User filter
    if ($user_filter) {
        $where_conditions[] = "u.name LIKE ?";
        $bind_params[] = "%{$user_filter}%";
        $bind_types .= 's';
    }
    
    // Action filter
    if ($action_filter) {
        $where_conditions[] = "al.action = ?";
        $bind_params[] = $action_filter;
        $bind_types .= 's';
    }
    
    $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
    
    // Get total count for pagination
    $count_sql = "
        SELECT COUNT(*) as total 
        FROM activity_log al 
        LEFT JOIN users u ON al.user_id = u.id 
        {$where_clause}
    ";
    
    $count_stmt = $db->prepare($count_sql);
    if (!empty($bind_params)) {
        $count_stmt->bind_param($bind_types, ...$bind_params);
    }
    $count_stmt->execute();
    $total_activities = $count_stmt->get_result()->fetch_assoc()['total'];
    $total_pages = ceil($total_activities / $per_page);
    
    // Get activities with pagination
    $activities_sql = "
        SELECT al.*, u.name as user_name, u.role as user_role
        FROM activity_log al 
        LEFT JOIN users u ON al.user_id = u.id 
        {$where_clause}
        ORDER BY al.created_at DESC 
        LIMIT ? OFFSET ?
    ";
    
    $bind_params[] = $per_page;
    $bind_params[] = $offset;
    $bind_types .= 'ii';
    
    $stmt = $db->prepare($activities_sql);
    if (!empty($bind_params)) {
        $stmt->bind_param($bind_types, ...$bind_params);
    }
    $stmt->execute();
    $activities = $stmt->get_result();
    
    // Get unique actions for filter
    $actions_sql = "SELECT DISTINCT action FROM activity_log ORDER BY action";
    $actions_result = $db->query($actions_sql);
    
    // Get unique users for filter
    $users_sql = "SELECT DISTINCT u.id, u.name FROM users u JOIN activity_log al ON u.id = al.user_id ORDER BY u.name";
    $users_result = $db->query($users_sql);
    
} catch (Exception $e) {
    set_flash_message('error', 'Error loading activity log: ' . $e->getMessage());
    $activities = [];
    $total_activities = 0;
    $total_pages = 0;
    $actions_result = [];
    $users_result = [];
}

include '../templates/header.php';
?>

<div class="min-h-screen bg-gray-50">
    <!-- Navigation -->
    <?php include '../templates/nav-admin.php'; ?>
    
    <div class="py-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <!-- Page Header -->
            <div class="mb-8">
                <h1 class="text-3xl font-bold text-gray-900">Activity Log</h1>
                <p class="mt-2 text-sm text-gray-600">
                    View system activity and user actions
                </p>
            </div>

            <!-- Flash Messages -->
            <?php include '../templates/flash-messages.php'; ?>

            <!-- Filters -->
            <div class="bg-white shadow rounded-lg mb-8">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h2 class="text-lg font-medium text-gray-900">Filters</h2>
                </div>
                <div class="p-6">
                    <form method="GET" action="" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                        <div>
                            <label for="date_from" class="block text-sm font-medium text-gray-700">Date From</label>
                            <input type="date" id="date_from" name="date_from" value="<?php echo $date_from; ?>"
                                   class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                        </div>
                        
                        <div>
                            <label for="date_to" class="block text-sm font-medium text-gray-700">Date To</label>
                            <input type="date" id="date_to" name="date_to" value="<?php echo $date_to; ?>"
                                   class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                        </div>
                        
                        <div>
                            <label for="user" class="block text-sm font-medium text-gray-700">User</label>
                            <select id="user" name="user" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                <option value="">All Users</option>
                                <?php if ($users_result && $users_result->num_rows > 0): ?>
                                    <?php while ($user = $users_result->fetch_assoc()): ?>
                                        <option value="<?php echo htmlspecialchars($user['name']); ?>" 
                                                <?php echo $user_filter === $user['name'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($user['name']); ?>
                                        </option>
                                    <?php endwhile; ?>
                                <?php endif; ?>
                            </select>
                        </div>
                        
                        <div>
                            <label for="action" class="block text-sm font-medium text-gray-700">Action</label>
                            <select id="action" name="action" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                <option value="">All Actions</option>
                                <?php if ($actions_result && $actions_result->num_rows > 0): ?>
                                    <?php while ($action = $actions_result->fetch_assoc()): ?>
                                        <option value="<?php echo htmlspecialchars($action['action']); ?>" 
                                                <?php echo $action_filter === $action['action'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $action['action']))); ?>
                                        </option>
                                    <?php endwhile; ?>
                                <?php endif; ?>
                            </select>
                        </div>
                        
                        <div class="md:col-span-4 flex justify-end space-x-3">
                            <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                                Apply Filters
                            </button>
                            <a href="activity-log.php" class="bg-gray-300 text-gray-700 px-4 py-2 rounded-md hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2">
                                Clear Filters
                            </a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Activity Log Table -->
            <div class="bg-white shadow rounded-lg">
                <div class="px-6 py-4 border-b border-gray-200">
                    <div class="flex items-center justify-between">
                        <h2 class="text-lg font-medium text-gray-900">Activity Log</h2>
                        <div class="text-sm text-gray-500">
                            <?php echo number_format($total_activities); ?> total activities
                        </div>
                    </div>
                </div>
                
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">User</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Action</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Description</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">IP Address</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date & Time</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php if ($activities && $activities->num_rows > 0): ?>
                                <?php while ($activity = $activities->fetch_assoc()): ?>
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="flex items-center">
                                                <div class="flex-shrink-0 h-10 w-10">
                                                    <div class="h-10 w-10 rounded-full bg-gray-300 flex items-center justify-center">
                                                        <span class="text-sm font-medium text-gray-700">
                                                            <?php echo strtoupper(substr($activity['user_name'] ?? 'S', 0, 1)); ?>
                                                        </span>
                                                    </div>
                                                </div>
                                                <div class="ml-4">
                                                    <div class="text-sm font-medium text-gray-900">
                                                        <?php echo htmlspecialchars($activity['user_name'] ?? 'System'); ?>
                                                    </div>
                                                    <?php if ($activity['user_role']): ?>
                                                        <div class="text-sm text-gray-500">
                                                            <?php echo ucfirst($activity['user_role']); ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full 
                                                <?php 
                                                switch($activity['action']) {
                                                    case 'login': echo 'bg-green-100 text-green-800'; break;
                                                    case 'logout': echo 'bg-gray-100 text-gray-800'; break;
                                                    case 'lead_created': echo 'bg-blue-100 text-blue-800'; break;
                                                    case 'lead_updated': echo 'bg-yellow-100 text-yellow-800'; break;
                                                    case 'campaign_created': echo 'bg-purple-100 text-purple-800'; break;
                                                    case 'data_export': echo 'bg-indigo-100 text-indigo-800'; break;
                                                    default: echo 'bg-gray-100 text-gray-800';
                                                }
                                                ?>">
                                                <?php echo ucfirst(str_replace('_', ' ', $activity['action'])); ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="text-sm text-gray-900">
                                                <?php echo htmlspecialchars($activity['description'] ?? ''); ?>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <?php echo htmlspecialchars($activity['ip_address'] ?? 'N/A'); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <?php echo date('M j, Y g:i A', strtotime($activity['created_at'])); ?>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="px-6 py-4 text-center text-sm text-gray-500">
                                        No activities found for the selected filters
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <div class="bg-white px-4 py-3 border-t border-gray-200 sm:px-6">
                        <div class="flex items-center justify-between">
                            <div class="flex-1 flex justify-between sm:hidden">
                                <?php if ($page > 1): ?>
                                    <a href="?page=<?php echo $page - 1; ?>&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>&user=<?php echo urlencode($user_filter); ?>&action=<?php echo urlencode($action_filter); ?>" 
                                       class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                                        Previous
                                    </a>
                                <?php endif; ?>
                                <?php if ($page < $total_pages): ?>
                                    <a href="?page=<?php echo $page + 1; ?>&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>&user=<?php echo urlencode($user_filter); ?>&action=<?php echo urlencode($action_filter); ?>" 
                                       class="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                                        Next
                                    </a>
                                <?php endif; ?>
                            </div>
                            <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                                <div>
                                    <p class="text-sm text-gray-700">
                                        Showing <span class="font-medium"><?php echo ($offset + 1); ?></span> to 
                                        <span class="font-medium"><?php echo min($offset + $per_page, $total_activities); ?></span> of 
                                        <span class="font-medium"><?php echo $total_activities; ?></span> results
                                    </p>
                                </div>
                                <div>
                                    <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px">
                                        <?php if ($page > 1): ?>
                                            <a href="?page=<?php echo $page - 1; ?>&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>&user=<?php echo urlencode($user_filter); ?>&action=<?php echo urlencode($action_filter); ?>" 
                                               class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                                                Previous
                                            </a>
                                        <?php endif; ?>
                                        
                                        <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                            <a href="?page=<?php echo $i; ?>&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>&user=<?php echo urlencode($user_filter); ?>&action=<?php echo urlencode($action_filter); ?>" 
                                               class="relative inline-flex items-center px-4 py-2 border text-sm font-medium 
                                                      <?php echo $i === $page ? 'z-10 bg-blue-50 border-blue-500 text-blue-600' : 'bg-white border-gray-300 text-gray-500 hover:bg-gray-50'; ?>">
                                                <?php echo $i; ?>
                                            </a>
                                        <?php endfor; ?>
                                        
                                        <?php if ($page < $total_pages): ?>
                                            <a href="?page=<?php echo $page + 1; ?>&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>&user=<?php echo urlencode($user_filter); ?>&action=<?php echo urlencode($action_filter); ?>" 
                                               class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                                                Next
                                            </a>
                                        <?php endif; ?>
                                    </nav>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include '../templates/footer.php'; ?>

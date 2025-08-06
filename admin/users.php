<?php
// Resgrow CRM - Admin Users Management
// Phase 2: User Role System

require_once '../includes/session.php';
require_once '../includes/functions.php';
require_once '../includes/db.php';

// Require admin role
SessionManager::requireRole('admin');

// Handle user actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $user_id = (int)($_POST['user_id'] ?? 0);
    $csrf_token = $_POST['csrf_token'] ?? '';
    
    if (!verify_csrf_token($csrf_token)) {
        set_flash_message('error', 'Security token mismatch.');
    } else {
        switch ($action) {
            case 'toggle_status':
                toggle_user_status($user_id);
                break;
            case 'delete_user':
                delete_user($user_id);
                break;
            case 'reset_password':
                reset_user_password($user_id);
                break;
        }
    }
    
    header('Location: users.php');
    exit();
}

// Get users with pagination
$page = (int)($_GET['page'] ?? 1);
$search = $_GET['search'] ?? '';
$role_filter = $_GET['role'] ?? '';
$status_filter = $_GET['status'] ?? '';

$users_data = get_users_paginated($page, $search, $role_filter, $status_filter);

$page_title = 'User Management';
include '../templates/header.php';
?>

<div class="min-h-screen bg-gray-50 flex">
    <!-- Sidebar -->
    <div class="fixed inset-y-0 left-0 z-50 w-64 bg-white shadow-lg transform -translate-x-full transition-transform duration-300 ease-in-out lg:translate-x-0 lg:static lg:inset-0" id="sidebar">
        <div class="flex items-center justify-center h-16 px-4 bg-primary-600">
            <h1 class="text-xl font-semibold text-white"><?php echo APP_NAME; ?></h1>
        </div>
        
        <nav class="mt-8">
            <div class="px-4 space-y-2">
                <a href="dashboard.php" class="flex items-center px-4 py-2 text-gray-600 hover:bg-gray-100 rounded-lg">
                    <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2H5a2 2 0 00-2-2z"></path>
                    </svg>
                    Dashboard
                </a>
                
                <a href="users.php" class="flex items-center px-4 py-2 text-gray-700 bg-primary-100 rounded-lg">
                    <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-2.239"></path>
                    </svg>
                    Users
                </a>
                
                <a href="analytics.php" class="flex items-center px-4 py-2 text-gray-600 hover:bg-gray-100 rounded-lg">
                    <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                    </svg>
                    Analytics
                </a>
            </div>
        </nav>
    </div>

    <!-- Mobile menu button -->
    <div class="lg:hidden">
        <button id="mobile-menu-btn" class="fixed top-4 left-4 z-50 p-2 rounded-md bg-primary-600 text-white">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
            </svg>
        </button>
    </div>

    <!-- Main Content -->
    <div class="flex-1 lg:ml-0">
        <!-- Header -->
        <header class="bg-white shadow-sm border-b border-gray-200">
            <div class="px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between items-center h-16">
                    <div class="flex items-center">
                        <h1 class="text-2xl font-semibold text-gray-900 ml-10 lg:ml-0">User Management</h1>
                    </div>
                    
                    <div class="flex items-center space-x-4">
                        <a href="create-user.php" class="bg-primary-600 hover:bg-primary-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                            Add User
                        </a>
                    </div>
                </div>
            </div>
        </header>

        <!-- Content -->
        <main class="p-4 sm:p-6 lg:p-8">
            <!-- Filters -->
            <div class="bg-white shadow rounded-lg mb-6">
                <div class="p-6">
                    <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                        <div>
                            <label for="search" class="block text-sm font-medium text-gray-700 mb-1">Search</label>
                            <input type="text" id="search" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                                   placeholder="Name or email..." 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-primary-500 focus:border-primary-500">
                        </div>
                        
                        <div>
                            <label for="role" class="block text-sm font-medium text-gray-700 mb-1">Role</label>
                            <select id="role" name="role" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-primary-500 focus:border-primary-500">
                                <option value="">All Roles</option>
                                <option value="admin" <?php echo $role_filter === 'admin' ? 'selected' : ''; ?>>Administrator</option>
                                <option value="marketing" <?php echo $role_filter === 'marketing' ? 'selected' : ''; ?>>Marketing</option>
                                <option value="sales" <?php echo $role_filter === 'sales' ? 'selected' : ''; ?>>Sales</option>
                            </select>
                        </div>
                        
                        <div>
                            <label for="status" class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                            <select id="status" name="status" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-primary-500 focus:border-primary-500">
                                <option value="">All Status</option>
                                <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option>
                                <option value="inactive" <?php echo $status_filter === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                            </select>
                        </div>
                        
                        <div class="flex items-end">
                            <button type="submit" class="w-full bg-primary-600 hover:bg-primary-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                                Filter
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Users Table -->
            <div class="bg-white shadow rounded-lg overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900">
                        Users (<?php echo $users_data['total']; ?> total)
                    </h3>
                </div>
                
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">User</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Role</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Last Login</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Created</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php if (empty($users_data['users'])): ?>
                            <tr>
                                <td colspan="6" class="px-6 py-4 text-center text-gray-500">
                                    No users found matching your criteria.
                                </td>
                            </tr>
                            <?php else: ?>
                            <?php foreach ($users_data['users'] as $user): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="w-10 h-10 bg-primary-100 rounded-full flex items-center justify-center">
                                            <span class="text-primary-600 font-medium text-sm">
                                                <?php echo strtoupper(substr($user['name'], 0, 1)); ?>
                                            </span>
                                        </div>
                                        <div class="ml-4">
                                            <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($user['name']); ?></div>
                                            <div class="text-sm text-gray-500"><?php echo htmlspecialchars($user['email']); ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-800">
                                        <?php echo get_role_display_name($user['role']); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <?php echo get_status_badge($user['status']); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php echo $user['last_login'] ? time_ago($user['last_login']) : 'Never'; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php echo format_date($user['created_at'], 'M j, Y'); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <div class="flex items-center justify-end space-x-2">
                                        <a href="edit-user.php?id=<?php echo $user['id']; ?>" 
                                           class="text-primary-600 hover:text-primary-900">Edit</a>
                                        
                                        <?php if ($user['id'] !== SessionManager::getUserId()): ?>
                                        <form method="POST" class="inline" onsubmit="return confirmAction('<?php echo $user['status'] === 'active' ? 'deactivate' : 'activate'; ?> this user?')">
                                            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                            <input type="hidden" name="action" value="toggle_status">
                                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                            <button type="submit" class="text-yellow-600 hover:text-yellow-900">
                                                <?php echo $user['status'] === 'active' ? 'Deactivate' : 'Activate'; ?>
                                            </button>
                                        </form>
                                        
                                        <form method="POST" class="inline" onsubmit="return confirmAction('reset password for this user?')">
                                            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                            <input type="hidden" name="action" value="reset_password">
                                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                            <button type="submit" class="text-blue-600 hover:text-blue-900">Reset</button>
                                        </form>
                                        
                                        <form method="POST" class="inline" onsubmit="return confirmAction('permanently delete this user? This cannot be undone.')">
                                            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                            <input type="hidden" name="action" value="delete_user">
                                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                            <button type="submit" class="text-red-600 hover:text-red-900">Delete</button>
                                        </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination -->
                <?php if ($users_data['pagination']['total_pages'] > 1): ?>
                <div class="bg-white px-4 py-3 flex items-center justify-between border-t border-gray-200 sm:px-6">
                    <div class="flex-1 flex justify-between sm:hidden">
                        <?php if ($users_data['pagination']['has_prev']): ?>
                        <a href="?page=<?php echo $users_data['pagination']['current_page'] - 1; ?>&search=<?php echo urlencode($search); ?>&role=<?php echo urlencode($role_filter); ?>&status=<?php echo urlencode($status_filter); ?>" 
                           class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                            Previous
                        </a>
                        <?php endif; ?>
                        <?php if ($users_data['pagination']['has_next']): ?>
                        <a href="?page=<?php echo $users_data['pagination']['current_page'] + 1; ?>&search=<?php echo urlencode($search); ?>&role=<?php echo urlencode($role_filter); ?>&status=<?php echo urlencode($status_filter); ?>" 
                           class="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                            Next
                        </a>
                        <?php endif; ?>
                    </div>
                    <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                        <div>
                            <p class="text-sm text-gray-700">
                                Showing <span class="font-medium"><?php echo ($users_data['pagination']['current_page'] - 1) * $users_data['pagination']['records_per_page'] + 1; ?></span>
                                to <span class="font-medium"><?php echo min($users_data['pagination']['current_page'] * $users_data['pagination']['records_per_page'], $users_data['total']); ?></span>
                                of <span class="font-medium"><?php echo $users_data['total']; ?></span> results
                            </p>
                        </div>
                        <div>
                            <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px">
                                <?php if ($users_data['pagination']['has_prev']): ?>
                                <a href="?page=<?php echo $users_data['pagination']['current_page'] - 1; ?>&search=<?php echo urlencode($search); ?>&role=<?php echo urlencode($role_filter); ?>&status=<?php echo urlencode($status_filter); ?>" 
                                   class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                                    Previous
                                </a>
                                <?php endif; ?>
                                
                                <?php for ($i = max(1, $users_data['pagination']['current_page'] - 2); $i <= min($users_data['pagination']['total_pages'], $users_data['pagination']['current_page'] + 2); $i++): ?>
                                <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&role=<?php echo urlencode($role_filter); ?>&status=<?php echo urlencode($status_filter); ?>" 
                                   class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium <?php echo $i === $users_data['pagination']['current_page'] ? 'text-primary-600 bg-primary-50' : 'text-gray-700 hover:bg-gray-50'; ?>">
                                    <?php echo $i; ?>
                                </a>
                                <?php endfor; ?>
                                
                                <?php if ($users_data['pagination']['has_next']): ?>
                                <a href="?page=<?php echo $users_data['pagination']['current_page'] + 1; ?>&search=<?php echo urlencode($search); ?>&role=<?php echo urlencode($role_filter); ?>&status=<?php echo urlencode($status_filter); ?>" 
                                   class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                                    Next
                                </a>
                                <?php endif; ?>
                            </nav>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
</div>

<script>
// Mobile menu toggle
document.getElementById('mobile-menu-btn').addEventListener('click', function() {
    const sidebar = document.getElementById('sidebar');
    sidebar.classList.toggle('-translate-x-full');
});

// Confirmation dialogs
function confirmAction(message) {
    return confirm('Are you sure you want to ' + message);
}
</script>

<?php
include '../templates/footer.php';

// Helper functions
function get_users_paginated($page = 1, $search = '', $role_filter = '', $status_filter = '', $records_per_page = 20) {
    global $db;
    
    $offset = ($page - 1) * $records_per_page;
    $where_conditions = [];
    $params = [];
    $types = '';
    
    if (!empty($search)) {
        $where_conditions[] = "(name LIKE ? OR email LIKE ?)";
        $search_param = "%{$search}%";
        $params[] = $search_param;
        $params[] = $search_param;
        $types .= 'ss';
    }
    
    if (!empty($role_filter)) {
        $where_conditions[] = "role = ?";
        $params[] = $role_filter;
        $types .= 's';
    }
    
    if (!empty($status_filter)) {
        $where_conditions[] = "status = ?";
        $params[] = $status_filter;
        $types .= 's';
    }
    
    $where_sql = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
    
    // Get total count
    $count_sql = "SELECT COUNT(*) as total FROM users {$where_sql}";
    $count_stmt = $db->prepare($count_sql);
    if (!empty($params)) {
        $count_stmt->bind_param($types, ...$params);
    }
    $count_stmt->execute();
    $total = $count_stmt->get_result()->fetch_assoc()['total'];
    
    // Get users
    $sql = "SELECT id, name, email, role, status, last_login, created_at 
            FROM users {$where_sql} 
            ORDER BY created_at DESC 
            LIMIT ?, ?";
    
    $params[] = $offset;
    $params[] = $records_per_page;
    $types .= 'ii';
    
    $stmt = $db->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $users = [];
    while ($row = $result->fetch_assoc()) {
        $users[] = $row;
    }
    
    $pagination = paginate($total, $records_per_page, $page);
    
    return [
        'users' => $users,
        'total' => $total,
        'pagination' => $pagination
    ];
}

function toggle_user_status($user_id) {
    global $db;
    
    if ($user_id === SessionManager::getUserId()) {
        set_flash_message('error', 'You cannot change your own status.');
        return;
    }
    
    $stmt = $db->prepare("SELECT status FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        set_flash_message('error', 'User not found.');
        return;
    }
    
    $user = $result->fetch_assoc();
    $new_status = $user['status'] === 'active' ? 'inactive' : 'active';
    
    $stmt = $db->prepare("UPDATE users SET status = ?, updated_at = NOW() WHERE id = ?");
    $stmt->bind_param("si", $new_status, $user_id);
    
    if ($stmt->execute()) {
        log_activity(SessionManager::getUserId(), 'user_status_change', "Changed user {$user_id} status to {$new_status}");
        set_flash_message('success', 'User status updated successfully.');
    } else {
        set_flash_message('error', 'Failed to update user status.');
    }
}

function delete_user($user_id) {
    global $db;
    
    if ($user_id === SessionManager::getUserId()) {
        set_flash_message('error', 'You cannot delete your own account.');
        return;
    }
    
    $stmt = $db->prepare("DELETE FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    
    if ($stmt->execute()) {
        log_activity(SessionManager::getUserId(), 'user_delete', "Deleted user {$user_id}");
        set_flash_message('success', 'User deleted successfully.');
    } else {
        set_flash_message('error', 'Failed to delete user.');
    }
}

function reset_user_password($user_id) {
    global $db;
    
    $new_password = 'password123'; // Default password
    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
    
    $stmt = $db->prepare("UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?");
    $stmt->bind_param("si", $hashed_password, $user_id);
    
    if ($stmt->execute()) {
        log_activity(SessionManager::getUserId(), 'password_reset', "Reset password for user {$user_id}");
        set_flash_message('success', 'Password reset to: password123');
    } else {
        set_flash_message('error', 'Failed to reset password.');
    }
}
?>
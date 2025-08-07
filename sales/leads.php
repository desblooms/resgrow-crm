<?php
// Resgrow CRM - Lead Management
// Comprehensive lead listing and management for sales

require_once '../config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

check_login();
if ($_SESSION['user_role'] !== 'sales' && $_SESSION['user_role'] !== 'admin') {
    header('Location: ../public/dashboard.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'];

// Handle filters and search
$status_filter = $_GET['status'] ?? '';
$platform_filter = $_GET['platform'] ?? '';
$search = $_GET['search'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Build query conditions
$where_conditions = [];
$bind_params = [];
$bind_types = '';

// Role-based filtering
if ($user_role === 'sales') {
    $where_conditions[] = "l.assigned_to = ?";
    $bind_params[] = $user_id;
    $bind_types .= 'i';
}

// Status filter
if ($status_filter) {
    $where_conditions[] = "l.status = ?";
    $bind_params[] = $status_filter;
    $bind_types .= 's';
}

// Platform filter
if ($platform_filter) {
    $where_conditions[] = "l.platform = ?";
    $bind_params[] = $platform_filter;
    $bind_types .= 's';
}

// Search filter
if ($search) {
    $where_conditions[] = "(l.full_name LIKE ? OR l.phone LIKE ? OR l.email LIKE ?)";
    $search_term = "%{$search}%";
    $bind_params[] = $search_term;
    $bind_params[] = $search_term;
    $bind_params[] = $search_term;
    $bind_types .= 'sss';
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get leads with pagination
$leads_sql = "SELECT l.*, c.title as campaign_title, u.name as assigned_to_name 
              FROM leads l 
              LEFT JOIN campaigns c ON l.campaign_id = c.id 
              LEFT JOIN users u ON l.assigned_to = u.id 
              {$where_clause}
              ORDER BY 
                CASE 
                    WHEN l.status = 'new' THEN 1
                    WHEN l.status = 'contacted' THEN 2
                    WHEN l.status = 'interested' THEN 3
                    WHEN l.status = 'follow-up' THEN 4
                    ELSE 5
                END,
                l.created_at DESC
              LIMIT ? OFFSET ?";

// Add pagination parameters
$bind_params[] = $per_page;
$bind_params[] = $offset;
$bind_types .= 'ii';

$stmt = $db->prepare($leads_sql);
if (!empty($bind_params)) {
    $stmt->bind_param($bind_types, ...$bind_params);
}
$stmt->execute();
$leads_result = $stmt->get_result();

// Get total count for pagination
$count_sql = "SELECT COUNT(*) as total 
              FROM leads l 
              LEFT JOIN campaigns c ON l.campaign_id = c.id 
              {$where_clause}";

$count_stmt = $db->prepare($count_sql);
if (!empty($where_conditions)) {
    // Remove pagination parameters for count
    $count_params = array_slice($bind_params, 0, -2);
    $count_types = substr($bind_types, 0, -2);
    if (!empty($count_params)) {
        $count_stmt->bind_param($count_types, ...$count_params);
    }
}
$count_stmt->execute();
$total_leads = $count_stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_leads / $per_page);

// Get filter options
$statuses = ['new', 'contacted', 'interested', 'follow-up', 'closed-won', 'closed-lost', 'no-response'];
$platforms_sql = "SELECT DISTINCT platform FROM leads ORDER BY platform";
$platforms_result = $db->query($platforms_sql);

include_once '../templates/header.php';
?>

<div class="min-h-screen bg-gray-50">
    <?php include_once '../templates/nav-sales.php'; ?>
    
    <div class="py-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <!-- Header -->
            <div class="mb-8">
                <div class="flex justify-between items-center">
                    <div>
                        <h1 class="text-3xl font-bold text-gray-900">Lead Management</h1>
                        <p class="mt-2 text-gray-600">
                            Manage and track your leads through the sales pipeline
                        </p>
                    </div>
                    <div class="flex space-x-3">
                        <a href="lead-detail.php?action=new" 
                           class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 transition duration-200">
                            Add New Lead
                        </a>
                        <a href="reports.php" 
                           class="bg-gray-600 text-white px-4 py-2 rounded-md hover:bg-gray-700 transition duration-200">
                            View Reports
                        </a>
                    </div>
                </div>
            </div>

            <!-- Filters -->
            <div class="bg-white p-6 rounded-lg shadow mb-6">
                <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div>
                        <label for="search" class="block text-sm font-medium text-gray-700">Search</label>
                        <input type="text" id="search" name="search" value="<?php echo htmlspecialchars($search); ?>"
                               placeholder="Name, phone, or email..."
                               class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    
                    <div>
                        <label for="status" class="block text-sm font-medium text-gray-700">Status</label>
                        <select id="status" name="status" 
                                class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                            <option value="">All Statuses</option>
                            <?php foreach ($statuses as $status): ?>
                            <option value="<?php echo $status; ?>" <?php echo $status_filter === $status ? 'selected' : ''; ?>>
                                <?php echo ucfirst(str_replace('-', ' ', $status)); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div>
                        <label for="platform" class="block text-sm font-medium text-gray-700">Platform</label>
                        <select id="platform" name="platform" 
                                class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                            <option value="">All Platforms</option>
                            <?php while ($platform = $platforms_result->fetch_assoc()): ?>
                            <option value="<?php echo htmlspecialchars($platform['platform']); ?>" 
                                    <?php echo $platform_filter === $platform['platform'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($platform['platform']); ?>
                            </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div class="flex items-end space-x-2">
                        <button type="submit" 
                                class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 transition duration-200">
                            Filter
                        </button>
                        <a href="leads.php" 
                           class="bg-gray-300 text-gray-700 px-4 py-2 rounded-md hover:bg-gray-400 transition duration-200">
                            Clear
                        </a>
                    </div>
                </form>
            </div>

            <!-- Results Summary -->
            <div class="mb-4">
                <p class="text-sm text-gray-600">
                    Showing <?php echo min($per_page, $total_leads - $offset); ?> of <?php echo $total_leads; ?> leads
                    <?php if ($search || $status_filter || $platform_filter): ?>
                    (filtered)
                    <?php endif; ?>
                </p>
            </div>

            <!-- Leads Table -->
            <div class="bg-white shadow rounded-lg overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Lead Info
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Contact
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Source
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Status
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Value
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Follow-up
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php while ($lead = $leads_result->fetch_assoc()): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div>
                                        <div class="text-sm font-medium text-gray-900">
                                            <?php echo htmlspecialchars($lead['full_name']); ?>
                                        </div>
                                        <?php if ($lead['campaign_title']): ?>
                                        <div class="text-sm text-gray-500">
                                            Campaign: <?php echo htmlspecialchars($lead['campaign_title']); ?>
                                        </div>
                                        <?php endif; ?>
                                        <div class="text-xs text-gray-400">
                                            ID: <?php echo $lead['id']; ?> • 
                                            Created: <?php echo time_ago($lead['created_at']); ?>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900">
                                        <div><?php echo htmlspecialchars($lead['phone']); ?></div>
                                        <?php if ($lead['email']): ?>
                                        <div class="text-gray-500"><?php echo htmlspecialchars($lead['email']); ?></div>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900"><?php echo htmlspecialchars($lead['platform']); ?></div>
                                    <?php if ($lead['lead_source']): ?>
                                    <div class="text-sm text-gray-500"><?php echo htmlspecialchars($lead['lead_source']); ?></div>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <?php echo get_status_badge($lead['status']); ?>
                                    <?php if ($lead['lead_quality']): ?>
                                    <div class="mt-1">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium 
                                                     <?php echo $lead['lead_quality'] === 'hot' ? 'bg-red-100 text-red-800' : 
                                                              ($lead['lead_quality'] === 'warm' ? 'bg-yellow-100 text-yellow-800' : 'bg-blue-100 text-blue-800'); ?>">
                                            <?php echo ucfirst($lead['lead_quality']); ?>
                                        </span>
                                    </div>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?php if ($lead['sale_value_qr']): ?>
                                        <?php echo format_currency($lead['sale_value_qr']); ?>
                                    <?php else: ?>
                                        <span class="text-gray-400">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?php if ($lead['next_follow_up']): ?>
                                        <div class="<?php echo strtotime($lead['next_follow_up']) < time() ? 'text-red-600' : 'text-gray-900'; ?>">
                                            <?php echo date('M j, Y', strtotime($lead['next_follow_up'])); ?>
                                        </div>
                                        <?php if (strtotime($lead['next_follow_up']) < time()): ?>
                                        <div class="text-xs text-red-500">Overdue</div>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-gray-400">None</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <div class="flex space-x-2">
                                        <a href="lead-detail.php?id=<?php echo $lead['id']; ?>" 
                                           class="text-blue-600 hover:text-blue-900">View</a>
                                        <button onclick="quickUpdateStatus(<?php echo $lead['id']; ?>, '<?php echo $lead['status']; ?>')"
                                                class="text-green-600 hover:text-green-900">Update</button>
                                        <a href="tel:<?php echo $lead['phone']; ?>" 
                                           class="text-purple-600 hover:text-purple-900">Call</a>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                <div class="bg-white px-4 py-3 flex items-center justify-between border-t border-gray-200 sm:px-6">
                    <div class="flex-1 flex justify-between sm:hidden">
                        <?php if ($page > 1): ?>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>" 
                           class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                            Previous
                        </a>
                        <?php endif; ?>
                        
                        <?php if ($page < $total_pages): ?>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>" 
                           class="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                            Next
                        </a>
                        <?php endif; ?>
                    </div>
                    
                    <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                        <div>
                            <p class="text-sm text-gray-700">
                                Showing page <?php echo $page; ?> of <?php echo $total_pages; ?>
                            </p>
                        </div>
                        <div>
                            <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                                <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>" 
                                   class="relative inline-flex items-center px-4 py-2 border text-sm font-medium 
                                          <?php echo $i === $page ? 'z-10 bg-blue-50 border-blue-500 text-blue-600' : 'bg-white border-gray-300 text-gray-500 hover:bg-gray-50'; ?>">
                                    <?php echo $i; ?>
                                </a>
                                <?php endfor; ?>
                            </nav>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Quick Update Modal -->
<div id="quickUpdateModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden">
    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
        <div class="mt-3">
            <h3 class="text-lg font-medium text-gray-900 mb-4">Quick Update Lead Status</h3>
            <form id="quickUpdateForm">
                <input type="hidden" id="leadId" name="lead_id">
                <div class="mb-4">
                    <label for="newStatus" class="block text-sm font-medium text-gray-700">New Status</label>
                    <select id="newStatus" name="status" required
                            class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                        <?php foreach ($statuses as $status): ?>
                        <option value="<?php echo $status; ?>"><?php echo ucfirst(str_replace('-', ' ', $status)); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-4">
                    <label for="notes" class="block text-sm font-medium text-gray-700">Notes (Optional)</label>
                    <textarea id="notes" name="notes" rows="3"
                              class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                              placeholder="Add notes about this status change..."></textarea>
                </div>
                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="closeQuickUpdate()" 
                            class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-200 rounded-md hover:bg-gray-300">
                        Cancel
                    </button>
                    <button type="submit" 
                            class="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-md hover:bg-blue-700">
                        Update
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function quickUpdateStatus(leadId, currentStatus) {
    document.getElementById('leadId').value = leadId;
    document.getElementById('newStatus').value = currentStatus;
    document.getElementById('quickUpdateModal').classList.remove('hidden');
}

function closeQuickUpdate() {
    document.getElementById('quickUpdateModal').classList.add('hidden');
    document.getElementById('quickUpdateForm').reset();
}

document.getElementById('quickUpdateForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    
    fetch('api/update-lead-status.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            closeQuickUpdate();
            location.reload(); // Refresh to show updated status
        } else {
            alert('Error updating lead: ' + (data.message || 'Unknown error'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error updating lead');
    });
});

// Auto-refresh every 2 minutes for new leads
setInterval(function() {
    if (window.location.search.includes('status=new')) {
        window.location.reload();
    }
}, 120000);
</script>

<?php include_once '../templates/footer.php'; ?>
<?php
// Resgrow CRM - Edit Campaign
// Phase 4: Campaign Creation Module

require_once '../includes/session.php';
require_once '../includes/functions.php';
require_once '../includes/db.php';

// Require marketing or admin role
if (!SessionManager::hasRole('marketing') && !SessionManager::hasRole('admin')) {
    header('Location: ../public/dashboard.php');
    exit();
}

$error_message = '';
$success_message = '';
$campaign_id = (int)($_GET['id'] ?? 0);

// Get campaign data
$campaign = get_campaign_by_id($campaign_id);
if (!$campaign) {
    set_flash_message('error', 'Campaign not found.');
    header('Location: campaigns.php');
    exit();
}

// Check permissions - only creator, assigned user, or admin can edit
$user_id = SessionManager::getUserId();
if (!SessionManager::hasRole('admin') && 
    $campaign['created_by'] != $user_id && 
    $campaign['assigned_to'] != $user_id) {
    set_flash_message('error', 'You do not have permission to edit this campaign.');
    header('Location: campaigns.php');
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = sanitize_input($_POST['title'] ?? '');
    $product_name = sanitize_input($_POST['product_name'] ?? '');
    $description = sanitize_input($_POST['description'] ?? '');
    $platforms = $_POST['platforms'] ?? [];
    $budget_qr = floatval($_POST['budget_qr'] ?? 0);
    $target_audience = sanitize_input($_POST['target_audience'] ?? '');
    $objectives = sanitize_input($_POST['objectives'] ?? '');
    $start_date = $_POST['start_date'] ?? '';
    $end_date = $_POST['end_date'] ?? '';
    $assigned_to = intval($_POST['assigned_to'] ?? 0);
    $status = $_POST['status'] ?? 'draft';
    $csrf_token = $_POST['csrf_token'] ?? '';
    
    // Validate CSRF token
    if (!verify_csrf_token($csrf_token)) {
        $error_message = 'Security token mismatch. Please try again.';
    }
    // Validate required fields
    elseif (empty($title) || empty($product_name) || empty($start_date) || empty($end_date)) {
        $error_message = 'Please fill in all required fields.';
    }
    // Validate dates
    elseif (strtotime($start_date) >= strtotime($end_date)) {
        $error_message = 'End date must be after start date.';
    }
    // Validate platforms
    elseif (empty($platforms)) {
        $error_message = 'Please select at least one platform.';
    }
    // Validate budget
    elseif ($budget_qr <= 0) {
        $error_message = 'Budget must be greater than 0.';
    }
    else {
        // Update campaign
        $result = update_campaign($campaign_id, $title, $product_name, $description, $platforms, $budget_qr, 
                                  $target_audience, $objectives, $start_date, $end_date, $assigned_to, $status);
        
        if ($result['success']) {
            log_activity(SessionManager::getUserId(), 'campaign_update', "Updated campaign: {$title}");
            set_flash_message('success', 'Campaign updated successfully.');
            header('Location: campaigns.php');
            exit();
        } else {
            $error_message = $result['message'];
        }
    }
}

// Get marketing team members for assignment
$marketing_users = get_marketing_users();

$page_title = 'Edit Campaign';
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
                
                <a href="campaigns.php" class="flex items-center px-4 py-2 text-gray-700 bg-primary-100 rounded-lg">
                    <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
                    </svg>
                    Campaigns
                </a>
                
                <a href="assign-leads.php" class="flex items-center px-4 py-2 text-gray-600 hover:bg-gray-100 rounded-lg">
                    <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                    </svg>
                    Assign Leads
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
                        <h1 class="text-2xl font-semibold text-gray-900 ml-10 lg:ml-0">Edit Campaign</h1>
                    </div>
                    
                    <div class="flex items-center space-x-4">
                        <a href="campaigns.php" class="text-gray-600 hover:text-gray-900">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </a>
                    </div>
                </div>
            </div>
        </header>

        <!-- Content -->
        <main class="p-4 sm:p-6 lg:p-8">
            <div class="max-w-4xl mx-auto">
                <!-- Messages -->
                <?php if ($error_message): ?>
                    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6" role="alert">
                        <span class="block sm:inline"><?php echo htmlspecialchars($error_message); ?></span>
                    </div>
                <?php endif; ?>

                <!-- Campaign Info Header -->
                <div class="bg-white shadow rounded-lg mb-6">
                    <div class="px-6 py-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <h3 class="text-lg font-medium text-gray-900"><?php echo htmlspecialchars($campaign['title']); ?></h3>
                                <p class="text-sm text-gray-500"><?php echo htmlspecialchars($campaign['product_name']); ?></p>
                                <div class="flex items-center mt-2 space-x-4">
                                    <?php echo get_status_badge($campaign['status']); ?>
                                    <span class="text-xs text-gray-500">
                                        Created <?php echo format_date($campaign['created_at'], 'M j, Y'); ?>
                                    </span>
                                    <?php if ($campaign['assigned_to_name']): ?>
                                        <span class="text-xs text-gray-500">
                                            Assigned to: <?php echo htmlspecialchars($campaign['assigned_to_name']); ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="text-right">
                                <div class="text-sm font-medium text-gray-900">
                                    <?php echo format_currency($campaign['budget_qr']); ?>
                                </div>
                                <div class="text-xs text-gray-500">Budget</div>
                                <?php if ($campaign['leads_count'] > 0): ?>
                                    <div class="text-xs text-gray-500 mt-1">
                                        <?php echo $campaign['leads_count']; ?> leads • <?php echo $campaign['deals_count']; ?> deals
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Edit Campaign Form -->
                <div class="bg-white shadow rounded-lg">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h3 class="text-lg font-medium text-gray-900">Update Campaign Information</h3>
                        <p class="mt-1 text-sm text-gray-500">Make changes to the campaign details and settings.</p>
                    </div>
                    
                    <form method="POST" class="px-6 py-4 space-y-6" data-validate>
                        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                        
                        <!-- Basic Information -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="title" class="block text-sm font-medium text-gray-700 mb-1">
                                    Campaign Title <span class="text-red-500">*</span>
                                </label>
                                <input type="text" id="title" name="title" required
                                       value="<?php echo htmlspecialchars($_POST['title'] ?? $campaign['title']); ?>"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-primary-500 focus:border-primary-500">
                            </div>
                            
                            <div>
                                <label for="product_name" class="block text-sm font-medium text-gray-700 mb-1">
                                    Product Name <span class="text-red-500">*</span>
                                </label>
                                <input type="text" id="product_name" name="product_name" required
                                       value="<?php echo htmlspecialchars($_POST['product_name'] ?? $campaign['product_name']); ?>"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-primary-500 focus:border-primary-500">
                            </div>
                        </div>
                        
                        <!-- Description -->
                        <div>
                            <label for="description" class="block text-sm font-medium text-gray-700 mb-1">
                                Campaign Description
                            </label>
                            <textarea id="description" name="description" rows="3"
                                      class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-primary-500 focus:border-primary-500"
                                      placeholder="Brief description of the campaign goals and messaging"><?php echo htmlspecialchars($_POST['description'] ?? $campaign['description']); ?></textarea>
                        </div>
                        
                        <!-- Platforms Selection -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-3">
                                Target Platforms <span class="text-red-500">*</span>
                            </label>
                            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                                <?php 
                                $available_platforms = ['Meta', 'TikTok', 'Snapchat', 'WhatsApp', 'Google', 'Direct Call', 'Website', 'Other'];
                                $selected_platforms = $_POST['platforms'] ?? json_decode($campaign['platforms'], true) ?? [];
                                ?>
                                <?php foreach ($available_platforms as $platform): ?>
                                    <label class="flex items-center p-3 border border-gray-200 rounded-lg hover:bg-gray-50 cursor-pointer">
                                        <input type="checkbox" name="platforms[]" value="<?php echo $platform; ?>" 
                                               class="h-4 w-4 text-primary-600 focus:ring-primary-500 border-gray-300 rounded"
                                               <?php echo in_array($platform, $selected_platforms) ? 'checked' : ''; ?>>
                                        <span class="ml-2 text-sm font-medium text-gray-900"><?php echo $platform; ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        
                        <!-- Budget and Timeline -->
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                            <div>
                                <label for="budget_qr" class="block text-sm font-medium text-gray-700 mb-1">
                                    Budget (QAR) <span class="text-red-500">*</span>
                                </label>
                                <div class="relative">
                                    <input type="number" id="budget_qr" name="budget_qr" required min="1" step="0.01"
                                           value="<?php echo htmlspecialchars($_POST['budget_qr'] ?? $campaign['budget_qr']); ?>"
                                           class="w-full pl-8 pr-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-primary-500 focus:border-primary-500">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <span class="text-gray-500 sm:text-sm">QAR</span>
                                    </div>
                                </div>
                            </div>
                            
                            <div>
                                <label for="start_date" class="block text-sm font-medium text-gray-700 mb-1">
                                    Start Date <span class="text-red-500">*</span>
                                </label>
                                <input type="date" id="start_date" name="start_date" required
                                       value="<?php echo htmlspecialchars($_POST['start_date'] ?? $campaign['start_date']); ?>"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-primary-500 focus:border-primary-500">
                            </div>
                            
                            <div>
                                <label for="end_date" class="block text-sm font-medium text-gray-700 mb-1">
                                    End Date <span class="text-red-500">*</span>
                                </label>
                                <input type="date" id="end_date" name="end_date" required
                                       value="<?php echo htmlspecialchars($_POST['end_date'] ?? $campaign['end_date']); ?>"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-primary-500 focus:border-primary-500">
                            </div>
                        </div>
                        
                        <!-- Target Audience -->
                        <div>
                            <label for="target_audience" class="block text-sm font-medium text-gray-700 mb-1">
                                Target Audience
                            </label>
                            <textarea id="target_audience" name="target_audience" rows="2"
                                      class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-primary-500 focus:border-primary-500"
                                      placeholder="e.g., Coffee enthusiasts aged 25-45 in Qatar, interested in premium products"><?php echo htmlspecialchars($_POST['target_audience'] ?? $campaign['target_audience']); ?></textarea>
                        </div>
                        
                        <!-- Campaign Objectives -->
                        <div>
                            <label for="objectives" class="block text-sm font-medium text-gray-700 mb-1">
                                Campaign Objectives
                            </label>
                            <textarea id="objectives" name="objectives" rows="2"
                                      class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-primary-500 focus:border-primary-500"
                                      placeholder="e.g., Generate 100 qualified leads, achieve 15% conversion rate"><?php echo htmlspecialchars($_POST['objectives'] ?? $campaign['objectives']); ?></textarea>
                        </div>
                        
                        <!-- Assignment and Status -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="assigned_to" class="block text-sm font-medium text-gray-700 mb-1">
                                    Assign to Marketing Member
                                </label>
                                <select id="assigned_to" name="assigned_to"
                                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-primary-500 focus:border-primary-500">
                                    <option value="">Assign later</option>
                                    <?php foreach ($marketing_users as $user): ?>
                                        <option value="<?php echo $user['id']; ?>" 
                                                <?php echo ($_POST['assigned_to'] ?? $campaign['assigned_to']) == $user['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($user['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div>
                                <label for="status" class="block text-sm font-medium text-gray-700 mb-1">
                                    Campaign Status
                                </label>
                                <select id="status" name="status"
                                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-primary-500 focus:border-primary-500">
                                    <option value="draft" <?php echo ($_POST['status'] ?? $campaign['status']) === 'draft' ? 'selected' : ''; ?>>Draft</option>
                                    <option value="active" <?php echo ($_POST['status'] ?? $campaign['status']) === 'active' ? 'selected' : ''; ?>>Active</option>
                                    <option value="paused" <?php echo ($_POST['status'] ?? $campaign['status']) === 'paused' ? 'selected' : ''; ?>>Paused</option>
                                    <option value="completed" <?php echo ($_POST['status'] ?? $campaign['status']) === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                    <option value="cancelled" <?php echo ($_POST['status'] ?? $campaign['status']) === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                </select>
                            </div>
                        </div>
                        
                        <!-- Campaign Performance Summary (Read-only) -->
                        <?php if ($campaign['leads_count'] > 0): ?>
                        <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                            <h4 class="text-sm font-medium text-gray-900 mb-3">📊 Current Performance</h4>
                            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 text-sm">
                                <div class="text-center p-2 bg-white rounded border">
                                    <div class="text-lg font-medium text-blue-600"><?php echo $campaign['leads_count']; ?></div>
                                    <div class="text-gray-500">Total Leads</div>
                                </div>
                                <div class="text-center p-2 bg-white rounded border">
                                    <div class="text-lg font-medium text-green-600"><?php echo $campaign['deals_count']; ?></div>
                                    <div class="text-gray-500">Closed Deals</div>
                                </div>
                                <div class="text-center p-2 bg-white rounded border">
                                    <div class="text-lg font-medium text-green-600">
                                        <?php echo $campaign['leads_count'] > 0 ? round(($campaign['deals_count'] / $campaign['leads_count']) * 100, 1) : 0; ?>%
                                    </div>
                                    <div class="text-gray-500">Conversion Rate</div>
                                </div>
                                <div class="text-center p-2 bg-white rounded border">
                                    <div class="text-lg font-medium text-green-600"><?php echo format_currency($campaign['revenue']); ?></div>
                                    <div class="text-gray-500">Total Revenue</div>
                                </div>
                            </div>
                            <div class="mt-3 text-xs text-gray-500 text-center">
                                ROI: <span class="font-medium <?php echo $campaign['revenue'] >= $campaign['budget_qr'] ? 'text-green-600' : 'text-red-600'; ?>">
                                    <?php echo $campaign['budget_qr'] > 0 ? round((($campaign['revenue'] - $campaign['budget_qr']) / $campaign['budget_qr']) * 100, 1) : 0; ?>%
                                </span>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Submit Buttons -->
                        <div class="flex justify-end space-x-3 pt-6 border-t border-gray-200">
                            <a href="campaigns.php" class="px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                                Cancel
                            </a>
                            <button type="submit" class="px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-primary-600 hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500">
                                Update Campaign
                            </button>
                        </div>
                    </form>
                </div>
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

// Form validation
document.addEventListener('DOMContentLoaded', function() {
    const form = document.querySelector('form[data-validate]');
    const inputs = {
        title: document.getElementById('title'),
        product_name: document.getElementById('product_name'),
        budget_qr: document.getElementById('budget_qr'),
        start_date: document.getElementById('start_date'),
        end_date: document.getElementById('end_date')
    };
    
    form.addEventListener('submit', function(e) {
        const selectedPlatforms = document.querySelectorAll('input[name="platforms[]"]:checked');
        if (selectedPlatforms.length === 0) {
            e.preventDefault();
            alert('Please select at least one platform for your campaign.');
            return false;
        }
        
        const startDate = new Date(inputs.start_date.value);
        const endDate = new Date(inputs.end_date.value);
        if (startDate >= endDate) {
            e.preventDefault();
            alert('End date must be after start date.');
            inputs.end_date.focus();
            return false;
        }
        
        const budget = parseFloat(inputs.budget_qr.value);
        if (budget <= 0) {
            e.preventDefault();
            alert('Budget must be greater than 0.');
            inputs.budget_qr.focus();
            return false;
        }
    });
});

// Auto-resize textareas
document.querySelectorAll('textarea').forEach(function(textarea) {
    textarea.addEventListener('input', function() {
        this.style.height = 'auto';
        this.style.height = this.scrollHeight + 'px';
    });
});

// Show confirmation for status changes
document.getElementById('status').addEventListener('change', function() {
    const originalStatus = '<?php echo $campaign['status']; ?>';
    const newStatus = this.value;
    
    if (originalStatus === 'active' && newStatus === 'cancelled') {
        if (!confirm('Are you sure you want to cancel this campaign? This will stop all lead generation activities.')) {
            this.value = originalStatus;
        }
    } else if (originalStatus === 'completed' && newStatus !== 'completed') {
        if (!confirm('Are you sure you want to change the status of a completed campaign?')) {
            this.value = originalStatus;
        }
    }
});
</script>

<?php
include '../templates/footer.php';

// Helper functions
function get_campaign_by_id($campaign_id) {
    global $db;
    
    $stmt = $db->prepare("
        SELECT c.*, 
               creator.name as created_by_name,
               assigned.name as assigned_to_name,
               COUNT(l.id) as leads_count,
               COUNT(CASE WHEN l.status = 'closed-won' THEN 1 END) as deals_count,
               COALESCE(SUM(CASE WHEN l.status = 'closed-won' THEN l.sale_value_qr END), 0) as revenue
        FROM campaigns c
        LEFT JOIN users creator ON c.created_by = creator.id
        LEFT JOIN users assigned ON c.assigned_to = assigned.id
        LEFT JOIN leads l ON c.id = l.campaign_id
        WHERE c.id = ?
        GROUP BY c.id, c.title, c.product_name, c.description, c.platforms, c.budget_qr, 
                 c.target_audience, c.objectives, c.created_by, c.assigned_to, 
                 c.start_date, c.end_date, c.status, c.created_at, c.updated_at,
                 creator.name, assigned.name
    ");
    $stmt->bind_param("i", $campaign_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    return $result->num_rows > 0 ? $result->fetch_assoc() : null;
}

function update_campaign($campaign_id, $title, $product_name, $description, $platforms, $budget_qr, $target_audience, $objectives, $start_date, $end_date, $assigned_to, $status) {
    global $db;
    
    try {
        $platforms_json = json_encode($platforms);
        
        // If no assignment, set to null
        $assigned_to = $assigned_to > 0 ? $assigned_to : null;
        
        $stmt = $db->prepare("
            UPDATE campaigns SET 
                title = ?, product_name = ?, description = ?, platforms = ?, budget_qr = ?, 
                target_audience = ?, objectives = ?, assigned_to = ?, 
                start_date = ?, end_date = ?, status = ?, updated_at = NOW()
            WHERE id = ?
        ");
        
        $stmt->bind_param("ssssdssisss", 
            $title, $product_name, $description, $platforms_json, $budget_qr,
            $target_audience, $objectives, $assigned_to,
            $start_date, $end_date, $status, $campaign_id
        );
        
        if ($stmt->execute()) {
            return ['success' => true, 'message' => 'Campaign updated successfully'];
        } else {
            return ['success' => false, 'message' => 'Failed to update campaign'];
        }
        
    } catch (Exception $e) {
        log_error("Campaign update error: " . $e->getMessage());
        return ['success' => false, 'message' => 'Database error occurred'];
    }
}

function get_marketing_users() {
    global $db;
    
    $stmt = $db->prepare("SELECT id, name FROM users WHERE (role = 'marketing' OR role = 'admin') AND status = 'active' ORDER BY name");
    $stmt->execute();
    $result = $stmt->get_result();
    
    $users = [];
    while ($row = $result->fetch_assoc()) {
        $users[] = $row;
    }
    
    return $users;
}
?>
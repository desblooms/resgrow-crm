<?php
// Resgrow CRM - Lead Detail Management
// Detailed view and management of individual leads

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
$lead_id = $_GET['id'] ?? null;
$action = $_GET['action'] ?? 'view';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'update_lead':
                $stmt = $db->prepare("UPDATE leads SET full_name = ?, phone = ?, email = ?, platform = ?, product = ?, notes = ?, status = ?, sale_value_qr = ?, lead_quality = ?, next_follow_up = ? WHERE id = ?");
                $stmt->bind_param("sssssssdssi", 
                    $_POST['full_name'], $_POST['phone'], $_POST['email'], $_POST['platform'], 
                    $_POST['product'], $_POST['notes'], $_POST['status'], $_POST['sale_value_qr'], 
                    $_POST['lead_quality'], $_POST['next_follow_up'], $lead_id);
                
                if ($stmt->execute()) {
                    log_activity($user_id, 'lead_updated', "Updated lead ID: {$lead_id}");
                    set_flash_message('success', 'Lead updated successfully');
                } else {
                    set_flash_message('error', 'Failed to update lead');
                }
                break;
                
            case 'add_interaction':
                $stmt = $db->prepare("INSERT INTO lead_interactions (lead_id, user_id, interaction_type, subject, content, duration_minutes, outcome, next_action, scheduled_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("iisssisss", 
                    $lead_id, $user_id, $_POST['interaction_type'], $_POST['subject'], 
                    $_POST['content'], $_POST['duration_minutes'], $_POST['outcome'], 
                    $_POST['next_action'], $_POST['scheduled_at']);
                
                if ($stmt->execute()) {
                    // Update lead's last contact date
                    $update_stmt = $db->prepare("UPDATE leads SET last_contact_date = NOW() WHERE id = ?");
                    $update_stmt->bind_param("i", $lead_id);
                    $update_stmt->execute();
                    
                    log_activity($user_id, 'interaction_added', "Added interaction for lead ID: {$lead_id}");
                    set_flash_message('success', 'Interaction added successfully');
                } else {
                    set_flash_message('error', 'Failed to add interaction');
                }
                break;
                
            case 'create_lead':
                $stmt = $db->prepare("INSERT INTO leads (full_name, phone, email, campaign_id, platform, product, notes, assigned_to, status, sale_value_qr, lead_source, lead_quality, next_follow_up) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("sssississssss", 
                    $_POST['full_name'], $_POST['phone'], $_POST['email'], $_POST['campaign_id'], 
                    $_POST['platform'], $_POST['product'], $_POST['notes'], $user_id, 
                    $_POST['status'], $_POST['sale_value_qr'], $_POST['lead_source'], 
                    $_POST['lead_quality'], $_POST['next_follow_up']);
                
                if ($stmt->execute()) {
                    $new_lead_id = $db->lastInsertId();
                    log_activity($user_id, 'lead_created', "Created lead ID: {$new_lead_id}");
                    set_flash_message('success', 'Lead created successfully');
                    header("Location: lead-detail.php?id={$new_lead_id}");
                    exit();
                } else {
                    set_flash_message('error', 'Failed to create lead');
                }
                break;
        }
    }
}

// Get lead data if viewing/editing existing lead
$lead = null;
$interactions = [];
if ($lead_id && $action !== 'new') {
    $lead_sql = "SELECT l.*, c.title as campaign_title, u.name as assigned_to_name 
                 FROM leads l 
                 LEFT JOIN campaigns c ON l.campaign_id = c.id 
                 LEFT JOIN users u ON l.assigned_to = u.id 
                 WHERE l.id = ?";
    
    if ($user_role === 'sales') {
        $lead_sql .= " AND l.assigned_to = ?";
    }
    
    $stmt = $db->prepare($lead_sql);
    if ($user_role === 'sales') {
        $stmt->bind_param("ii", $lead_id, $user_id);
    } else {
        $stmt->bind_param("i", $lead_id);
    }
    $stmt->execute();
    $lead = $stmt->get_result()->fetch_assoc();
    
    if (!$lead) {
        set_flash_message('error', 'Lead not found or access denied');
        header('Location: leads.php');
        exit();
    }
    
    // Get interactions for this lead
    $interactions_sql = "SELECT li.*, u.name as user_name 
                         FROM lead_interactions li 
                         JOIN users u ON li.user_id = u.id 
                         WHERE li.lead_id = ? 
                         ORDER BY li.created_at DESC";
    $stmt = $db->prepare($interactions_sql);
    $stmt->bind_param("i", $lead_id);
    $stmt->execute();
    $interactions = $stmt->get_result();
}

// Get available campaigns for dropdown
$campaigns_sql = "SELECT id, title FROM campaigns WHERE status = 'active' ORDER BY title";
$campaigns = $db->query($campaigns_sql);

// Define options
$platforms = ['Meta', 'TikTok', 'Snapchat', 'WhatsApp', 'Google', 'Direct Call', 'Website', 'Other'];
$statuses = ['new', 'contacted', 'interested', 'follow-up', 'closed-won', 'closed-lost', 'no-response'];
$lead_qualities = ['hot', 'warm', 'cold'];
$interaction_types = ['call', 'email', 'whatsapp', 'meeting', 'note'];
$outcomes = ['successful', 'no_answer', 'callback_requested', 'not_interested', 'interested'];

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
                        <h1 class="text-3xl font-bold text-gray-900">
                            <?php if ($action === 'new'): ?>
                                Add New Lead
                            <?php else: ?>
                                Lead Details - <?php echo htmlspecialchars($lead['full_name'] ?? 'Unknown'); ?>
                            <?php endif; ?>
                        </h1>
                        <div class="flex items-center mt-2 space-x-4">
                            <a href="leads.php" class="text-blue-600 hover:text-blue-800">← Back to Leads</a>
                            <?php if ($lead): ?>
                            <span class="text-gray-500">Lead ID: <?php echo $lead['id']; ?></span>
                            <span class="text-gray-500">Created: <?php echo time_ago($lead['created_at']); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php if ($lead): ?>
                    <div class="flex space-x-3">
                        <a href="tel:<?php echo $lead['phone']; ?>" 
                           class="bg-green-600 text-white px-4 py-2 rounded-md hover:bg-green-700 transition duration-200">
                            📞 Call
                        </a>
                        <?php if ($lead['email']): ?>
                        <a href="mailto:<?php echo $lead['email']; ?>" 
                           class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 transition duration-200">
                            📧 Email
                        </a>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Flash Messages -->
            <?php foreach (get_flash_messages() as $message): ?>
            <div class="mb-4">
                <?php echo show_alert($message['type'], $message['message']); ?>
            </div>
            <?php endforeach; ?>

            <?php if ($action === 'new' || !$lead): ?>
            <!-- New Lead Form -->
            <div class="bg-white shadow rounded-lg">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900">Lead Information</h3>
                </div>
                <form method="POST" class="p-6">
                    <input type="hidden" name="action" value="create_lead">
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="full_name" class="block text-sm font-medium text-gray-700">Full Name *</label>
                            <input type="text" id="full_name" name="full_name" required
                                   class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                        </div>
                        
                        <div>
                            <label for="phone" class="block text-sm font-medium text-gray-700">Phone *</label>
                            <input type="tel" id="phone" name="phone" required
                                   class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                        </div>
                        
                        <div>
                            <label for="email" class="block text-sm font-medium text-gray-700">Email</label>
                            <input type="email" id="email" name="email"
                                   class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                        </div>
                        
                        <div>
                            <label for="platform" class="block text-sm font-medium text-gray-700">Platform *</label>
                            <select id="platform" name="platform" required
                                    class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                <option value="">Select Platform</option>
                                <?php foreach ($platforms as $platform): ?>
                                <option value="<?php echo $platform; ?>"><?php echo $platform; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div>
                            <label for="campaign_id" class="block text-sm font-medium text-gray-700">Campaign</label>
                            <select id="campaign_id" name="campaign_id"
                                    class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                <option value="">No Campaign</option>
                                <?php while ($campaign = $campaigns->fetch_assoc()): ?>
                                <option value="<?php echo $campaign['id']; ?>"><?php echo htmlspecialchars($campaign['title']); ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        
                        <div>
                            <label for="product" class="block text-sm font-medium text-gray-700">Product</label>
                            <input type="text" id="product" name="product"
                                   class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                        </div>
                        
                        <div>
                            <label for="status" class="block text-sm font-medium text-gray-700">Status</label>
                            <select id="status" name="status"
                                    class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                <?php foreach ($statuses as $status): ?>
                                <option value="<?php echo $status; ?>" <?php echo $status === 'new' ? 'selected' : ''; ?>>
                                    <?php echo ucfirst(str_replace('-', ' ', $status)); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div>
                            <label for="lead_quality" class="block text-sm font-medium text-gray-700">Lead Quality</label>
                            <select id="lead_quality" name="lead_quality"
                                    class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                <?php foreach ($lead_qualities as $quality): ?>
                                <option value="<?php echo $quality; ?>" <?php echo $quality === 'warm' ? 'selected' : ''; ?>>
                                    <?php echo ucfirst($quality); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div>
                            <label for="sale_value_qr" class="block text-sm font-medium text-gray-700">Sale Value (QAR)</label>
                            <input type="number" step="0.01" id="sale_value_qr" name="sale_value_qr"
                                   class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                        </div>
                        
                        <div>
                            <label for="next_follow_up" class="block text-sm font-medium text-gray-700">Next Follow-up</label>
                            <input type="datetime-local" id="next_follow_up" name="next_follow_up"
                                   class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                        </div>
                        
                        <div>
                            <label for="lead_source" class="block text-sm font-medium text-gray-700">Lead Source</label>
                            <input type="text" id="lead_source" name="lead_source"
                                   class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                        </div>
                    </div>
                    
                    <div class="mt-6">
                        <label for="notes" class="block text-sm font-medium text-gray-700">Notes</label>
                        <textarea id="notes" name="notes" rows="4"
                                  class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"></textarea>
                    </div>
                    
                    <div class="mt-6 flex justify-end space-x-3">
                        <a href="leads.php" 
                           class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-200 rounded-md hover:bg-gray-300">
                            Cancel
                        </a>
                        <button type="submit" 
                                class="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-md hover:bg-blue-700">
                            Create Lead
                        </button>
                    </div>
                </form>
            </div>

            <?php else: ?>
            <!-- Lead Details and Edit Form -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                <!-- Lead Information -->
                <div class="lg:col-span-2 space-y-6">
                    <!-- Lead Details Form -->
                    <div class="bg-white shadow rounded-lg">
                        <div class="px-6 py-4 border-b border-gray-200">
                            <div class="flex justify-between items-center">
                                <h3 class="text-lg font-medium text-gray-900">Lead Information</h3>
                                <div class="flex items-center space-x-2">
                                    <?php echo get_status_badge($lead['status']); ?>
                                    <?php if ($lead['lead_quality']): ?>
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium 
                                                 <?php echo $lead['lead_quality'] === 'hot' ? 'bg-red-100 text-red-800' : 
                                                          ($lead['lead_quality'] === 'warm' ? 'bg-yellow-100 text-yellow-800' : 'bg-blue-100 text-blue-800'); ?>">
                                        <?php echo ucfirst($lead['lead_quality']); ?> Lead
                                    </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <form method="POST" class="p-6">
                            <input type="hidden" name="action" value="update_lead">
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label for="full_name" class="block text-sm font-medium text-gray-700">Full Name *</label>
                                    <input type="text" id="full_name" name="full_name" value="<?php echo htmlspecialchars($lead['full_name']); ?>" required
                                           class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                </div>
                                
                                <div>
                                    <label for="phone" class="block text-sm font-medium text-gray-700">Phone *</label>
                                    <input type="tel" id="phone" name="phone" value="<?php echo htmlspecialchars($lead['phone']); ?>" required
                                           class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                </div>
                                
                                <div>
                                    <label for="email" class="block text-sm font-medium text-gray-700">Email</label>
                                    <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($lead['email'] ?? ''); ?>"
                                           class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                </div>
                                
                                <div>
                                    <label for="platform" class="block text-sm font-medium text-gray-700">Platform *</label>
                                    <select id="platform" name="platform" required
                                            class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                        <?php foreach ($platforms as $platform): ?>
                                        <option value="<?php echo $platform; ?>" <?php echo $lead['platform'] === $platform ? 'selected' : ''; ?>>
                                            <?php echo $platform; ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div>
                                    <label for="product" class="block text-sm font-medium text-gray-700">Product</label>
                                    <input type="text" id="product" name="product" value="<?php echo htmlspecialchars($lead['product'] ?? ''); ?>"
                                           class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                </div>
                                
                                <div>
                                    <label for="status" class="block text-sm font-medium text-gray-700">Status</label>
                                    <select id="status" name="status"
                                            class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                        <?php foreach ($statuses as $status): ?>
                                        <option value="<?php echo $status; ?>" <?php echo $lead['status'] === $status ? 'selected' : ''; ?>>
                                            <?php echo ucfirst(str_replace('-', ' ', $status)); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div>
                                    <label for="lead_quality" class="block text-sm font-medium text-gray-700">Lead Quality</label>
                                    <select id="lead_quality" name="lead_quality"
                                            class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                        <?php foreach ($lead_qualities as $quality): ?>
                                        <option value="<?php echo $quality; ?>" <?php echo $lead['lead_quality'] === $quality ? 'selected' : ''; ?>>
                                            <?php echo ucfirst($quality); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div>
                                    <label for="sale_value_qr" class="block text-sm font-medium text-gray-700">Sale Value (QAR)</label>
                                    <input type="number" step="0.01" id="sale_value_qr" name="sale_value_qr" 
                                           value="<?php echo $lead['sale_value_qr']; ?>"
                                           class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                </div>
                                
                                <div>
                                    <label for="next_follow_up" class="block text-sm font-medium text-gray-700">Next Follow-up</label>
                                    <input type="datetime-local" id="next_follow_up" name="next_follow_up" 
                                           value="<?php echo $lead['next_follow_up'] ? date('Y-m-d\TH:i', strtotime($lead['next_follow_up'])) : ''; ?>"
                                           class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                </div>
                            </div>
                            
                            <div class="mt-6">
                                <label for="notes" class="block text-sm font-medium text-gray-700">Notes</label>
                                <textarea id="notes" name="notes" rows="4"
                                          class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"><?php echo htmlspecialchars($lead['notes'] ?? ''); ?></textarea>
                            </div>
                            
                            <div class="mt-6 flex justify-end">
                                <button type="submit" 
                                        class="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-md hover:bg-blue-700">
                                    Update Lead
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- Interactions -->
                    <div class="bg-white shadow rounded-lg">
                        <div class="px-6 py-4 border-b border-gray-200">
                            <h3 class="text-lg font-medium text-gray-900">Interactions History</h3>
                        </div>
                        <div class="p-6">
                            <div class="space-y-4">
                                <?php while ($interaction = $interactions->fetch_assoc()): ?>
                                <div class="border-l-4 border-blue-400 pl-4 py-2">
                                    <div class="flex justify-between items-start">
                                        <div>
                                            <h4 class="text-sm font-medium text-gray-900">
                                                <?php echo ucfirst($interaction['interaction_type']); ?>
                                                <?php if ($interaction['subject']): ?>
                                                - <?php echo htmlspecialchars($interaction['subject']); ?>
                                                <?php endif; ?>
                                            </h4>
                                            <p class="text-sm text-gray-600 mt-1"><?php echo htmlspecialchars($interaction['content']); ?></p>
                                            <?php if ($interaction['outcome']): ?>
                                            <p class="text-xs text-gray-500 mt-1">
                                                Outcome: <?php echo ucfirst(str_replace('_', ' ', $interaction['outcome'])); ?>
                                            </p>
                                            <?php endif; ?>
                                        </div>
                                        <div class="text-right">
                                            <div class="text-xs text-gray-500"><?php echo htmlspecialchars($interaction['user_name']); ?></div>
                                            <div class="text-xs text-gray-500"><?php echo time_ago($interaction['created_at']); ?></div>
                                        </div>
                                    </div>
                                </div>
                                <?php endwhile; ?>
                                
                                <?php if ($interactions->num_rows === 0): ?>
                                <p class="text-gray-500 text-center py-4">No interactions recorded yet</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Sidebar -->
                <div class="space-y-6">
                    <!-- Lead Summary -->
                    <div class="bg-white shadow rounded-lg p-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Lead Summary</h3>
                        <dl class="space-y-2">
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Campaign</dt>
                                <dd class="text-sm text-gray-900"><?php echo htmlspecialchars($lead['campaign_title'] ?? 'No Campaign'); ?></dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Assigned To</dt>
                                <dd class="text-sm text-gray-900"><?php echo htmlspecialchars($lead['assigned_to_name'] ?? 'Unassigned'); ?></dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Created</dt>
                                <dd class="text-sm text-gray-900"><?php echo date('M j, Y g:i A', strtotime($lead['created_at'])); ?></dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Last Updated</dt>
                                <dd class="text-sm text-gray-900"><?php echo time_ago($lead['updated_at']); ?></dd>
                            </div>
                            <?php if ($lead['last_contact_date']): ?>
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Last Contact</dt>
                                <dd class="text-sm text-gray-900"><?php echo time_ago($lead['last_contact_date']); ?></dd>
                            </div>
                            <?php endif; ?>
                        </dl>
                    </div>

                    <!-- Add Interaction -->
                    <div class="bg-white shadow rounded-lg">
                        <div class="px-6 py-4 border-b border-gray-200">
                            <h3 class="text-lg font-medium text-gray-900">Add Interaction</h3>
                        </div>
                        <form method="POST" class="p-6">
                            <input type="hidden" name="action" value="add_interaction">
                            
                            <div class="space-y-4">
                                <div>
                                    <label for="interaction_type" class="block text-sm font-medium text-gray-700">Type</label>
                                    <select id="interaction_type" name="interaction_type" required
                                            class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                        <?php foreach ($interaction_types as $type): ?>
                                        <option value="<?php echo $type; ?>"><?php echo ucfirst($type); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div>
                                    <label for="subject" class="block text-sm font-medium text-gray-700">Subject</label>
                                    <input type="text" id="subject" name="subject"
                                           class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                </div>
                                
                                <div>
                                    <label for="content" class="block text-sm font-medium text-gray-700">Content</label>
                                    <textarea id="content" name="content" rows="3" required
                                              class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"></textarea>
                                </div>
                                
                                <div>
                                    <label for="outcome" class="block text-sm font-medium text-gray-700">Outcome</label>
                                    <select id="outcome" name="outcome"
                                            class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                        <option value="">Select Outcome</option>
                                        <?php foreach ($outcomes as $outcome): ?>
                                        <option value="<?php echo $outcome; ?>"><?php echo ucfirst(str_replace('_', ' ', $outcome)); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div>
                                    <label for="duration_minutes" class="block text-sm font-medium text-gray-700">Duration (minutes)</label>
                                    <input type="number" id="duration_minutes" name="duration_minutes" min="0"
                                           class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                </div>
                            </div>
                            
                            <div class="mt-6">
                                <button type="submit" 
                                        class="w-full bg-blue-600 text-white py-2 px-4 rounded-md hover:bg-blue-700 transition duration-200">
                                    Add Interaction
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
// Auto-format phone number
document.getElementById('phone').addEventListener('input', function(e) {
    let value = e.target.value.replace(/\D/g, '');
    if (value.length >= 8) {
        if (value.startsWith('974')) {
            value = '+' + value;
        } else if (!value.startsWith('+974')) {
            value = '+974' + value.slice(-8);
        }
    }
    e.target.value = value;
});

// Auto-save draft (could be implemented)
let formData = {};
const inputs = document.querySelectorAll('input, select, textarea');
inputs.forEach(input => {
    input.addEventListener('change', function() {
        formData[this.name] = this.value;
        localStorage.setItem('lead_draft_<?php echo $lead_id ?? "new"; ?>', JSON.stringify(formData));
    });
});

// Load draft on page load
window.addEventListener('load', function() {
    const draft = localStorage.getItem('lead_draft_<?php echo $lead_id ?? "new"; ?>');
    if (draft && window.location.search.includes('action=new')) {
        const data = JSON.parse(draft);
        Object.keys(data).forEach(key => {
            const input = document.querySelector(`[name="${key}"]`);
            if (input && !input.value) {
                input.value = data[key];
            }
        });
    }
});
</script>

<?php include_once '../templates/footer.php'; ?>
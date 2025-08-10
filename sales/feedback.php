<?php
// Resgrow CRM - Lead Feedback System
// Phase 8: Lead Feedback System

require_once '../includes/session.php';
require_once '../includes/functions.php';
require_once '../includes/db.php';

// Require sales or admin role
if (!SessionManager::hasRole('sales') && !SessionManager::hasRole('admin')) {
    header('Location: ../public/dashboard.php');
    exit();
}

$page_title = 'Lead Feedback System';
$user_id = SessionManager::getUserId();
$user_role = SessionManager::getRole();

// Handle feedback submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $lead_id = $_POST['lead_id'] ?? null;
    $feedback_type = $_POST['feedback_type'] ?? '';
    $reason_text = $_POST['reason_text'] ?? '';
    $follow_up_required = isset($_POST['follow_up_required']) ? 1 : 0;
    $follow_up_date = $_POST['follow_up_date'] ?? null;
    
    if ($lead_id && $feedback_type && $reason_text) {
        try {
            global $db;
            
            // Check if lead exists and user has access
            $lead_check = $db->prepare("SELECT id, assigned_to FROM leads WHERE id = ?");
            $lead_check->bind_param("i", $lead_id);
            $lead_check->execute();
            $lead = $lead_check->get_result()->fetch_assoc();
            
            if (!$lead) {
                set_flash_message('error', 'Lead not found');
            } elseif ($user_role === 'sales' && $lead['assigned_to'] != $user_id) {
                set_flash_message('error', 'Access denied to this lead');
            } else {
                // Insert feedback
                $stmt = $db->prepare("INSERT INTO lead_feedback (lead_id, sales_id, feedback_type, reason_text, follow_up_required, follow_up_date) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("iissis", $lead_id, $user_id, $feedback_type, $reason_text, $follow_up_required, $follow_up_date);
                
                if ($stmt->execute()) {
                    // Update lead status to closed-lost
                    $update_stmt = $db->prepare("UPDATE leads SET status = 'closed-lost' WHERE id = ?");
                    $update_stmt->bind_param("i", $lead_id);
                    $update_stmt->execute();
                    
                    // Log activity
                    log_activity($user_id, 'feedback_submitted', "Submitted feedback for lead ID: {$lead_id}");
                    set_flash_message('success', 'Feedback submitted successfully');
                    
                    header('Location: feedback.php');
                    exit();
                } else {
                    set_flash_message('error', 'Failed to submit feedback');
                }
            }
        } catch (Exception $e) {
            set_flash_message('error', 'Error submitting feedback: ' . $e->getMessage());
        }
    } else {
        set_flash_message('error', 'Please fill in all required fields');
    }
}

// Get leads that need feedback (closed-lost without feedback or assigned to current user)
try {
    global $db;
    
    if ($user_role === 'admin') {
        $leads_query = "
            SELECT l.*, c.title as campaign_title, u.name as assigned_to_name,
                   lf.reason_text as existing_feedback
            FROM leads l
            LEFT JOIN campaigns c ON l.campaign_id = c.id
            LEFT JOIN users u ON l.assigned_to = u.id
            LEFT JOIN lead_feedback lf ON l.id = lf.lead_id
            WHERE l.status = 'closed-lost' AND lf.id IS NULL
            ORDER BY l.updated_at DESC
        ";
        $leads = $db->query($leads_query);
    } else {
        $leads_query = "
            SELECT l.*, c.title as campaign_title, u.name as assigned_to_name,
                   lf.reason_text as existing_feedback
            FROM leads l
            LEFT JOIN campaigns c ON l.campaign_id = c.id
            LEFT JOIN users u ON l.assigned_to = u.id
            LEFT JOIN lead_feedback lf ON l.id = lf.lead_id
            WHERE l.assigned_to = ? AND l.status = 'closed-lost' AND lf.id IS NULL
            ORDER BY l.updated_at DESC
        ";
        $stmt = $db->prepare($leads_query);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $leads = $stmt->get_result();
    }
    
    // Get feedback history
    if ($user_role === 'admin') {
        $feedback_query = "
            SELECT lf.*, l.full_name, l.phone, l.platform, c.title as campaign_title, u.name as sales_name
            FROM lead_feedback lf
            JOIN leads l ON lf.lead_id = l.id
            LEFT JOIN campaigns c ON l.campaign_id = c.id
            JOIN users u ON lf.sales_id = u.id
            ORDER BY lf.submitted_at DESC
            LIMIT 50
        ";
        $feedback_history = $db->query($feedback_query);
    } else {
        $feedback_query = "
            SELECT lf.*, l.full_name, l.phone, l.platform, c.title as campaign_title, u.name as sales_name
            FROM lead_feedback lf
            JOIN leads l ON lf.lead_id = l.id
            LEFT JOIN campaigns c ON l.campaign_id = c.id
            JOIN users u ON lf.sales_id = u.id
            WHERE lf.sales_id = ?
            ORDER BY lf.submitted_at DESC
            LIMIT 50
        ";
        $stmt = $db->prepare($feedback_query);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $feedback_history = $stmt->get_result();
    }
    
} catch (Exception $e) {
    set_flash_message('error', 'Error loading data: ' . $e->getMessage());
    $leads = [];
    $feedback_history = [];
}

$feedback_types = ['not_interested', 'budget_issue', 'competitor', 'timing', 'other'];

include '../templates/header.php';
?>

<div class="min-h-screen bg-gray-50">
    <!-- Navigation -->
    <?php include '../templates/nav-sales.php'; ?>
    
    <div class="py-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <!-- Page Header -->
            <div class="mb-8">
                <h1 class="text-3xl font-bold text-gray-900">Lead Feedback System</h1>
                <p class="mt-2 text-sm text-gray-600">
                    Submit reasons for not closing sales and track feedback history
                </p>
            </div>

            <!-- Flash Messages -->
            <?php include '../templates/flash-messages.php'; ?>

            <!-- Feedback Submission Section -->
            <div class="bg-white shadow rounded-lg mb-8">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h2 class="text-lg font-medium text-gray-900">Submit Lead Feedback</h2>
                </div>
                <div class="p-6">
                    <form method="POST" action="" class="space-y-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="lead_id" class="block text-sm font-medium text-gray-700">Select Lead</label>
                                <select id="lead_id" name="lead_id" required
                                        class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                    <option value="">Choose a lead...</option>
                                    <?php if ($leads && $leads->num_rows > 0): ?>
                                        <?php while ($lead = $leads->fetch_assoc()): ?>
                                            <option value="<?php echo $lead['id']; ?>">
                                                <?php echo htmlspecialchars($lead['full_name'] . ' - ' . $lead['phone'] . ' (' . $lead['platform'] . ')'); ?>
                                            </option>
                                        <?php endwhile; ?>
                                    <?php endif; ?>
                                </select>
                            </div>
                            
                            <div>
                                <label for="feedback_type" class="block text-sm font-medium text-gray-700">Feedback Type</label>
                                <select id="feedback_type" name="feedback_type" required
                                        class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                    <option value="">Select feedback type...</option>
                                    <option value="not_interested">Not Interested</option>
                                    <option value="budget_issue">Budget Issue</option>
                                    <option value="competitor">Competitor</option>
                                    <option value="timing">Timing Issue</option>
                                    <option value="other">Other</option>
                                </select>
                            </div>
                        </div>
                        
                        <div>
                            <label for="reason_text" class="block text-sm font-medium text-gray-700">Reason for Not Closing</label>
                            <textarea id="reason_text" name="reason_text" rows="4" required
                                      class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                                      placeholder="Please provide detailed reason for not closing this sale..."></textarea>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="flex items-center">
                                <input type="checkbox" id="follow_up_required" name="follow_up_required" value="1"
                                       class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                <label for="follow_up_required" class="ml-2 block text-sm text-gray-900">
                                    Follow-up required
                                </label>
                            </div>
                            
                            <div>
                                <label for="follow_up_date" class="block text-sm font-medium text-gray-700">Follow-up Date</label>
                                <input type="datetime-local" id="follow_up_date" name="follow_up_date"
                                       class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                            </div>
                        </div>
                        
                        <div class="flex justify-end">
                            <button type="submit" 
                                    class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                                Submit Feedback
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Feedback History -->
            <div class="bg-white shadow rounded-lg">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h2 class="text-lg font-medium text-gray-900">Feedback History</h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Lead</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Platform</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Campaign</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Feedback Type</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Reason</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Sales Agent</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php if ($feedback_history && $feedback_history->num_rows > 0): ?>
                                <?php while ($feedback = $feedback_history->fetch_assoc()): ?>
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($feedback['full_name']); ?></div>
                                            <div class="text-sm text-gray-500"><?php echo htmlspecialchars($feedback['phone']); ?></div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            <?php echo htmlspecialchars($feedback['platform']); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            <?php echo htmlspecialchars($feedback['campaign_title'] ?? 'N/A'); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full 
                                                <?php 
                                                switch($feedback['feedback_type']) {
                                                    case 'not_interested': echo 'bg-red-100 text-red-800'; break;
                                                    case 'budget_issue': echo 'bg-yellow-100 text-yellow-800'; break;
                                                    case 'competitor': echo 'bg-orange-100 text-orange-800'; break;
                                                    case 'timing': echo 'bg-blue-100 text-blue-800'; break;
                                                    default: echo 'bg-gray-100 text-gray-800';
                                                }
                                                ?>">
                                                <?php echo ucfirst(str_replace('_', ' ', $feedback['feedback_type'])); ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 text-sm text-gray-900">
                                            <div class="max-w-xs truncate" title="<?php echo htmlspecialchars($feedback['reason_text']); ?>">
                                                <?php echo htmlspecialchars($feedback['reason_text']); ?>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            <?php echo htmlspecialchars($feedback['sales_name']); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <?php echo date('M j, Y g:i A', strtotime($feedback['submitted_at'])); ?>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="px-6 py-4 text-center text-sm text-gray-500">
                                        No feedback history found
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Show/hide follow-up date based on checkbox
    const followUpCheckbox = document.getElementById('follow_up_required');
    const followUpDate = document.getElementById('follow_up_date');
    
    followUpCheckbox.addEventListener('change', function() {
        followUpDate.disabled = !this.checked;
        if (!this.checked) {
            followUpDate.value = '';
        }
    });
    
    // Initialize state
    followUpDate.disabled = !followUpCheckbox.checked;
});
</script>

<?php include '../templates/footer.php'; ?>

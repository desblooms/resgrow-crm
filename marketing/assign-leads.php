<?php
// Resgrow CRM - Assign Leads
// Marketing team functionality to assign leads to sales team

require_once '../includes/session.php';
require_once '../includes/functions.php';
require_once '../includes/db.php';

// Require marketing or admin role
if (!SessionManager::hasRole('marketing') && !SessionManager::hasRole('admin')) {
    header('Location: ../public/dashboard.php');
    exit();
}

$page_title = 'Assign Leads';
$user_id = SessionManager::getUserId();
$user_role = SessionManager::getRole();

// Handle lead assignment
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $lead_id = $_POST['lead_id'] ?? null;
    $assigned_to = $_POST['assigned_to'] ?? null;
    $action = $_POST['action'] ?? '';
    
    if ($action === 'assign' && $lead_id && $assigned_to) {
        try {
            global $db;
            
            // Check if lead exists and user has access
            $lead_check = $db->prepare("SELECT l.id, l.assigned_to, c.created_by FROM leads l LEFT JOIN campaigns c ON l.campaign_id = c.id WHERE l.id = ?");
            $lead_check->bind_param("i", $lead_id);
            $lead_check->execute();
            $lead = $lead_check->get_result()->fetch_assoc();
            
            if (!$lead) {
                set_flash_message('error', 'Lead not found');
            } elseif ($user_role === 'marketing' && $lead['created_by'] != $user_id) {
                set_flash_message('error', 'Access denied to this lead');
            } else {
                // Update lead assignment
                $stmt = $db->prepare("UPDATE leads SET assigned_to = ?, updated_at = NOW() WHERE id = ?");
                $stmt->bind_param("ii", $assigned_to, $lead_id);
                
                if ($stmt->execute()) {
                    // Log activity
                    log_activity($user_id, 'lead_assigned', "Assigned lead ID: {$lead_id} to user ID: {$assigned_to}");
                    set_flash_message('success', 'Lead assigned successfully');
                    
                    header('Location: assign-leads.php');
                    exit();
                } else {
                    set_flash_message('error', 'Failed to assign lead');
                }
            }
        } catch (Exception $e) {
            set_flash_message('error', 'Error assigning lead: ' . $e->getMessage());
        }
    } elseif ($action === 'bulk_assign') {
        $lead_ids = $_POST['lead_ids'] ?? [];
        $assigned_to = $_POST['assigned_to'] ?? null;
        
        if (!empty($lead_ids) && $assigned_to) {
            try {
                global $db;
                
                $success_count = 0;
                foreach ($lead_ids as $lead_id) {
                    $stmt = $db->prepare("UPDATE leads SET assigned_to = ?, updated_at = NOW() WHERE id = ?");
                    $stmt->bind_param("ii", $assigned_to, $lead_id);
                    
                    if ($stmt->execute()) {
                        $success_count++;
                        log_activity($user_id, 'lead_assigned', "Bulk assigned lead ID: {$lead_id} to user ID: {$assigned_to}");
                    }
                }
                
                set_flash_message('success', "Successfully assigned {$success_count} leads");
                header('Location: assign-leads.php');
                exit();
            } catch (Exception $e) {
                set_flash_message('error', 'Error in bulk assignment: ' . $e->getMessage());
            }
        } else {
            set_flash_message('error', 'Please select leads and assignee');
        }
    }
}

// Get unassigned leads
try {
    global $db;
    
    if ($user_role === 'admin') {
        $leads_query = "
            SELECT l.*, c.title as campaign_title, u.name as assigned_to_name
            FROM leads l
            LEFT JOIN campaigns c ON l.campaign_id = c.id
            LEFT JOIN users u ON l.assigned_to = u.id
            WHERE l.assigned_to IS NULL OR l.assigned_to = 0
            ORDER BY l.created_at DESC
        ";
        $leads = $db->query($leads_query);
    } else {
        $leads_query = "
            SELECT l.*, c.title as campaign_title, u.name as assigned_to_name
            FROM leads l
            LEFT JOIN campaigns c ON l.campaign_id = c.id
            LEFT JOIN users u ON l.assigned_to = u.id
            WHERE (l.assigned_to IS NULL OR l.assigned_to = 0)
            AND (c.created_by = ? OR l.assigned_to = ?)
            ORDER BY l.created_at DESC
        ";
        $stmt = $db->prepare($leads_query);
        $stmt->bind_param("ii", $user_id, $user_id);
        $stmt->execute();
        $leads = $stmt->get_result();
    }
    
    // Get sales team members
    $sales_query = "SELECT id, name FROM users WHERE role = 'sales' AND status = 'active' ORDER BY name";
    $sales_team = $db->query($sales_query);
    
} catch (Exception $e) {
    set_flash_message('error', 'Error loading data: ' . $e->getMessage());
    $leads = [];
    $sales_team = [];
}

include '../templates/header.php';
?>

<div class="min-h-screen bg-gray-50">
    <!-- Navigation -->
    <?php include '../templates/nav-marketing.php'; ?>
    
    <div class="py-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <!-- Page Header -->
            <div class="mb-8">
                <h1 class="text-3xl font-bold text-gray-900">Assign Leads</h1>
                <p class="mt-2 text-sm text-gray-600">
                    Assign unassigned leads to sales team members
                </p>
            </div>

            <!-- Flash Messages -->
            <?php include '../templates/flash-messages.php'; ?>

            <!-- Bulk Assignment Section -->
            <div class="bg-white shadow rounded-lg mb-8">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h2 class="text-lg font-medium text-gray-900">Bulk Assignment</h2>
                </div>
                <div class="p-6">
                    <form method="POST" action="" id="bulkAssignForm">
                        <input type="hidden" name="action" value="bulk_assign">
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="bulk_assigned_to" class="block text-sm font-medium text-gray-700">Assign to Sales Member</label>
                                <select id="bulk_assigned_to" name="assigned_to" required
                                        class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                    <option value="">Select sales member...</option>
                                    <?php if ($sales_team && $sales_team->num_rows > 0): ?>
                                        <?php while ($member = $sales_team->fetch_assoc()): ?>
                                            <option value="<?php echo $member['id']; ?>">
                                                <?php echo htmlspecialchars($member['name']); ?>
                                            </option>
                                        <?php endwhile; ?>
                                    <?php endif; ?>
                                </select>
                            </div>
                            
                            <div class="flex items-end">
                                <button type="submit" id="bulkAssignBtn" disabled
                                        class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed">
                                    Assign Selected Leads
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Leads List -->
            <div class="bg-white shadow rounded-lg">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h2 class="text-lg font-medium text-gray-900">Unassigned Leads</h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    <input type="checkbox" id="selectAll" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Lead</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Platform</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Campaign</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Created</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php if ($leads && $leads->num_rows > 0): ?>
                                <?php while ($lead = $leads->fetch_assoc()): ?>
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <input type="checkbox" name="lead_ids[]" value="<?php echo $lead['id']; ?>" 
                                                   class="lead-checkbox rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($lead['full_name']); ?></div>
                                            <div class="text-sm text-gray-500"><?php echo htmlspecialchars($lead['phone']); ?></div>
                                            <?php if ($lead['email']): ?>
                                                <div class="text-sm text-gray-500"><?php echo htmlspecialchars($lead['email']); ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            <?php echo htmlspecialchars($lead['platform']); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            <?php echo htmlspecialchars($lead['campaign_title'] ?? 'N/A'); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full 
                                                <?php 
                                                switch($lead['status']) {
                                                    case 'new': echo 'bg-green-100 text-green-800'; break;
                                                    case 'contacted': echo 'bg-blue-100 text-blue-800'; break;
                                                    case 'interested': echo 'bg-yellow-100 text-yellow-800'; break;
                                                    case 'follow-up': echo 'bg-orange-100 text-orange-800'; break;
                                                    case 'closed-won': echo 'bg-green-100 text-green-800'; break;
                                                    case 'closed-lost': echo 'bg-red-100 text-red-800'; break;
                                                    default: echo 'bg-gray-100 text-gray-800';
                                                }
                                                ?>">
                                                <?php echo ucfirst(str_replace('-', ' ', $lead['status'])); ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <?php echo date('M j, Y g:i A', strtotime($lead['created_at'])); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                            <button type="button" onclick="assignLead(<?php echo $lead['id']; ?>)"
                                                    class="text-blue-600 hover:text-blue-900">
                                                Assign
                                            </button>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="px-6 py-4 text-center text-sm text-gray-500">
                                        No unassigned leads found
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

<!-- Assignment Modal -->
<div id="assignmentModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden">
    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
        <div class="mt-3">
            <h3 class="text-lg font-medium text-gray-900 mb-4">Assign Lead</h3>
            <form id="assignmentForm" method="POST" action="">
                <input type="hidden" name="action" value="assign">
                <input type="hidden" id="modal_lead_id" name="lead_id">
                
                <div class="mb-4">
                    <label for="modal_assigned_to" class="block text-sm font-medium text-gray-700">Assign to Sales Member</label>
                    <select id="modal_assigned_to" name="assigned_to" required
                            class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                        <option value="">Select sales member...</option>
                        <?php 
                        if ($sales_team && $sales_team->num_rows > 0) {
                            $sales_team->data_seek(0);
                            while ($member = $sales_team->fetch_assoc()): 
                        ?>
                            <option value="<?php echo $member['id']; ?>">
                                <?php echo htmlspecialchars($member['name']); ?>
                            </option>
                        <?php 
                            endwhile;
                        }
                        ?>
                    </select>
                </div>
                
                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="closeModal()"
                            class="bg-gray-300 text-gray-700 px-4 py-2 rounded-md hover:bg-gray-400">
                        Cancel
                    </button>
                    <button type="submit"
                            class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700">
                        Assign
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Select all functionality
    const selectAll = document.getElementById('selectAll');
    const leadCheckboxes = document.querySelectorAll('.lead-checkbox');
    const bulkAssignBtn = document.getElementById('bulkAssignBtn');
    
    if (selectAll) {
        selectAll.addEventListener('change', function() {
            leadCheckboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
            });
            updateBulkAssignButton();
        });
    }
    
    // Individual checkbox functionality
    leadCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            updateBulkAssignButton();
            
            // Update select all checkbox
            const allChecked = Array.from(leadCheckboxes).every(cb => cb.checked);
            const someChecked = Array.from(leadCheckboxes).some(cb => cb.checked);
            
            if (selectAll) {
                selectAll.checked = allChecked;
                selectAll.indeterminate = someChecked && !allChecked;
            }
        });
    });
    
    function updateBulkAssignButton() {
        const checkedBoxes = document.querySelectorAll('.lead-checkbox:checked');
        const assignedTo = document.getElementById('bulk_assigned_to').value;
        
        bulkAssignBtn.disabled = checkedBoxes.length === 0 || !assignedTo;
    }
    
    // Bulk assign form
    const bulkAssignForm = document.getElementById('bulkAssignForm');
    if (bulkAssignForm) {
        bulkAssignForm.addEventListener('submit', function(e) {
            const checkedBoxes = document.querySelectorAll('.lead-checkbox:checked');
            if (checkedBoxes.length === 0) {
                e.preventDefault();
                alert('Please select at least one lead to assign');
                return;
            }
            
            const assignedTo = document.getElementById('bulk_assigned_to').value;
            if (!assignedTo) {
                e.preventDefault();
                alert('Please select a sales member to assign to');
                return;
            }
        });
    }
    
    // Update bulk assign button when assigned_to changes
    const bulkAssignedTo = document.getElementById('bulk_assigned_to');
    if (bulkAssignedTo) {
        bulkAssignedTo.addEventListener('change', updateBulkAssignButton);
    }
});

function assignLead(leadId) {
    document.getElementById('modal_lead_id').value = leadId;
    document.getElementById('assignmentModal').classList.remove('hidden');
}

function closeModal() {
    document.getElementById('assignmentModal').classList.add('hidden');
}

// Close modal when clicking outside
document.getElementById('assignmentModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeModal();
    }
});
</script>

<?php include '../templates/footer.php'; ?>

<?php
// Resgrow CRM - Data Export Tool
// Export leads, campaigns, and analytics data

require_once '../config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

check_login();
if ($_SESSION['user_role'] !== 'admin' && $_SESSION['user_role'] !== 'marketing') {
    header('Location: ../public/dashboard.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'];

// Handle export requests
if (isset($_GET['action']) && $_GET['action'] === 'export') {
    $export_type = $_GET['type'] ?? 'leads';
    $format = $_GET['format'] ?? 'csv';
    $date_from = $_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
    $date_to = $_GET['date_to'] ?? date('Y-m-d');
    
    switch ($export_type) {
        case 'leads':
            exportLeads($db, $user_role, $user_id, $format, $date_from, $date_to);
            break;
        case 'campaigns':
            exportCampaigns($db, $user_role, $user_id, $format, $date_from, $date_to);
            break;
        case 'analytics':
            exportAnalytics($db, $user_role, $user_id, $format, $date_from, $date_to);
            break;
        case 'interactions':
            exportInteractions($db, $user_role, $user_id, $format, $date_from, $date_to);
            break;
        default:
            set_flash_message('error', 'Invalid export type');
    }
}

function exportLeads($db, $user_role, $user_id, $format, $date_from, $date_to) {
    $sql = "SELECT l.*, c.title as campaign_title, u.name as assigned_to_name 
            FROM leads l 
            LEFT JOIN campaigns c ON l.campaign_id = c.id 
            LEFT JOIN users u ON l.assigned_to = u.id 
            WHERE l.created_at >= ? AND l.created_at <= ?";
    
    $params = [$date_from . ' 00:00:00', $date_to . ' 23:59:59'];
    $types = 'ss';
    
    if ($user_role === 'sales') {
        $sql .= " AND l.assigned_to = ?";
        $params[] = $user_id;
        $types .= 'i';
    } elseif ($user_role === 'marketing') {
        $sql .= " AND (c.created_by = ? OR l.assigned_to = ?)";
        $params[] = $user_id;
        $params[] = $user_id;
        $types .= 'ii';
    }
    
    $sql .= " ORDER BY l.created_at DESC";
    
    $stmt = $db->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $filename = "leads_export_" . date('Y-m-d_H-i-s');
    
    if ($format === 'csv') {
        outputCSV($result, $filename, [
            'id' => 'ID',
            'full_name' => 'Full Name',
            'phone' => 'Phone',
            'email' => 'Email',
            'campaign_title' => 'Campaign',
            'platform' => 'Platform',
            'product' => 'Product',
            'status' => 'Status',
            'lead_quality' => 'Quality',
            'sale_value_qr' => 'Sale Value (QAR)',
            'lead_source' => 'Source',
            'assigned_to_name' => 'Assigned To',
            'notes' => 'Notes',
            'last_contact_date' => 'Last Contact',
            'next_follow_up' => 'Next Follow-up',
            'created_at' => 'Created Date',
            'updated_at' => 'Updated Date'
        ]);
    } else {
        outputJSON($result, $filename);
    }
    
    // Log export activity
    log_activity($user_id, 'leads_exported', "Exported leads data ({$format})");
}

function exportCampaigns($db, $user_role, $user_id, $format, $date_from, $date_to) {
    $sql = "SELECT c.*, 
                   u1.name as created_by_name,
                   u2.name as assigned_to_name,
                   COUNT(l.id) as total_leads,
                   COUNT(CASE WHEN l.status = 'closed-won' THEN 1 END) as won_leads,
                   SUM(CASE WHEN l.status = 'closed-won' THEN l.sale_value_qr ELSE 0 END) as total_revenue
            FROM campaigns c 
            LEFT JOIN users u1 ON c.created_by = u1.id 
            LEFT JOIN users u2 ON c.assigned_to = u2.id 
            LEFT JOIN leads l ON c.id = l.campaign_id
            WHERE c.created_at >= ? AND c.created_at <= ?";
    
    $params = [$date_from . ' 00:00:00', $date_to . ' 23:59:59'];
    $types = 'ss';
    
    if ($user_role === 'marketing') {
        $sql .= " AND c.created_by = ?";
        $params[] = $user_id;
        $types .= 'i';
    }
    
    $sql .= " GROUP BY c.id ORDER BY c.created_at DESC";
    
    $stmt = $db->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $filename = "campaigns_export_" . date('Y-m-d_H-i-s');
    
    if ($format === 'csv') {
        outputCSV($result, $filename, [
            'id' => 'ID',
            'title' => 'Title',
            'product_name' => 'Product',
            'description' => 'Description',
            'platforms' => 'Platforms',
            'budget_qr' => 'Budget (QAR)',
            'target_audience' => 'Target Audience',
            'objectives' => 'Objectives',
            'created_by_name' => 'Created By',
            'assigned_to_name' => 'Assigned To',
            'status' => 'Status',
            'start_date' => 'Start Date',
            'end_date' => 'End Date',
            'total_leads' => 'Total Leads',
            'won_leads' => 'Won Leads',
            'total_revenue' => 'Total Revenue (QAR)',
            'created_at' => 'Created Date',
            'updated_at' => 'Updated Date'
        ]);
    } else {
        outputJSON($result, $filename);
    }
    
    log_activity($user_id, 'campaigns_exported', "Exported campaigns data ({$format})");
}

function exportAnalytics($db, $user_role, $user_id, $format, $date_from, $date_to) {
    // Get comprehensive analytics data
    $analytics = [];
    
    // Overall statistics
    $stats_sql = "SELECT 
                    COUNT(DISTINCT l.id) as total_leads,
                    COUNT(DISTINCT CASE WHEN l.status = 'closed-won' THEN l.id END) as won_leads,
                    SUM(CASE WHEN l.status = 'closed-won' THEN l.sale_value_qr ELSE 0 END) as total_revenue,
                    AVG(CASE WHEN l.status = 'closed-won' THEN l.sale_value_qr END) as avg_deal_size,
                    COUNT(DISTINCT c.id) as total_campaigns
                  FROM leads l 
                  LEFT JOIN campaigns c ON l.campaign_id = c.id
                  WHERE l.created_at >= ? AND l.created_at <= ?";
    
    $params = [$date_from . ' 00:00:00', $date_to . ' 23:59:59'];
    $types = 'ss';
    
    if ($user_role === 'sales') {
        $stats_sql .= " AND l.assigned_to = ?";
        $params[] = $user_id;
        $types .= 'i';
    } elseif ($user_role === 'marketing') {
        $stats_sql .= " AND (c.created_by = ? OR l.assigned_to = ?)";
        $params[] = $user_id;
        $params[] = $user_id;
        $types .= 'ii';
    }
    
    $stmt = $db->prepare($stats_sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $overall_stats = $stmt->get_result()->fetch_assoc();
    
    // Platform breakdown
    $platform_sql = "SELECT platform, 
                             COUNT(*) as total_leads,
                             COUNT(CASE WHEN status = 'closed-won' THEN 1 END) as won_leads,
                             SUM(CASE WHEN status = 'closed-won' THEN sale_value_qr ELSE 0 END) as revenue
                      FROM leads l 
                      WHERE l.created_at >= ? AND l.created_at <= ?";
    
    if ($user_role === 'sales') {
        $platform_sql .= " AND l.assigned_to = ?";
    } elseif ($user_role === 'marketing') {
        $platform_sql .= " AND l.campaign_id IN (SELECT id FROM campaigns WHERE created_by = ?)";
    }
    
    $platform_sql .= " GROUP BY platform ORDER BY total_leads DESC";
    
    $stmt = $db->prepare($platform_sql);
    if ($user_role !== 'admin') {
        $stmt->bind_param($types, ...$params);
    } else {
        $stmt->bind_param('ss', $date_from . ' 00:00:00', $date_to . ' 23:59:59');
    }
    $stmt->execute();
    $platform_stats = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    // Daily trends
    $daily_sql = "SELECT DATE(created_at) as date,
                         COUNT(*) as leads,
                         COUNT(CASE WHEN status = 'closed-won' THEN 1 END) as won_leads,
                         SUM(CASE WHEN status = 'closed-won' THEN sale_value_qr ELSE 0 END) as revenue
                  FROM leads l 
                  WHERE l.created_at >= ? AND l.created_at <= ?";
    
    if ($user_role === 'sales') {
        $daily_sql .= " AND l.assigned_to = ?";
    } elseif ($user_role === 'marketing') {
        $daily_sql .= " AND l.campaign_id IN (SELECT id FROM campaigns WHERE created_by = ?)";
    }
    
    $daily_sql .= " GROUP BY DATE(created_at) ORDER BY date ASC";
    
    $stmt = $db->prepare($daily_sql);
    if ($user_role !== 'admin') {
        $stmt->bind_param($types, ...$params);
    } else {
        $stmt->bind_param('ss', $date_from . ' 00:00:00', $date_to . ' 23:59:59');
    }
    $stmt->execute();
    $daily_trends = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    $filename = "analytics_export_" . date('Y-m-d_H-i-s');
    
    if ($format === 'csv') {
        // Create a structured CSV with multiple sections
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
        
        $output = fopen('php://output', 'w');
        
        // Overall Statistics Section
        fputcsv($output, ['OVERALL STATISTICS']);
        fputcsv($output, ['Metric', 'Value']);
        fputcsv($output, ['Total Leads', $overall_stats['total_leads']]);
        fputcsv($output, ['Won Leads', $overall_stats['won_leads']]);
        fputcsv($output, ['Total Revenue (QAR)', number_format($overall_stats['total_revenue'], 2)]);
        fputcsv($output, ['Average Deal Size (QAR)', number_format($overall_stats['avg_deal_size'], 2)]);
        fputcsv($output, ['Total Campaigns', $overall_stats['total_campaigns']]);
        fputcsv($output, ['Conversion Rate (%)', $overall_stats['total_leads'] > 0 ? round(($overall_stats['won_leads'] / $overall_stats['total_leads']) * 100, 2) : 0]);
        fputcsv($output, []);
        
        // Platform Statistics
        fputcsv($output, ['PLATFORM BREAKDOWN']);
        fputcsv($output, ['Platform', 'Total Leads', 'Won Leads', 'Revenue (QAR)', 'Conversion Rate (%)']);
        foreach ($platform_stats as $platform) {
            $conversion_rate = $platform['total_leads'] > 0 ? round(($platform['won_leads'] / $platform['total_leads']) * 100, 2) : 0;
            fputcsv($output, [
                $platform['platform'],
                $platform['total_leads'],
                $platform['won_leads'],
                number_format($platform['revenue'], 2),
                $conversion_rate
            ]);
        }
        fputcsv($output, []);
        
        // Daily Trends
        fputcsv($output, ['DAILY TRENDS']);
        fputcsv($output, ['Date', 'Leads', 'Won Leads', 'Revenue (QAR)']);
        foreach ($daily_trends as $day) {
            fputcsv($output, [
                $day['date'],
                $day['leads'],
                $day['won_leads'],
                number_format($day['revenue'], 2)
            ]);
        }
        
        fclose($output);
        exit();
    } else {
        $analytics_data = [
            'overall_statistics' => $overall_stats,
            'platform_breakdown' => $platform_stats,
            'daily_trends' => $daily_trends,
            'export_date' => date('Y-m-d H:i:s'),
            'date_range' => [
                'from' => $date_from,
                'to' => $date_to
            ]
        ];
        
        outputJSONData($analytics_data, $filename);
    }
    
    log_activity($user_id, 'analytics_exported', "Exported analytics data ({$format})");
}

function exportInteractions($db, $user_role, $user_id, $format, $date_from, $date_to) {
    $sql = "SELECT li.*, l.full_name as lead_name, l.phone as lead_phone, u.name as user_name
            FROM lead_interactions li
            JOIN leads l ON li.lead_id = l.id
            JOIN users u ON li.user_id = u.id
            WHERE li.created_at >= ? AND li.created_at <= ?";
    
    $params = [$date_from . ' 00:00:00', $date_to . ' 23:59:59'];
    $types = 'ss';
    
    if ($user_role === 'sales') {
        $sql .= " AND li.user_id = ?";
        $params[] = $user_id;
        $types .= 'i';
    } elseif ($user_role === 'marketing') {
        $sql .= " AND l.campaign_id IN (SELECT id FROM campaigns WHERE created_by = ?)";
        $params[] = $user_id;
        $types .= 'i';
    }
    
    $sql .= " ORDER BY li.created_at DESC";
    
    $stmt = $db->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $filename = "interactions_export_" . date('Y-m-d_H-i-s');
    
    if ($format === 'csv') {
        outputCSV($result, $filename, [
            'id' => 'ID',
            'lead_name' => 'Lead Name',
            'lead_phone' => 'Lead Phone',
            'user_name' => 'User',
            'interaction_type' => 'Type',
            'subject' => 'Subject',
            'content' => 'Content',
            'duration_minutes' => 'Duration (min)',
            'outcome' => 'Outcome',
            'next_action' => 'Next Action',
            'scheduled_at' => 'Scheduled At',
            'created_at' => 'Created Date'
        ]);
    } else {
        outputJSON($result, $filename);
    }
    
    log_activity($user_id, 'interactions_exported', "Exported interactions data ({$format})");
}

function outputCSV($result, $filename, $columns) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    // Write header
    fputcsv($output, array_values($columns));
    
    // Write data
    while ($row = $result->fetch_assoc()) {
        $csv_row = [];
        foreach (array_keys($columns) as $key) {
            $value = $row[$key] ?? '';
            // Handle JSON fields
            if (in_array($key, ['platforms']) && $value) {
                $decoded = json_decode($value, true);
                $value = is_array($decoded) ? implode(', ', $decoded) : $value;
            }
            $csv_row[] = $value;
        }
        fputcsv($output, $csv_row);
    }
    
    fclose($output);
    exit();
}

function outputJSON($result, $filename) {
    $data = [];
    while ($row = $result->fetch_assoc()) {
        // Decode JSON fields
        if (isset($row['platforms']) && $row['platforms']) {
            $row['platforms'] = json_decode($row['platforms'], true);
        }
        $data[] = $row;
    }
    
    outputJSONData($data, $filename);
}

function outputJSONData($data, $filename) {
    header('Content-Type: application/json');
    header('Content-Disposition: attachment; filename="' . $filename . '.json"');
    
    echo json_encode($data, JSON_PRETTY_PRINT);
    exit();
}

include_once '../templates/header.php';
?>

<div class="min-h-screen bg-gray-50">
    <?php 
    if ($_SESSION['user_role'] === 'admin') {
        include_once '../templates/nav-admin.php';
    } else {
        include_once '../templates/nav-marketing.php';
    }
    ?>
    
    <div class="py-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <!-- Header -->
            <div class="mb-8">
                <h1 class="text-3xl font-bold text-gray-900">Data Export</h1>
                <p class="mt-2 text-gray-600">
                    Export your CRM data for analysis or backup
                </p>
            </div>

            <!-- Flash Messages -->
            <?php foreach (get_flash_messages() as $message): ?>
            <div class="mb-4">
                <?php echo show_alert($message['type'], $message['message']); ?>
            </div>
            <?php endforeach; ?>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                <!-- Export Form -->
                <div class="bg-white shadow rounded-lg">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h3 class="text-lg font-medium text-gray-900">Export Data</h3>
                    </div>
                    <form method="GET" class="p-6">
                        <input type="hidden" name="action" value="export">
                        
                        <div class="space-y-6">
                            <div>
                                <label for="type" class="block text-sm font-medium text-gray-700">Export Type</label>
                                <select id="type" name="type" required
                                        class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                    <option value="leads">Leads Data</option>
                                    <option value="campaigns">Campaigns Data</option>
                                    <option value="analytics">Analytics Report</option>
                                    <option value="interactions">Lead Interactions</option>
                                </select>
                            </div>
                            
                            <div>
                                <label for="format" class="block text-sm font-medium text-gray-700">Export Format</label>
                                <select id="format" name="format" required
                                        class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                    <option value="csv">CSV (Comma Separated Values)</option>
                                    <option value="json">JSON (JavaScript Object Notation)</option>
                                </select>
                            </div>
                            
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label for="date_from" class="block text-sm font-medium text-gray-700">From Date</label>
                                    <input type="date" id="date_from" name="date_from" 
                                           value="<?php echo date('Y-m-d', strtotime('-30 days')); ?>"
                                           class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                </div>
                                
                                <div>
                                    <label for="date_to" class="block text-sm font-medium text-gray-700">To Date</label>
                                    <input type="date" id="date_to" name="date_to" 
                                           value="<?php echo date('Y-m-d'); ?>"
                                           class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                </div>
                            </div>
                            
                            <button type="submit" 
                                    class="w-full bg-blue-600 text-white py-2 px-4 rounded-md hover:bg-blue-700 transition duration-200">
                                Export Data
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Export Information -->
                <div class="bg-white shadow rounded-lg">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h3 class="text-lg font-medium text-gray-900">Export Information</h3>
                    </div>
                    <div class="p-6">
                        <div class="space-y-6">
                            <!-- Export Types -->
                            <div>
                                <h4 class="text-md font-medium text-gray-900 mb-3">Available Export Types</h4>
                                <div class="space-y-3">
                                    <div class="bg-gray-50 p-3 rounded-md">
                                        <h5 class="font-medium text-gray-900">Leads Data</h5>
                                        <p class="text-sm text-gray-600">Complete lead information including contact details, status, assigned users, and campaign associations.</p>
                                    </div>
                                    
                                    <div class="bg-gray-50 p-3 rounded-md">
                                        <h5 class="font-medium text-gray-900">Campaigns Data</h5>
                                        <p class="text-sm text-gray-600">Campaign details with performance metrics, budget information, and associated leads count.</p>
                                    </div>
                                    
                                    <div class="bg-gray-50 p-3 rounded-md">
                                        <h5 class="font-medium text-gray-900">Analytics Report</h5>
                                        <p class="text-sm text-gray-600">Comprehensive analytics including overall statistics, platform breakdown, and daily trends.</p>
                                    </div>
                                    
                                    <div class="bg-gray-50 p-3 rounded-md">
                                        <h5 class="font-medium text-gray-900">Lead Interactions</h5>
                                        <p class="text-sm text-gray-600">All recorded interactions between users and leads including calls, emails, and meetings.</p>
                                    </div>
                                </div>
                            </div>

                            <!-- Format Information -->
                            <div>
                                <h4 class="text-md font-medium text-gray-900 mb-3">Export Formats</h4>
                                <div class="space-y-2">
                                    <div class="flex items-start">
                                        <div class="text-sm text-gray-600">
                                            <strong>CSV:</strong> Best for importing into spreadsheet applications like Excel or Google Sheets. Easy to read and manipulate.
                                        </div>
                                    </div>
                                    <div class="flex items-start">
                                        <div class="text-sm text-gray-600">
                                            <strong>JSON:</strong> Ideal for developers and API integrations. Preserves data structure and is machine-readable.
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Access Control -->
                            <div>
                                <h4 class="text-md font-medium text-gray-900 mb-3">Data Access</h4>
                                <div class="text-sm text-gray-600">
                                    <?php if ($_SESSION['user_role'] === 'admin'): ?>
                                    <p>As an administrator, you can export all data across the system.</p>
                                    <?php elseif ($_SESSION['user_role'] === 'marketing'): ?>
                                    <p>You can export data for campaigns you created and their associated leads.</p>
                                    <?php else: ?>
                                    <p>You can export data for leads assigned to you.</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Date validation
document.addEventListener('DOMContentLoaded', function() {
    const dateFrom = document.getElementById('date_from');
    const dateTo = document.getElementById('date_to');
    
    function validateDates() {
        if (dateFrom.value && dateTo.value) {
            if (new Date(dateFrom.value) > new Date(dateTo.value)) {
                dateTo.setCustomValidity('End date must be after start date');
            } else {
                dateTo.setCustomValidity('');
            }
        }
    }
    
    dateFrom.addEventListener('change', validateDates);
    dateTo.addEventListener('change', validateDates);
});

// Form submission handling
document.querySelector('form').addEventListener('submit', function(e) {
    const button = e.target.querySelector('button[type="submit"]');
    button.textContent = 'Exporting...';
    button.disabled = true;
    
    // Re-enable button after a delay in case of errors
    setTimeout(function() {
        button.textContent = 'Export Data';
        button.disabled = false;
    }, 5000);
});
</script>

<?php include_once '../templates/footer.php'; ?>

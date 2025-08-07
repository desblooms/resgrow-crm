<?php
// Resgrow CRM - Data Import Tool
// Import leads and campaign data from CSV files

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
$upload_dir = __DIR__ . '/uploads/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

// Handle file upload and processing
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] === UPLOAD_ERR_OK) {
        $import_type = $_POST['import_type'] ?? 'leads';
        $file_path = $upload_dir . basename($_FILES['csv_file']['name']);
        
        if (move_uploaded_file($_FILES['csv_file']['tmp_name'], $file_path)) {
            $result = processCSVImport($file_path, $import_type, $user_id, $db);
            set_flash_message($result['success'] ? 'success' : 'error', $result['message']);
            
            // Clean up uploaded file
            unlink($file_path);
        } else {
            set_flash_message('error', 'Failed to upload file');
        }
    }
}

function processCSVImport($file_path, $import_type, $user_id, $db) {
    try {
        $handle = fopen($file_path, 'r');
        if (!$handle) {
            return ['success' => false, 'message' => 'Could not open file'];
        }
        
        $headers = fgetcsv($handle);
        if (!$headers) {
            fclose($handle);
            return ['success' => false, 'message' => 'Invalid CSV format'];
        }
        
        $imported = 0;
        $errors = 0;
        $error_details = [];
        
        if ($import_type === 'leads') {
            $result = importLeads($handle, $headers, $user_id, $db);
        } elseif ($import_type === 'campaigns') {
            $result = importCampaigns($handle, $headers, $user_id, $db);
        } else {
            fclose($handle);
            return ['success' => false, 'message' => 'Invalid import type'];
        }
        
        fclose($handle);
        return $result;
        
    } catch (Exception $e) {
        error_log("Import error: " . $e->getMessage());
        return ['success' => false, 'message' => 'Import failed: ' . $e->getMessage()];
    }
}

function importLeads($handle, $headers, $user_id, $db) {
    $required_fields = ['full_name', 'phone', 'platform'];
    $optional_fields = ['email', 'campaign_id', 'product', 'notes', 'status', 'sale_value_qr', 'lead_source', 'lead_quality', 'next_follow_up'];
    
    // Map headers to database fields
    $field_map = [];
    foreach ($headers as $index => $header) {
        $header = strtolower(trim($header));
        $header = str_replace(' ', '_', $header);
        $field_map[$index] = $header;
    }
    
    // Check required fields
    foreach ($required_fields as $field) {
        if (!in_array($field, $field_map)) {
            return ['success' => false, 'message' => "Missing required field: {$field}"];
        }
    }
    
    $imported = 0;
    $errors = 0;
    $error_details = [];
    
    while (($row = fgetcsv($handle)) !== false) {
        try {
            $lead_data = [];
            foreach ($field_map as $index => $field) {
                $lead_data[$field] = isset($row[$index]) ? trim($row[$index]) : '';
            }
            
            // Validate required fields
            foreach ($required_fields as $field) {
                if (empty($lead_data[$field])) {
                    throw new Exception("Missing required field: {$field}");
                }
            }
            
            // Validate phone format
            if (!validate_phone($lead_data['phone'])) {
                throw new Exception("Invalid phone format: {$lead_data['phone']}");
            }
            
            // Validate email if provided
            if (!empty($lead_data['email']) && !validate_email($lead_data['email'])) {
                throw new Exception("Invalid email format: {$lead_data['email']}");
            }
            
            // Prepare data for insertion
            $sql = "INSERT INTO leads (full_name, phone, email, campaign_id, platform, product, notes, assigned_to, status, sale_value_qr, lead_source, lead_quality, next_follow_up, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
            
            $stmt = $db->prepare($sql);
            $stmt->bind_param("sssississssss",
                $lead_data['full_name'],
                $lead_data['phone'],
                $lead_data['email'] ?: null,
                $lead_data['campaign_id'] ?: null,
                $lead_data['platform'],
                $lead_data['product'] ?: null,
                $lead_data['notes'] ?: null,
                $user_id,
                $lead_data['status'] ?: 'new',
                $lead_data['sale_value_qr'] ?: null,
                $lead_data['lead_source'] ?: null,
                $lead_data['lead_quality'] ?: 'warm',
                $lead_data['next_follow_up'] ?: null
            );
            
            if ($stmt->execute()) {
                $imported++;
            } else {
                throw new Exception("Database error: " . $stmt->error);
            }
            
        } catch (Exception $e) {
            $errors++;
            $error_details[] = "Row " . ($imported + $errors) . ": " . $e->getMessage();
        }
    }
    
    // Log the import activity
    log_activity($user_id, 'leads_imported', "Imported {$imported} leads, {$errors} errors");
    
    $message = "Import completed: {$imported} leads imported";
    if ($errors > 0) {
        $message .= ", {$errors} errors occurred";
    }
    
    return ['success' => true, 'message' => $message, 'details' => $error_details];
}

function importCampaigns($handle, $headers, $user_id, $db) {
    $required_fields = ['title', 'product_name', 'start_date', 'end_date'];
    $optional_fields = ['description', 'platforms', 'budget_qr', 'target_audience', 'objectives', 'assigned_to', 'status'];
    
    // Map headers to database fields
    $field_map = [];
    foreach ($headers as $index => $header) {
        $header = strtolower(trim($header));
        $header = str_replace(' ', '_', $header);
        $field_map[$index] = $header;
    }
    
    // Check required fields
    foreach ($required_fields as $field) {
        if (!in_array($field, $field_map)) {
            return ['success' => false, 'message' => "Missing required field: {$field}"];
        }
    }
    
    $imported = 0;
    $errors = 0;
    $error_details = [];
    
    while (($row = fgetcsv($handle)) !== false) {
        try {
            $campaign_data = [];
            foreach ($field_map as $index => $field) {
                $campaign_data[$field] = isset($row[$index]) ? trim($row[$index]) : '';
            }
            
            // Validate required fields
            foreach ($required_fields as $field) {
                if (empty($campaign_data[$field])) {
                    throw new Exception("Missing required field: {$field}");
                }
            }
            
            // Validate dates
            if (strtotime($campaign_data['start_date']) >= strtotime($campaign_data['end_date'])) {
                throw new Exception("End date must be after start date");
            }
            
            // Process platforms field
            $platforms_json = null;
            if (!empty($campaign_data['platforms'])) {
                $platforms = explode(',', $campaign_data['platforms']);
                $platforms = array_map('trim', $platforms);
                $platforms_json = json_encode($platforms);
            }
            
            $sql = "INSERT INTO campaigns (title, product_name, description, platforms, budget_qr, target_audience, objectives, created_by, assigned_to, start_date, end_date, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
            
            $stmt = $db->prepare($sql);
            $stmt->bind_param("ssssdssiisss",
                $campaign_data['title'],
                $campaign_data['product_name'],
                $campaign_data['description'] ?: null,
                $platforms_json,
                $campaign_data['budget_qr'] ?: null,
                $campaign_data['target_audience'] ?: null,
                $campaign_data['objectives'] ?: null,
                $user_id,
                $campaign_data['assigned_to'] ?: null,
                $campaign_data['start_date'],
                $campaign_data['end_date'],
                $campaign_data['status'] ?: 'draft'
            );
            
            if ($stmt->execute()) {
                $imported++;
            } else {
                throw new Exception("Database error: " . $stmt->error);
            }
            
        } catch (Exception $e) {
            $errors++;
            $error_details[] = "Row " . ($imported + $errors) . ": " . $e->getMessage();
        }
    }
    
    // Log the import activity
    log_activity($user_id, 'campaigns_imported', "Imported {$imported} campaigns, {$errors} errors");
    
    $message = "Import completed: {$imported} campaigns imported";
    if ($errors > 0) {
        $message .= ", {$errors} errors occurred";
    }
    
    return ['success' => true, 'message' => $message, 'details' => $error_details];
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
                <h1 class="text-3xl font-bold text-gray-900">Data Import</h1>
                <p class="mt-2 text-gray-600">
                    Import leads and campaigns from CSV files
                </p>
            </div>

            <!-- Flash Messages -->
            <?php foreach (get_flash_messages() as $message): ?>
            <div class="mb-4">
                <?php echo show_alert($message['type'], $message['message']); ?>
            </div>
            <?php endforeach; ?>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                <!-- Import Form -->
                <div class="bg-white shadow rounded-lg">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h3 class="text-lg font-medium text-gray-900">Upload CSV File</h3>
                    </div>
                    <form method="POST" enctype="multipart/form-data" class="p-6">
                        <div class="space-y-6">
                            <div>
                                <label for="import_type" class="block text-sm font-medium text-gray-700">Import Type</label>
                                <select id="import_type" name="import_type" required
                                        class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                    <option value="leads">Leads</option>
                                    <option value="campaigns">Campaigns</option>
                                </select>
                            </div>
                            
                            <div>
                                <label for="csv_file" class="block text-sm font-medium text-gray-700">CSV File</label>
                                <input type="file" id="csv_file" name="csv_file" accept=".csv" required
                                       class="mt-1 block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                                <p class="mt-2 text-sm text-gray-500">Maximum file size: 5MB</p>
                            </div>
                            
                            <button type="submit" 
                                    class="w-full bg-blue-600 text-white py-2 px-4 rounded-md hover:bg-blue-700 transition duration-200">
                                Import Data
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Instructions -->
                <div class="bg-white shadow rounded-lg">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h3 class="text-lg font-medium text-gray-900">CSV Format Requirements</h3>
                    </div>
                    <div class="p-6">
                        <div class="space-y-6">
                            <!-- Leads Format -->
                            <div>
                                <h4 class="text-md font-medium text-gray-900 mb-3">Leads CSV Format</h4>
                                <div class="bg-gray-50 p-4 rounded-md">
                                    <p class="text-sm text-gray-700 mb-2"><strong>Required columns:</strong></p>
                                    <ul class="text-sm text-gray-600 list-disc list-inside space-y-1">
                                        <li><code>full_name</code> - Lead's full name</li>
                                        <li><code>phone</code> - Phone number (Qatar format preferred)</li>
                                        <li><code>platform</code> - Lead source platform</li>
                                    </ul>
                                    
                                    <p class="text-sm text-gray-700 mt-4 mb-2"><strong>Optional columns:</strong></p>
                                    <ul class="text-sm text-gray-600 list-disc list-inside space-y-1">
                                        <li><code>email</code> - Email address</li>
                                        <li><code>campaign_id</code> - Campaign ID</li>
                                        <li><code>product</code> - Product name</li>
                                        <li><code>notes</code> - Additional notes</li>
                                        <li><code>status</code> - Lead status (new, contacted, etc.)</li>
                                        <li><code>sale_value_qr</code> - Sale value in QAR</li>
                                        <li><code>lead_source</code> - Specific lead source</li>
                                        <li><code>lead_quality</code> - hot, warm, or cold</li>
                                        <li><code>next_follow_up</code> - Next follow-up date (YYYY-MM-DD HH:MM:SS)</li>
                                    </ul>
                                </div>
                            </div>

                            <!-- Campaigns Format -->
                            <div>
                                <h4 class="text-md font-medium text-gray-900 mb-3">Campaigns CSV Format</h4>
                                <div class="bg-gray-50 p-4 rounded-md">
                                    <p class="text-sm text-gray-700 mb-2"><strong>Required columns:</strong></p>
                                    <ul class="text-sm text-gray-600 list-disc list-inside space-y-1">
                                        <li><code>title</code> - Campaign title</li>
                                        <li><code>product_name</code> - Product being promoted</li>
                                        <li><code>start_date</code> - Start date (YYYY-MM-DD)</li>
                                        <li><code>end_date</code> - End date (YYYY-MM-DD)</li>
                                    </ul>
                                    
                                    <p class="text-sm text-gray-700 mt-4 mb-2"><strong>Optional columns:</strong></p>
                                    <ul class="text-sm text-gray-600 list-disc list-inside space-y-1">
                                        <li><code>description</code> - Campaign description</li>
                                        <li><code>platforms</code> - Comma-separated platforms</li>
                                        <li><code>budget_qr</code> - Budget in QAR</li>
                                        <li><code>target_audience</code> - Target audience description</li>
                                        <li><code>objectives</code> - Campaign objectives</li>
                                        <li><code>assigned_to</code> - Assigned user ID</li>
                                        <li><code>status</code> - Campaign status (draft, active, etc.)</li>
                                    </ul>
                                </div>
                            </div>

                            <!-- Sample Downloads -->
                            <div>
                                <h4 class="text-md font-medium text-gray-900 mb-3">Sample Templates</h4>
                                <div class="space-y-2">
                                    <a href="sample-templates/leads-template.csv" download
                                       class="inline-flex items-center px-3 py-2 border border-gray-300 shadow-sm text-sm leading-4 font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                        📄 Download Leads Template
                                    </a>
                                    <a href="sample-templates/campaigns-template.csv" download
                                       class="inline-flex items-center px-3 py-2 border border-gray-300 shadow-sm text-sm leading-4 font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                        📄 Download Campaigns Template
                                    </a>
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
// File validation
document.getElementById('csv_file').addEventListener('change', function(e) {
    const file = e.target.files[0];
    if (file) {
        const maxSize = 5 * 1024 * 1024; // 5MB
        if (file.size > maxSize) {
            alert('File is too large. Maximum size is 5MB.');
            e.target.value = '';
            return;
        }
        
        const allowedTypes = ['text/csv', 'application/csv'];
        if (!allowedTypes.includes(file.type) && !file.name.toLowerCase().endsWith('.csv')) {
            alert('Please select a CSV file.');
            e.target.value = '';
            return;
        }
    }
});

// Form validation
document.querySelector('form').addEventListener('submit', function(e) {
    const fileInput = document.getElementById('csv_file');
    const importType = document.getElementById('import_type');
    
    if (!fileInput.files[0]) {
        e.preventDefault();
        alert('Please select a CSV file to import.');
        return;
    }
    
    if (!importType.value) {
        e.preventDefault();
        alert('Please select an import type.');
        return;
    }
    
    // Show loading state
    const button = e.target.querySelector('button[type="submit"]');
    button.textContent = 'Importing...';
    button.disabled = true;
});
</script>

<?php include_once '../templates/footer.php'; ?>
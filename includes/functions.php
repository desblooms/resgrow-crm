<?php
// Resgrow CRM - Utility Functions
// Phase 1: Project Setup & Auth

// Security Functions
function sanitize_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

function validate_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

function validate_phone($phone) {
    // Remove spaces and special characters
    $phone = preg_replace('/[^0-9+]/', '', $phone);
    
    // Basic validation for Qatar numbers
    if (preg_match('/^(\+974|974|00974)?[3-9]\d{7}$/', $phone)) {
        return true;
    }
    
    return false;
}

function generate_csrf_token() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf_token($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Date & Time Functions
function format_date($date, $format = 'Y-m-d H:i:s') {
    return date($format, strtotime($date));
}

function time_ago($datetime) {
    $time = time() - strtotime($datetime);
    
    if ($time < 60) return 'just now';
    if ($time < 3600) return floor($time/60) . ' minutes ago';
    if ($time < 86400) return floor($time/3600) . ' hours ago';
    if ($time < 2592000) return floor($time/86400) . ' days ago';
    if ($time < 31536000) return floor($time/2592000) . ' months ago';
    
    return floor($time/31536000) . ' years ago';
}

// Currency Functions
function format_currency($amount, $currency = 'QAR') {
    return number_format($amount, 2) . ' ' . $currency;
}

function parse_currency($amount_string) {
    return floatval(preg_replace('/[^0-9.]/', '', $amount_string));
}

// Alert & Message Functions
function set_flash_message($type, $message) {
    $_SESSION['flash_messages'][] = [
        'type' => $type,
        'message' => $message
    ];
}

function get_flash_messages() {
    $messages = $_SESSION['flash_messages'] ?? [];
    unset($_SESSION['flash_messages']);
    return $messages;
}

function show_alert($type, $message) {
    $alert_classes = [
        'success' => 'bg-green-100 border-green-400 text-green-700',
        'error' => 'bg-red-100 border-red-400 text-red-700',
        'warning' => 'bg-yellow-100 border-yellow-400 text-yellow-700',
        'info' => 'bg-blue-100 border-blue-400 text-blue-700'
    ];
    
    $class = $alert_classes[$type] ?? $alert_classes['info'];
    
    return "<div class='border px-4 py-3 rounded mb-4 {$class}' role='alert'>
                <span class='block sm:inline'>{$message}</span>
            </div>";
}

// File Upload Functions
function upload_file($file, $upload_dir = '../data/uploads/') {
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    $allowed_types = ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx', 'xlsx', 'csv'];
    $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    if (!in_array($file_extension, $allowed_types)) {
        return ['success' => false, 'message' => 'File type not allowed'];
    }
    
    if ($file['size'] > 5 * 1024 * 1024) { // 5MB limit
        return ['success' => false, 'message' => 'File size too large (max 5MB)'];
    }
    
    $filename = uniqid() . '_' . basename($file['name']);
    $target_path = $upload_dir . $filename;
    
    if (move_uploaded_file($file['tmp_name'], $target_path)) {
        return [
            'success' => true, 
            'message' => 'File uploaded successfully',
            'filename' => $filename,
            'path' => $target_path
        ];
    } else {
        return ['success' => false, 'message' => 'File upload failed'];
    }
}

// Logging Functions
function log_error($message, $file = '../data/logs/error.log') {
    $log_dir = dirname($file);
    if (!is_dir($log_dir)) {
        mkdir($log_dir, 0755, true);
    }
    
    $timestamp = date('Y-m-d H:i:s');
    $log_entry = "[{$timestamp}] {$message}" . PHP_EOL;
    
    file_put_contents($file, $log_entry, FILE_APPEND | LOCK_EX);
}

function log_activity($user_id, $action, $details = '') {
    global $db;
    
    $stmt = $db->prepare("INSERT INTO activity_log (user_id, action, details, ip_address, created_at) VALUES (?, ?, ?, ?, NOW())");
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $stmt->bind_param("isss", $user_id, $action, $details, $ip_address);
    $stmt->execute();
}

// Pagination Functions
function paginate($total_records, $records_per_page = 20, $current_page = 1) {
    $total_pages = ceil($total_records / $records_per_page);
    $offset = ($current_page - 1) * $records_per_page;
    
    return [
        'total_records' => $total_records,
        'total_pages' => $total_pages,
        'current_page' => $current_page,
        'records_per_page' => $records_per_page,
        'offset' => $offset,
        'has_prev' => $current_page > 1,
        'has_next' => $current_page < $total_pages
    ];
}

// Role & Permission Functions
function get_role_display_name($role) {
    $roles = [
        'admin' => 'Administrator',
        'marketing' => 'Marketing Team',
        'sales' => 'Sales Team'
    ];
    
    return $roles[$role] ?? ucfirst($role);
}

function get_role_permissions($role) {
    $permissions = [
        'admin' => ['all'],
        'marketing' => ['campaigns', 'leads_assign', 'reports'],
        'sales' => ['leads_view', 'leads_update', 'feedback']
    ];
    
    return $permissions[$role] ?? [];
}

// Status Functions
function get_status_badge($status) {
    $badges = [
        'active' => 'bg-green-100 text-green-800',
        'inactive' => 'bg-red-100 text-red-800',
        'pending' => 'bg-yellow-100 text-yellow-800',
        'closed' => 'bg-blue-100 text-blue-800',
        'follow-up' => 'bg-purple-100 text-purple-800'
    ];
    
    $class = $badges[$status] ?? 'bg-gray-100 text-gray-800';
    
    return "<span class='px-2 inline-flex text-xs leading-5 font-semibold rounded-full {$class}'>
                " . ucfirst($status) . "
            </span>";
}
?>
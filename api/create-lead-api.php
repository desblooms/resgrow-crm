<?php
// Resgrow CRM - Create Lead API
// Phase 15: API & Integration Ready

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once '../config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON input']);
    exit();
}

// Validate required fields
$required_fields = ['full_name', 'phone', 'platform'];
$missing_fields = [];

foreach ($required_fields as $field) {
    if (empty($input[$field])) {
        $missing_fields[] = $field;
    }
}

if (!empty($missing_fields)) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing required fields: ' . implode(', ', $missing_fields)]);
    exit();
}

// Validate platform
$valid_platforms = ['Meta', 'TikTok', 'Snapchat', 'WhatsApp', 'Google', 'Direct Call', 'Website', 'Other'];
if (!in_array($input['platform'], $valid_platforms)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid platform. Must be one of: ' . implode(', ', $valid_platforms)]);
    exit();
}

// Validate phone number
if (!validate_phone($input['phone'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid phone number format']);
    exit();
}

// Validate email if provided
if (!empty($input['email']) && !validate_email($input['email'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid email format']);
    exit();
}

try {
    global $db;
    
    // Check if lead already exists (by phone)
    $check_stmt = $db->prepare("SELECT id FROM leads WHERE phone = ?");
    $check_stmt->bind_param("s", $input['phone']);
    $check_stmt->execute();
    $existing = $check_stmt->get_result()->fetch_assoc();
    
    if ($existing) {
        http_response_code(409);
        echo json_encode(['error' => 'Lead with this phone number already exists']);
        exit();
    }
    
    // Get campaign if campaign_id is provided
    $campaign_id = null;
    if (!empty($input['campaign_id'])) {
        $campaign_stmt = $db->prepare("SELECT id FROM campaigns WHERE id = ? AND status = 'active'");
        $campaign_stmt->bind_param("i", $input['campaign_id']);
        $campaign_stmt->execute();
        $campaign = $campaign_stmt->get_result()->fetch_assoc();
        
        if (!$campaign) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid campaign ID']);
            exit();
        }
        $campaign_id = $input['campaign_id'];
    }
    
    // Auto-assign to sales team if not specified
    $assigned_to = null;
    if (!empty($input['assigned_to'])) {
        $user_stmt = $db->prepare("SELECT id FROM users WHERE id = ? AND role = 'sales' AND status = 'active'");
        $user_stmt->bind_param("i", $input['assigned_to']);
        $user_stmt->execute();
        $user = $user_stmt->get_result()->fetch_assoc();
        
        if (!$user) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid assigned_to user ID']);
            exit();
        }
        $assigned_to = $input['assigned_to'];
    } else {
        // Auto-assign to a random active sales user
        $sales_query = "SELECT id FROM users WHERE role = 'sales' AND status = 'active' ORDER BY RAND() LIMIT 1";
        $sales_result = $db->query($sales_query);
        if ($sales_result && $sales_result->num_rows > 0) {
            $sales_user = $sales_result->fetch_assoc();
            $assigned_to = $sales_user['id'];
        }
    }
    
    // Insert the lead
    $stmt = $db->prepare("
        INSERT INTO leads (
            full_name, phone, email, campaign_id, platform, product, 
            notes, assigned_to, status, sale_value_qr, lead_source, 
            lead_quality, next_follow_up
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    $stmt->bind_param("sssississssss", 
        $input['full_name'],
        $input['phone'],
        $input['email'] ?? null,
        $campaign_id,
        $input['platform'],
        $input['product'] ?? null,
        $input['notes'] ?? null,
        $assigned_to,
        $input['status'] ?? 'new',
        $input['sale_value_qr'] ?? null,
        $input['lead_source'] ?? null,
        $input['lead_quality'] ?? 'warm',
        $input['next_follow_up'] ?? null
    );
    
    if ($stmt->execute()) {
        $lead_id = $db->lastInsertId();
        
        // Log the API activity
        log_activity(null, 'api_lead_created', "API created lead ID: {$lead_id} from platform: {$input['platform']}");
        
        // Get the created lead
        $lead_stmt = $db->prepare("
            SELECT l.*, c.title as campaign_title, u.name as assigned_to_name
            FROM leads l
            LEFT JOIN campaigns c ON l.campaign_id = c.id
            LEFT JOIN users u ON l.assigned_to = u.id
            WHERE l.id = ?
        ");
        $lead_stmt->bind_param("i", $lead_id);
        $lead_stmt->execute();
        $lead = $lead_stmt->get_result()->fetch_assoc();
        
        // Return success response
        http_response_code(201);
        echo json_encode([
            'success' => true,
            'message' => 'Lead created successfully',
            'lead_id' => $lead_id,
            'lead' => $lead
        ]);
        
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to create lead']);
    }
    
} catch (Exception $e) {
    error_log("API Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
}
?>

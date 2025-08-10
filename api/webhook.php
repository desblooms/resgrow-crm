<?php
// Resgrow CRM - Webhook Endpoint
// Phase 15: API & Integration Ready

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Hub-Signature');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once '../config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

// Webhook secret (should be configured in production)
$webhook_secret = 'resgrow_webhook_secret_2024';

// Verify webhook signature if provided
function verifyWebhookSignature($payload, $signature, $secret) {
    if (empty($signature)) {
        return true; // Skip verification if no signature provided
    }
    
    $expected_signature = 'sha256=' . hash_hmac('sha256', $payload, $secret);
    return hash_equals($expected_signature, $signature);
}

// Handle different webhook types
function processWebhook($data, $source) {
    global $db;
    
    switch ($source) {
        case 'meta':
            return processMetaWebhook($data);
        case 'tiktok':
            return processTikTokWebhook($data);
        case 'snapchat':
            return processSnapchatWebhook($data);
        case 'whatsapp':
            return processWhatsAppWebhook($data);
        default:
            return processGenericWebhook($data, $source);
    }
}

function processMetaWebhook($data) {
    global $db;
    
    // Extract lead information from Meta webhook
    $lead_data = [
        'full_name' => $data['name'] ?? $data['full_name'] ?? '',
        'phone' => $data['phone'] ?? $data['phone_number'] ?? '',
        'email' => $data['email'] ?? '',
        'platform' => 'Meta',
        'lead_source' => $data['source'] ?? 'Meta Ads',
        'notes' => json_encode($data),
        'status' => 'new'
    ];
    
    return createLeadFromWebhook($lead_data);
}

function processTikTokWebhook($data) {
    global $db;
    
    $lead_data = [
        'full_name' => $data['name'] ?? $data['full_name'] ?? '',
        'phone' => $data['phone'] ?? $data['phone_number'] ?? '',
        'email' => $data['email'] ?? '',
        'platform' => 'TikTok',
        'lead_source' => $data['source'] ?? 'TikTok Ads',
        'notes' => json_encode($data),
        'status' => 'new'
    ];
    
    return createLeadFromWebhook($lead_data);
}

function processSnapchatWebhook($data) {
    global $db;
    
    $lead_data = [
        'full_name' => $data['name'] ?? $data['full_name'] ?? '',
        'phone' => $data['phone'] ?? $data['phone_number'] ?? '',
        'email' => $data['email'] ?? '',
        'platform' => 'Snapchat',
        'lead_source' => $data['source'] ?? 'Snapchat Ads',
        'notes' => json_encode($data),
        'status' => 'new'
    ];
    
    return createLeadFromWebhook($lead_data);
}

function processWhatsAppWebhook($data) {
    global $db;
    
    $lead_data = [
        'full_name' => $data['name'] ?? $data['full_name'] ?? '',
        'phone' => $data['phone'] ?? $data['phone_number'] ?? '',
        'email' => $data['email'] ?? '',
        'platform' => 'WhatsApp',
        'lead_source' => $data['source'] ?? 'WhatsApp Business',
        'notes' => json_encode($data),
        'status' => 'new'
    ];
    
    return createLeadFromWebhook($lead_data);
}

function processGenericWebhook($data, $source) {
    global $db;
    
    $lead_data = [
        'full_name' => $data['name'] ?? $data['full_name'] ?? '',
        'phone' => $data['phone'] ?? $data['phone_number'] ?? '',
        'email' => $data['email'] ?? '',
        'platform' => 'Other',
        'lead_source' => $source,
        'notes' => json_encode($data),
        'status' => 'new'
    ];
    
    return createLeadFromWebhook($lead_data);
}

function createLeadFromWebhook($lead_data) {
    global $db;
    
    // Validate required fields
    if (empty($lead_data['full_name']) || empty($lead_data['phone'])) {
        return ['success' => false, 'error' => 'Missing required fields'];
    }
    
    // Check if lead already exists
    $check_stmt = $db->prepare("SELECT id FROM leads WHERE phone = ?");
    $check_stmt->bind_param("s", $lead_data['phone']);
    $check_stmt->execute();
    $existing = $check_stmt->get_result()->fetch_assoc();
    
    if ($existing) {
        return ['success' => false, 'error' => 'Lead already exists'];
    }
    
    // Auto-assign to sales team
    $sales_query = "SELECT id FROM users WHERE role = 'sales' AND status = 'active' ORDER BY RAND() LIMIT 1";
    $sales_result = $db->query($sales_query);
    $assigned_to = null;
    
    if ($sales_result && $sales_result->num_rows > 0) {
        $sales_user = $sales_result->fetch_assoc();
        $assigned_to = $sales_user['id'];
    }
    
    // Insert the lead
    $stmt = $db->prepare("
        INSERT INTO leads (
            full_name, phone, email, platform, lead_source, 
            notes, assigned_to, status, lead_quality
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    $stmt->bind_param("sssssssss", 
        $lead_data['full_name'],
        $lead_data['phone'],
        $lead_data['email'],
        $lead_data['platform'],
        $lead_data['lead_source'],
        $lead_data['notes'],
        $assigned_to,
        $lead_data['status'],
        'warm'
    );
    
    if ($stmt->execute()) {
        $lead_id = $db->lastInsertId();
        
        // Log the webhook activity
        log_activity(null, 'webhook_lead_created', "Webhook created lead ID: {$lead_id} from platform: {$lead_data['platform']}");
        
        return ['success' => true, 'lead_id' => $lead_id];
    } else {
        return ['success' => false, 'error' => 'Failed to create lead'];
    }
}

// Main webhook processing
try {
    $method = $_SERVER['REQUEST_METHOD'];
    
    if ($method === 'GET') {
        // Webhook verification (for Meta, etc.)
        $challenge = $_GET['hub_challenge'] ?? '';
        $verify_token = $_GET['hub_verify_token'] ?? '';
        
        if ($challenge && $verify_token === $webhook_secret) {
            echo $challenge;
            exit();
        }
        
        http_response_code(200);
        echo json_encode(['status' => 'Webhook endpoint active']);
        exit();
    }
    
    if ($method === 'POST') {
        // Get the raw payload
        $payload = file_get_contents('php://input');
        $data = json_decode($payload, true);
        
        // Verify signature if provided
        $signature = $_SERVER['HTTP_X_HUB_SIGNATURE'] ?? '';
        if (!verifyWebhookSignature($payload, $signature, $webhook_secret)) {
            http_response_code(401);
            echo json_encode(['error' => 'Invalid signature']);
            exit();
        }
        
        // Determine webhook source
        $source = 'generic';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        if (strpos($user_agent, 'facebook') !== false || strpos($user_agent, 'meta') !== false) {
            $source = 'meta';
        } elseif (strpos($user_agent, 'tiktok') !== false) {
            $source = 'tiktok';
        } elseif (strpos($user_agent, 'snapchat') !== false) {
            $source = 'snapchat';
        } elseif (strpos($user_agent, 'whatsapp') !== false) {
            $source = 'whatsapp';
        }
        
        // Process the webhook
        $result = processWebhook($data, $source);
        
        if ($result['success']) {
            http_response_code(200);
            echo json_encode([
                'success' => true,
                'message' => 'Webhook processed successfully',
                'lead_id' => $result['lead_id']
            ]);
        } else {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => $result['error']
            ]);
        }
    }
    
} catch (Exception $e) {
    error_log("Webhook Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
}
?>

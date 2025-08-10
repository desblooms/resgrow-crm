<?php
// Resgrow CRM - API Index Router
// Main API entry point with authentication and routing

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

// Handle preflight OPTIONS requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once '../config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

class APIRouter {
    private $db;
    private $request_uri;
    private $method;
    private $headers;
    
    public function __construct() {
        global $db;
        $this->db = $db;
        $this->request_uri = $_SERVER['REQUEST_URI'];
        $this->method = $_SERVER['REQUEST_METHOD'];
        $this->headers = getallheaders() ?: [];
        
        // Remove base path and query string
        $this->request_uri = parse_url($this->request_uri, PHP_URL_PATH);
        $this->request_uri = str_replace('/api', '', $this->request_uri);
    }
    
    public function route() {
        try {
            // Parse route
            $route_parts = explode('/', trim($this->request_uri, '/'));
            $endpoint = $route_parts[0] ?? '';
            $id = $route_parts[1] ?? null;
            
            // Authentication check (except for auth endpoints)
            if ($endpoint !== 'auth' && !$this->authenticateRequest()) {
                $this->sendResponse(401, ['error' => 'Unauthorized']);
                return;
            }
            
            switch ($endpoint) {
                case 'auth':
                    $this->handleAuth();
                    break;
                case 'leads':
                    $this->handleLeads($id);
                    break;
                case 'campaigns':
                    $this->handleCampaigns($id);
                    break;
                case 'users':
                    $this->handleUsers($id);
                    break;
                case 'dashboard':
                    $this->handleDashboard();
                    break;
                case 'analytics':
                    $this->handleAnalytics();
                    break;
                default:
                    $this->sendResponse(404, ['error' => 'Endpoint not found']);
            }
        } catch (Exception $e) {
            error_log("API Error: " . $e->getMessage());
            $this->sendResponse(500, ['error' => 'Internal server error']);
        }
    }
    
    private function authenticateRequest() {
        $auth_header = $this->headers['Authorization'] ?? '';
        
        if (empty($auth_header)) {
            return false;
        }
        
        // Extract token from "Bearer TOKEN" format
        if (strpos($auth_header, 'Bearer ') === 0) {
            $token = substr($auth_header, 7);
            return $this->validateToken($token);
        }
        
        return false;
    }
    
    private function validateToken($token) {
        // For now, check if user session is valid
        // In production, implement proper JWT or API key validation
        session_start();
        return isset($_SESSION['user_id']) && isset($_SESSION['user_role']);
    }
    
    private function handleAuth() {
        switch ($this->method) {
            case 'POST':
                $this->login();
                break;
            case 'DELETE':
                $this->logout();
                break;
            default:
                $this->sendResponse(405, ['error' => 'Method not allowed']);
        }
    }
    
    private function login() {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($input['email']) || !isset($input['password'])) {
            $this->sendResponse(400, ['error' => 'Email and password required']);
            return;
        }
        
        $stmt = $this->db->prepare("SELECT id, name, email, password, role, status FROM users WHERE email = ? AND status = 'active'");
        $stmt->bind_param("s", $input['email']);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($user = $result->fetch_assoc()) {
            if (password_verify($input['password'], $user['password'])) {
                session_start();
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_role'] = $user['role'];
                $_SESSION['user_name'] = $user['name'];
                
                // Update last login
                $update_stmt = $this->db->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
                $update_stmt->bind_param("i", $user['id']);
                $update_stmt->execute();
                
                // Log activity
                log_activity($user['id'], 'api_login', 'User logged in via API');
                
                $this->sendResponse(200, [
                    'message' => 'Login successful',
                    'user' => [
                        'id' => $user['id'],
                        'name' => $user['name'],
                        'email' => $user['email'],
                        'role' => $user['role']
                    ],
                    'token' => session_id() // Simple token - use JWT in production
                ]);
            } else {
                $this->sendResponse(401, ['error' => 'Invalid credentials']);
            }
        } else {
            $this->sendResponse(401, ['error' => 'User not found or inactive']);
        }
    }
    
    private function logout() {
        session_start();
        if (isset($_SESSION['user_id'])) {
            log_activity($_SESSION['user_id'], 'api_logout', 'User logged out via API');
        }
        session_destroy();
        $this->sendResponse(200, ['message' => 'Logout successful']);
    }
    
    private function handleLeads($id) {
        require_once 'endpoints/leads.php';
        $leadsAPI = new LeadsAPI($this->db);
        $leadsAPI->handle($this->method, $id, $this->getInput());
    }
    
    private function handleCampaigns($id) {
        require_once 'endpoints/campaigns.php';
        $campaignsAPI = new CampaignsAPI($this->db);
        $campaignsAPI->handle($this->method, $id, $this->getInput());
    }
    
    private function handleUsers($id) {
        require_once 'endpoints/users.php';
        $usersAPI = new UsersAPI($this->db);
        $usersAPI->handle($this->method, $id, $this->getInput());
    }
    
    private function handleDashboard() {
        require_once 'endpoints/dashboard.php';
        $dashboardAPI = new DashboardAPI($this->db);
        $dashboardAPI->handle($this->method, null, $this->getInput());
    }
    
    private function handleAnalytics() {
        require_once 'endpoints/analytics.php';
        $analyticsAPI = new AnalyticsAPI($this->db);
        $analyticsAPI->handle($this->method, null, $this->getInput());
    }
    
    private function getInput() {
        return json_decode(file_get_contents('php://input'), true) ?: [];
    }
    
    public function sendResponse($status_code, $data) {
        http_response_code($status_code);
        echo json_encode($data, JSON_PRETTY_PRINT);
        exit();
    }
}

// Initialize and route
$router = new APIRouter();
$router->route();
<<<<<<< HEAD
?>
=======
?>
>>>>>>> e981dd606dbc3d13315396933ec31366209c7f6d

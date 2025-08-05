<?php
// Resgrow CRM - Login Page
// Phase 1: Project Setup & Auth

require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Redirect if already logged in
if (SessionManager::isLoggedIn()) {
    header('Location: dashboard.php');
    exit();
}

$error_message = '';
$success_message = '';

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitize_input($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $csrf_token = $_POST['csrf_token'] ?? '';
    
    // Validate CSRF token
    if (!verify_csrf_token($csrf_token)) {
        $error_message = 'Security token mismatch. Please try again.';
    } elseif (empty($email) || empty($password)) {
        $error_message = 'Please fill in all fields.';
    } elseif (!validate_email($email)) {
        $error_message = 'Please enter a valid email address.';
    } else {
        $auth = new Auth();
        $result = $auth->login($email, $password);
        
        if ($result['success']) {
            // Redirect based on role
            switch ($result['role']) {
                case 'admin':
                    header('Location: ../admin/dashboard.php');
                    break;
                case 'marketing':
                    header('Location: ../marketing/dashboard.php');
                    break;
                case 'sales':
                    header('Location: ../sales/dashboard.php');
                    break;
                default:
                    header('Location: dashboard.php');
            }
            exit();
        } else {
            $error_message = $result['message'];
        }
    }
}

// Handle logout message
if (isset($_GET['logout']) && $_GET['logout'] === 'success') {
    $success_message = 'You have been logged out successfully.';
}

$page_title = 'Login';
include '../templates/header.php';
?>

<div class="min-h-screen flex items-center justify-center bg-gray-50 py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-md w-full space-y-8">
        <!-- Header -->
        <div>
            <div class="mx-auto h-20 w-20 bg-primary-600 rounded-full flex items-center justify-center">
                <svg class="h-10 w-10 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                </svg>
            </div>
            <h2 class="mt-6 text-center text-3xl font-extrabold text-gray-900">
                Sign in to <?php echo APP_NAME; ?>
            </h2>
            <p class="mt-2 text-center text-sm text-gray-600">
                Marketing & Sales Management System
            </p>
        </div>

        <!-- Messages -->
        <?php if ($error_message): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded" role="alert">
                <span class="block sm:inline"><?php echo htmlspecialchars($error_message); ?></span>
            </div>
        <?php endif; ?>

        <?php if ($success_message): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded" role="alert">
                <span class="block sm:inline"><?php echo htmlspecialchars($success_message); ?></span>
            </div>
        <?php endif; ?>

        <!-- Login Form -->
        <form class="mt-8 space-y-6" method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
            
            <div class="rounded-md shadow-sm -space-y-px">
                <div>
                    <label for="email" class="sr-only">Email address</label>
                    <input 
                        id="email" 
                        name="email" 
                        type="email" 
                        autocomplete="email" 
                        required 
                        class="appearance-none rounded-none relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 rounded-t-md focus:outline-none focus:ring-primary-500 focus:border-primary-500 focus:z-10 sm:text-sm" 
                        placeholder="Email address"
                        value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                    >
                </div>
                <div>
                    <label for="password" class="sr-only">Password</label>
                    <input 
                        id="password" 
                        name="password" 
                        type="password" 
                        autocomplete="current-password" 
                        required 
                        class="appearance-none rounded-none relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 rounded-b-md focus:outline-none focus:ring-primary-500 focus:border-primary-500 focus:z-10 sm:text-sm" 
                        placeholder="Password"
                    >
                </div>
            </div>

            <div class="flex items-center justify-between">
                <div class="flex items-center">
                    <input 
                        id="remember-me" 
                        name="remember-me" 
                        type="checkbox" 
                        class="h-4 w-4 text-primary-600 focus:ring-primary-500 border-gray-300 rounded"
                    >
                    <label for="remember-me" class="ml-2 block text-sm text-gray-900">
                        Remember me
                    </label>
                </div>

                <div class="text-sm">
                    <a href="forgot-password.php" class="font-medium text-primary-600 hover:text-primary-500">
                        Forgot your password?
                    </a>
                </div>
            </div>

            <div>
                <button 
                    type="submit" 
                    class="group relative w-full flex justify-center py-2 px-4 border border-transparent text-sm font-medium rounded-md text-white bg-primary-600 hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 transition duration-200"
                >
                    <span class="absolute left-0 inset-y-0 flex items-center pl-3">
                        <svg class="h-5 w-5 text-primary-500 group-hover:text-primary-400" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"></path>
                        </svg>
                    </span>
                    Sign in
                </button>
            </div>
        </form>

        <!-- Demo Users (Development Only) -->
        <?php if (defined('APP_ENV') && APP_ENV === 'development'): ?>
        <div class="mt-6 border-t border-gray-200 pt-6">
            <p class="text-center text-sm text-gray-500 mb-4">Demo Users (Development Only)</p>
            <div class="space-y-2 text-xs">
                <div class="bg-gray-100 p-2 rounded">
                    <strong>Admin:</strong> admin@resgrow.com / password123
                </div>
                <div class="bg-gray-100 p-2 rounded">
                    <strong>Marketing:</strong> marketing@resgrow.com / password123
                </div>
                <div class="bg-gray-100 p-2 rounded">
                    <strong>Sales:</strong> sales@resgrow.com / password123
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Footer Links -->
        <div class="text-center">
            <p class="text-sm text-gray-600">
                Need help? Contact your administrator or 
                <a href="mailto:support@resgrow.com" class="font-medium text-primary-600 hover:text-primary-500">
                    support@resgrow.com
                </a>
            </p>
        </div>
    </div>
</div>

<script>
// Add loading state to login button
document.addEventListener('DOMContentLoaded', function() {
    const form = document.querySelector('form');
    const submitBtn = document.querySelector('button[type="submit"]');
    
    form.addEventListener('submit', function() {
        showLoading(submitBtn);
    });
    
    // Prevent double submission
    let submitted = false;
    form.addEventListener('submit', function(e) {
        if (submitted) {
            e.preventDefault();
            return false;
        }
        submitted = true;
    });
});

// Auto-focus first empty field
document.addEventListener('DOMContentLoaded', function() {
    const emailField = document.getElementById('email');
    const passwordField = document.getElementById('password');
    
    if (!emailField.value) {
        emailField.focus();
    } else {
        passwordField.focus();
    }
});
</script>

<?php include '../templates/footer.php'; ?>
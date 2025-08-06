<?php
// Resgrow CRM - Create User
// Phase 2: User Role System

require_once '../includes/session.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// Require admin role
SessionManager::requireRole('admin');

$error_message = '';
$success_message = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = sanitize_input($_POST['name'] ?? '');
    $email = sanitize_input($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $role = $_POST['role'] ?? 'sales';
    $phone = sanitize_input($_POST['phone'] ?? '');
    $status = $_POST['status'] ?? 'active';
    $csrf_token = $_POST['csrf_token'] ?? '';
    
    // Validate CSRF token
    if (!verify_csrf_token($csrf_token)) {
        $error_message = 'Security token mismatch. Please try again.';
    }
    // Validate required fields
    elseif (empty($name) || empty($email) || empty($password)) {
        $error_message = 'Please fill in all required fields.';
    }
    // Validate email
    elseif (!validate_email($email)) {
        $error_message = 'Please enter a valid email address.';
    }
    // Validate password length
    elseif (strlen($password) < PASSWORD_MIN_LENGTH) {
        $error_message = 'Password must be at least ' . PASSWORD_MIN_LENGTH . ' characters long.';
    }
    // Validate password confirmation
    elseif ($password !== $confirm_password) {
        $error_message = 'Passwords do not match.';
    }
    // Validate phone if provided
    elseif (!empty($phone) && !validate_phone($phone)) {
        $error_message = 'Please enter a valid Qatar phone number.';
    }
    // Validate role
    elseif (!in_array($role, ['admin', 'marketing', 'sales'])) {
        $error_message = 'Invalid role selected.';
    }
    else {
        // Create user
        $auth = new Auth();
        $result = $auth->register($name, $email, $password, $role);
        
        if ($result['success']) {
            // Update additional fields
            update_user_details($result['user_id'], $phone, $status);
            
            log_activity(SessionManager::getUserId(), 'user_create', "Created new user: {$name} ({$email})");
            set_flash_message('success', 'User created successfully.');
            header('Location: users.php');
            exit();
        } else {
            $error_message = $result['message'];
        }
    }
}

$page_title = 'Create User';
include '../templates/header.php';
?>

<div class="min-h-screen bg-gray-50 flex">
    <!-- Sidebar -->
    <div class="fixed inset-y-0 left-0 z-50 w-64 bg-white shadow-lg transform -translate-x-full transition-transform duration-300 ease-in-out lg:translate-x-0 lg:static lg:inset-0" id="sidebar">
        <div class="flex items-center justify-center h-16 px-4 bg-primary-600">
            <h1 class="text-xl font-semibold text-white"><?php echo APP_NAME; ?></h1>
        </div>
        
        <nav class="mt-8">
            <div class="px-4 space-y-2">
                <a href="dashboard.php" class="flex items-center px-4 py-2 text-gray-600 hover:bg-gray-100 rounded-lg">
                    <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2H5a2 2 0 00-2-2z"></path>
                    </svg>
                    Dashboard
                </a>
                
                <a href="users.php" class="flex items-center px-4 py-2 text-gray-700 bg-primary-100 rounded-lg">
                    <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-2.239"></path>
                    </svg>
                    Users
                </a>
            </div>
        </nav>
    </div>

    <!-- Mobile menu button -->
    <div class="lg:hidden">
        <button id="mobile-menu-btn" class="fixed top-4 left-4 z-50 p-2 rounded-md bg-primary-600 text-white">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
            </svg>
        </button>
    </div>

    <!-- Main Content -->
    <div class="flex-1 lg:ml-0">
        <!-- Header -->
        <header class="bg-white shadow-sm border-b border-gray-200">
            <div class="px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between items-center h-16">
                    <div class="flex items-center">
                        <h1 class="text-2xl font-semibold text-gray-900 ml-10 lg:ml-0">Create User</h1>
                    </div>
                    
                    <div class="flex items-center space-x-4">
                        <a href="users.php" class="text-gray-600 hover:text-gray-900">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </a>
                    </div>
                </div>
            </div>
        </header>

        <!-- Content -->
        <main class="p-4 sm:p-6 lg:p-8">
            <div class="max-w-2xl mx-auto">
                <!-- Messages -->
                <?php if ($error_message): ?>
                    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6" role="alert">
                        <span class="block sm:inline"><?php echo htmlspecialchars($error_message); ?></span>
                    </div>
                <?php endif; ?>

                <?php if ($success_message): ?>
                    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6" role="alert">
                        <span class="block sm:inline"><?php echo htmlspecialchars($success_message); ?></span>
                    </div>
                <?php endif; ?>

                <!-- Create User Form -->
                <div class="bg-white shadow rounded-lg">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h3 class="text-lg font-medium text-gray-900">User Information</h3>
                        <p class="mt-1 text-sm text-gray-500">Create a new user account for the CRM system.</p>
                    </div>
                    
                    <form method="POST" class="px-6 py-4 space-y-6">
                        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                        
                        <!-- Personal Information -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="name" class="block text-sm font-medium text-gray-700 mb-1">
                                    Full Name <span class="text-red-500">*</span>
                                </label>
                                <input type="text" id="name" name="name" required
                                       value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-primary-500 focus:border-primary-500"
                                       placeholder="Enter full name">
                            </div>
                            
                            <div>
                                <label for="phone" class="block text-sm font-medium text-gray-700 mb-1">
                                    Phone Number
                                </label>
                                <input type="tel" id="phone" name="phone"
                                       value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-primary-500 focus:border-primary-500"
                                       placeholder="+974 XXXX XXXX">
                                <p class="text-xs text-gray-500 mt-1">Qatar phone number (optional)</p>
                            </div>
                        </div>
                        
                        <!-- Account Information -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="email" class="block text-sm font-medium text-gray-700 mb-1">
                                    Email Address <span class="text-red-500">*</span>
                                </label>
                                <input type="email" id="email" name="email" required
                                       value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-primary-500 focus:border-primary-500"
                                       placeholder="user@example.com">
                            </div>
                            
                            <div>
                                <label for="role" class="block text-sm font-medium text-gray-700 mb-1">
                                    Role <span class="text-red-500">*</span>
                                </label>
                                <select id="role" name="role" required
                                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-primary-500 focus:border-primary-500">
                                    <option value="sales" <?php echo ($_POST['role'] ?? 'sales') === 'sales' ? 'selected' : ''; ?>>Sales Team</option>
                                    <option value="marketing" <?php echo ($_POST['role'] ?? '') === 'marketing' ? 'selected' : ''; ?>>Marketing Team</option>
                                    <option value="admin" <?php echo ($_POST['role'] ?? '') === 'admin' ? 'selected' : ''; ?>>Administrator</option>
                                </select>
                            </div>
                        </div>
                        
                        <!-- Password Information -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="password" class="block text-sm font-medium text-gray-700 mb-1">
                                    Password <span class="text-red-500">*</span>
                                </label>
                                <input type="password" id="password" name="password" required
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-primary-500 focus:border-primary-500"
                                       placeholder="Enter password">
                                <p class="text-xs text-gray-500 mt-1">Minimum <?php echo PASSWORD_MIN_LENGTH; ?> characters</p>
                            </div>
                            
                            <div>
                                <label for="confirm_password" class="block text-sm font-medium text-gray-700 mb-1">
                                    Confirm Password <span class="text-red-500">*</span>
                                </label>
                                <input type="password" id="confirm_password" name="confirm_password" required
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-primary-500 focus:border-primary-500"
                                       placeholder="Confirm password">
                            </div>
                        </div>
                        
                        <!-- Status -->
                        <div>
                            <label for="status" class="block text-sm font-medium text-gray-700 mb-1">
                                Status
                            </label>
                            <select id="status" name="status"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-primary-500 focus:border-primary-500">
                                <option value="active" <?php echo ($_POST['status'] ?? 'active') === 'active' ? 'selected' : ''; ?>>Active</option>
                                <option value="inactive" <?php echo ($_POST['status'] ?? '') === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                <option value="pending" <?php echo ($_POST['status'] ?? '') === 'pending' ? 'selected' : ''; ?>>Pending</option>
                            </select>
                            <p class="text-xs text-gray-500 mt-1">User account status</p>
                        </div>
                        
                        <!-- Role Description -->
                        <div class="bg-gray-50 rounded-lg p-4">
                            <h4 class="text-sm font-medium text-gray-900 mb-2">Role Permissions</h4>
                            <div class="space-y-2 text-xs text-gray-600">
                                <div id="role-admin" class="hidden">
                                    <strong>Administrator:</strong> Full system access, user management, analytics, settings, and all data export capabilities.
                                </div>
                                <div id="role-marketing" class="hidden">
                                    <strong>Marketing Team:</strong> Create and manage campaigns, assign leads to sales team, view campaign performance and analytics.
                                </div>
                                <div id="role-sales" class="">
                                    <strong>Sales Team:</strong> View assigned leads, update lead status, record sales values, and submit feedback for closed leads.
                                </div>
                            </div>
                        </div>
                        
                        <!-- Submit Buttons -->
                        <div class="flex justify-end space-x-3 pt-6 border-t border-gray-200">
                            <a href="users.php" class="px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                                Cancel
                            </a>
                            <button type="submit" class="px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-primary-600 hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500">
                                Create User
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>
</div>

<script>
// Mobile menu toggle
document.getElementById('mobile-menu-btn').addEventListener('click', function() {
    const sidebar = document.getElementById('sidebar');
    sidebar.classList.toggle('-translate-x-full');
});

// Role description toggle
document.getElementById('role').addEventListener('change', function() {
    const roleValue = this.value;
    const descriptions = document.querySelectorAll('[id^="role-"]');
    
    descriptions.forEach(desc => desc.classList.add('hidden'));
    
    const activeDesc = document.getElementById('role-' + roleValue);
    if (activeDesc) {
        activeDesc.classList.remove('hidden');
    }
});

// Password confirmation validation
document.getElementById('confirm_password').addEventListener('blur', function() {
    const password = document.getElementById('password').value;
    const confirmPassword = this.value;
    
    if (password && confirmPassword && password !== confirmPassword) {
        this.classList.add('border-red-500');
        this.classList.remove('border-gray-300');
        
        // Add error message if not exists
        if (!this.parentNode.querySelector('.password-error')) {
            const errorDiv = document.createElement('div');
            errorDiv.className = 'password-error text-red-500 text-xs mt-1';
            errorDiv.textContent = 'Passwords do not match';
            this.parentNode.appendChild(errorDiv);
        }
    } else {
        this.classList.remove('border-red-500');
        this.classList.add('border-gray-300');
        
        // Remove error message
        const errorDiv = this.parentNode.querySelector('.password-error');
        if (errorDiv) {
            errorDiv.remove();
        }
    }
});

// Phone number formatting
document.getElementById('phone').addEventListener('input', function() {
    let value = this.value.replace(/[^0-9+]/g, '');
    
    // Auto-add +974 for Qatar numbers
    if (value.length > 0 && !value.startsWith('+974') && !value.startsWith('974')) {
        if (value.startsWith('3') || value.startsWith('4') || value.startsWith('5') || 
            value.startsWith('6') || value.startsWith('7') || value.startsWith('8') || value.startsWith('9')) {
            value = '+974' + value;
        }
    }
    
    this.value = value;
});

// Form validation before submit
document.querySelector('form').addEventListener('submit', function(e) {
    const password = document.getElementById('password').value;
    const confirmPassword = document.getElementById('confirm_password').value;
    
    if (password !== confirmPassword) {
        e.preventDefault();
        alert('Passwords do not match. Please check your passwords and try again.');
        document.getElementById('confirm_password').focus();
    }
});
</script>

<?php
include '../templates/footer.php';

// Helper function to update additional user details
function update_user_details($user_id, $phone, $status) {
    global $db;
    
    $stmt = $db->prepare("UPDATE users SET phone = ?, status = ?, updated_at = NOW() WHERE id = ?");
    $stmt->bind_param("ssi", $phone, $status, $user_id);
    $stmt->execute();
}
?>
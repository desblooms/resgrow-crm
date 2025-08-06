<?php
// Resgrow CRM - Edit User
// Phase 2: User Role System

require_once '../includes/session.php';
require_once '../includes/functions.php';
require_once '../includes/db.php';

// Require admin role
SessionManager::requireRole('admin');

$error_message = '';
$success_message = '';
$user_id = (int)($_GET['id'] ?? 0);

// Get user data
$user = get_user_by_id($user_id);
if (!$user) {
    set_flash_message('error', 'User not found.');
    header('Location: users.php');
    exit();
}

// Prevent editing own account through this interface
if ($user_id === SessionManager::getUserId()) {
    set_flash_message('error', 'Please use the profile page to edit your own account.');
    header('Location: users.php');
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = sanitize_input($_POST['name'] ?? '');
    $email = sanitize_input($_POST['email'] ?? '');
    $role = $_POST['role'] ?? 'sales';
    $phone = sanitize_input($_POST['phone'] ?? '');
    $status = $_POST['status'] ?? 'active';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $csrf_token = $_POST['csrf_token'] ?? '';
    
    // Validate CSRF token
    if (!verify_csrf_token($csrf_token)) {
        $error_message = 'Security token mismatch. Please try again.';
    }
    // Validate required fields
    elseif (empty($name) || empty($email)) {
        $error_message = 'Please fill in all required fields.';
    }
    // Validate email
    elseif (!validate_email($email)) {
        $error_message = 'Please enter a valid email address.';
    }
    // Validate phone if provided
    elseif (!empty($phone) && !validate_phone($phone)) {
        $error_message = 'Please enter a valid Qatar phone number.';
    }
    // Validate role
    elseif (!in_array($role, ['admin', 'marketing', 'sales'])) {
        $error_message = 'Invalid role selected.';
    }
    // Validate password if provided
    elseif (!empty($new_password)) {
        if (strlen($new_password) < PASSWORD_MIN_LENGTH) {
            $error_message = 'Password must be at least ' . PASSWORD_MIN_LENGTH . ' characters long.';
        } elseif ($new_password !== $confirm_password) {
            $error_message = 'Passwords do not match.';
        }
    }
    else {
        // Update user
        $result = update_user($user_id, $name, $email, $role, $phone, $status, $new_password);
        
        if ($result['success']) {
            log_activity(SessionManager::getUserId(), 'user_update', "Updated user: {$name} ({$email})");
            set_flash_message('success', 'User updated successfully.');
            header('Location: users.php');
            exit();
        } else {
            $error_message = $result['message'];
        }
    }
}

$page_title = 'Edit User';
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
                        <h1 class="text-2xl font-semibold text-gray-900 ml-10 lg:ml-0">Edit User</h1>
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

                <!-- User Info Header -->
                <div class="bg-white shadow rounded-lg mb-6">
                    <div class="px-6 py-4">
                        <div class="flex items-center">
                            <div class="w-12 h-12 bg-primary-100 rounded-full flex items-center justify-center">
                                <span class="text-primary-600 font-medium text-lg">
                                    <?php echo strtoupper(substr($user['name'], 0, 1)); ?>
                                </span>
                            </div>
                            <div class="ml-4">
                                <h3 class="text-lg font-medium text-gray-900"><?php echo htmlspecialchars($user['name']); ?></h3>
                                <p class="text-sm text-gray-500"><?php echo htmlspecialchars($user['email']); ?></p>
                                <div class="flex items-center mt-1">
                                    <?php echo get_status_badge($user['status']); ?>
                                    <span class="ml-2 text-xs text-gray-500">
                                        Created <?php echo format_date($user['created_at'], 'M j, Y'); ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Edit User Form -->
                <div class="bg-white shadow rounded-lg">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h3 class="text-lg font-medium text-gray-900">Update User Information</h3>
                        <p class="mt-1 text-sm text-gray-500">Make changes to the user account details.</p>
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
                                       value="<?php echo htmlspecialchars($_POST['name'] ?? $user['name']); ?>"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-primary-500 focus:border-primary-500">
                            </div>
                            
                            <div>
                                <label for="phone" class="block text-sm font-medium text-gray-700 mb-1">
                                    Phone Number
                                </label>
                                <input type="tel" id="phone" name="phone"
                                       value="<?php echo htmlspecialchars($_POST['phone'] ?? $user['phone'] ?? ''); ?>"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-primary-500 focus:border-primary-500"
                                       placeholder="+974 XXXX XXXX">
                            </div>
                        </div>
                        
                        <!-- Account Information -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="email" class="block text-sm font-medium text-gray-700 mb-1">
                                    Email Address <span class="text-red-500">*</span>
                                </label>
                                <input type="email" id="email" name="email" required
                                       value="<?php echo htmlspecialchars($_POST['email'] ?? $user['email']); ?>"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-primary-500 focus:border-primary-500">
                            </div>
                            
                            <div>
                                <label for="role" class="block text-sm font-medium text-gray-700 mb-1">
                                    Role <span class="text-red-500">*</span>
                                </label>
                                <select id="role" name="role" required
                                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-primary-500 focus:border-primary-500">
                                    <option value="sales" <?php echo ($_POST['role'] ?? $user['role']) === 'sales' ? 'selected' : ''; ?>>Sales Team</option>
                                    <option value="marketing" <?php echo ($_POST['role'] ?? $user['role']) === 'marketing' ? 'selected' : ''; ?>>Marketing Team</option>
                                    <option value="admin" <?php echo ($_POST['role'] ?? $user['role']) === 'admin' ? 'selected' : ''; ?>>Administrator</option>
                                </select>
                            </div>
                        </div>
                        
                        <!-- Status -->
                        <div>
                            <label for="status" class="block text-sm font-medium text-gray-700 mb-1">
                                Account Status
                            </label>
                            <select id="status" name="status"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-primary-500 focus:border-primary-500">
                                <option value="active" <?php echo ($_POST['status'] ?? $user['status']) === 'active' ? 'selected' : ''; ?>>Active</option>
                                <option value="inactive" <?php echo ($_POST['status'] ?? $user['status']) === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                <option value="pending" <?php echo ($_POST['status'] ?? $user['status']) === 'pending' ? 'selected' : ''; ?>>Pending</option>
                            </select>
                        </div>
                        
                        <!-- Password Change -->
                        <div class="border-t border-gray-200 pt-6">
                            <h4 class="text-md font-medium text-gray-900 mb-4">Change Password (Optional)</h4>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label for="new_password" class="block text-sm font-medium text-gray-700 mb-1">
                                        New Password
                                    </label>
                                    <input type="password" id="new_password" name="new_password"
                                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-primary-500 focus:border-primary-500"
                                           placeholder="Leave blank to keep current">
                                    <p class="text-xs text-gray-500 mt-1">Minimum <?php echo PASSWORD_MIN_LENGTH; ?> characters</p>
                                </div>
                                
                                <div>
                                    <label for="confirm_password" class="block text-sm font-medium text-gray-700 mb-1">
                                        Confirm New Password
                                    </label>
                                    <input type="password" id="confirm_password" name="confirm_password"
                                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-primary-500 focus:border-primary-500"
                                           placeholder="Confirm new password">
                                </div>
                            </div>
                        </div>
                        
                        <!-- Account Info -->
                        <div class="bg-gray-50 rounded-lg p-4">
                            <h4 class="text-sm font-medium text-gray-900 mb-2">Account Information</h4>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm text-gray-600">
                                <div>
                                    <span class="font-medium">Created:</span> <?php echo format_date($user['created_at'], 'M j, Y g:i A'); ?>
                                </div>
                                <div>
                                    <span class="font-medium">Last Updated:</span> <?php echo format_date($user['updated_at'], 'M j, Y g:i A'); ?>
                                </div>
                                <div>
                                    <span class="font-medium">Last Login:</span> <?php echo $user['last_login'] ? format_date($user['last_login'], 'M j, Y g:i A') : 'Never'; ?>
                                </div>
                                <div>
                                    <span class="font-medium">Login Attempts:</span> <?php echo $user['login_attempts'] ?? 0; ?>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Submit Buttons -->
                        <div class="flex justify-end space-x-3 pt-6 border-t border-gray-200">
                            <a href="users.php" class="px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                                Cancel
                            </a>
                            <button type="submit" class="px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-primary-600 hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500">
                                Update User
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

// Password confirmation validation
document.getElementById('confirm_password').addEventListener('blur', function() {
    const password = document.getElementById('new_password').value;
    const confirmPassword = this.value;
    
    if (password && confirmPassword && password !== confirmPassword) {
        this.classList.add('border-red-500');
        this.classList.remove('border-gray-300');
        
        if (!this.parentNode.querySelector('.password-error')) {
            const errorDiv = document.createElement('div');
            errorDiv.className = 'password-error text-red-500 text-xs mt-1';
            errorDiv.textContent = 'Passwords do not match';
            this.parentNode.appendChild(errorDiv);
        }
    } else {
        this.classList.remove('border-red-500');
        this.classList.add('border-gray-300');
        
        const errorDiv = this.parentNode.querySelector('.password-error');
        if (errorDiv) {
            errorDiv.remove();
        }
    }
});

// Clear confirm password when new password changes
document.getElementById('new_password').addEventListener('input', function() {
    const confirmPassword = document.getElementById('confirm_password');
    if (confirmPassword.value) {
        confirmPassword.value = '';
        confirmPassword.classList.remove('border-red-500');
        confirmPassword.classList.add('border-gray-300');
        
        const errorDiv = confirmPassword.parentNode.querySelector('.password-error');
        if (errorDiv) {
            errorDiv.remove();
        }
    }
});

// Form validation before submit
document.querySelector('form').addEventListener('submit', function(e) {
    const newPassword = document.getElementById('new_password').value;
    const confirmPassword = document.getElementById('confirm_password').value;
    
    if (newPassword && newPassword !== confirmPassword) {
        e.preventDefault();
        alert('Passwords do not match. Please check your passwords and try again.');
        document.getElementById('confirm_password').focus();
    }
});
</script>

<?php
include '../templates/footer.php';

// Helper functions
function get_user_by_id($user_id) {
    global $db;
    
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    return $result->num_rows > 0 ? $result->fetch_assoc() : null;
}

function update_user($user_id, $name, $email, $role, $phone, $status, $new_password = '') {
    global $db;
    
    // Check if email is already taken by another user
    $stmt = $db->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
    $stmt->bind_param("si", $email, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        return ['success' => false, 'message' => 'Email address is already in use by another user.'];
    }
    
    // Update user information
    if (!empty($new_password)) {
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt = $db->prepare("UPDATE users SET name = ?, email = ?, role = ?, phone = ?, status = ?, password = ?, updated_at = NOW() WHERE id = ?");
        $stmt->bind_param("ssssssi", $name, $email, $role, $phone, $status, $hashed_password, $user_id);
    } else {
        $stmt = $db->prepare("UPDATE users SET name = ?, email = ?, role = ?, phone = ?, status = ?, updated_at = NOW() WHERE id = ?");
        $stmt->bind_param("sssssi", $name, $email, $role, $phone, $status, $user_id);
    }
    
    if ($stmt->execute()) {
        return ['success' => true, 'message' => 'User updated successfully'];
    } else {
        return ['success' => false, 'message' => 'Failed to update user'];
    }
}
?>
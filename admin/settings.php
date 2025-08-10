<?php
// Resgrow CRM - Admin Settings
// System settings and configuration management

require_once '../includes/session.php';
require_once '../includes/functions.php';
require_once '../includes/db.php';

// Require admin role
SessionManager::requireRole('admin');

$page_title = 'System Settings';
$error_message = '';
$success_message = '';

// Handle settings update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $csrf_token = $_POST['csrf_token'] ?? '';
    
    if (!verify_csrf_token($csrf_token)) {
        set_flash_message('error', 'Security token mismatch.');
    } else {
        switch ($action) {
            case 'update_general':
                updateGeneralSettings($_POST);
                break;
            case 'update_email':
                updateEmailSettings($_POST);
                break;
            case 'update_security':
                updateSecuritySettings($_POST);
                break;
            case 'clear_cache':
                clearSystemCache();
                break;
            case 'backup_database':
                createDatabaseBackup();
                break;
        }
    }
    
    header('Location: settings.php');
    exit();
}

// Get current settings
$settings = getSystemSettings();

include '../templates/header.php';
?>

<div class="min-h-screen bg-gray-50">
    <!-- Navigation -->
    <?php include '../templates/nav-admin.php'; ?>
    
    <div class="py-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <!-- Page Header -->
            <div class="mb-8">
                <h1 class="text-3xl font-bold text-gray-900">System Settings</h1>
                <p class="mt-2 text-sm text-gray-600">
                    Manage system configuration and settings
                </p>
            </div>

            <!-- Flash Messages -->
            <?php include '../templates/flash-messages.php'; ?>

            <!-- Settings Tabs -->
            <div class="bg-white shadow rounded-lg">
                <div class="border-b border-gray-200">
                    <nav class="-mb-px flex space-x-8 px-6" aria-label="Tabs">
                        <button onclick="showTab('general')" id="tab-general" class="tab-button border-primary-500 text-primary-600 whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                            General Settings
                        </button>
                        <button onclick="showTab('email')" id="tab-email" class="tab-button border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                            Email Settings
                        </button>
                        <button onclick="showTab('security')" id="tab-security" class="tab-button border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                            Security Settings
                        </button>
                        <button onclick="showTab('maintenance')" id="tab-maintenance" class="tab-button border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                            Maintenance
                        </button>
                    </nav>
                </div>

                <!-- General Settings Tab -->
                <div id="tab-content-general" class="tab-content p-6">
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="update_general">
                        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                        
                        <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                            <div>
                                <label for="app_name" class="block text-sm font-medium text-gray-700">Application Name</label>
                                <input type="text" id="app_name" name="app_name" value="<?php echo htmlspecialchars($settings['app_name'] ?? APP_NAME); ?>"
                                       class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                            </div>
                            
                            <div>
                                <label for="timezone" class="block text-sm font-medium text-gray-700">Timezone</label>
                                <select id="timezone" name="timezone" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                    <option value="Asia/Qatar" <?php echo ($settings['timezone'] ?? TIMEZONE) === 'Asia/Qatar' ? 'selected' : ''; ?>>Asia/Qatar</option>
                                    <option value="UTC" <?php echo ($settings['timezone'] ?? TIMEZONE) === 'UTC' ? 'selected' : ''; ?>>UTC</option>
                                    <option value="Europe/London" <?php echo ($settings['timezone'] ?? TIMEZONE) === 'Europe/London' ? 'selected' : ''; ?>>Europe/London</option>
                                    <option value="America/New_York" <?php echo ($settings['timezone'] ?? TIMEZONE) === 'America/New_York' ? 'selected' : ''; ?>>America/New_York</option>
                                </select>
                            </div>
                            
                            <div>
                                <label for="session_timeout" class="block text-sm font-medium text-gray-700">Session Timeout (minutes)</label>
                                <input type="number" id="session_timeout" name="session_timeout" value="<?php echo $settings['session_timeout'] ?? SESSION_TIMEOUT / 60; ?>"
                                       class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                            </div>
                            
                            <div>
                                <label for="password_min_length" class="block text-sm font-medium text-gray-700">Minimum Password Length</label>
                                <input type="number" id="password_min_length" name="password_min_length" value="<?php echo $settings['password_min_length'] ?? PASSWORD_MIN_LENGTH; ?>"
                                       class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                            </div>
                        </div>
                        
                        <div class="mt-6">
                            <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                                Update General Settings
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Email Settings Tab -->
                <div id="tab-content-email" class="tab-content p-6 hidden">
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="update_email">
                        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                        
                        <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                            <div>
                                <label for="smtp_host" class="block text-sm font-medium text-gray-700">SMTP Host</label>
                                <input type="text" id="smtp_host" name="smtp_host" value="<?php echo htmlspecialchars($settings['smtp_host'] ?? ''); ?>"
                                       class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                            </div>
                            
                            <div>
                                <label for="smtp_port" class="block text-sm font-medium text-gray-700">SMTP Port</label>
                                <input type="number" id="smtp_port" name="smtp_port" value="<?php echo $settings['smtp_port'] ?? 587; ?>"
                                       class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                            </div>
                            
                            <div>
                                <label for="smtp_username" class="block text-sm font-medium text-gray-700">SMTP Username</label>
                                <input type="text" id="smtp_username" name="smtp_username" value="<?php echo htmlspecialchars($settings['smtp_username'] ?? ''); ?>"
                                       class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                            </div>
                            
                            <div>
                                <label for="smtp_password" class="block text-sm font-medium text-gray-700">SMTP Password</label>
                                <input type="password" id="smtp_password" name="smtp_password" value="<?php echo htmlspecialchars($settings['smtp_password'] ?? ''); ?>"
                                       class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                            </div>
                            
                            <div>
                                <label for="from_email" class="block text-sm font-medium text-gray-700">From Email</label>
                                <input type="email" id="from_email" name="from_email" value="<?php echo htmlspecialchars($settings['from_email'] ?? ''); ?>"
                                       class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                            </div>
                            
                            <div>
                                <label for="from_name" class="block text-sm font-medium text-gray-700">From Name</label>
                                <input type="text" id="from_name" name="from_name" value="<?php echo htmlspecialchars($settings['from_name'] ?? APP_NAME); ?>"
                                       class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                            </div>
                        </div>
                        
                        <div class="mt-6">
                            <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                                Update Email Settings
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Security Settings Tab -->
                <div id="tab-content-security" class="tab-content p-6 hidden">
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="update_security">
                        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                        
                        <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                            <div>
                                <label for="max_login_attempts" class="block text-sm font-medium text-gray-700">Maximum Login Attempts</label>
                                <input type="number" id="max_login_attempts" name="max_login_attempts" value="<?php echo $settings['max_login_attempts'] ?? 5; ?>"
                                       class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                            </div>
                            
                            <div>
                                <label for="lockout_duration" class="block text-sm font-medium text-gray-700">Lockout Duration (minutes)</label>
                                <input type="number" id="lockout_duration" name="lockout_duration" value="<?php echo $settings['lockout_duration'] ?? 30; ?>"
                                       class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                            </div>
                            
                            <div>
                                <label for="require_2fa" class="block text-sm font-medium text-gray-700">Require 2FA for Admins</label>
                                <select id="require_2fa" name="require_2fa" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                    <option value="0" <?php echo ($settings['require_2fa'] ?? 0) == 0 ? 'selected' : ''; ?>>No</option>
                                    <option value="1" <?php echo ($settings['require_2fa'] ?? 0) == 1 ? 'selected' : ''; ?>>Yes</option>
                                </select>
                            </div>
                            
                            <div>
                                <label for="session_regenerate" class="block text-sm font-medium text-gray-700">Regenerate Session ID</label>
                                <select id="session_regenerate" name="session_regenerate" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                    <option value="0" <?php echo ($settings['session_regenerate'] ?? 0) == 0 ? 'selected' : ''; ?>>Never</option>
                                    <option value="1" <?php echo ($settings['session_regenerate'] ?? 0) == 1 ? 'selected' : ''; ?>>On Login</option>
                                    <option value="2" <?php echo ($settings['session_regenerate'] ?? 0) == 2 ? 'selected' : ''; ?>>Every Request</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="mt-6">
                            <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                                Update Security Settings
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Maintenance Tab -->
                <div id="tab-content-maintenance" class="tab-content p-6 hidden">
                    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                        <div class="bg-gray-50 p-6 rounded-lg">
                            <h3 class="text-lg font-medium text-gray-900 mb-4">System Cache</h3>
                            <p class="text-sm text-gray-600 mb-4">Clear system cache to free up memory and improve performance.</p>
                            <form method="POST" action="" class="inline">
                                <input type="hidden" name="action" value="clear_cache">
                                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                <button type="submit" class="bg-yellow-600 text-white px-4 py-2 rounded-md hover:bg-yellow-700 focus:outline-none focus:ring-2 focus:ring-yellow-500 focus:ring-offset-2">
                                    Clear Cache
                                </button>
                            </form>
                        </div>
                        
                        <div class="bg-gray-50 p-6 rounded-lg">
                            <h3 class="text-lg font-medium text-gray-900 mb-4">Database Backup</h3>
                            <p class="text-sm text-gray-600 mb-4">Create a backup of the current database.</p>
                            <form method="POST" action="" class="inline">
                                <input type="hidden" name="action" value="backup_database">
                                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                <button type="submit" class="bg-green-600 text-white px-4 py-2 rounded-md hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2">
                                    Create Backup
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function showTab(tabName) {
    // Hide all tab contents
    document.querySelectorAll('.tab-content').forEach(content => {
        content.classList.add('hidden');
    });
    
    // Remove active class from all tab buttons
    document.querySelectorAll('.tab-button').forEach(button => {
        button.classList.remove('border-primary-500', 'text-primary-600');
        button.classList.add('border-transparent', 'text-gray-500');
    });
    
    // Show selected tab content
    document.getElementById('tab-content-' + tabName).classList.remove('hidden');
    
    // Add active class to selected tab button
    document.getElementById('tab-' + tabName).classList.remove('border-transparent', 'text-gray-500');
    document.getElementById('tab-' + tabName).classList.add('border-primary-500', 'text-primary-600');
}

// Show general tab by default
document.addEventListener('DOMContentLoaded', function() {
    showTab('general');
});
</script>

<?php include '../templates/footer.php'; ?>

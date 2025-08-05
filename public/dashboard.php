<?php
// Resgrow CRM - Main Dashboard
// Phase 1: Project Setup & Auth

require_once '../includes/session.php';
require_once '../includes/functions.php';

// Require login
SessionManager::requireLogin();

// Redirect to role-specific dashboard
$user_role = SessionManager::getRole();

switch ($user_role) {
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
        // If role is not recognized, show generic dashboard
        $page_title = 'Dashboard';
        include '../templates/header.php';
        ?>
        
        <div class="min-h-screen bg-gray-50">
            <!-- Navigation -->
            <nav class="bg-white shadow-sm border-b border-gray-200">
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div class="flex justify-between h-16">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <h1 class="text-xl font-semibold text-gray-900"><?php echo APP_NAME; ?></h1>
                            </div>
                        </div>
                        
                        <div class="flex items-center space-x-4">
                            <span class="text-sm text-gray-700">
                                Welcome, <?php echo htmlspecialchars(SessionManager::getUsername()); ?>
                            </span>
                            <div class="relative dropdown">
                                <button class="flex items-center text-sm bg-white rounded-full focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500" id="user-menu-button">
                                    <span class="sr-only">Open user menu</span>
                                    <div class="h-8 w-8 bg-primary-600 rounded-full flex items-center justify-center">
                                        <span class="text-white text-sm font-medium">
                                            <?php echo strtoupper(substr(SessionManager::getUsername(), 0, 1)); ?>
                                        </span>
                                    </div>
                                </button>
                                
                                <div class="hidden origin-top-right absolute right-0 mt-2 w-48 rounded-md shadow-lg bg-white ring-1 ring-black ring-opacity-5" id="user-menu">
                                    <div class="py-1">
                                        <a href="profile.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Profile</a>
                                        <a href="settings.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Settings</a>
                                        <a href="logout.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Sign out</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </nav>

            <!-- Main Content -->
            <main class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
                <div class="px-4 py-6 sm:px-0">
                    <div class="text-center">
                        <div class="mx-auto h-32 w-32 bg-primary-100 rounded-full flex items-center justify-center mb-6">
                            <svg class="h-16 w-16 text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                            </svg>
                        </div>
                        
                        <h1 class="text-3xl font-bold text-gray-900 mb-4">
                            Welcome to <?php echo APP_NAME; ?>
                        </h1>
                        
                        <p class="text-lg text-gray-600 mb-8">
                            Marketing & Sales Management System for Qatar's F&B Market
                        </p>
                        
                        <div class="bg-yellow-50 border border-yellow-200 rounded-md p-4 mb-8">
                            <div class="flex">
                                <div class="ml-3">
                                    <h3 class="text-sm font-medium text-yellow-800">
                                        Role Not Configured
                                    </h3>
                                    <div class="mt-2 text-sm text-yellow-700">
                                        <p>Your account role (<?php echo htmlspecialchars($user_role); ?>) is not properly configured. Please contact your administrator.</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="flex justify-center">
                            <a href="logout.php" class="bg-primary-600 hover:bg-primary-700 text-white font-medium py-2 px-4 rounded transition duration-200">
                                Sign Out
                            </a>
                        </div>
                    </div>
                </div>
            </main>
        </div>

        <script>
        // User menu dropdown
        document.addEventListener('DOMContentLoaded', function() {
            const menuButton = document.getElementById('user-menu-button');
            const menu = document.getElementById('user-menu');
            
            menuButton.addEventListener('click', function() {
                menu.classList.toggle('hidden');
            });
            
            // Close menu when clicking outside
            document.addEventListener('click', function(e) {
                if (!menuButton.contains(e.target) && !menu.contains(e.target)) {
                    menu.classList.add('hidden');
                }
            });
        });
        </script>

        <?php
        include '../templates/footer.php';
        break;
}
?>
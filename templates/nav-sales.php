<?php
// Sales Navigation Template
$current_page = basename($_SERVER['PHP_SELF'], '.php');
?>

<nav class="bg-white shadow">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
            <div class="flex">
                <div class="flex-shrink-0 flex items-center">
                    <h2 class="text-xl font-bold text-gray-900">Resgrow CRM</h2>
                </div>
                <div class="hidden sm:ml-6 sm:flex sm:space-x-8">
                    <a href="dashboard.php" 
                       class="<?php echo $current_page === 'dashboard' ? 'border-blue-500 text-gray-900' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'; ?> inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium">
                        Dashboard
                    </a>
                    <a href="leads.php" 
                       class="<?php echo in_array($current_page, ['leads', 'lead-detail']) ? 'border-blue-500 text-gray-900' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'; ?> inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium">
                        Leads
                    </a>
<<<<<<< HEAD
=======
                    <a href="feedback.php" 
                       class="<?php echo $current_page === 'feedback' ? 'border-blue-500 text-gray-900' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'; ?> inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium">
                        Feedback
                    </a>
                    <a href="activity-tracker.php" 
                       class="<?php echo $current_page === 'activity-tracker' ? 'border-blue-500 text-gray-900' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'; ?> inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium">
                        Activity Tracker
                    </a>
>>>>>>> e981dd606dbc3d13315396933ec31366209c7f6d
                    <a href="reports.php" 
                       class="<?php echo $current_page === 'reports' ? 'border-blue-500 text-gray-900' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'; ?> inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium">
                        Reports
                    </a>
                    <?php if ($_SESSION['user_role'] === 'admin'): ?>
                    <a href="../admin/dashboard.php" 
                       class="border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium">
                        Admin
                    </a>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="hidden sm:ml-6 sm:flex sm:items-center">
                <div class="ml-3 relative">
                    <div class="flex items-center space-x-4">
                        <span class="text-sm text-gray-700">
                            <?php echo htmlspecialchars($_SESSION['user_name']); ?>
                            (<?php echo ucfirst($_SESSION['user_role']); ?>)
                        </span>
                        <a href="../public/logout.php" 
                           class="bg-gray-800 text-white px-3 py-2 rounded-md text-sm font-medium hover:bg-gray-700">
                            Logout
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- Mobile menu button -->
            <div class="-mr-2 flex items-center sm:hidden">
                <button type="button" 
                        class="bg-white inline-flex items-center justify-center p-2 rounded-md text-gray-400 hover:text-gray-500 hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-blue-500"
                        onclick="toggleMobileMenu()">
                    <svg class="block h-6 w-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <!-- Mobile menu -->
    <div class="sm:hidden hidden" id="mobile-menu">
        <div class="pt-2 pb-3 space-y-1">
            <a href="dashboard.php" 
               class="<?php echo $current_page === 'dashboard' ? 'bg-blue-50 border-blue-500 text-blue-700' : 'border-transparent text-gray-600 hover:bg-gray-50 hover:border-gray-300 hover:text-gray-800'; ?> block pl-3 pr-4 py-2 border-l-4 text-base font-medium">
                Dashboard
            </a>
            <a href="leads.php" 
               class="<?php echo in_array($current_page, ['leads', 'lead-detail']) ? 'bg-blue-50 border-blue-500 text-blue-700' : 'border-transparent text-gray-600 hover:bg-gray-50 hover:border-gray-300 hover:text-gray-800'; ?> block pl-3 pr-4 py-2 border-l-4 text-base font-medium">
                Leads
            </a>
<<<<<<< HEAD
=======
            <a href="feedback.php" 
               class="<?php echo $current_page === 'feedback' ? 'bg-blue-50 border-blue-500 text-blue-700' : 'border-transparent text-gray-600 hover:bg-gray-50 hover:border-gray-300 hover:text-gray-800'; ?> block pl-3 pr-4 py-2 border-l-4 text-base font-medium">
                Feedback
            </a>
            <a href="activity-tracker.php" 
               class="<?php echo $current_page === 'activity-tracker' ? 'bg-blue-50 border-blue-500 text-blue-700' : 'border-transparent text-gray-600 hover:bg-gray-50 hover:border-gray-300 hover:text-gray-800'; ?> block pl-3 pr-4 py-2 border-l-4 text-base font-medium">
                Activity Tracker
            </a>
>>>>>>> e981dd606dbc3d13315396933ec31366209c7f6d
            <a href="reports.php" 
               class="<?php echo $current_page === 'reports' ? 'bg-blue-50 border-blue-500 text-blue-700' : 'border-transparent text-gray-600 hover:bg-gray-50 hover:border-gray-300 hover:text-gray-800'; ?> block pl-3 pr-4 py-2 border-l-4 text-base font-medium">
                Reports
            </a>
            <?php if ($_SESSION['user_role'] === 'admin'): ?>
            <a href="../admin/dashboard.php" 
               class="border-transparent text-gray-600 hover:bg-gray-50 hover:border-gray-300 hover:text-gray-800 block pl-3 pr-4 py-2 border-l-4 text-base font-medium">
                Admin
            </a>
            <?php endif; ?>
            <a href="../public/logout.php" 
               class="border-transparent text-gray-600 hover:bg-gray-50 hover:border-gray-300 hover:text-gray-800 block pl-3 pr-4 py-2 border-l-4 text-base font-medium">
                Logout
            </a>
        </div>
    </div>
</nav>

<script>
function toggleMobileMenu() {
    const menu = document.getElementById('mobile-menu');
    menu.classList.toggle('hidden');
}
<<<<<<< HEAD
</script>
=======
</script>
>>>>>>> e981dd606dbc3d13315396933ec31366209c7f6d

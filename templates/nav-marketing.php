<?php
// Marketing Navigation Template
$current_page = basename($_SERVER['PHP_SELF'], '.php');
?>

<nav class="bg-white shadow border-b border-gray-200">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
            
            <!-- Logo and Primary Navigation -->
            <div class="flex">
                <div class="flex-shrink-0 flex items-center">
                    <a href="dashboard.php" class="flex items-center">
                        <div class="h-8 w-8 bg-primary-600 rounded-lg flex items-center justify-center">
                            <svg class="h-5 w-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                            </svg>
                        </div>
                        <span class="ml-2 text-xl font-semibold text-gray-900"><?php echo APP_NAME; ?></span>
                    </a>
                </div>
                
                <!-- Desktop Navigation Links -->
                <div class="hidden sm:ml-6 sm:flex sm:space-x-8">
                    <a href="dashboard.php" 
                       class="<?php echo $current_page === 'dashboard' ? 'border-primary-500 text-gray-900' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'; ?> inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium">
                        Dashboard
                    </a>
                    <a href="campaigns.php" 
                       class="<?php echo $current_page === 'campaigns' ? 'border-primary-500 text-gray-900' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'; ?> inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium">
                        Campaigns
                    </a>
                    <a href="new-campaign.php" 
                       class="<?php echo $current_page === 'new-campaign' ? 'border-primary-500 text-gray-900' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'; ?> inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium">
                        New Campaign
                    </a>
                    <?php if (SessionManager::hasRole('admin')): ?>
                    <a href="../admin/analytics.php" 
                       class="border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium">
                        Analytics
                    </a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Right Side Navigation -->
            <div class="hidden sm:ml-6 sm:flex sm:items-center">
                <div class="ml-3 relative">
                    <div class="flex items-center space-x-4">
                        <span class="text-sm text-gray-700">
                            <?php echo htmlspecialchars(SessionManager::getUsername()); ?>
                            (<?php echo ucfirst(SessionManager::getRole()); ?>)
                        </span>
                        <a href="../public/logout.php" 
                           class="bg-gray-800 text-white px-3 py-2 rounded-md text-sm font-medium hover:bg-gray-700">
                            Logout
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- Mobile menu button -->
            <div class="flex items-center sm:hidden">
                <button type="button" class="inline-flex items-center justify-center p-2 rounded-md text-gray-400 hover:text-gray-500 hover:bg-gray-100" id="mobile-menu-button">
                    <span class="sr-only">Open main menu</span>
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <!-- Mobile menu -->
    <div class="hidden sm:hidden" id="mobile-menu">
        <div class="pt-2 pb-3 space-y-1">
            <a href="dashboard.php" 
               class="<?php echo $current_page === 'dashboard' ? 'bg-primary-50 border-primary-500 text-primary-700' : 'border-transparent text-gray-600 hover:bg-gray-50 hover:border-gray-300 hover:text-gray-800'; ?> block pl-3 pr-4 py-2 border-l-4 text-base font-medium">
                Dashboard
            </a>
            <a href="campaigns.php" 
               class="<?php echo $current_page === 'campaigns' ? 'bg-primary-50 border-primary-500 text-primary-700' : 'border-transparent text-gray-600 hover:bg-gray-50 hover:border-gray-300 hover:text-gray-800'; ?> block pl-3 pr-4 py-2 border-l-4 text-base font-medium">
                Campaigns
            </a>
            <a href="new-campaign.php" 
               class="<?php echo $current_page === 'new-campaign' ? 'bg-primary-50 border-primary-500 text-primary-700' : 'border-transparent text-gray-600 hover:bg-gray-50 hover:border-gray-300 hover:text-gray-800'; ?> block pl-3 pr-4 py-2 border-l-4 text-base font-medium">
                New Campaign
            </a>
            <?php if (SessionManager::hasRole('admin')): ?>
            <a href="../admin/analytics.php" 
               class="border-transparent text-gray-600 hover:bg-gray-50 hover:border-gray-300 hover:text-gray-800 block pl-3 pr-4 py-2 border-l-4 text-base font-medium">
                Analytics
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
document.addEventListener('DOMContentLoaded', function() {
    // Mobile menu toggle
    const mobileMenuButton = document.getElementById('mobile-menu-button');
    const mobileMenu = document.getElementById('mobile-menu');
    
    if (mobileMenuButton && mobileMenu) {
        mobileMenuButton.addEventListener('click', function() {
            mobileMenu.classList.toggle('hidden');
        });
    }
});
</script>

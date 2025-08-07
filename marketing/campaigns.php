<?php
// Resgrow CRM - Campaigns Management List
// Phase 4: Campaign Creation Module

require_once '../includes/session.php';
require_once '../includes/functions.php';
require_once '../includes/db.php';

// Require marketing or admin role
if (!SessionManager::hasRole('marketing') && !SessionManager::hasRole('admin')) {
    header('Location: ../public/dashboard.php');
    exit();
}

// Handle campaign actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $campaign_id = (int)($_POST['campaign_id'] ?? 0);
    $csrf_token = $_POST['csrf_token'] ?? '';
    
    if (!verify_csrf_token($csrf_token)) {
        set_flash_message('error', 'Security token mismatch.');
    } else {
        switch ($action) {
            case 'toggle_status':
                toggle_campaign_status($campaign_id);
                break;
            case 'delete_campaign':
                delete_campaign($campaign_id);
                break;
            case 'duplicate_campaign':
                duplicate_campaign($campaign_id);
                break;
        }
    }
    
    header('Location: campaigns.php');
    exit();
}

// Get campaigns with pagination and filters
$page = (int)($_GET['page'] ?? 1);
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? '';
$platform_filter = $_GET['platform'] ?? '';

$campaigns_data = get_campaigns_paginated($page, $search, $status_filter, $platform_filter);

$page_title = 'Campaigns Management';
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
                
                <a href="campaigns.php" class="flex items-center px-4 py-2 text-gray-700 bg-primary-100 rounded-lg">
                    <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
                    </svg>
                    Campaigns
                </a>
                
                <a href="assign-leads.php" class="flex items-center px-4 py-2 text-gray-600 hover:bg-gray-100 rounded-lg">
                    <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                    </svg>
                    Assign Leads
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
                        <h1 class="text-2xl font-semibold text-gray-900 ml-10 lg:ml-0">Campaigns</h1>
                    </div>
                    
                    <div class="flex items-center space-x-4">
                        <a href="new-campaign.php" class="bg-primary-600 hover:bg-primary-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                            <svg class="-ml-1 mr-2 h-4 w-4 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                            </svg>
                            New Campaign
                        </a>
                    </div>
                </div>
            </div>
        </header>

        <!-- Content -->
        <main class="p-4 sm:p-6 lg:p-8">
            
            <!-- Campaign Stats Summary -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="p-5">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <div class="w-8 h-8 bg-blue-100 rounded-md flex items-center justify-center">
                                    <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
                                    </svg>
                                </div>
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 truncate">Total Campaigns</dt>
                                    <dd class="text-lg font-medium text-gray-900"><?php echo $campaigns_data['total']; ?></dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="p-5">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <div class="w-8 h-8 bg-green-100 rounded-md flex items-center justify-center">
                                    <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                                    </svg>
                                </div>
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 truncate">Active</dt>
                                    <dd class="text-lg font-medium text-gray-900"><?php echo $campaigns_data['active_count']; ?></dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="p-5">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <div class="w-8 h-8 bg-yellow-100 rounded-md flex items-center justify-center">
                                    <svg class="w-5 h-5 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"></path>
                                    </svg>
                                </div>
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 truncate">Total Budget</dt>
                                    <dd class="text-lg font-medium text-gray-900"><?php echo format_currency($campaigns_data['total_budget']); ?></dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="p-5">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <div class="w-8 h-8 bg-purple-100 rounded-md flex items-center justify-center">
                                    <svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                                    </svg>
                                </div>
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 truncate">Leads Generated</dt>
                                    <dd class="text-lg font-medium text-gray-900"><?php echo $campaigns_data['total_leads']; ?></dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Filters -->
            <div class="bg-white shadow rounded-lg mb-6">
                <div class="p-6">
                    <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                        <div>
                            <label for="search" class="block text-sm font-medium text-gray-700 mb-1">Search</label>
                            <input type="text" id="search" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                                   placeholder="Campaign title or product..." 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-primary-500 focus:border-primary-500">
                        </div>
                        
                        <div>
                            <label for="status" class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                            <select id="status" name="status" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-primary-500 focus:border-primary-500">
                                <option value="">All Status</option>
                                <option value="draft" <?php echo $status_filter === 'draft' ? 'selected' : ''; ?>>Draft</option>
                                <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option>
                                <option value="paused" <?php echo $status_filter === 'paused' ? 'selected' : ''; ?>>Paused</option>
                                <option value="completed" <?php echo $status_filter === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                <option value="cancelled" <?php echo $status_filter === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                            </select>
                        </div>
                        
                        <div>
                            <label for="platform" class="block text-sm font-medium text-gray-700 mb-1">Platform</label>
                            <select id="platform" name="platform" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-primary-500 focus:border-primary-500">
                                <option value="">All Platforms</option>
                                <option value="Meta" <?php echo $platform_filter === 'Meta' ? 'selected' : ''; ?>>Meta</option>
                                <option value="TikTok" <?php echo $platform_filter === 'TikTok' ? 'selected' : ''; ?>>TikTok</option>
                                <option value="Snapchat" <?php echo $platform_filter === 'Snapchat' ? 'selected' : ''; ?>>Snapchat</option>
                                <option value="WhatsApp" <?php echo $platform_filter === 'WhatsApp' ? 'selected' : ''; ?>>WhatsApp</option>
                                <option value="Google" <?php echo $platform_filter === 'Google' ? 'selected' : ''; ?>>Google</option>
                            </select>
                        </div>
                        
                        <div class="flex items-end">
                            <button type="submit" class="w-full bg-primary-600 hover:bg-primary-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                                Filter
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Campaigns Table -->
            <div class="bg-white shadow rounded-lg overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900">
                        Campaigns (<?php echo $campaigns_data['total']; ?> total)
                    </h3>
                </div>
                
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Campaign</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Platforms</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Budget</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Performance</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Timeline</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php if (empty($campaigns_data['campaigns'])): ?>
                            <tr>
                                <td colspan="7" class="px-6 py-12 text-center text-gray-500">
                                    <div class="flex flex-col items-center">
                                        <svg class="w-12 h-12 text-gray-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
                                        </svg>
                                        <p class="text-lg font-medium text-gray-900 mb-1">No campaigns found</p>
                                        <p class="text-sm text-gray-500 mb-4">Get started by creating your first campaign</p>
                                        <a href="new-campaign.php" class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-primary-600 hover:bg-primary-700">
                                            <svg class="-ml-1 mr-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                                            </svg>
                                            Create Campaign
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php else: ?>
                            <?php foreach ($campaigns_data['campaigns'] as $campaign): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4">
                                    <div class="flex items-start">
                                        <div class="flex-1">
                                            <div class="text-sm font-medium text-gray-900">
                                                <?php echo htmlspecialchars($campaign['title']); ?>
                                            </div>
                                            <div class="text-sm text-gray-500">
                                                <?php echo htmlspecialchars($campaign['product_name']); ?>
                                            </div>
                                            <?php if ($campaign['assigned_to_name']): ?>
                                                <div class="text-xs text-gray-400 mt-1">
                                                    Assigned to: <?php echo htmlspecialchars($campaign['assigned_to_name']); ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <?php echo get_status_badge($campaign['status']); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex flex-wrap gap-1">
                                        <?php 
                                        $platforms = json_decode($campaign['platforms'], true) ?? [];
                                        foreach (array_slice($platforms, 0, 3) as $platform): 
                                        ?>
                                            <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-800">
                                                <?php echo htmlspecialchars($platform); ?>
                                            </span>
                                        <?php endforeach; ?>
                                        <?php if (count($platforms) > 3): ?>
                                            <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-gray-100 text-gray-800">
                                                +<?php echo count($platforms) - 3; ?> more
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?php echo format_currency($campaign['budget_qr']); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900">
                                        <?php echo $campaign['leads_generated']; ?> leads
                                    </div>
                                    <div class="text-sm text-gray-500">
                                        <?php echo $campaign['deals_closed']; ?> deals • <?php echo format_currency($campaign['revenue']); ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <div><?php echo format_date($campaign['start_date'], 'M j'); ?> - <?php echo format_date($campaign['end_date'], 'M j, Y'); ?></div>
                                    <div class="text-xs">
                                        <?php
                                        $days_remaining = (strtotime($campaign['end_date']) - time()) / (60 * 60 * 24);
                                        if ($days_remaining > 0) {
                                            echo ceil($days_remaining) . ' days left';
                                        } else {
                                            echo abs(floor($days_remaining)) . ' days ago';
                                        }
                                        ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <div class="flex items-center justify-end space-x-2">
                                        <a href="edit-campaign.php?id=<?php echo $campaign['id']; ?>" 
                                           class="text-primary-600 hover:text-primary-900">Edit</a>
                                        
                                        <form method="POST" class="inline" onsubmit="return confirmAction('change status of this campaign?')">
                                            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                            <input type="hidden" name="action" value="toggle_status">
                                            <input type="hidden" name="campaign_id" value="<?php echo $campaign['id']; ?>">
                                            <button type="submit" class="text-yellow-600 hover:text-yellow-900">
                                                <?php echo $campaign['status'] === 'active' ? 'Pause' : 'Activate'; ?>
                                            </button>
                                        </form>
                                        
                                        <form method="POST" class="inline" onsubmit="return confirmAction('duplicate this campaign?')">
                                            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                            <input type="hidden" name="action" value="duplicate_campaign">
                                            <input type="hidden" name="campaign_id" value="<?php echo $campaign['id']; ?>">
                                            <button type="submit" class="text-blue-600 hover:text-blue-900">Duplicate</button>
                                        </form>
                                        
                                        <form method="POST" class="inline" onsubmit="return confirmAction('permanently delete this campaign? This cannot be undone.')">
                                            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                            <input type="hidden" name="action" value="delete_campaign">
                                            <input type="hidden" name="campaign_id" value="<?php echo $campaign['id']; ?>">
                                            <button type="submit" class="text-red-600 hover:text-red-900">Delete</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination -->
                <?php if ($campaigns_data['pagination']['total_pages'] > 1): ?>
                <div class="bg-white px-4 py-3 flex items-center justify-between border-t border-gray-200 sm:px-6">
                    <div class="flex-1 flex justify-between sm:hidden">
                        <?php if ($campaigns_data['pagination']['has_prev']): ?>
                        <a href="?page=<?php echo $campaigns_data['pagination']['current_page'] - 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>&platform=<?php echo urlencode($platform_filter); ?>" 
                           class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                            Previous
                        </a>
                        <?php endif; ?>
                        <?php if ($campaigns_data['pagination']['has_next']): ?>
                        <a href="?page=<?php echo $campaigns_data['pagination']['current_page'] + 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>&platform=<?php echo urlencode($platform_filter); ?>" 
                           class="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                            Next
                        </a>
                        <?php endif; ?>
                    </div>
                    <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                        <div>
                            <p class="text-sm text-gray-700">
                                Showing <span class="font-medium"><?php echo ($campaigns_data['pagination']['current_page'] - 1) * $campaigns_data['pagination']['records_per_page'] + 1; ?></span>
                                to <span class="font-medium"><?php echo min($campaigns_data['pagination']['current_page'] * $campaigns_data['pagination']['records_per_page'], $campaigns_data['total']); ?></span>
                                of <span class="font-medium"><?php echo $campaigns_data['total']; ?></span> results
                            </p>
                        </div>
                        <div>
                            <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px">
                                <?php if ($campaigns_data['pagination']['has_prev']): ?>
                                <a href="?page=<?php echo $campaigns_data['pagination']['current_page'] - 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>&platform=<?php echo urlencode($platform_filter); ?>" 
                                   class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                                    Previous
                                </a>
                                <?php endif; ?>
                                
                                <?php for ($i = max(1, $campaigns_data['pagination']['current_page'] - 2); $i <= min($campaigns_data['pagination']['total_pages'], $campaigns_data['pagination']['current_page'] + 2); $i++): ?>
                                <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>&platform=<?php echo urlencode($platform_filter); ?>" 
                                   class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium <?php echo $i === $campaigns_data['pagination']['current_page'] ? 'text-primary-600 bg-primary-50' : 'text-gray-700 hover:bg-gray-50'; ?>">
                                    <?php echo $i; ?>
                                </a>
                                <?php endfor; ?>
                                
                                <?php if ($campaigns_data['pagination']['has_next']): ?>
                                <a href="?page=<?php echo $campaigns_data['pagination']['current_page'] + 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>&platform=<?php echo urlencode($platform_filter); ?>" 
                                   class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                                    Next
                                </a>
                                <?php endif; ?>
                            </nav>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
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

// Confirmation dialogs
function confirmAction(message) {
    return confirm('Are you sure you want to ' + message);
}

// Auto-submit filter form with debounce
let filterTimeout;
document.querySelectorAll('#search, #status, #platform').forEach(input => {
    input.addEventListener('input', function() {
        clearTimeout(filterTimeout);
        filterTimeout = setTimeout(() => {
            this.form.submit();
        }, 500);
    });
});
</script>

<?php
include '../templates/footer.php';

// Helper functions
function get_campaigns_paginated($page = 1, $search = '', $status_filter = '', $platform_filter = '', $records_per_page = 20) {
    global $db;
    
    $offset = ($page - 1) * $records_per_page;
    $where_conditions = [];
    $params = [];
    $types = '';
    
    if (!empty($search)) {
        $where_conditions[] = "(c.title LIKE ? OR c.product_name LIKE ?)";
        $search_param = "%{$search}%";
        $params[] = $search_param;
        $params[] = $search_param;
        $types .= 'ss';
    }
    
    if (!empty($status_filter)) {
        $where_conditions[] = "c.status = ?";
        $params[] = $status_filter;
        $types .= 's';
    }
    
    if (!empty($platform_filter)) {
        $where_conditions[] = "JSON_CONTAINS(c.platforms, ?)";
        $params[] = '"' . $platform_filter . '"';
        $types .= 's';
    }
    
    $where_sql = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
    
    // Get total count and stats
    $count_sql = "
        SELECT 
            COUNT(*) as total,
            COUNT(CASE WHEN c.status = 'active' THEN 1 END) as active_count,
            COALESCE(SUM(c.budget_qr), 0) as total_budget,
            COALESCE(SUM(lead_counts.leads), 0) as total_leads
        FROM campaigns c
        LEFT JOIN (
            SELECT campaign_id, COUNT(*) as leads 
            FROM leads 
            GROUP BY campaign_id
        ) lead_counts ON c.id = lead_counts.campaign_id
        {$where_sql}
    ";
    
    $count_stmt = $db->prepare($count_sql);
    if (!empty($params)) {
        $count_stmt->bind_param($types, ...$params);
    }
    $count_stmt->execute();
    $stats = $count_stmt->get_result()->fetch_assoc();
    
    // Get campaigns with performance data
    $sql = "
        SELECT 
            c.*,
            u.name as assigned_to_name,
            creator.name as created_by_name,
            COALESCE(lead_stats.leads_generated, 0) as leads_generated,
            COALESCE(lead_stats.deals_closed, 0) as deals_closed,
            COALESCE(lead_stats.revenue, 0) as revenue
        FROM campaigns c
        LEFT JOIN users u ON c.assigned_to = u.id
        LEFT JOIN users creator ON c.created_by = creator.id
        LEFT JOIN (
            SELECT 
                campaign_id,
                COUNT(*) as leads_generated,
                COUNT(CASE WHEN status = 'closed-won' THEN 1 END) as deals_closed,
                COALESCE(SUM(CASE WHEN status = 'closed-won' THEN sale_value_qr END), 0) as revenue
            FROM leads 
            GROUP BY campaign_id
        ) lead_stats ON c.id = lead_stats.campaign_id
        {$where_sql}
        ORDER BY c.created_at DESC 
        LIMIT ?, ?
    ";
    
    $params[] = $offset;
    $params[] = $records_per_page;
    $types .= 'ii';
    
    $stmt = $db->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $campaigns = [];
    while ($row = $result->fetch_assoc()) {
        $campaigns[] = $row;
    }
    
    $pagination = paginate($stats['total'], $records_per_page, $page);
    
    return [
        'campaigns' => $campaigns,
        'total' => $stats['total'],
        'active_count' => $stats['active_count'],
        'total_budget' => $stats['total_budget'],
        'total_leads' => $stats['total_leads'],
        'pagination' => $pagination
    ];
}

function toggle_campaign_status($campaign_id) {
    global $db;
    
    $stmt = $db->prepare("SELECT status FROM campaigns WHERE id = ?");
    $stmt->bind_param("i", $campaign_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        set_flash_message('error', 'Campaign not found.');
        return;
    }
    
    $campaign = $result->fetch_assoc();
    
    // Toggle between active and paused
    $new_status = $campaign['status'] === 'active' ? 'paused' : 'active';
    
    $stmt = $db->prepare("UPDATE campaigns SET status = ?, updated_at = NOW() WHERE id = ?");
    $stmt->bind_param("si", $new_status, $campaign_id);
    
    if ($stmt->execute()) {
        log_activity(SessionManager::getUserId(), 'campaign_status_change', "Changed campaign {$campaign_id} status to {$new_status}");
        set_flash_message('success', 'Campaign status updated successfully.');
    } else {
        set_flash_message('error', 'Failed to update campaign status.');
    }
}

function delete_campaign($campaign_id) {
    global $db;
    
    // Check if campaign has leads
    $stmt = $db->prepare("SELECT COUNT(*) as lead_count FROM leads WHERE campaign_id = ?");
    $stmt->bind_param("i", $campaign_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    
    if ($result['lead_count'] > 0) {
        set_flash_message('error', 'Cannot delete campaign with existing leads. Please reassign or delete leads first.');
        return;
    }
    
    $stmt = $db->prepare("DELETE FROM campaigns WHERE id = ?");
    $stmt->bind_param("i", $campaign_id);
    
    if ($stmt->execute()) {
        log_activity(SessionManager::getUserId(), 'campaign_delete', "Deleted campaign {$campaign_id}");
        set_flash_message('success', 'Campaign deleted successfully.');
    } else {
        set_flash_message('error', 'Failed to delete campaign.');
    }
}

function duplicate_campaign($campaign_id) {
    global $db;
    
    $stmt = $db->prepare("SELECT * FROM campaigns WHERE id = ?");
    $stmt->bind_param("i", $campaign_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        set_flash_message('error', 'Campaign not found.');
        return;
    }
    
    $campaign = $result->fetch_assoc();
    
    // Create duplicate with modified title and dates
    $new_title = $campaign['title'] . ' (Copy)';
    $new_start_date = date('Y-m-d');
    $new_end_date = date('Y-m-d', strtotime('+30 days'));
    
    $stmt = $db->prepare("
        INSERT INTO campaigns (
            title, product_name, description, platforms, budget_qr, 
            target_audience, objectives, created_by, assigned_to, 
            start_date, end_date, status, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'draft', NOW())
    ");
    
    $created_by = SessionManager::getUserId();
    
    $stmt->bind_param("ssssdssiiis", 
        $new_title, $campaign['product_name'], $campaign['description'], 
        $campaign['platforms'], $campaign['budget_qr'], $campaign['target_audience'], 
        $campaign['objectives'], $created_by, $campaign['assigned_to'],
        $new_start_date, $new_end_date
    );
    
    if ($stmt->execute()) {
        log_activity(SessionManager::getUserId(), 'campaign_duplicate', "Duplicated campaign {$campaign_id}");
        set_flash_message('success', 'Campaign duplicated successfully.');
    } else {
        set_flash_message('error', 'Failed to duplicate campaign.');
    }
}
?>
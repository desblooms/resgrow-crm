<?php
// Resgrow CRM - Daily Activity Tracker
// Phase 11: Daily Activity Tracker

require_once '../includes/session.php';
require_once '../includes/functions.php';
require_once '../includes/db.php';

// Require sales or admin role
if (!SessionManager::hasRole('sales') && !SessionManager::hasRole('admin')) {
    header('Location: ../public/dashboard.php');
    exit();
}

$page_title = 'Daily Activity Tracker';
$user_id = SessionManager::getUserId();
$user_role = SessionManager::getRole();

// Get date filter (default to today)
$selected_date = $_GET['date'] ?? date('Y-m-d');
$date_obj = new DateTime($selected_date);

// Handle activity submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $activity_date = $_POST['activity_date'] ?? date('Y-m-d');
    $leads_contacted = $_POST['leads_contacted'] ?? 0;
    $calls_made = $_POST['calls_made'] ?? 0;
    $qar_closed = $_POST['qar_closed'] ?? 0;
    $notes = $_POST['notes'] ?? '';
    
    try {
        global $db;
        
        // Check if activity already exists for this date
        $check_stmt = $db->prepare("SELECT id FROM daily_activity WHERE user_id = ? AND activity_date = ?");
        $check_stmt->bind_param("is", $user_id, $activity_date);
        $check_stmt->execute();
        $existing = $check_stmt->get_result()->fetch_assoc();
        
        if ($existing) {
            // Update existing activity
            $stmt = $db->prepare("UPDATE daily_activity SET leads_contacted = ?, calls_made = ?, qar_closed = ?, notes = ?, updated_at = NOW() WHERE user_id = ? AND activity_date = ?");
            $stmt->bind_param("iidsis", $leads_contacted, $calls_made, $qar_closed, $notes, $user_id, $activity_date);
        } else {
            // Insert new activity
            $stmt = $db->prepare("INSERT INTO daily_activity (user_id, activity_date, leads_contacted, calls_made, qar_closed, notes) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("isidss", $user_id, $activity_date, $leads_contacted, $calls_made, $qar_closed, $notes);
        }
        
        if ($stmt->execute()) {
            log_activity($user_id, 'activity_updated', "Updated daily activity for {$activity_date}");
            set_flash_message('success', 'Activity updated successfully');
            header('Location: activity-tracker.php?date=' . $activity_date);
            exit();
        } else {
            set_flash_message('error', 'Failed to update activity');
        }
    } catch (Exception $e) {
        set_flash_message('error', 'Error updating activity: ' . $e->getMessage());
    }
}

// Get daily activity data
try {
    global $db;
    
    // Get today's activity for current user
    $activity_query = "SELECT * FROM daily_activity WHERE user_id = ? AND activity_date = ?";
    $stmt = $db->prepare($activity_query);
    $stmt->bind_param("is", $user_id, $selected_date);
    $stmt->execute();
    $today_activity = $stmt->get_result()->fetch_assoc();
    
    // Get today's leads count
    $leads_query = "SELECT COUNT(*) as count FROM leads WHERE assigned_to = ? AND DATE(created_at) = ?";
    $stmt = $db->prepare($leads_query);
    $stmt->bind_param("is", $user_id, $selected_date);
    $stmt->execute();
    $today_leads = $stmt->get_result()->fetch_assoc()['count'];
    
    // Get today's closed deals
    $closed_query = "SELECT COUNT(*) as count, COALESCE(SUM(sale_value_qr), 0) as total_qar FROM leads WHERE assigned_to = ? AND DATE(created_at) = ? AND status = 'closed-won'";
    $stmt = $db->prepare($closed_query);
    $stmt->bind_param("is", $user_id, $selected_date);
    $stmt->execute();
    $today_closed = $stmt->get_result()->fetch_assoc();
    
    // Get weekly activity summary
    $week_start = date('Y-m-d', strtotime('monday this week'));
    $week_end = date('Y-m-d', strtotime('sunday this week'));
    
    $weekly_query = "
        SELECT 
            activity_date,
            leads_contacted,
            calls_made,
            qar_closed
        FROM daily_activity 
        WHERE user_id = ? AND activity_date BETWEEN ? AND ?
        ORDER BY activity_date ASC
    ";
    $stmt = $db->prepare($weekly_query);
    $stmt->bind_param("iss", $user_id, $week_start, $week_end);
    $stmt->execute();
    $weekly_activity = $stmt->get_result();
    
    // Get team activity (for admin)
    if ($user_role === 'admin') {
        $team_query = "
            SELECT 
                u.name,
                da.activity_date,
                da.leads_contacted,
                da.calls_made,
                da.qar_closed,
                da.notes
            FROM daily_activity da
            JOIN users u ON da.user_id = u.id
            WHERE da.activity_date = ? AND u.role = 'sales'
            ORDER BY da.qar_closed DESC
        ";
        $stmt = $db->prepare($team_query);
        $stmt->bind_param("s", $selected_date);
        $stmt->execute();
        $team_activity = $stmt->get_result();
    }
    
} catch (Exception $e) {
    set_flash_message('error', 'Error loading activity data: ' . $e->getMessage());
    $today_activity = null;
    $today_leads = 0;
    $today_closed = ['count' => 0, 'total_qar' => 0];
    $weekly_activity = [];
    $team_activity = [];
}

include '../templates/header.php';
?>

<div class="min-h-screen bg-gray-50">
    <!-- Navigation -->
    <?php include '../templates/nav-sales.php'; ?>
    
    <div class="py-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <!-- Page Header -->
            <div class="mb-8">
                <h1 class="text-3xl font-bold text-gray-900">Daily Activity Tracker</h1>
                <p class="mt-2 text-sm text-gray-600">
                    Track your daily activities, leads contacted, calls made, and QAR closed
                </p>
            </div>

            <!-- Flash Messages -->
            <?php include '../templates/flash-messages.php'; ?>

            <!-- Date Selector -->
            <div class="bg-white shadow rounded-lg mb-8">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h2 class="text-lg font-medium text-gray-900">Select Date</h2>
                </div>
                <div class="p-6">
                    <form method="GET" action="" class="flex items-center space-x-4">
                        <div>
                            <label for="date" class="block text-sm font-medium text-gray-700">Date</label>
                            <input type="date" id="date" name="date" value="<?php echo $selected_date; ?>"
                                   class="mt-1 block border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                        </div>
                        <div class="flex items-end">
                            <button type="submit" 
                                    class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                                View Activity
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Today's Summary Cards -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="p-5">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <svg class="h-6 w-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                                </svg>
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 truncate">Today's Leads</dt>
                                    <dd class="text-lg font-medium text-gray-900"><?php echo $today_leads; ?></dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="p-5">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <svg class="h-6 w-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path>
                                </svg>
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 truncate">Calls Made</dt>
                                    <dd class="text-lg font-medium text-gray-900"><?php echo $today_activity['calls_made'] ?? 0; ?></dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="p-5">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <svg class="h-6 w-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"></path>
                                </svg>
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 truncate">QAR Closed</dt>
                                    <dd class="text-lg font-medium text-gray-900"><?php echo number_format($today_activity['qar_closed'] ?? 0, 2); ?></dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="p-5">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <svg class="h-6 w-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 truncate">Deals Closed</dt>
                                    <dd class="text-lg font-medium text-gray-900"><?php echo $today_closed['count']; ?></dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Activity Form -->
            <div class="bg-white shadow rounded-lg mb-8">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h2 class="text-lg font-medium text-gray-900">Update Daily Activity</h2>
                </div>
                <div class="p-6">
                    <form method="POST" action="" class="space-y-6">
                        <input type="hidden" name="activity_date" value="<?php echo $selected_date; ?>">
                        
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                            <div>
                                <label for="leads_contacted" class="block text-sm font-medium text-gray-700">Leads Contacted</label>
                                <input type="number" id="leads_contacted" name="leads_contacted" min="0"
                                       value="<?php echo $today_activity['leads_contacted'] ?? 0; ?>"
                                       class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                            </div>
                            
                            <div>
                                <label for="calls_made" class="block text-sm font-medium text-gray-700">Calls Made</label>
                                <input type="number" id="calls_made" name="calls_made" min="0"
                                       value="<?php echo $today_activity['calls_made'] ?? 0; ?>"
                                       class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                            </div>
                            
                            <div>
                                <label for="qar_closed" class="block text-sm font-medium text-gray-700">QAR Closed</label>
                                <input type="number" id="qar_closed" name="qar_closed" min="0" step="0.01"
                                       value="<?php echo $today_activity['qar_closed'] ?? 0; ?>"
                                       class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                            </div>
                        </div>
                        
                        <div>
                            <label for="notes" class="block text-sm font-medium text-gray-700">Notes</label>
                            <textarea id="notes" name="notes" rows="3"
                                      class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                                      placeholder="Add any notes about today's activities..."><?php echo htmlspecialchars($today_activity['notes'] ?? ''); ?></textarea>
                        </div>
                        
                        <div class="flex justify-end">
                            <button type="submit" 
                                    class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                                Update Activity
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Weekly Activity Chart -->
            <div class="bg-white shadow rounded-lg mb-8">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h2 class="text-lg font-medium text-gray-900">Weekly Activity Summary</h2>
                </div>
                <div class="p-6">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Leads Contacted</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Calls Made</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">QAR Closed</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php 
                                $week_dates = [];
                                for ($i = 0; $i < 7; $i++) {
                                    $date = date('Y-m-d', strtotime($week_start . ' +' . $i . ' days'));
                                    $week_dates[$date] = ['leads_contacted' => 0, 'calls_made' => 0, 'qar_closed' => 0];
                                }
                                
                                if ($weekly_activity && $weekly_activity->num_rows > 0) {
                                    while ($activity = $weekly_activity->fetch_assoc()) {
                                        $week_dates[$activity['activity_date']] = $activity;
                                    }
                                }
                                
                                foreach ($week_dates as $date => $activity): 
                                    $is_today = $date === $selected_date;
                                ?>
                                    <tr class="<?php echo $is_today ? 'bg-blue-50' : ''; ?>">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                            <?php echo date('D, M j', strtotime($date)); ?>
                                            <?php if ($is_today): ?>
                                                <span class="ml-2 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                                    Today
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            <?php echo $activity['leads_contacted']; ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            <?php echo $activity['calls_made']; ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            <?php echo number_format($activity['qar_closed'], 2); ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Team Activity (Admin Only) -->
            <?php if ($user_role === 'admin' && isset($team_activity) && $team_activity->num_rows > 0): ?>
            <div class="bg-white shadow rounded-lg">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h2 class="text-lg font-medium text-gray-900">Team Activity - <?php echo date('M j, Y', strtotime($selected_date)); ?></h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Sales Agent</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Leads Contacted</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Calls Made</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">QAR Closed</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Notes</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php while ($member = $team_activity->fetch_assoc()): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                        <?php echo htmlspecialchars($member['name']); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <?php echo $member['leads_contacted']; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <?php echo $member['calls_made']; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <?php echo number_format($member['qar_closed'], 2); ?>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-900">
                                        <div class="max-w-xs truncate" title="<?php echo htmlspecialchars($member['notes']); ?>">
                                            <?php echo htmlspecialchars($member['notes']); ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include '../templates/footer.php'; ?>

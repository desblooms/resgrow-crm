<?php
// Resgrow CRM - Database Backup & Restore
// Complete database backup and restore functionality

require_once '../config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

check_login();
if ($_SESSION['user_role'] !== 'admin') {
    header('Location: ../public/dashboard.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$backup_dir = __DIR__ . '/backups/';
if (!is_dir($backup_dir)) {
    mkdir($backup_dir, 0755, true);
}

// Handle backup/restore actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'create_backup':
                $result = createDatabaseBackup($backup_dir);
                set_flash_message($result['success'] ? 'success' : 'error', $result['message']);
                if ($result['success']) {
                    log_activity($user_id, 'database_backup', "Created database backup: {$result['filename']}");
                }
                break;
                
            case 'restore_backup':
                if (isset($_POST['backup_file'])) {
                    $result = restoreDatabaseBackup($backup_dir . $_POST['backup_file'], $db);
                    set_flash_message($result['success'] ? 'success' : 'error', $result['message']);
                    if ($result['success']) {
                        log_activity($user_id, 'database_restore', "Restored database from: {$_POST['backup_file']}");
                    }
                }
                break;
                
            case 'delete_backup':
                if (isset($_POST['backup_file'])) {
                    $file_path = $backup_dir . $_POST['backup_file'];
                    if (file_exists($file_path) && unlink($file_path)) {
                        set_flash_message('success', 'Backup file deleted successfully');
                        log_activity($user_id, 'backup_deleted', "Deleted backup file: {$_POST['backup_file']}");
                    } else {
                        set_flash_message('error', 'Failed to delete backup file');
                    }
                }
                break;
        }
    }
}

function createDatabaseBackup($backup_dir) {
    try {
        $filename = 'resgrow_crm_backup_' . date('Y-m-d_H-i-s') . '.sql';
        $file_path = $backup_dir . $filename;
        
        // Use mysqldump command
        $command = sprintf(
            'mysqldump --host=%s --user=%s --password=%s %s > %s 2>&1',
            escapeshellarg(DB_HOST),
            escapeshellarg(DB_USER),
            escapeshellarg(DB_PASS),
            escapeshellarg(DB_NAME),
            escapeshellarg($file_path)
        );
        
        $output = [];
        $return_code = 0;
        exec($command, $output, $return_code);
        
        if ($return_code === 0 && file_exists($file_path) && filesize($file_path) > 0) {
            return [
                'success' => true,
                'message' => "Database backup created successfully: {$filename}",
                'filename' => $filename
            ];
        } else {
            // Fallback to PHP-based backup if mysqldump fails
            return createPHPBackup($file_path, $filename);
        }
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => 'Backup failed: ' . $e->getMessage()
        ];
    }
}

function createPHPBackup($file_path, $filename) {
    global $db;
    
    try {
        $backup_content = "-- Resgrow CRM Database Backup\n";
        $backup_content .= "-- Generated on: " . date('Y-m-d H:i:s') . "\n";
        $backup_content .= "-- Database: " . DB_NAME . "\n\n";
        
        $backup_content .= "SET FOREIGN_KEY_CHECKS=0;\n";
        $backup_content .= "SET SQL_MODE = \"NO_AUTO_VALUE_ON_ZERO\";\n";
        $backup_content .= "SET AUTOCOMMIT = 0;\n";
        $backup_content .= "START TRANSACTION;\n\n";
        
        // Get all tables
        $tables_result = $db->query("SHOW TABLES");
        while ($table_row = $tables_result->fetch_array()) {
            $table = $table_row[0];
            
            // Get table structure
            $backup_content .= "-- Table structure for table `{$table}`\n";
            $backup_content .= "DROP TABLE IF EXISTS `{$table}`;\n";
            
            $create_result = $db->query("SHOW CREATE TABLE `{$table}`");
            $create_row = $create_result->fetch_assoc();
            $backup_content .= $create_row['Create Table'] . ";\n\n";
            
            // Get table data
            $data_result = $db->query("SELECT * FROM `{$table}`");
            if ($data_result->num_rows > 0) {
                $backup_content .= "-- Dumping data for table `{$table}`\n";
                
                while ($row = $data_result->fetch_assoc()) {
                    $columns = array_keys($row);
                    $values = array_values($row);
                    
                    // Escape values
                    $escaped_values = array_map(function($value) use ($db) {
                        if ($value === null) {
                            return 'NULL';
                        }
                        return "'" . $db->escape($value) . "'";
                    }, $values);
                    
                    $backup_content .= "INSERT INTO `{$table}` (`" . implode('`, `', $columns) . "`) VALUES (" . implode(', ', $escaped_values) . ");\n";
                }
                $backup_content .= "\n";
            }
        }
        
        $backup_content .= "SET FOREIGN_KEY_CHECKS=1;\n";
        $backup_content .= "COMMIT;\n";
        
        if (file_put_contents($file_path, $backup_content)) {
            return [
                'success' => true,
                'message' => "Database backup created successfully: {$filename}",
                'filename' => $filename
            ];
        } else {
            return [
                'success' => false,
                'message' => 'Failed to write backup file'
            ];
        }
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => 'PHP backup failed: ' . $e->getMessage()
        ];
    }
}

function restoreDatabaseBackup($file_path, $db) {
    try {
        if (!file_exists($file_path)) {
            return ['success' => false, 'message' => 'Backup file not found'];
        }
        
        $sql_content = file_get_contents($file_path);
        if ($sql_content === false) {
            return ['success' => false, 'message' => 'Failed to read backup file'];
        }
        
        // Split SQL content into individual statements
        $statements = explode(';', $sql_content);
        
        $executed = 0;
        $errors = 0;
        
        foreach ($statements as $statement) {
            $statement = trim($statement);
            if (empty($statement) || strpos($statement, '--') === 0) {
                continue;
            }
            
            try {
                if ($db->query($statement)) {
                    $executed++;
                } else {
                    $errors++;
                    error_log("SQL Error: " . $db->error . " | Statement: " . substr($statement, 0, 100));
                }
            } catch (Exception $e) {
                $errors++;
                error_log("SQL Exception: " . $e->getMessage());
            }
        }
        
        if ($errors > 0) {
            return [
                'success' => false,
                'message' => "Restore completed with errors: {$executed} statements executed, {$errors} errors"
            ];
        } else {
            return [
                'success' => true,
                'message' => "Database restored successfully: {$executed} statements executed"
            ];
        }
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => 'Restore failed: ' . $e->getMessage()
        ];
    }
}

function getBackupFiles($backup_dir) {
    $files = [];
    if (is_dir($backup_dir)) {
        $scan = scandir($backup_dir);
        foreach ($scan as $file) {
            if ($file !== '.' && $file !== '..' && pathinfo($file, PATHINFO_EXTENSION) === 'sql') {
                $file_path = $backup_dir . $file;
                $files[] = [
                    'name' => $file,
                    'size' => filesize($file_path),
                    'modified' => filemtime($file_path)
                ];
            }
        }
        
        // Sort by modification time (newest first)
        usort($files, function($a, $b) {
            return $b['modified'] - $a['modified'];
        });
    }
    return $files;
}

// Get existing backup files
$backup_files = getBackupFiles($backup_dir);

// Get database statistics
$stats_sql = "SELECT 
                (SELECT COUNT(*) FROM users) as users,
                (SELECT COUNT(*) FROM campaigns) as campaigns,
                (SELECT COUNT(*) FROM leads) as leads,
                (SELECT COUNT(*) FROM lead_interactions) as interactions,
                (SELECT COUNT(*) FROM activity_log) as activity_logs";
$stats = $db->query($stats_sql)->fetch_assoc();

include_once '../templates/header.php';
?>

<div class="min-h-screen bg-gray-50">
    <?php include_once '../templates/nav-admin.php'; ?>
    
    <div class="py-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <!-- Header -->
            <div class="mb-8">
                <h1 class="text-3xl font-bold text-gray-900">Database Backup & Restore</h1>
                <p class="mt-2 text-gray-600">
                    Create backups and restore your CRM database
                </p>
            </div>

            <!-- Flash Messages -->
            <?php foreach (get_flash_messages() as $message): ?>
            <div class="mb-4">
                <?php echo show_alert($message['type'], $message['message']); ?>
            </div>
            <?php endforeach; ?>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                <!-- Create Backup -->
                <div class="bg-white shadow rounded-lg">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h3 class="text-lg font-medium text-gray-900">Create Backup</h3>
                    </div>
                    <div class="p-6">
                        <div class="space-y-4">
                            <div class="bg-blue-50 p-4 rounded-md">
                                <h4 class="text-sm font-medium text-blue-900 mb-2">Database Statistics</h4>
                                <div class="text-sm text-blue-800 space-y-1">
                                    <div>Users: <?php echo number_format($stats['users']); ?></div>
                                    <div>Campaigns: <?php echo number_format($stats['campaigns']); ?></div>
                                    <div>Leads: <?php echo number_format($stats['leads']); ?></div>
                                    <div>Interactions: <?php echo number_format($stats['interactions']); ?></div>
                                    <div>Activity Logs: <?php echo number_format($stats['activity_logs']); ?></div>
                                </div>
                            </div>
                            
                            <form method="POST">
                                <input type="hidden" name="action" value="create_backup">
                                <button type="submit" 
                                        class="w-full bg-blue-600 text-white py-2 px-4 rounded-md hover:bg-blue-700 transition duration-200"
                                        onclick="this.textContent='Creating Backup...'; this.disabled=true;">
                                    Create New Backup
                                </button>
                            </form>
                            
                            <div class="text-xs text-gray-500">
                                <p><strong>Note:</strong> Backups include all data, table structures, and relationships. The process may take a few minutes for large databases.</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Existing Backups -->
                <div class="lg:col-span-2">
                    <div class="bg-white shadow rounded-lg">
                        <div class="px-6 py-4 border-b border-gray-200">
                            <h3 class="text-lg font-medium text-gray-900">Backup Files</h3>
                        </div>
                        <div class="p-6">
                            <?php if (empty($backup_files)): ?>
                            <div class="text-center py-8">
                                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                </svg>
                                <h3 class="mt-2 text-sm font-medium text-gray-900">No backup files</h3>
                                <p class="mt-1 text-sm text-gray-500">Create your first backup to get started.</p>
                            </div>
                            <?php else: ?>
                            <div class="space-y-4">
                                <?php foreach ($backup_files as $file): ?>
                                <div class="flex items-center justify-between p-4 border border-gray-200 rounded-lg">
                                    <div class="flex-1">
                                        <h4 class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($file['name']); ?></h4>
                                        <div class="flex items-center mt-1 text-sm text-gray-500">
                                            <span><?php echo number_format($file['size'] / 1024, 1); ?> KB</span>
                                            <span class="mx-2">•</span>
                                            <span><?php echo date('M j, Y g:i A', $file['modified']); ?></span>
                                        </div>
                                    </div>
                                    <div class="flex items-center space-x-2">
                                        <a href="<?php echo 'backups/' . urlencode($file['name']); ?>" download
                                           class="text-blue-600 hover:text-blue-800 text-sm font-medium">
                                            Download
                                        </a>
                                        <button onclick="confirmRestore('<?php echo htmlspecialchars($file['name']); ?>')"
                                                class="text-green-600 hover:text-green-800 text-sm font-medium">
                                            Restore
                                        </button>
                                        <button onclick="confirmDelete('<?php echo htmlspecialchars($file['name']); ?>')"
                                                class="text-red-600 hover:text-red-800 text-sm font-medium">
                                            Delete
                                        </button>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Warning Notice -->
            <div class="mt-8 bg-yellow-50 border border-yellow-200 rounded-md p-4">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-yellow-400" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                        </svg>
                    </div>
                    <div class="ml-3">
                        <h3 class="text-sm font-medium text-yellow-800">Important Backup Information</h3>
                        <div class="mt-2 text-sm text-yellow-700">
                            <ul class="list-disc list-inside space-y-1">
                                <li><strong>Restore Warning:</strong> Restoring a backup will overwrite all current data. This action cannot be undone.</li>
                                <li><strong>Regular Backups:</strong> Create regular backups before making significant changes to your data.</li>
                                <li><strong>Storage:</strong> Backup files are stored on the server. Consider downloading important backups to external storage.</li>
                                <li><strong>Security:</strong> Backup files contain sensitive data. Ensure proper access controls are in place.</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Confirmation Modals -->
<div id="restoreModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden">
    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
        <div class="mt-3">
            <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-red-100">
                <svg class="h-6 w-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                </svg>
            </div>
            <h3 class="text-lg font-medium text-gray-900 text-center mt-4">Confirm Database Restore</h3>
            <p class="text-sm text-gray-500 text-center mt-2">
                This will restore the database from the selected backup and <strong>overwrite all current data</strong>. This action cannot be undone.
            </p>
            <p class="text-sm text-gray-700 text-center mt-2" id="restoreFileName"></p>
            
            <form method="POST" class="mt-6">
                <input type="hidden" name="action" value="restore_backup">
                <input type="hidden" name="backup_file" id="restoreBackupFile">
                <div class="flex justify-center space-x-3">
                    <button type="button" onclick="closeRestoreModal()" 
                            class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-200 rounded-md hover:bg-gray-300">
                        Cancel
                    </button>
                    <button type="submit" 
                            class="px-4 py-2 text-sm font-medium text-white bg-red-600 rounded-md hover:bg-red-700">
                        Restore Database
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<div id="deleteModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden">
    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
        <div class="mt-3">
            <h3 class="text-lg font-medium text-gray-900 text-center">Delete Backup File</h3>
            <p class="text-sm text-gray-500 text-center mt-2">
                Are you sure you want to delete this backup file? This action cannot be undone.
            </p>
            <p class="text-sm text-gray-700 text-center mt-2" id="deleteFileName"></p>
            
            <form method="POST" class="mt-6">
                <input type="hidden" name="action" value="delete_backup">
                <input type="hidden" name="backup_file" id="deleteBackupFile">
                <div class="flex justify-center space-x-3">
                    <button type="button" onclick="closeDeleteModal()" 
                            class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-200 rounded-md hover:bg-gray-300">
                        Cancel
                    </button>
                    <button type="submit" 
                            class="px-4 py-2 text-sm font-medium text-white bg-red-600 rounded-md hover:bg-red-700">
                        Delete File
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function confirmRestore(filename) {
    document.getElementById('restoreFileName').textContent = 'File: ' + filename;
    document.getElementById('restoreBackupFile').value = filename;
    document.getElementById('restoreModal').classList.remove('hidden');
}

function closeRestoreModal() {
    document.getElementById('restoreModal').classList.add('hidden');
}

function confirmDelete(filename) {
    document.getElementById('deleteFileName').textContent = 'File: ' + filename;
    document.getElementById('deleteBackupFile').value = filename;
    document.getElementById('deleteModal').classList.remove('hidden');
}

function closeDeleteModal() {
    document.getElementById('deleteModal').classList.add('hidden');
}

// Auto-close modals when clicking outside
document.addEventListener('click', function(e) {
    if (e.target.id === 'restoreModal') {
        closeRestoreModal();
    }
    if (e.target.id === 'deleteModal') {
        closeDeleteModal();
    }
});
</script>

<?php include_once '../templates/footer.php'; ?>
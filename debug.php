<?php
// Resgrow CRM - Debug Test Page
// Phase 3: Admin Dashboard - System Testing

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>🔧 Resgrow CRM Debug Page</h1>";
echo "<style>body{font-family:Arial;margin:20px;} .ok{color:green;} .error{color:red;} .warning{color:orange;}</style>";

// Test 1: Basic PHP Info
echo "<h2>1. PHP Environment</h2>";
echo "✅ PHP Version: " . PHP_VERSION . "<br>";
echo "✅ Current Directory: " . __DIR__ . "<br>";
echo "✅ Document Root: " . $_SERVER['DOCUMENT_ROOT'] . "<br>";

// Test 2: File Structure
echo "<h2>2. File Structure Check</h2>";
$required_files = [
    '../config.php',
    '../includes/db.php',
    '../includes/session.php',
    '../includes/auth.php',
    '../includes/functions.php',
    '../templates/header.php',
    '../templates/footer.php',
    'dashboard.php'
];

foreach ($required_files as $file) {
    if (file_exists($file)) {
        echo "✅ $file<br>";
    } else {
        echo "<span class='error'>❌ $file - MISSING</span><br>";
    }
}

// Test 3: Config Loading
echo "<h2>3. Configuration Test</h2>";
try {
    require_once '../config.php';
    echo "✅ Config loaded successfully<br>";
    echo "✅ APP_NAME: " . APP_NAME . "<br>";
    echo "✅ DB_HOST: " . DB_HOST . "<br>";
    echo "✅ DB_NAME: " . DB_NAME . "<br>";
    echo "✅ BASE_URL: " . BASE_URL . "<br>";
} catch (Exception $e) {
    echo "<span class='error'>❌ Config error: " . $e->getMessage() . "</span><br>";
}

// Test 4: Database Connection
echo "<h2>4. Database Connection Test</h2>";
try {
    require_once '../includes/db.php';
    
    if (isset($db)) {
        echo "✅ Database class instantiated<br>";
        
        // Test basic query
        $result = $db->query("SELECT 1 as test");
        if ($result) {
            echo "✅ Basic query successful<br>";
        } else {
            echo "<span class='error'>❌ Basic query failed</span><br>";
        }
        
        // Check required tables
        $missing_tables = $db->checkTables();
        if (empty($missing_tables)) {
            echo "✅ All required tables exist<br>";
        } else {
            echo "<span class='error'>❌ Missing tables: " . implode(', ', $missing_tables) . "</span><br>";
            echo "<p>Please run the schema.sql file to create the database tables.</p>";
        }
        
        // Test users table
        $users_result = $db->query("SELECT COUNT(*) as count FROM users");
        if ($users_result) {
            $count = $users_result->fetch_assoc()['count'];
            echo "✅ Users table has $count records<br>";
        } else {
            echo "<span class='warning'>⚠️ Could not query users table</span><br>";
        }
        
    } else {
        echo "<span class='error'>❌ Database object not created</span><br>";
    }
} catch (Exception $e) {
    echo "<span class='error'>❌ Database error: " . $e->getMessage() . "</span><br>";
}

// Test 5: Session Management
echo "<h2>5. Session Test</h2>";
try {
    require_once '../includes/session.php';
    echo "✅ Session manager loaded<br>";
    
    if (session_status() === PHP_SESSION_ACTIVE) {
        echo "✅ Session is active<br>";
        echo "Session ID: " . session_id() . "<br>";
    } else {
        echo "<span class='warning'>⚠️ Session not active</span><br>";
    }
} catch (Exception $e) {
    echo "<span class='error'>❌ Session error: " . $e->getMessage() . "</span><br>";
}

// Test 6: Functions Loading
echo "<h2>6. Functions Test</h2>";
try {
    require_once '../includes/functions.php';
    echo "✅ Functions file loaded<br>";
    
    if (function_exists('format_currency')) {
        $test_amount = format_currency(1000);
        echo "✅ format_currency function works: $test_amount<br>";
    }
    
    if (function_exists('validate_email')) {
        $test_email = validate_email('test@example.com');
        echo "✅ validate_email function works: " . ($test_email ? 'Valid' : 'Invalid') . "<br>";
    }
    
} catch (Exception $e) {
    echo "<span class='error'>❌ Functions error: " . $e->getMessage() . "</span><br>";
}

// Test 7: Template Loading
echo "<h2>7. Template Test</h2>";
try {
    if (file_exists('../templates/header.php')) {
        echo "✅ Header template exists<br>";
    }
    if (file_exists('../templates/footer.php')) {
        echo "✅ Footer template exists<br>";
    }
} catch (Exception $e) {
    echo "<span class='error'>❌ Template error: " . $e->getMessage() . "</span><br>";
}

// Test 8: Permissions Check
echo "<h2>8. File Permissions</h2>";
$writable_dirs = ['../data', '../data/logs'];
foreach ($writable_dirs as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    
    if (is_writable($dir)) {
        echo "✅ $dir is writable<br>";
    } else {
        echo "<span class='error'>❌ $dir is not writable</span><br>";
    }
}

echo "<h2>9. Quick Actions</h2>";
echo "<a href='dashboard.php' style='background:blue;color:white;padding:10px;text-decoration:none;margin:5px;display:inline-block;'>Test Dashboard</a>";
echo "<a href='users.php' style='background:green;color:white;padding:10px;text-decoration:none;margin:5px;display:inline-block;'>Test Users Page</a>";
echo "<a href='../public/login.php' style='background:orange;color:white;padding:10px;text-decoration:none;margin:5px;display:inline-block;'>Go to Login</a>";

echo "<hr><p><strong>If you see any red errors above, please fix them first before testing the dashboard.</strong></p>";
?>
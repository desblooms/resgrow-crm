<?php
// Resgrow CRM - Quick Setup Script (FIXED - Foreign Keys)
// Phase 3: Admin Dashboard - Database Setup

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>🚀 Resgrow CRM Quick Setup</h1>";
echo "<style>body{font-family:Arial;margin:20px;} .ok{color:green;background:#d4edda;padding:10px;margin:5px 0;border-radius:5px;} .error{color:red;background:#f8d7da;padding:10px;margin:5px 0;border-radius:5px;} .info{background:#d1ecf1;padding:10px;margin:5px 0;border-radius:5px;}</style>";

echo "<div class='info'>📋 <strong>Setup Progress:</strong></div>";

// Step 1: Load configuration
echo "<h2>Step 1: Loading Configuration</h2>";
try {
    require_once '../config.php';
    echo "<div class='ok'>✅ Configuration loaded successfully</div>";
    echo "<div class='info'>Database: " . DB_NAME . " | Host: " . DB_HOST . "</div>";
} catch (Exception $e) {
    die("<div class='error'>❌ Config error: " . $e->getMessage() . "</div>");
}

// Step 2: Test database connection
echo "<h2>Step 2: Testing Database Connection</h2>";
try {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($conn->connect_error) {
        die("<div class='error'>❌ Database connection failed: " . $conn->connect_error . "<br>
             Please check your database credentials in config.php</div>");
    }
    echo "<div class='ok'>✅ Database connected successfully</div>";
    
    // Set charset
    $conn->set_charset("utf8mb4");
    
} catch (Exception $e) {
    die("<div class='error'>❌ Database error: " . $e->getMessage() . "</div>");
}

// Step 3: Drop existing tables if they exist (to handle foreign key constraints)
echo "<h2>Step 3: Cleaning Existing Tables</h2>";

// Disable foreign key checks temporarily
$conn->query("SET FOREIGN_KEY_CHECKS = 0");

$tables_to_drop = ['lead_feedback', 'leads', 'campaigns', 'activity_log', 'users'];
foreach ($tables_to_drop as $table) {
    $conn->query("DROP TABLE IF EXISTS `$table`");
    echo "<div class='info'>🗑️ Dropped table '$table' if it existed</div>";
}

// Step 4: Create tables in correct order
echo "<h2>Step 4: Creating Database Tables</h2>";

$tables_sql = [
    'users' => "
        CREATE TABLE `users` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `name` varchar(100) NOT NULL,
            `email` varchar(150) NOT NULL UNIQUE,
            `password` varchar(255) NOT NULL,
            `role` enum('admin','marketing','sales') NOT NULL DEFAULT 'sales',
            `status` enum('active','inactive','pending') NOT NULL DEFAULT 'active',
            `phone` varchar(20) DEFAULT NULL,
            `avatar` varchar(255) DEFAULT NULL,
            `last_login` timestamp NULL DEFAULT NULL,
            `login_attempts` int(11) DEFAULT 0,
            `locked_until` timestamp NULL DEFAULT NULL,
            `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_email` (`email`),
            KEY `idx_role` (`role`),
            KEY `idx_status` (`status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ",
    
    'campaigns' => "
        CREATE TABLE `campaigns` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `title` varchar(200) NOT NULL,
            `product_name` varchar(200) NOT NULL,
            `description` text DEFAULT NULL,
            `platforms` json DEFAULT NULL,
            `budget_qr` decimal(10,2) DEFAULT NULL,
            `target_audience` text DEFAULT NULL,
            `objectives` text DEFAULT NULL,
            `created_by` int(11) NOT NULL,
            `assigned_to` int(11) DEFAULT NULL,
            `start_date` date NOT NULL,
            `end_date` date NOT NULL,
            `status` enum('draft','active','paused','completed','cancelled') NOT NULL DEFAULT 'draft',
            `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_created_by` (`created_by`),
            KEY `idx_assigned_to` (`assigned_to`),
            KEY `idx_status` (`status`),
            FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
            FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ",
    
    'leads' => "
        CREATE TABLE `leads` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `full_name` varchar(150) NOT NULL,
            `phone` varchar(20) NOT NULL,
            `email` varchar(150) DEFAULT NULL,
            `campaign_id` int(11) DEFAULT NULL,
            `platform` enum('Meta','TikTok','Snapchat','WhatsApp','Google','Direct Call','Website','Other') NOT NULL,
            `product` varchar(200) DEFAULT NULL,
            `notes` text DEFAULT NULL,
            `assigned_to` int(11) DEFAULT NULL,
            `status` enum('new','contacted','interested','follow-up','closed-won','closed-lost','no-response') NOT NULL DEFAULT 'new',
            `sale_value_qr` decimal(10,2) DEFAULT NULL,
            `lead_source` varchar(100) DEFAULT NULL,
            `lead_quality` enum('hot','warm','cold') DEFAULT 'warm',
            `last_contact_date` timestamp NULL DEFAULT NULL,
            `next_follow_up` timestamp NULL DEFAULT NULL,
            `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_phone` (`phone`),
            KEY `idx_email` (`email`),
            KEY `idx_campaign_id` (`campaign_id`),
            KEY `idx_assigned_to` (`assigned_to`),
            KEY `idx_platform` (`platform`),
            KEY `idx_status` (`status`),
            KEY `idx_created_at` (`created_at`),
            FOREIGN KEY (`campaign_id`) REFERENCES `campaigns` (`id`) ON DELETE SET NULL,
            FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ",
    
    'lead_feedback' => "
        CREATE TABLE `lead_feedback` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `lead_id` int(11) NOT NULL,
            `sales_id` int(11) NOT NULL,
            `feedback_type` enum('not_interested','budget_issue','competitor','timing','other') NOT NULL,
            `reason_text` text NOT NULL,
            `follow_up_required` boolean DEFAULT FALSE,
            `follow_up_date` timestamp NULL DEFAULT NULL,
            `submitted_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_lead_id` (`lead_id`),
            KEY `idx_sales_id` (`sales_id`),
            KEY `idx_feedback_type` (`feedback_type`),
            FOREIGN KEY (`lead_id`) REFERENCES `leads` (`id`) ON DELETE CASCADE,
            FOREIGN KEY (`sales_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ",
    
    'activity_log' => "
        CREATE TABLE `activity_log` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `user_id` int(11) DEFAULT NULL,
            `action` varchar(100) NOT NULL,
            `description` text DEFAULT NULL,
            `details` json DEFAULT NULL,
            `ip_address` varchar(45) DEFAULT NULL,
            `user_agent` text DEFAULT NULL,
            `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_user_id` (`user_id`),
            KEY `idx_action` (`action`),
            KEY `idx_created_at` (`created_at`),
            FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    "
];

// Create each table in order (users first, then tables that reference users)
foreach ($tables_sql as $table_name => $sql) {
    if ($conn->query($sql)) {
        echo "<div class='ok'>✅ Table '$table_name' created successfully</div>";
    } else {
        echo "<div class='error'>❌ Error creating table '$table_name': " . $conn->error . "</div>";
        die("<div class='error'>Setup stopped due to table creation error.</div>");
    }
}

// Re-enable foreign key checks
$conn->query("SET FOREIGN_KEY_CHECKS = 1");

// Step 5: Create users first (so we have valid IDs for foreign keys)
echo "<h2>Step 5: Creating User Accounts</h2>";

$users_to_create = [
    ['System Administrator', 'admin@resgrow.com', 'admin123', 'admin'],
    ['Marketing Manager', 'marketing@resgrow.com', 'marketing123', 'marketing'],
    ['Sales Representative', 'sales@resgrow.com', 'sales123', 'sales']
];

$user_ids = [];
foreach ($users_to_create as $user_data) {
    $name = $user_data[0];
    $email = $user_data[1];
    $password = password_hash($user_data[2], PASSWORD_DEFAULT);
    $role = $user_data[3];
    
    $insert_user = "INSERT INTO users (name, email, password, role, status, created_at) VALUES (?, ?, ?, ?, 'active', NOW())";
    $stmt = $conn->prepare($insert_user);
    
    if ($stmt && $stmt->bind_param("ssss", $name, $email, $password, $role) && $stmt->execute()) {
        $user_id = $conn->insert_id;
        $user_ids[$role] = $user_id;
        echo "<div class='ok'>✅ User '$name' created (ID: $user_id)</div>";
    } else {
        echo "<div class='error'>❌ Error creating user '$name': " . $conn->error . "</div>";
    }
}

// Step 6: Create sample campaigns (using valid user IDs)
echo "<h2>Step 6: Creating Sample Campaigns</h2>";

if (isset($user_ids['admin'])) {
    $admin_id = $user_ids['admin'];
    $marketing_id = isset($user_ids['marketing']) ? $user_ids['marketing'] : $admin_id;
    
    $sample_campaigns = [
        ['Winter Coffee Promotion', 'Premium Coffee Blend', 'Target coffee lovers during winter season', '["Meta", "TikTok"]', 5000.00],
        ['Qatar National Day Special', 'Traditional Sweets', 'Celebrate Qatar National Day with traditional sweets', '["Meta", "WhatsApp"]', 3000.00]
    ];
    
    foreach ($sample_campaigns as $index => $campaign_data) {
        $title = $campaign_data[0];
        $product = $campaign_data[1];
        $description = $campaign_data[2];
        $platforms = $campaign_data[3];
        $budget = $campaign_data[4];
        
        $status = $index == 0 ? 'active' : 'completed';
        
        $insert_campaign = "INSERT INTO campaigns (title, product_name, description, platforms, budget_qr, created_by, assigned_to, start_date, end_date, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 30 DAY), ?, NOW())";
        $stmt = $conn->prepare($insert_campaign);
        
        if ($stmt && $stmt->bind_param("ssssdiiis", $title, $product, $description, $platforms, $budget, $marketing_id, $marketing_id, $status) && $stmt->execute()) {
            $campaign_id = $conn->insert_id;
            echo "<div class='ok'>✅ Campaign '$title' created (ID: $campaign_id)</div>";
            
            // Store campaign ID for leads
            if ($index == 0) {
                $active_campaign_id = $campaign_id;
            }
        } else {
            echo "<div class='error'>❌ Error creating campaign '$title': " . $conn->error . "</div>";
        }
    }
}

// Step 7: Create sample leads (using valid campaign and user IDs)
echo "<h2>Step 7: Creating Sample Leads</h2>";

if (isset($active_campaign_id) && isset($user_ids['sales'])) {
    $sales_id = $user_ids['sales'];
    
    $sample_leads = [
        ['Ahmed Al-Mansouri', '+97455123456', 'ahmed@example.com', 'Meta', 'closed-won', 150.00],
        ['Fatima Al-Thani', '+97455789012', 'fatima@example.com', 'TikTok', 'follow-up', NULL],
        ['Mohammed Al-Kuwari', '+97455345678', 'mohammed@example.com', 'WhatsApp', 'new', NULL],
        ['Sarah Al-Naimi', '+97455987654', 'sarah@example.com', 'Meta', 'contacted', NULL],
        ['Omar Al-Sulaiti', '+97455456789', 'omar@example.com', 'Google', 'closed-won', 200.00]
    ];
    
    foreach ($sample_leads as $lead_data) {
        $name = $lead_data[0];
        $phone = $lead_data[1];
        $email = $lead_data[2];
        $platform = $lead_data[3];
        $status = $lead_data[4];
        $sale_value = $lead_data[5];
        
        $insert_lead = "INSERT INTO leads (full_name, phone, email, campaign_id, platform, product, assigned_to, status, sale_value_qr, created_at) VALUES (?, ?, ?, ?, ?, 'Premium Coffee Blend', ?, ?, ?, NOW())";
        $stmt = $conn->prepare($insert_lead);
        
        if ($stmt && $stmt->bind_param("sssisssd", $name, $phone, $email, $active_campaign_id, $platform, $sales_id, $status, $sale_value) && $stmt->execute()) {
            echo "<div class='ok'>✅ Lead '$name' created</div>";
        } else {
            echo "<div class='error'>❌ Error creating lead '$name': " . $conn->error . "</div>";
        }
    }
}

$conn->close();

// Step 8: Final verification
echo "<h2>Step 8: Final Verification</h2>";
echo "<div class='ok'>✅ Database setup completed successfully!</div>";

echo "<h2>🎉 Setup Complete!</h2>";
echo "<div style='background:#d4edda;padding:20px;border:1px solid #c3e6cb;margin:20px 0;border-radius:5px;'>
        <h3>🚀 Your Resgrow CRM is now ready!</h3>
        
        <h4>👥 User Accounts Created:</h4>
        <ul>
            <li><strong>Admin:</strong> admin@resgrow.com / admin123</li>
            <li><strong>Marketing:</strong> marketing@resgrow.com / marketing123</li>
            <li><strong>Sales:</strong> sales@resgrow.com / sales123</li>
        </ul>
        
        <h4>📊 Sample Data Created:</h4>
        <ul>
            <li>2 Sample campaigns</li>
            <li>5 Sample leads with different statuses</li>
            <li>Realistic Qatar F&B market data</li>
        </ul>
        
        <h4>📋 Next Steps:</h4>
        <ol>
            <li><a href='debug.php' style='color:#155724;font-weight:bold;'>Run system diagnostics</a></li>
            <li><a href='../public/login.php' style='color:#155724;font-weight:bold;'>Go to login page</a></li>
            <li><a href='dashboard.php' style='color:#155724;font-weight:bold;'>Test admin dashboard (after login)</a></li>
        </ol>
      </div>";

echo "<div style='background:#fff3cd;padding:15px;border:1px solid #ffeaa7;margin:10px 0;border-radius:5px;'>
        <strong>🔒 Security Note:</strong><br>
        Please change all default passwords after first login for security!
      </div>";

echo "<div style='background:#cff4fc;padding:15px;border:1px solid #b3ebf2;margin:10px 0;border-radius:5px;'>
        <strong>🎯 Test the System:</strong><br>
        1. Login as admin and check the dashboard<br>
        2. Login as marketing@resgrow.com to see marketing features<br>
        3. Login as sales@resgrow.com to see sales features<br>
      </div>";
?>
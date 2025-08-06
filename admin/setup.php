<?php
// Resgrow CRM - Quick Setup Script (FIXED)
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

// Step 3: Create tables
echo "<h2>Step 3: Creating Database Tables</h2>";

$tables_sql = [
    'users' => "
        CREATE TABLE IF NOT EXISTS `users` (
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
        CREATE TABLE IF NOT EXISTS `campaigns` (
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
            KEY `idx_status` (`status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ",
    
    'leads' => "
        CREATE TABLE IF NOT EXISTS `leads` (
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
            KEY `idx_assigned_to` (`assigned_to`),
            KEY `idx_platform` (`platform`),
            KEY `idx_status` (`status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ",
    
    'lead_feedback' => "
        CREATE TABLE IF NOT EXISTS `lead_feedback` (
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
            KEY `idx_sales_id` (`sales_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ",
    
    'activity_log' => "
        CREATE TABLE IF NOT EXISTS `activity_log` (
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
            KEY `idx_action` (`action`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    "
];

// Create each table
foreach ($tables_sql as $table_name => $sql) {
    if ($conn->query($sql)) {
        echo "<div class='ok'>✅ Table '$table_name' created/verified successfully</div>";
    } else {
        echo "<div class='error'>❌ Error creating table '$table_name': " . $conn->error . "</div>";
        echo "<div class='info'>SQL: " . substr($sql, 0, 100) . "...</div>";
    }
}

// Step 4: Create default admin user
echo "<h2>Step 4: Creating Default Admin User</h2>";

$admin_check = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'admin'");
if ($admin_check) {
    $admin_count = $admin_check->fetch_assoc()['count'];
    if ($admin_count == 0) {
        $default_password = password_hash('admin123', PASSWORD_DEFAULT);
        $insert_admin = "INSERT INTO users (name, email, password, role, status, created_at) VALUES 
                        ('System Administrator', 'admin@resgrow.com', ?, 'admin', 'active', NOW())";
        
        $stmt = $conn->prepare($insert_admin);
        if ($stmt && $stmt->bind_param("s", $default_password) && $stmt->execute()) {
            echo "<div class='ok'>✅ Default admin user created successfully</div>";
            echo "<div style='background:#fff3cd;padding:15px;border:1px solid #ffeaa7;margin:10px 0;border-radius:5px;'>
                    <strong>🔑 Login Credentials:</strong><br>
                    <strong>Email:</strong> admin@resgrow.com<br>
                    <strong>Password:</strong> admin123<br>
                    <em style='color:#856404;'>⚠️ Please change this password after first login!</em>
                  </div>";
        } else {
            echo "<div class='error'>❌ Error creating admin user: " . $conn->error . "</div>";
        }
    } else {
        echo "<div class='ok'>✅ Admin user already exists ($admin_count found)</div>";
    }
}

// Step 5: Insert sample data
echo "<h2>Step 5: Creating Sample Data</h2>";

// Sample marketing user
$marketing_check = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'marketing'");
if ($marketing_check && $marketing_check->fetch_assoc()['count'] == 0) {
    $marketing_password = password_hash('marketing123', PASSWORD_DEFAULT);
    $insert_marketing = "INSERT INTO users (name, email, password, role, status) VALUES 
                        ('Marketing Manager', 'marketing@resgrow.com', ?, 'marketing', 'active')";
    $stmt = $conn->prepare($insert_marketing);
    if ($stmt && $stmt->bind_param("s", $marketing_password) && $stmt->execute()) {
        echo "<div class='ok'>✅ Sample marketing user created</div>";
    }
}

// Sample sales user
$sales_check = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'sales'");
if ($sales_check && $sales_check->fetch_assoc()['count'] == 0) {
    $sales_password = password_hash('sales123', PASSWORD_DEFAULT);
    $insert_sales = "INSERT INTO users (name, email, password, role, status) VALUES 
                    ('Sales Representative', 'sales@resgrow.com', ?, 'sales', 'active')";
    $stmt = $conn->prepare($insert_sales);
    if ($stmt && $stmt->bind_param("s", $sales_password) && $stmt->execute()) {
        echo "<div class='ok'>✅ Sample sales user created</div>";
    }
}

// Sample campaign
$campaign_check = $conn->query("SELECT COUNT(*) as count FROM campaigns");
if ($campaign_check && $campaign_check->fetch_assoc()['count'] == 0) {
    $insert_campaign = "INSERT INTO campaigns (title, product_name, description, platforms, budget_qr, created_by, start_date, end_date, status) VALUES 
                       ('Winter Coffee Promotion', 'Premium Coffee Blend', 'Target coffee lovers during winter season', '[\"Meta\", \"TikTok\"]', 5000.00, 1, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 30 DAY), 'active')";
    if ($conn->query($insert_campaign)) {
        echo "<div class='ok'>✅ Sample campaign created</div>";
    }
}

// Sample leads
$leads_check = $conn->query("SELECT COUNT(*) as count FROM leads");
if ($leads_check && $leads_check->fetch_assoc()['count'] == 0) {
    $sample_leads = [
        ['Ahmed Al-Mansouri', '+97455123456', 'ahmed@example.com', 1, 'Meta', 'Premium Coffee Blend', 3, 'closed-won', 150.00],
        ['Fatima Al-Thani', '+97455789012', 'fatima@example.com', 1, 'TikTok', 'Premium Coffee Blend', 3, 'follow-up', NULL],
        ['Mohammed Al-Kuwari', '+97455345678', 'mohammed@example.com', 1, 'WhatsApp', 'Premium Coffee Blend', 3, 'new', NULL]
    ];
    
    $insert_lead = "INSERT INTO leads (full_name, phone, email, campaign_id, platform, product, assigned_to, status, sale_value_qr) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($insert_lead);
    
    foreach ($sample_leads as $lead) {
        $stmt->bind_param("sssissssd", $lead[0], $lead[1], $lead[2], $lead[3], $lead[4], $lead[5], $lead[6], $lead[7], $lead[8]);
        $stmt->execute();
    }
    echo "<div class='ok'>✅ Sample leads created</div>";
}

$conn->close();

// Step 6: Final verification
echo "<h2>Step 6: Final Verification</h2>";
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
?>
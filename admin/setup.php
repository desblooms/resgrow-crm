<?php
// Resgrow CRM - Quick Setup Script
// Phase 3: Admin Dashboard - Database Setup

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>🚀 Resgrow CRM Quick Setup</h1>";
echo "<style>body{font-family:Arial;margin:20px;} .ok{color:green;} .error{color:red;}</style>";

// Load configuration
try {
    require_once '../config.php';
    echo "<div class='ok'>✅ Configuration loaded</div>";
} catch (Exception $e) {
    die("<div class='error'>❌ Config error: " . $e->getMessage() . "</div>");
}

// Connect to database
try {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($conn->connect_error) {
        die("<div class='error'>❌ Database connection failed: " . $conn->connect_error . "</div>");
    }
    echo "<div class='ok'>✅ Database connected</div>";
} catch (Exception $e) {
    die("<div class='error'>❌ Database error: " . $e->getMessage() . "</div>");
}

// Create tables if they don't exist
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    "
];

// Create tables
foreach ($tables_sql as $table_name => $sql) {
    if ($conn->query($sql)) {
        echo "<div class='ok'>✅ Table '$table_name' created/verified</div>";
    } else {
        echo "<div class='error'>❌ Error creating table '$table_name': " . $conn->error . "</div>";
    }
}

// Insert default admin user if none exists
$admin_check = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'admin'");
if ($admin_check) {
    $admin_count = $admin_check->fetch_assoc()['count'];
    if ($admin_count == 0) {
        $default_password = password_hash('admin123', PASSWORD_DEFAULT);
        $insert_admin = "INSERT INTO users (name, email, password, role, status) VALUES 
                        ('System Administrator', 'admin@resgrow.com', '$default_password', 'admin', 'active')";
        
        if ($conn->query($insert_admin)) {
            echo "<div class='ok'>✅ Default admin user created</div>";
            echo "<div style='background:#fff3cd;padding:10px;border:1px solid #ffeaa7;margin:10px 0;'>
                    <strong>📧 Login Credentials:</strong><br>
                    Email: admin@resgrow.com<br>
                    Password: admin123<br>
                    <em>Please change this password after first login!</em>
                  </div>";
        } else {
            echo "<div class='error'>❌ Error creating admin user: " . $conn->error . "</div>";
        }
    } else {
        echo "<div class='ok'>✅ Admin user already exists</div>";
    }
}

// Insert sample data
$sample_campaign = "INSERT IGNORE INTO campaigns (id, title, product_name, description, platforms, budget_qr, created_by, start_date, end_date, status) VALUES 
                   (1, 'Test Campaign', 'Sample Product', 'Sample campaign for testing', '[\"Meta\", \"TikTok\"]', 1000.00, 1, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 30 DAY), 'active')";

if ($conn->query($sample_campaign)) {
    echo "<div class='ok'>✅ Sample campaign created</div>";
}

$sample_leads = "INSERT IGNORE INTO leads (id, full_name, phone, email, campaign_id, platform, product, assigned_to, status, sale_value_qr) VALUES 
                (1, 'Ahmed Al-Mansouri', '+97455123456', 'ahmed@example.com', 1, 'Meta', 'Sample Product', 1, 'closed-won', 150.00),
                (2, 'Fatima Al-Thani', '+97455789012', 'fatima@example.com', 1, 'TikTok', 'Sample Product', 1, 'follow-up', NULL),
                (3, 'Mohammed Al-Kuwari', '+97455345678', 'mohammed@example.com', 1, 'WhatsApp', 'Sample Product', 1, 'new', NULL)";

if ($conn->query($sample_leads)) {
    echo "<div class='ok'>✅ Sample leads created</div>";
}

$conn->close();

echo "<h2>🎉 Setup Complete!</h2>";
echo "<p><strong>Next Steps:</strong></p>";
echo "<ol>";
echo "<li><a href='debug.php'>Run system diagnostics</a></li>";
echo "<li><a href='../public/login.php'>Go to login page</a></li>";
echo "<li><a href='dashboard.php'>Test admin dashboard (after login)</a></li>";
echo "</ol>";

echo "<div style='background:#d4edda;padding:15px;border:1px solid #c3e6cb;margin:20px 0;'>
        <strong>✅ Setup completed successfully!</strong><br>
        Your Resgrow CRM is now ready to use.
      </div>";
?>
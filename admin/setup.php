<?php
// Resgrow CRM - Simple Setup Script (No Binding Issues)
// Phase 3: Admin Dashboard - Database Setup

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>🚀 Resgrow CRM Simple Setup</h1>";
echo "<style>body{font-family:Arial;margin:20px;} .ok{color:green;background:#d4edda;padding:10px;margin:5px 0;border-radius:5px;} .error{color:red;background:#f8d7da;padding:10px;margin:5px 0;border-radius:5px;} .info{background:#d1ecf1;padding:10px;margin:5px 0;border-radius:5px;}</style>";

// Load configuration
require_once '../config.php';
echo "<div class='ok'>✅ Configuration loaded</div>";

// Connect to database
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    die("<div class='error'>❌ Connection failed: " . $conn->connect_error . "</div>");
}
echo "<div class='ok'>✅ Database connected</div>";

// Set charset
$conn->set_charset("utf8mb4");

// Disable foreign key checks
$conn->query("SET FOREIGN_KEY_CHECKS = 0");

// Drop existing tables
$tables = ['lead_feedback', 'leads', 'campaigns', 'activity_log', 'users'];
foreach ($tables as $table) {
    $conn->query("DROP TABLE IF EXISTS `$table`");
}
echo "<div class='info'>🗑️ Cleaned existing tables</div>";

// Create tables using direct SQL (avoiding parameter binding issues)
$sql = "
-- Users table
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Campaigns table
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Leads table
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
  FOREIGN KEY (`campaign_id`) REFERENCES `campaigns` (`id`) ON DELETE SET NULL,
  FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Lead feedback table
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
  FOREIGN KEY (`lead_id`) REFERENCES `leads` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`sales_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Activity log table
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
  FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
";

// Execute the SQL
if ($conn->multi_query($sql)) {
    do {
        if ($result = $conn->store_result()) {
            $result->free();
        }
    } while ($conn->next_result());
    echo "<div class='ok'>✅ All tables created successfully</div>";
} else {
    echo "<div class='error'>❌ Error creating tables: " . $conn->error . "</div>";
}

// Re-enable foreign key checks
$conn->query("SET FOREIGN_KEY_CHECKS = 1");

// Insert users with direct SQL (no binding)
$admin_password = password_hash('admin123', PASSWORD_DEFAULT);
$marketing_password = password_hash('marketing123', PASSWORD_DEFAULT);
$sales_password = password_hash('sales123', PASSWORD_DEFAULT);

$users_sql = "
INSERT INTO users (name, email, password, role, status) VALUES 
('System Administrator', 'admin@resgrow.com', '$admin_password', 'admin', 'active'),
('Marketing Manager', 'marketing@resgrow.com', '$marketing_password', 'marketing', 'active'),
('Sales Representative', 'sales@resgrow.com', '$sales_password', 'sales', 'active');
";

if ($conn->query($users_sql)) {
    echo "<div class='ok'>✅ Users created successfully</div>";
} else {
    echo "<div class='error'>❌ Error creating users: " . $conn->error . "</div>";
}

// Insert sample campaigns
$campaigns_sql = "
INSERT INTO campaigns (title, product_name, description, platforms, budget_qr, created_by, assigned_to, start_date, end_date, status) VALUES 
('Winter Coffee Promotion', 'Premium Coffee Blend', 'Target coffee lovers during winter season', '[\"Meta\", \"TikTok\"]', 5000.00, 2, 2, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 30 DAY), 'active'),
('Qatar National Day Special', 'Traditional Sweets', 'Celebrate Qatar National Day with traditional sweets', '[\"Meta\", \"WhatsApp\"]', 3000.00, 2, 2, '2024-12-18', '2024-12-31', 'completed');
";

if ($conn->query($campaigns_sql)) {
    echo "<div class='ok'>✅ Sample campaigns created</div>";
} else {
    echo "<div class='error'>❌ Error creating campaigns: " . $conn->error . "</div>";
}

// Insert sample leads
$leads_sql = "
INSERT INTO leads (full_name, phone, email, campaign_id, platform, product, assigned_to, status, sale_value_qr) VALUES 
('Ahmed Al-Mansouri', '+97455123456', 'ahmed@example.com', 1, 'Meta', 'Premium Coffee Blend', 3, 'closed-won', 150.00),
('Fatima Al-Thani', '+97455789012', 'fatima@example.com', 1, 'TikTok', 'Premium Coffee Blend', 3, 'follow-up', NULL),
('Mohammed Al-Kuwari', '+97455345678', 'mohammed@example.com', 2, 'WhatsApp', 'Traditional Sweets', 3, 'closed-won', 200.00),
('Sarah Al-Naimi', '+97455987654', 'sarah@example.com', 1, 'Meta', 'Premium Coffee Blend', 3, 'contacted', NULL),
('Omar Al-Sulaiti', '+97455456789', 'omar@example.com', 1, 'Google', 'Premium Coffee Blend', 3, 'new', NULL);
";

if ($conn->query($leads_sql)) {
    echo "<div class='ok'>✅ Sample leads created</div>";
} else {
    echo "<div class='error'>❌ Error creating leads: " . $conn->error . "</div>";
}

$conn->close();

echo "<h2>🎉 Setup Complete!</h2>";
echo "<div style='background:#d4edda;padding:20px;border:1px solid #c3e6cb;margin:20px 0;border-radius:5px;'>
        <h3>🚀 Your Resgrow CRM is now ready!</h3>
        
        <h4>👥 Login Credentials:</h4>
        <ul>
            <li><strong>Admin:</strong> admin@resgrow.com / admin123</li>
            <li><strong>Marketing:</strong> marketing@resgrow.com / marketing123</li>
            <li><strong>Sales:</strong> sales@resgrow.com / sales123</li>
        </ul>
        
        <h4>📊 Sample Data:</h4>
        <ul>
            <li>✅ 3 User accounts</li>
            <li>✅ 2 Sample campaigns</li>
            <li>✅ 5 Sample leads</li>
        </ul>
        
        <h4>🚀 Test Your System:</h4>
        <p><a href='../public/login.php' style='background:#28a745;color:white;padding:10px 20px;text-decoration:none;border-radius:5px;font-weight:bold;'>🔓 Go to Login Page</a></p>
      </div>";
?>
-- Resgrow CRM Database Schema - FIXED VERSION
-- Phase 1: Project Setup & Auth
-- MySQL/MariaDB Compatible Database Structure

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";

-- Database: resgrow_crm

-- --------------------------------------------------------

-- Table structure for table `users`
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

-- --------------------------------------------------------

-- Table structure for table `campaigns`
CREATE TABLE `campaigns` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(200) NOT NULL,
  `product_name` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `platforms` json DEFAULT NULL COMMENT 'Array of platforms: Meta, TikTok, Snap, etc.',
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
  KEY `idx_dates` (`start_date`, `end_date`),
  FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

-- Table structure for table `leads`
CREATE TABLE `leads` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `full_name` varchar(150) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `email` varchar(150) DEFAULT NULL,
  `campaign_id` int(11) DEFAULT NULL,
  `platform` enum('Meta','TikTok','Snapchat','WhatsApp','Google','Direct Call','Website','Other') NOT NULL,
  `product` varchar(200) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `assigned_to` int(11) DEFAULT NULL COMMENT 'Sales agent ID',
  `status` enum('new','contacted','interested','follow-up','closed-won','closed-lost','no-response') NOT NULL DEFAULT 'new',
  `sale_value_qr` decimal(10,2) DEFAULT NULL,
  `lead_source` varchar(100) DEFAULT NULL COMMENT 'Specific source within platform',
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

-- Table structure for table `lead_feedback`
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

-- Table structure for table `activity_log`
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

-- Table structure for table `lead_interactions`
CREATE TABLE `lead_interactions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `lead_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `interaction_type` enum('call','email','whatsapp','meeting','note') NOT NULL,
  `subject` varchar(200) DEFAULT NULL,
  `content` text DEFAULT NULL,
  `duration_minutes` int(11) DEFAULT NULL,
  `outcome` enum('successful','no_answer','callback_requested','not_interested','interested') DEFAULT NULL,
  `next_action` varchar(200) DEFAULT NULL,
  `scheduled_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_lead_id` (`lead_id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_interaction_type` (`interaction_type`),
  KEY `idx_created_at` (`created_at`),
  FOREIGN KEY (`lead_id`) REFERENCES `leads` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

-- Table structure for table `campaign_performance`
CREATE TABLE `campaign_performance` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `campaign_id` int(11) NOT NULL,
  `date` date NOT NULL,
  `platform` varchar(50) NOT NULL,
  `impressions` int(11) DEFAULT 0,
  `clicks` int(11) DEFAULT 0,
  `leads_generated` int(11) DEFAULT 0,
  `cost_qr` decimal(10,2) DEFAULT 0.00,
  `conversions` int(11) DEFAULT 0,
  `revenue_qr` decimal(10,2) DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_campaign_date_platform` (`campaign_id`, `date`, `platform`),
  KEY `idx_campaign_id` (`campaign_id`),
  KEY `idx_date` (`date`),
  KEY `idx_platform` (`platform`),
  FOREIGN KEY (`campaign_id`) REFERENCES `campaigns` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

-- Table structure for table `user_settings`
CREATE TABLE `user_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user_setting` (`user_id`, `setting_key`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_setting_key` (`setting_key`),
  FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

-- Table structure for table `notifications`
CREATE TABLE `notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `title` varchar(200) NOT NULL,
  `message` text NOT NULL,
  `type` enum('info','success','warning','error') NOT NULL DEFAULT 'info',
  `action_url` varchar(255) DEFAULT NULL,
  `is_read` boolean NOT NULL DEFAULT FALSE,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `read_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_is_read` (`is_read`),
  KEY `idx_created_at` (`created_at`),
  FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

-- Insert default users with bcrypt hashed passwords
-- Default password for all: password123

-- Admin user
INSERT INTO `users` (`name`, `email`, `password`, `role`, `status`, `created_at`) VALUES
('System Administrator', 'admin@resgrow.com', '$2y$10$YWNhb3d0ZWFtbC5pbmVkLdOtOpKbeij9.QQ2K.L/.og.at2.uheWG', 'admin', 'active', NOW());

-- Marketing user  
INSERT INTO `users` (`name`, `email`, `password`, `role`, `status`, `created_at`) VALUES
('Marketing Manager', 'marketing@resgrow.com', '$2y$10$YWNhb3d0ZWFtbC5pbmVkLdOtOpKbeij9.QQ2K.L/.og.at2.uheWG', 'marketing', 'active', NOW());

-- Sales user
INSERT INTO `users` (`name`, `email`, `password`, `role`, `status`, `created_at`) VALUES
('Sales Representative', 'sales@resgrow.com', '$2y$10$YWNhb3d0ZWFtbC5pbmVkLdOtOpKbeij9.QQ2K.L/.og.at2.uheWG', 'sales', 'active', NOW());

-- --------------------------------------------------------

-- Create additional indexes for better performance (FIXED SYNTAX)
CREATE INDEX idx_leads_created_date ON leads(created_at);
CREATE INDEX idx_leads_assigned_status ON leads(assigned_to, status);
CREATE INDEX idx_campaign_performance_metrics ON campaign_performance(campaign_id, date, platform);
CREATE INDEX idx_activity_log_date ON activity_log(created_at);
CREATE INDEX idx_lead_interactions_date ON lead_interactions(created_at);

-- --------------------------------------------------------

-- Create views for reporting
CREATE VIEW vw_lead_summary AS
SELECT 
    l.id,
    l.full_name,
    l.phone,
    l.email,
    l.platform,
    l.status,
    l.sale_value_qr,
    l.created_at,
    c.title as campaign_title,
    u.name as assigned_to_name,
    DATEDIFF(CURRENT_DATE, DATE(l.created_at)) as days_old
FROM leads l
LEFT JOIN campaigns c ON l.campaign_id = c.id
LEFT JOIN users u ON l.assigned_to = u.id;

-- --------------------------------------------------------

CREATE VIEW vw_campaign_stats AS
SELECT 
    c.id,
    c.title,
    c.status,
    c.budget_qr,
    COUNT(l.id) as total_leads,
    COUNT(CASE WHEN l.status = 'closed-won' THEN 1 END) as closed_won,
    COUNT(CASE WHEN l.status = 'closed-lost' THEN 1 END) as closed_lost,
    SUM(CASE WHEN l.status = 'closed-won' THEN l.sale_value_qr ELSE 0 END) as total_revenue,
    ROUND(
        (COUNT(CASE WHEN l.status = 'closed-won' THEN 1 END) * 100.0 / NULLIF(COUNT(l.id), 0)), 2
    ) as conversion_rate
FROM campaigns c
LEFT JOIN leads l ON c.id = l.campaign_id
GROUP BY c.id, c.title, c.status, c.budget_qr;

-- --------------------------------------------------------

CREATE VIEW vw_sales_performance AS
SELECT 
    u.id,
    u.name,
    COUNT(l.id) as total_leads,
    COUNT(CASE WHEN l.status = 'closed-won' THEN 1 END) as closed_deals,
    SUM(CASE WHEN l.status = 'closed-won' THEN l.sale_value_qr ELSE 0 END) as total_revenue,
    ROUND(AVG(CASE WHEN l.status = 'closed-won' THEN l.sale_value_qr END), 2) as avg_deal_size,
    ROUND(
        (COUNT(CASE WHEN l.status = 'closed-won' THEN 1 END) * 100.0 / NULLIF(COUNT(l.id), 0)), 2
    ) as conversion_rate
FROM users u
LEFT JOIN leads l ON u.id = l.assigned_to
WHERE u.role = 'sales' AND u.status = 'active'
GROUP BY u.id, u.name;

-- --------------------------------------------------------

-- Create stored procedures for common operations

DELIMITER $$

CREATE PROCEDURE GetDashboardStats(IN user_role VARCHAR(20), IN user_id INT)
BEGIN
    DECLARE total_leads INT DEFAULT 0;
    DECLARE new_leads INT DEFAULT 0;
    DECLARE closed_deals INT DEFAULT 0;
    DECLARE total_revenue DECIMAL(10,2) DEFAULT 0.00;
    
    IF user_role = 'admin' THEN
        SELECT COUNT(*) INTO total_leads FROM leads;
        SELECT COUNT(*) INTO new_leads FROM leads WHERE status = 'new';
        SELECT COUNT(*) INTO closed_deals FROM leads WHERE status = 'closed-won';
        SELECT COALESCE(SUM(sale_value_qr), 0) INTO total_revenue FROM leads WHERE status = 'closed-won';
    ELSEIF user_role = 'sales' THEN
        SELECT COUNT(*) INTO total_leads FROM leads WHERE assigned_to = user_id;
        SELECT COUNT(*) INTO new_leads FROM leads WHERE assigned_to = user_id AND status = 'new';
        SELECT COUNT(*) INTO closed_deals FROM leads WHERE assigned_to = user_id AND status = 'closed-won';
        SELECT COALESCE(SUM(sale_value_qr), 0) INTO total_revenue FROM leads WHERE assigned_to = user_id AND status = 'closed-won';
    ELSEIF user_role = 'marketing' THEN
        SELECT COUNT(*) INTO total_leads FROM leads l 
        JOIN campaigns c ON l.campaign_id = c.id 
        WHERE c.created_by = user_id;
        SELECT COUNT(*) INTO new_leads FROM leads l 
        JOIN campaigns c ON l.campaign_id = c.id 
        WHERE c.created_by = user_id AND l.status = 'new';
        SELECT COUNT(*) INTO closed_deals FROM leads l 
        JOIN campaigns c ON l.campaign_id = c.id 
        WHERE c.created_by = user_id AND l.status = 'closed-won';
        SELECT COALESCE(SUM(l.sale_value_qr), 0) INTO total_revenue FROM leads l 
        JOIN campaigns c ON l.campaign_id = c.id 
        WHERE c.created_by = user_id AND l.status = 'closed-won';
    END IF;
    
    SELECT total_leads, new_leads, closed_deals, total_revenue;
END$$

DELIMITER ;

-- --------------------------------------------------------

-- Create triggers for automatic timestamps and logging

DELIMITER $$

CREATE TRIGGER tr_leads_update_timestamp
    BEFORE UPDATE ON leads
    FOR EACH ROW
BEGIN
    SET NEW.updated_at = CURRENT_TIMESTAMP;
END$$

CREATE TRIGGER tr_campaigns_update_timestamp
    BEFORE UPDATE ON campaigns
    FOR EACH ROW
BEGIN
    SET NEW.updated_at = CURRENT_TIMESTAMP;
END$$

CREATE TRIGGER tr_users_update_timestamp
    BEFORE UPDATE ON users
    FOR EACH ROW
BEGIN
    SET NEW.updated_at = CURRENT_TIMESTAMP;
END$$

DELIMITER ;

-- --------------------------------------------------------

-- Sample campaign data (optional)
INSERT INTO `campaigns` (`title`, `product_name`, `description`, `platforms`, `budget_qr`, `created_by`, `start_date`, `end_date`, `status`) VALUES
('Winter Coffee Promotion', 'Premium Coffee Blend', 'Target coffee lovers during winter season', '["Meta", "TikTok"]', 5000.00, 2, '2025-01-01', '2025-02-28', 'active'),
('Qatar National Day Special', 'Traditional Sweets', 'Celebrate Qatar National Day with traditional sweets', '["Meta", "WhatsApp"]', 3000.00, 2, '2024-12-18', '2024-12-31', 'completed');

-- Sample leads data (optional)
INSERT INTO `leads` (`full_name`, `phone`, `email`, `campaign_id`, `platform`, `product`, `assigned_to`, `status`, `sale_value_qr`) VALUES
('Ahmed Al-Mansouri', '+97455123456', 'ahmed@example.com', 1, 'Meta', 'Premium Coffee Blend', 3, 'closed-won', 150.00),
('Fatima Al-Thani', '+97455789012', 'fatima@example.com', 1, 'TikTok', 'Premium Coffee Blend', 3, 'follow-up', NULL),
('Mohammed Al-Kuwari', '+97455345678', 'mohammed@example.com', 2, 'WhatsApp', 'Traditional Sweets', 3, 'closed-won', 200.00);

-- --------------------------------------------------------

COMMIT;
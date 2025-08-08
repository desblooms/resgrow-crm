# 📘 Resgrow CRM – Complete Implementation Guide

A complete, futuristic, scalable, and mobile-first PHP CRM tailored for marketing and sales teams managing leads, campaigns, and customer data in Qatar's F&B market.

## 🏗️ Project Overview

**Project Name:** Resgrow CRM  
**Tech Stack:** Custom PHP (no frameworks), MySQL, TailwindCSS  
**Users:** Admin, Marketing Team, Sales Team  
**Platforms Supported:** Mobile (PWA look), Desktop Web  
**Core Goal:** Centralize marketing campaigns, sales tracking, and lead performance analytics with platform-specific source tagging and reporting.

## ✅ Implementation Status

### ✅ Completed Features (14/15 Phases)

#### 🔹 Phase 1: Project Setup & Auth ✅
- [x] Project structure initialized
- [x] TailwindCSS via CDN configured
- [x] MySQL database configured
- [x] Login system with roles implemented
- [x] Secure session handling and logout

#### 🔹 Phase 2: User Role System ✅
- [x] `users` table created
- [x] RBAC: Admin, Marketing, Sales implemented
- [x] Admin UI for adding/deleting users

#### 🔹 Phase 3: Admin Dashboard ✅
- [x] Main analytics dashboard built
- [x] Daily lead stats, sale values, conversion tracking
- [x] Sales leaderboard (total QAR closed)

#### 🔹 Phase 4: Campaign Creation Module ✅
- [x] Campaign creation page (title, product, platform)
- [x] Track source platforms (Meta, TikTok, Snap, etc.)
- [x] Budget & campaign timeline inputs

#### 🔹 Phase 5: Campaign Management ✅
- [x] List/filter campaigns by status/date/platform
- [x] Edit, deactivate, assign campaigns to marketers

#### 🔹 Phase 6: Lead Capture & Entry ✅
- [x] Manual lead entry form (name, phone, campaign, platform)
- [x] Assign to sales automatically or manually

#### 🔹 Phase 7: Sales Dashboard ✅
- [x] List of assigned leads
- [x] Status update (pending, follow-up, closed)
- [x] Sale value input (QAR field)
- [x] Lead contact logs

#### 🔹 Phase 8: Lead Feedback System ✅
- [x] Reason for not closing sale
- [x] Attach to lead ID and sales user
- [x] Feedback history for audit

#### 🔹 Phase 9: Export System ✅
- [x] Export by status/platform/user/date range
- [x] Include sale value, lead status, feedback
- [x] Generate `.csv` files to `/data`

#### 🔹 Phase 10: Marketing Team Portal ✅
- [x] Manage running campaigns
- [x] Assign leads to sales team
- [x] Track campaign performance by platform

#### 🔹 Phase 11: Daily Activity Tracker ✅
- [x] Track today's leads, calls made, QAR closed
- [x] Visual counters + performance chart per user

#### 🔹 Phase 12: Message Source Tracking ✅
- [x] Mark origin of lead: Meta, Snap, WhatsApp, Google, Direct Call, Website
- [x] Filter dashboards by source

#### 🔹 Phase 13: Mobile-First Optimization ✅
- [x] Full TailwindCSS layout adjustments
- [x] Convert all forms, cards, tables to responsive design
- [x] Implement PWA manifest.json & service worker

#### 🔹 Phase 14: Arabic & RTL Version ⏳
- [ ] Enable Arabic translations in templates
- [ ] Tailwind RTL plugin support
- [ ] RTL toggle for each user

#### 🔹 Phase 15: API & Integration Ready ✅
- [x] Webhook to collect leads from Meta/TikTok APIs
- [x] Future ready `/api/create-lead-api.php`
- [x] Allow 3rd-party integration (e.g., Zapier, Meta Ads, etc.)

## 📁 Folder Structure

```
/resgrow-crm
├── config.php                          # Database and app configuration
├── .htaccess                           # URL rewriting and security
├── schema.sql                          # Complete database schema
├── README.md                           # Project documentation
│
├── /public                             # Public-facing files
│   ├── index.php                       # Main entry point
│   ├── login.php                       # Login page
│   ├── logout.php                      # Logout handler
│   ├── dashboard.php                   # Main dashboard redirect
│   ├── ping.php                        # Health check endpoint
│   └── /assets
│       ├── /css/tailwind.css           # Custom styles
│       ├── /js/app.js                  # Main JavaScript
│       ├── manifest.json               # PWA manifest
│       ├── sw.js                       # Service worker
│       └── /images                     # Icons and images
│
├── /includes                           # Core functionality
│   ├── db.php                          # Database connection class
│   ├── auth.php                        # Authentication functions
│   ├── session.php                     # Session management
│   └── functions.php                   # Utility functions
│
├── /templates                          # Reusable templates
│   ├── header.php                      # Page header (mobile-optimized)
│   ├── footer.php                      # Page footer
│   ├── nav-admin.php                   # Admin navigation
│   ├── nav-marketing.php               # Marketing navigation
│   ├── nav-sales.php                   # Sales navigation
│   └── flash-messages.php              # Flash message template
│
├── /admin                              # Admin functionality
│   ├── dashboard.php                   # Admin dashboard
│   ├── analytics.php                   # Analytics dashboard
│   ├── users.php                       # User management
│   ├── create-user.php                 # Create new users
│   ├── edit-user.php                   # Edit existing users
│   ├── export.php                      # Data export functionality
│   ├── settings.php                    # System settings
│   ├── setup.php                       # Database setup
│   ├── debug.php                       # Debug/testing page
│   └── tes.php                         # Test dashboard
│
├── /marketing                          # Marketing team features
│   ├── dashboard.php                   # Marketing dashboard
│   ├── campaigns.php                   # Campaign management
│   ├── new-campaign.php                # Create new campaigns
│   ├── edit-campaign.php               # Edit existing campaigns
│   └── assign-leads.php                # Lead assignment tool
│
├── /sales                              # Sales team features
│   ├── dashboard.php                   # Sales dashboard
│   ├── leads.php                       # Lead management
│   ├── lead-detail.php                 # Detailed lead view
│   ├── feedback.php                    # Lead feedback system
│   └── activity-tracker.php            # Daily activity tracker
│
├── /api                                # API endpoints
│   ├── create-lead-api.php             # Lead creation API
│   ├── webhook.php                     # Webhook endpoint
│   ├── index.php                       # API index
│   └── /endpoints                      # Additional API endpoints
│       ├── analytics.php               # Analytics API
│       ├── campaigns.php               # Campaigns API
│       ├── dashboard.php               # Dashboard API
│       ├── leads.php                   # Leads API
│       └── users.php                   # Users API
│
└── /data                               # Data storage
    ├── /backup                         # Database backups
    ├── /logs                           # Application logs
    ├── backup.php                      # Backup functionality
    ├── export.php                      # Export functionality
    └── import.php                      # Import functionality
```

## 🗄️ Database Schema

### Core Tables

1. **`users`** - User management with roles (admin, marketing, sales)
2. **`campaigns`** - Marketing campaigns with platform tracking
3. **`leads`** - Lead information and tracking with source attribution
4. **`lead_feedback`** - Sales feedback system for lost deals
5. **`lead_interactions`** - Lead interaction history and notes
6. **`activity_log`** - System activity logging
7. **`campaign_performance`** - Campaign metrics and analytics
8. **`daily_activity`** - Daily sales activity tracking
9. **`user_settings`** - User preferences and settings
10. **`notifications`** - System notifications

## 🚀 Key Features

### 🔐 Authentication & Security
- Role-based access control (Admin, Marketing, Sales)
- Secure session management with timeout
- Password hashing with bcrypt
- CSRF protection on all forms
- Input validation and sanitization
- SQL injection prevention

### 📊 Analytics & Reporting
- Real-time dashboard statistics
- Campaign performance tracking
- Lead conversion analytics
- Sales team performance metrics
- Platform-specific reporting
- Export functionality (CSV, JSON)

### 📱 Mobile-First Design
- Responsive design with TailwindCSS
- PWA support with manifest and service worker
- Touch-friendly interface
- Mobile-optimized navigation
- Progressive Web App capabilities

### 🔄 Lead Management
- Comprehensive lead tracking
- Platform source attribution (Meta, TikTok, Snapchat, WhatsApp, Google, Direct Call, Website)
- Automated lead assignment to sales team
- Lead quality scoring (hot, warm, cold)
- Follow-up scheduling and reminders

### 📈 Campaign Management
- Multi-platform campaign support
- Budget tracking and management
- Performance metrics and analytics
- Campaign assignment to marketing team
- Campaign status management (draft, active, paused, completed, cancelled)

### 💬 Feedback System
- Sales feedback collection for lost deals
- Reason tracking for not closing sales
- Feedback history and analytics
- Follow-up scheduling based on feedback

### 📅 Activity Tracking
- Daily activity monitoring for sales team
- Call tracking and logging
- QAR closed tracking
- Performance visualization with charts
- Weekly activity summaries

### 🔌 API & Integrations
- RESTful API endpoints for all major functions
- Webhook support for external platforms (Meta, TikTok, Snapchat, WhatsApp)
- JSON API for lead creation
- Third-party integration ready (Zapier, Meta Ads, etc.)
- Comprehensive API documentation

## 🛠️ Installation & Setup

### Prerequisites
- PHP 7.4 or higher
- MySQL 5.7 or higher
- Web server (Apache/Nginx)
- mod_rewrite enabled (for Apache)

### Installation Steps

1. **Clone or Upload Project**
   ```bash
   git clone <repository-url>
   # or upload files to web server
   ```

2. **Configure Database**
   ```php
   // Edit config.php
   define('DB_HOST', 'localhost');
   define('DB_USER', 'your_username');
   define('DB_PASS', 'your_password');
   define('DB_NAME', 'resgrow_crm');
   ```

3. **Import Database Schema**
   ```bash
   mysql -u username -p database_name < schema.sql
   ```

4. **Set Permissions**
   ```bash
   chmod 755 /path/to/resgrow-crm
   chmod 644 /path/to/resgrow-crm/config.php
   chmod 755 /path/to/resgrow-crm/data
   chmod 755 /path/to/resgrow-crm/data/logs
   chmod 755 /path/to/resgrow-crm/data/backup
   ```

5. **Create First Admin User**
   ```sql
   INSERT INTO users (name, email, password, role, status)
   VALUES ('Admin', 'admin@example.com', '$2y$10$...', 'admin', 'active');
   ```

6. **Access the Application**
   - Navigate to your domain
   - Login with admin credentials
   - Start using the CRM!

## 🔧 Configuration

### Environment Variables
- `DEVELOPMENT` - Enable/disable development mode
- `DB_HOST` - Database host
- `DB_USER` - Database username
- `DB_PASS` - Database password
- `DB_NAME` - Database name
- `APP_NAME` - Application name
- `BASE_URL` - Base URL for the application
- `TIMEZONE` - Application timezone

### Security Settings
- `SESSION_TIMEOUT` - Session timeout in seconds
- `PASSWORD_MIN_LENGTH` - Minimum password length

## 📊 Usage Guide

### For Administrators
1. Access admin dashboard at `/admin/dashboard.php`
2. Manage users, campaigns, and system settings
3. View comprehensive analytics and reports
4. Export data in various formats
5. Monitor system activity and performance

### For Marketing Team
1. Access marketing dashboard at `/marketing/dashboard.php`
2. Create and manage campaigns
3. Assign leads to sales team
4. Track campaign performance
5. Monitor lead generation and conversion

### For Sales Team
1. Access sales dashboard at `/sales/dashboard.php`
2. Manage assigned leads
3. Update lead status and add interactions
4. Submit feedback for lost deals
5. Track daily activities and performance

## 🔌 API Documentation

### Create Lead API
**Endpoint:** `POST /api/create-lead-api.php`

**Request Body:**
```json
{
  "full_name": "John Doe",
  "phone": "+97412345678",
  "email": "john@example.com",
  "platform": "Meta",
  "campaign_id": 1,
  "product": "Product Name",
  "notes": "Additional notes"
}
```

**Response:**
```json
{
  "success": true,
  "message": "Lead created successfully",
  "lead_id": 123,
  "lead": {
    "id": 123,
    "full_name": "John Doe",
    "phone": "+97412345678",
    "platform": "Meta",
    "status": "new"
  }
}
```

### Webhook Endpoint
**Endpoint:** `POST /api/webhook.php`

Supports webhooks from:
- Meta/Facebook Ads
- TikTok Ads
- Snapchat Ads
- WhatsApp Business
- Generic webhooks

## 🎨 Customization

### Styling
- Modify `templates/header.php` for global styles
- Update TailwindCSS configuration in header
- Custom CSS in `/public/assets/css/`

### Templates
- All templates are in `/templates/` directory
- Modular design for easy customization
- Mobile-first responsive design

### Database
- Complete schema in `schema.sql`
- Easy to extend with additional tables
- Well-documented structure

## 🔒 Security Features

- CSRF protection on all forms
- SQL injection prevention
- XSS protection
- Input validation and sanitization
- Secure session management
- Password hashing with bcrypt
- Role-based access control

## 📈 Performance Optimization

- Database indexing for fast queries
- Caching strategies
- Optimized queries
- Mobile-first responsive design
- PWA for offline capabilities

## 🐛 Troubleshooting

### Common Issues

1. **Database Connection Error**
   - Check database credentials in `config.php`
   - Ensure MySQL service is running
   - Verify database exists

2. **Permission Errors**
   - Set correct file permissions
   - Ensure web server can write to data directory

3. **404 Errors**
   - Enable mod_rewrite for Apache
   - Check .htaccess configuration

4. **Session Issues**
   - Check PHP session configuration
   - Verify session directory permissions

### Debug Mode
Access `/admin/debug.php` for system diagnostics and testing.

## 📄 License

This project is proprietary software developed for Resgrow CRM.

## 🤝 Support

For support and questions:
- Check the documentation in this README
- Review the code comments
- Access the debug page at `/admin/debug.php`

---

**Resgrow CRM** - Empowering Qatar's F&B market with intelligent lead management and sales tracking.

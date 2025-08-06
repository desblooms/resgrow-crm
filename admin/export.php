<?php
// Resgrow CRM - Data Export Handler
// Phase 3: Admin Dashboard

require_once '../includes/session.php';
require_once '../includes/functions.php';

// Require admin access
SessionManager::requireRole('admin');

// Get export parameters
$type = $_GET['type'] ?? 'leads';
$format = $_GET['format'] ?? 'csv';
$date_from = $_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
$date_to = $_GET['date_to'] ?? date('Y-m-d');

try {
    global $db;
    
    // Generate filename
    $filename = APP_NAME . '_' . $type . '_export_' . date('Y-m-d_H-i-s');
    
    switch ($type) {
        case 'leads':
            exportLeads($db, $date_from, $date_to, $format, $filename);
            break;
            
        case 'analytics':
            exportAnalytics($db, $date_from, $date_to, $format, $filename);
            break;
            
        case 'users':
            exportUsers($db, $format, $filename);
            break;
            
        case 'campaigns':
            exportCampaigns($db, $date_from, $date_to, $format, $filename);
            break;
            
        default:
            throw new Exception('Invalid export type');
    }
    
} catch (Exception $e) {
    log_error("Export error: " . $e->getMessage());
    header('Location: dashboard.php');
    set_flash_message('error', 'Export failed: ' . $e->getMessage());
    exit();
}

function exportLeads($db, $date_from, $date_to, $format, $filename) {
    $query = "
        SELECT 
            l.id,
            l.full_name,
            l.phone,
            l.email,
            l.platform,
            l.product,
            l.status,
            l.sale_value_qr,
            l.lead_quality,
            l.created_at,
            l.updated_at,
            c.title as campaign_title,
            u.name as assigned_to_name,
            lf.reason_text as feedback_reason
        FROM leads l
        LEFT JOIN campaigns c ON l.campaign_id = c.id
        LEFT JOIN users u ON l.assigned_to = u.id
        LEFT JOIN lead_feedback lf ON l.id = lf.lead_id
        WHERE DATE(l.created_at) BETWEEN ? AND ?
        ORDER BY l.created_at DESC
    ";
    
    $stmt = $db->prepare($query);
    $stmt->bind_param("ss", $date_from, $date_to);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $headers = [
        'ID', 'Full Name', 'Phone', 'Email', 'Platform', 'Product', 
        'Status', 'Sale Value (QAR)', 'Lead Quality', 'Campaign', 
        'Assigned To', 'Created Date', 'Updated Date', 'Feedback Reason'
    ];
    
    if ($format === 'csv') {
        exportAsCSV($result, $headers, $filename);
    } else {
        exportAsPDF($result, $headers, $filename, 'Leads Export Report');
    }
    
    // Log the export
    log_activity(SessionManager::getUserId(), 'data_export', "Exported leads data from $date_from to $date_to as $format");
}

function exportAnalytics($db, $date_from, $date_to, $format, $filename) {
    $query = "
        SELECT 
            DATE(l.created_at) as date,
            l.platform,
            c.title as campaign,
            COUNT(*) as leads_count,
            COUNT(CASE WHEN l.status = 'closed-won' THEN 1 END) as conversions,
            COUNT(CASE WHEN l.status = 'closed-lost' THEN 1 END) as lost_leads,
            COALESCE(SUM(CASE WHEN l.status = 'closed-won' THEN l.sale_value_qr END), 0) as revenue,
            COALESCE(AVG(CASE WHEN l.status = 'closed-won' THEN l.sale_value_qr END), 0) as avg_deal_value
        FROM leads l
        LEFT JOIN campaigns c ON l.campaign_id = c.id
        WHERE DATE(l.created_at) BETWEEN ? AND ?
        GROUP BY DATE(l.created_at), l.platform, c.title
        ORDER BY l.created_at DESC, l.platform
    ";
    
    $stmt = $db->prepare($query);
    $stmt->bind_param("ss", $date_from, $date_to);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $headers = [
        'Date', 'Platform', 'Campaign', 'Leads Count', 'Conversions', 
        'Lost Leads', 'Revenue (QAR)', 'Avg Deal Value (QAR)'
    ];
    
    if ($format === 'csv') {
        exportAsCSV($result, $headers, $filename);
    } else {
        exportAsPDF($result, $headers, $filename, 'Analytics Export Report');
    }
    
    log_activity(SessionManager::getUserId(), 'analytics_export', "Exported analytics data from $date_from to $date_to as $format");
}

function exportUsers($db, $format, $filename) {
    $query = "
        SELECT 
            u.id,
            u.name,
            u.email,
            u.role,
            u.status,
            u.created_at,
            u.last_login,
            COUNT(l.id) as assigned_leads,
            COUNT(CASE WHEN l.status = 'closed-won' THEN 1 END) as closed_deals,
            COALESCE(SUM(CASE WHEN l.status = 'closed-won' THEN l.sale_value_qr END), 0) as total_sales
        FROM users u
        LEFT JOIN leads l ON u.id = l.assigned_to
        GROUP BY u.id, u.name, u.email, u.role, u.status, u.created_at, u.last_login
        ORDER BY u.created_at DESC
    ";
    
    $result = $db->query($query);
    
    $headers = [
        'ID', 'Name', 'Email', 'Role', 'Status', 'Created Date', 
        'Last Login', 'Assigned Leads', 'Closed Deals', 'Total Sales (QAR)'
    ];
    
    if ($format === 'csv') {
        exportAsCSV($result, $headers, $filename);
    } else {
        exportAsPDF($result, $headers, $filename, 'Users Export Report');
    }
    
    log_activity(SessionManager::getUserId(), 'users_export', "Exported users data as $format");
}

function exportCampaigns($db, $date_from, $date_to, $format, $filename) {
    $query = "
        SELECT 
            c.id,
            c.title,
            c.product_name,
            c.platforms,
            c.budget_qr,
            c.status,
            c.start_date,
            c.end_date,
            c.created_at,
            u.name as created_by_name,
            COUNT(l.id) as leads_generated,
            COUNT(CASE WHEN l.status = 'closed-won' THEN 1 END) as deals_closed,
            COALESCE(SUM(CASE WHEN l.status = 'closed-won' THEN l.sale_value_qr END), 0) as revenue
        FROM campaigns c
        LEFT JOIN users u ON c.created_by = u.id
        LEFT JOIN leads l ON c.id = l.campaign_id AND DATE(l.created_at) BETWEEN ? AND ?
        GROUP BY c.id, c.title, c.product_name, c.platforms, c.budget_qr, c.status, 
                 c.start_date, c.end_date, c.created_at, u.name
        ORDER BY c.created_at DESC
    ";
    
    $stmt = $db->prepare($query);
    $stmt->bind_param("ss", $date_from, $date_to);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $headers = [
        'ID', 'Campaign Title', 'Product Name', 'Platforms', 'Budget (QAR)', 
        'Status', 'Start Date', 'End Date', 'Created Date', 'Created By', 
        'Leads Generated', 'Deals Closed', 'Revenue (QAR)'
    ];
    
    if ($format === 'csv') {
        exportAsCSV($result, $headers, $filename);
    } else {
        exportAsPDF($result, $headers, $filename, 'Campaigns Export Report');
    }
    
    log_activity(SessionManager::getUserId(), 'campaigns_export', "Exported campaigns data from $date_from to $date_to as $format");
}

function exportAsCSV($result, $headers, $filename) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    header('Pragma: public');
    header('Expires: 0');
    
    $output = fopen('php://output', 'w');
    
    // Add BOM for Excel compatibility
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Write headers
    fputcsv($output, $headers);
    
    // Write data
    while ($row = $result->fetch_assoc()) {
        // Clean and format data
        $clean_row = [];
        foreach ($row as $value) {
            if (is_null($value)) {
                $clean_row[] = '';
            } elseif (is_array($value) || is_object($value)) {
                $clean_row[] = json_encode($value);
            } else {
                $clean_row[] = (string) $value;
            }
        }
        fputcsv($output, $clean_row);
    }
    
    fclose($output);
}

function exportAsPDF($result, $headers, $filename, $title) {
    // Simple HTML to PDF conversion (basic implementation)
    // For production, consider using libraries like TCPDF or mPDF
    
    header('Content-Type: text/html');
    header('Content-Disposition: attachment; filename="' . $filename . '.html"');
    
    echo "<!DOCTYPE html>";
    echo "<html><head>";
    echo "<title>" . htmlspecialchars($title) . "</title>";
    echo "<meta charset='UTF-8'>";
    echo "<style>";
    echo "body { font-family: Arial, sans-serif; margin: 20px; }";
    echo "table { width: 100%; border-collapse: collapse; margin-top: 20px; }";
    echo "th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }";
    echo "th { background-color: #f2f2f2; font-weight: bold; }";
    echo "tr:nth-child(even) { background-color: #f9f9f9; }";
    echo ".header { text-align: center; margin-bottom: 30px; }";
    echo ".export-info { color: #666; font-size: 12px; margin-bottom: 20px; }";
    echo "@media print { body { margin: 0; } }";
    echo "</style>";
    echo "</head><body>";
    
    echo "<div class='header'>";
    echo "<h1>" . htmlspecialchars($title) . "</h1>";
    echo "<h2>" . APP_NAME . "</h2>";
    echo "</div>";
    
    echo "<div class='export-info'>";
    echo "Exported on: " . date('Y-m-d H:i:s') . "<br>";
    echo "Exported by: " . htmlspecialchars(SessionManager::getUsername()) . "<br>";
    echo "Total records: " . $result->num_rows;
    echo "</div>";
    
    echo "<table>";
    echo "<thead><tr>";
    foreach ($headers as $header) {
        echo "<th>" . htmlspecialchars($header) . "</th>";
    }
    echo "</tr></thead>";
    
    echo "<tbody>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        foreach ($row as $value) {
            if (is_null($value)) {
                echo "<td>-</td>";
            } elseif (is_array($value) || is_object($value)) {
                echo "<td>" . htmlspecialchars(json_encode($value)) . "</td>";
            } else {
                echo "<td>" . htmlspecialchars($value) . "</td>";
            }
        }
        echo "</tr>";
    }
    echo "</tbody>";
    
    echo "</table>";
    echo "</body></html>";
}
?>
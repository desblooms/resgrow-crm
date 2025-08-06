<?php
// Resgrow CRM - Database Connection (Debug Version)
// Phase 3: Admin Dashboard - Debugging

require_once '../config.php';

class Database {
    private $connection;
    
    public function __construct() {
        $this->connect();
    }
    
    private function connect() {
        try {
            // Enable error reporting for debugging
            mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
            
            $this->connection = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
            
            if ($this->connection->connect_error) {
                throw new Exception("Connection failed: " . $this->connection->connect_error);
            }
            
            $this->connection->set_charset("utf8mb4");
            
            // Test connection with a simple query
            $test_query = "SELECT 1 as test";
            $result = $this->connection->query($test_query);
            if (!$result) {
                throw new Exception("Test query failed: " . $this->connection->error);
            }
            
            // Debug: Uncomment to see connection success
            // error_log("Database connection successful");
            
        } catch (Exception $e) {
            error_log("Database connection error: " . $e->getMessage());
            
            // Show detailed error in development
            if (defined('DEVELOPMENT') && DEVELOPMENT === true) {
                die("Database Error: " . $e->getMessage() . "<br>Check your database credentials in config.php");
            } else {
                die("Database connection failed. Please check configuration.");
            }
        }
    }
    
    public function getConnection() {
        return $this->connection;
    }
    
    public function query($sql) {
        $result = $this->connection->query($sql);
        if (!$result) {
            error_log("Query error: " . $this->connection->error . " | SQL: " . $sql);
            return false;
        }
        return $result;
    }
    
    public function prepare($sql) {
        $stmt = $this->connection->prepare($sql);
        if (!$stmt) {
            error_log("Prepare error: " . $this->connection->error . " | SQL: " . $sql);
            return false;
        }
        return $stmt;
    }
    
    public function escape($string) {
        return $this->connection->real_escape_string($string);
    }
    
    public function lastInsertId() {
        return $this->connection->insert_id;
    }
    
    public function close() {
        if ($this->connection) {
            $this->connection->close();
        }
    }
    
    // Debug method to test if tables exist
    public function checkTables() {
        $required_tables = ['users', 'campaigns', 'leads', 'lead_feedback', 'activity_log'];
        $missing_tables = [];
        
        foreach ($required_tables as $table) {
            $result = $this->query("SHOW TABLES LIKE '$table'");
            if (!$result || $result->num_rows === 0) {
                $missing_tables[] = $table;
            }
        }
        
        return $missing_tables;
    }
}

// Create global database instance
try {
    $db = new Database();
    
    // Check for missing tables in development
    if (defined('DEVELOPMENT') && DEVELOPMENT === true) {
        $missing_tables = $db->checkTables();
        if (!empty($missing_tables)) {
            error_log("Missing database tables: " . implode(', ', $missing_tables));
        }
    }
    
} catch (Exception $e) {
    error_log("Failed to create database instance: " . $e->getMessage());
    if (defined('DEVELOPMENT') && DEVELOPMENT === true) {
        die("Database initialization failed: " . $e->getMessage());
    }
}
?>
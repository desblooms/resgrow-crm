<?php
// Resgrow CRM - Database Connection (FIXED)
// Phase 1: Project Setup & Auth

// Make sure config is loaded first
if (!defined('DB_HOST')) {
    require_once __DIR__ . '/../config.php';
}

class Database {
    private $connection;
    
    public function __construct() {
        $this->connect();
    }
    
    private function connect() {
        try {
            // Enable error reporting for mysqli
            mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
            
            $this->connection = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
            
            if ($this->connection->connect_error) {
                throw new Exception("Connection failed: " . $this->connection->connect_error);
            }
            
            $this->connection->set_charset("utf8mb4");
            
        } catch (Exception $e) {
            error_log("Database connection error: " . $e->getMessage());
            
            // Show detailed error in development
            if (defined('DEVELOPMENT') && DEVELOPMENT === true) {
                die("Database Error: " . $e->getMessage() . "<br>
                     Host: " . DB_HOST . "<br>
                     User: " . DB_USER . "<br>
                     Database: " . DB_NAME . "<br>
                     Check your database credentials in config.php");
            } else {
                die("Database connection failed. Please check configuration.");
            }
        }
    }
    
    public function getConnection() {
        return $this->connection;
    }
    
    public function query($sql) {
        try {
            $result = $this->connection->query($sql);
            if (!$result) {
                error_log("Query error: " . $this->connection->error . " | SQL: " . $sql);
                return false;
            }
            return $result;
        } catch (Exception $e) {
            error_log("Query exception: " . $e->getMessage() . " | SQL: " . $sql);
            return false;
        }
    }
    
    public function prepare($sql) {
        try {
            $stmt = $this->connection->prepare($sql);
            if (!$stmt) {
                error_log("Prepare error: " . $this->connection->error . " | SQL: " . $sql);
                return false;
            }
            return $stmt;
        } catch (Exception $e) {
            error_log("Prepare exception: " . $e->getMessage() . " | SQL: " . $sql);
            return false;
        }
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
    
    // Get table info for debugging
    public function getTableInfo($table_name) {
        $result = $this->query("DESCRIBE `$table_name`");
        if ($result) {
            $columns = [];
            while ($row = $result->fetch_assoc()) {
                $columns[] = $row;
            }
            return $columns;
        }
        return false;
    }
}

// Create global database instance - but only if needed
if (!isset($GLOBALS['db']) || !$GLOBALS['db']) {
    try {
        $GLOBALS['db'] = new Database();
        // Make it available as $db in global scope
        $db = $GLOBALS['db'];
    } catch (Exception $e) {
        error_log("Failed to create database instance: " . $e->getMessage());
        if (defined('DEVELOPMENT') && DEVELOPMENT === true) {
            // In development, show the error but don't die immediately
            // This allows the calling script to handle it
            $db = null;
            $GLOBALS['db'] = null;
        } else {
            $db = null;
            $GLOBALS['db'] = null;
        }
    }
} else {
    $db = $GLOBALS['db'];
}
?>
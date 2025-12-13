<?php
class DatabaseService {
    private static $instance = null;
    private $connection;
    private $host = "localhost";
    private $dbname = "lost_and_found_system";
    private $username = "root";  // Change to your MySQL username
    private $password = "";      // Change to your MySQL password
    
    private function __construct() {
        try {
            $this->connection = new PDO(
                "mysql:host={$this->host};dbname={$this->dbname};charset=utf8mb4",
                $this->username,
                $this->password,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ]
            );
        } catch (PDOException $e) {
            die("Database connection failed: " . $e->getMessage());
        }
    }
    
    public static function getInstance() {
        if (self::$instance == null) {
            self::$instance = new DatabaseService();
        }
        return self::$instance;
    }
    
    public function getConnection() {
        return $this->connection;
    }
    
    public function beginTransaction() {
        return $this->connection->beginTransaction();
    }
    
    public function commit() {
        return $this->connection->commit();
    }
    
    public function rollBack() {
        return $this->connection->rollBack();
    }
    
    // Helper method to check if database exists, create if not
    public function initializeDatabase() {
        try {
            // Try to connect to MySQL server without selecting database
            $tempConnection = new PDO(
                "mysql:host={$this->host};charset=utf8mb4",
                $this->username,
                $this->password
            );
            
            // Create database if it doesn't exist
            $tempConnection->exec("CREATE DATABASE IF NOT EXISTS {$this->dbname}");
            $tempConnection->exec("USE {$this->dbname}");
            
        } catch (PDOException $e) {
            die("Database initialization failed: " . $e->getMessage());
        }
    }
}
?>
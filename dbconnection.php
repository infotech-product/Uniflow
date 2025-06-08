<?php
// dbconnection.php - Database connection handler

// Include configuration file
require_once 'config.php';

class DatabaseConnection {
    private static $instance = null;
    private $connection;
    private $host;
    private $username;
    private $password;
    private $database;
    private $charset;

    // Private constructor to prevent direct instantiation
    private function __construct() {
        $this->host = DB_HOST;
        $this->username = DB_USERNAME;
        $this->password = DB_PASSWORD;
        $this->database = DB_NAME;
        $this->charset = DB_CHARSET;

        try {
            $dsn = "mysql:host={$this->host};dbname={$this->database};charset={$this->charset}";
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_PERSISTENT => true
            ];

            $this->connection = new PDO($dsn, $this->username, $this->password, $options);
            
            if (APP_DEBUG) {
                echo "Database connection established successfully!<br>";
            }
        } catch (PDOException $e) {
            if (APP_DEBUG) {
                die("Database connection failed: " . $e->getMessage());
            } else {
                die("Database connection failed. Please try again later.");
            }
        }
    }

    // Singleton pattern - ensures only one connection instance
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    // Get the PDO connection
    public function getConnection() {
        return $this->connection;
    }

    // Prevent cloning of the instance
    private function __clone() {}

    // Prevent unserializing of the instance
    public function __wakeup() {
        throw new Exception("Cannot unserialize singleton");
    }

    // Execute a prepared statement with parameters
    public function executeQuery($query, $params = []) {
        try {
            $stmt = $this->connection->prepare($query);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            if (APP_DEBUG) {
                throw new Exception("Query execution failed: " . $e->getMessage());
            } else {
                throw new Exception("Query execution failed");
            }
        }
    }

    // Get single record
    public function fetchOne($query, $params = []) {
        $stmt = $this->executeQuery($query, $params);
        return $stmt->fetch();
    }

    // Get multiple records
    public function fetchAll($query, $params = []) {
        $stmt = $this->executeQuery($query, $params);
        return $stmt->fetchAll();
    }

    // Insert record and return last insert ID
    public function insert($query, $params = []) {
        $this->executeQuery($query, $params);
        return $this->connection->lastInsertId();
    }

    // Update/Delete records and return affected rows count
    public function update($query, $params = []) {
        $stmt = $this->executeQuery($query, $params);
        return $stmt->rowCount();
    }

    // Begin transaction
    public function beginTransaction() {
        return $this->connection->beginTransaction();
    }

    // Commit transaction
    public function commit() {
        return $this->connection->commit();
    }

    // Rollback transaction
    public function rollback() {
        return $this->connection->rollback();
    }

    // Close connection (destructor)
    public function __destruct() {
        $this->connection = null;
    }
}

// Simple function to get database instance (for backward compatibility)
function getDB() {
    return DatabaseConnection::getInstance();
}

// Example usage:
/*
// Get database instance
$db = getDB();

// Example SELECT query
$users = $db->fetchAll("SELECT * FROM users WHERE status = ?", ['active']);

// Example INSERT query
$userId = $db->insert("INSERT INTO users (username, email, password) VALUES (?, ?, ?)", 
                     ['john_doe', 'john@example.com', password_hash('password123', PASSWORD_DEFAULT)]);

// Example UPDATE query
$affectedRows = $db->update("UPDATE users SET last_login = NOW() WHERE id = ?", [$userId]);

// Transaction example
try {
    $db->beginTransaction();
    $db->executeQuery("INSERT INTO orders (user_id, total) VALUES (?, ?)", [1, 99.99]);
    $db->executeQuery("UPDATE products SET stock = stock - 1 WHERE id = ?", [1]);
    $db->commit();
} catch (Exception $e) {
    $db->rollback();
    echo "Transaction failed: " . $e->getMessage();
}
*/

?>
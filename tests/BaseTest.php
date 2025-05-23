<?php
/**
 * BaseTest Class
 * A base class for all tests to extend, provides common test functionality
 * 
 * @codeCoverageIgnore
 */

use PHPUnit\Framework\TestCase;

if (!class_exists('PHPUnit\Framework\TestCase') && class_exists('PHPUnit_Framework_TestCase')) {
    class_alias('PHPUnit_Framework_TestCase', 'PHPUnit\Framework\TestCase');
}

/**
 * BaseTest class to handle version compatibility and common test functionality
 * 
 * @abstract
 */
abstract class BaseTest extends TestCase
{
    protected $conn;
    
    /**
     * Set up test environment
     */
    protected function setUp(): void
    {
        parent::setUp();
        
        // Get test database connection
        $this->conn = $this->getTestDatabaseConnection();
    }
    
    /**
     * Clean up test environment
     */
    protected function tearDown(): void
    {
        if ($this->conn) {
            $this->conn->close();
            $this->conn = null;
        }
        
        parent::tearDown();
    }
    
    /**
     * Get a test database connection
     * 
     * @return mysqli|null Database connection or null on failure
     */
    protected function getTestDatabaseConnection() 
    {
        // Use test database for testing
        $servername = "localhost";
        $username = "root";
        $password = "";  
        $dbname = "aftermarket_toolkit_test";
        
        try {
            $conn = @new mysqli($servername, $username, $password, $dbname);
            
            if ($conn->connect_error) {
                $this->markTestSkipped("Could not connect to test database: " . $conn->connect_error);
                return null;
            }
            
            // Set charset to avoid encoding issues
            $conn->set_charset("utf8mb4");
            
            return $conn;
        } catch (Exception $e) {
            $this->markTestSkipped("Database Error: " . $e->getMessage());
            return null;
        }
    }
      /**
     * Reset test database to known state
     */
    protected function resetTestDatabase() 
    {
        if (!$this->conn) {
            return;
        }
        
        // Check if tables exist - if not, create them
        $result = $this->conn->query("SHOW TABLES");
        if ($result->num_rows == 0) {
            // Tables don't exist, create them from SQL file
            $sqlFile = file_get_contents(__DIR__ . '/setup_test_db.sql');
            
            // Split SQL file into individual statements
            $statements = array_filter(
                array_map('trim', 
                    explode(';', $sqlFile)
                )
            );
            
            // Execute each statement
            foreach ($statements as $statement) {
                if (!empty($statement)) {
                    $this->conn->query($statement);
                }
            }
            
            if ($this->conn->error) {
                $this->markTestSkipped("Error setting up test database: " . $this->conn->error);
                return;
            }
        } else {
            // Tables already exist, just truncate them
            // Disable foreign key checks temporarily
            $this->conn->query("SET FOREIGN_KEY_CHECKS = 0");
            
            // Get all tables
            $result = $this->conn->query("SHOW TABLES");
            while ($row = $result->fetch_array()) {
                $table = $row[0];
                $this->conn->query("TRUNCATE TABLE `$table`");
            }
            
            // Re-enable foreign key checks
            $this->conn->query("SET FOREIGN_KEY_CHECKS = 1");
        }
    }
    
    /**
     * Create test users
     */
    protected function createTestUsers() 
    {
        if (!$this->conn) {
            return;
        }
        
        // Create test users
        $this->conn->query("
            INSERT INTO users (id, username, email, password, created_at) VALUES 
            (1, 'testuser1', 'test1@example.com', '" . password_hash('password', PASSWORD_DEFAULT) . "', NOW()),
            (2, 'testuser2', 'test2@example.com', '" . password_hash('password', PASSWORD_DEFAULT) . "', NOW())
        ");
    }
}
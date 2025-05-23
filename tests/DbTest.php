<?php
/**
 * DbTest
 * Tests for database connection and basic operations
 */

require_once __DIR__ . '/BaseTest.php';

class DbTest extends BaseTest
{
    // Using parent's protected $conn instead of redefining it
    
    protected function setUp(): void
    {
        parent::setUp();
    }
    
    protected function tearDown(): void
    {
        if ($this->conn) {
            $this->conn->close();
            $this->conn = null;
        }
        
        parent::tearDown();
    }
    
    public function testDatabaseConnection()
    {
        // Test that we can connect to the test database
        $this->conn = getTestDatabaseConnection();
        $this->assertNotNull($this->conn);
        $this->assertInstanceOf(mysqli::class, $this->conn);
    }
    
    public function testBasicQuery()
    {
        $this->conn = getTestDatabaseConnection();
        
        // Create a test table
        $createTableQuery = "
            CREATE TEMPORARY TABLE test_table (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(50) NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ";
        $result = $this->conn->query($createTableQuery);
        $this->assertTrue($result);
        
        // Insert a test record
        $insertQuery = "INSERT INTO test_table (name) VALUES ('Test Record')";
        $result = $this->conn->query($insertQuery);
        $this->assertTrue($result);
        
        // Verify record was inserted
        $selectQuery = "SELECT * FROM test_table";
        $result = $this->conn->query($selectQuery);
        $this->assertEquals(1, $result->num_rows);
        
        $row = $result->fetch_assoc();
        $this->assertEquals('Test Record', $row['name']);
    }
    
    public function testPreparedStatement()
    {
        $this->conn = getTestDatabaseConnection();
        
        // Create a test table
        $createTableQuery = "
            CREATE TEMPORARY TABLE test_users (
                id INT AUTO_INCREMENT PRIMARY KEY,
                username VARCHAR(50) NOT NULL,
                email VARCHAR(100) NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ";
        $this->conn->query($createTableQuery);
        
        // Test prepared statement insert
        $stmt = $this->conn->prepare("INSERT INTO test_users (username, email) VALUES (?, ?)");
        $stmt->bind_param("ss", $username, $email);
        
        $username = "testuser";
        $email = "test@example.com";
        $result = $stmt->execute();
        $this->assertTrue($result);
        
        // Test prepared statement select
        $stmt = $this->conn->prepare("SELECT * FROM test_users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $this->assertEquals(1, $result->num_rows);
        
        $user = $result->fetch_assoc();
        $this->assertEquals('testuser', $user['username']);
        $this->assertEquals('test@example.com', $user['email']);
    }
}
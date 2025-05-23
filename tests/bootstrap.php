<?php
/**
 * Test bootstrap file
 * Sets up the environment for running tests
 */

// Define test environment
define('TESTING', true);

// Include the main application files
require_once __DIR__ . '/../config/db.php';

// Create a test database connection
function getTestDatabaseConnection() {
    // Use test database for testing
    $servername = "localhost";
    $username = "root";
    $password = "";  
    $dbname = "aftermarket_toolkit_test";
    
    try {
        $conn = @new mysqli($servername, $username, $password, $dbname);
        
        if ($conn->connect_error) {
            throw new Exception("Test database connection failed: " . $conn->connect_error);
        }
        
        // Set charset to avoid encoding issues
        $conn->set_charset("utf8mb4");
        
        return $conn;
    } catch (Exception $e) {
        echo "Database Error: " . $e->getMessage();
        return null;
    }
}

// Helper function to reset the test database
function resetTestDatabase($conn) {
    // Truncate tables or reset to known state
    $tables = ['notifications', 'messages', 'listings', 'users', 'friend_requests', 'forum_threads', 'forum_replies', 'jobs'];
    
    foreach ($tables as $table) {
        $conn->query("TRUNCATE TABLE $table");
    }
}
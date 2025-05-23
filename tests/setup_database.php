<?php
/**
 * Set up the test database
 * Run this script to create and initialize the test database
 */

// Database connection details
$host = 'localhost';
$user = 'root';
$password = '';
$testDb = 'aftermarket_toolkit_test';

echo "Setting up the test database...\n";

try {
    // Connect to MySQL server
    $pdo = new PDO("mysql:host=$host", $user, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Create the test database if it doesn't exist
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$testDb`");
    echo "Test database created or already exists.\n";
    
    // Connect to the test database
    $pdo = new PDO("mysql:host=$host;dbname=$testDb", $user, $password);
    
    // Read and execute the SQL schema file
    $sql = file_get_contents(__DIR__ . '/setup_test_db.sql');
    
    // Split SQL into separate statements
    $statements = explode(';', $sql);
    foreach ($statements as $statement) {
        $statement = trim($statement);
        if (!empty($statement)) {
            $pdo->exec($statement);
        }
    }
    
    echo "Database schema imported successfully!\n";
    echo "The test database is ready for use.\n";
} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage() . "\n");
}
<?php
// Script to ensure email_verified and verification_token columns exist in users table
require_once __DIR__ . '/../config/db.php';

// Function to check if column exists
function column_exists($conn, $table, $column) {
    $query = "SHOW COLUMNS FROM `{$table}` LIKE '{$column}'";
    $result = $conn->query($query);
    return $result->num_rows > 0;
}

// Check if email_verified column exists in users table
if (!column_exists($conn, 'users', 'email_verified')) {
    // Add the column with default value 0 (not verified)
    $query = "ALTER TABLE `users` ADD COLUMN `email_verified` TINYINT(1) NOT NULL DEFAULT 0";
    
    if ($conn->query($query)) {
        echo "Success: Added email_verified column to users table.<br>";
    } else {
        echo "Error adding column: " . $conn->error . "<br>";
    }
} else {
    echo "Column 'email_verified' already exists in users table.<br>";
}

// Check if verification_token column exists in users table
if (!column_exists($conn, 'users', 'verification_token')) {
    // Add the column
    $query = "ALTER TABLE `users` ADD COLUMN `verification_token` VARCHAR(64) NULL";
    
    if ($conn->query($query)) {
        echo "Success: Added verification_token column to users table.<br>";
    } else {
        echo "Error adding column: " . $conn->error . "<br>";
    }
} else {
    echo "Column 'verification_token' already exists in users table.<br>";
}

// Check if token_expiry column exists in users table
if (!column_exists($conn, 'users', 'token_expiry')) {
    // Add the column
    $query = "ALTER TABLE `users` ADD COLUMN `token_expiry` DATETIME NULL";
    
    if ($conn->query($query)) {
        echo "Success: Added token_expiry column to users table.<br>";
    } else {
        echo "Error adding column: " . $conn->error . "<br>";
    }
} else {
    echo "Column 'token_expiry' already exists in users table.<br>";
}

// Check if reset_token column exists in users table
if (!column_exists($conn, 'users', 'reset_token')) {
    // Add the column
    $query = "ALTER TABLE `users` ADD COLUMN `reset_token` VARCHAR(64) NULL";
    
    if ($conn->query($query)) {
        echo "Success: Added reset_token column to users table.<br>";
    } else {
        echo "Error adding column: " . $conn->error . "<br>";
    }
} else {
    echo "Column 'reset_token' already exists in users table.<br>";
}

// Check if reset_token_expiry column exists in users table
if (!column_exists($conn, 'users', 'reset_token_expiry')) {
    // Add the column
    $query = "ALTER TABLE `users` ADD COLUMN `reset_token_expiry` DATETIME NULL";
    
    if ($conn->query($query)) {
        echo "Success: Added reset_token_expiry column to users table.<br>";
    } else {
        echo "Error adding column: " . $conn->error . "<br>";
    }
} else {
    echo "Column 'reset_token_expiry' already exists in users table.<br>";
}

echo "Done!";
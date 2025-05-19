<?php
// Script to ensure email_notifications column exists in users table
require_once __DIR__ . '/../config/db.php';

// Function to check if column exists
function column_exists($conn, $table, $column) {
    $query = "SHOW COLUMNS FROM `{$table}` LIKE '{$column}'";
    $result = $conn->query($query);
    return $result->num_rows > 0;
}

// Check if email_notifications column exists in users table
if (!column_exists($conn, 'users', 'email_notifications')) {
    // Add the column with default value 1 (enabled)
    $query = "ALTER TABLE `users` ADD COLUMN `email_notifications` TINYINT(1) NOT NULL DEFAULT 1";
    
    if ($conn->query($query)) {
        echo "Success: Added email_notifications column to users table.<br>";
    } else {
        echo "Error adding column: " . $conn->error . "<br>";
    }
} else {
    echo "Column 'email_notifications' already exists in users table.<br>";
}

echo "Done!";
<?php
/**
 * Auto-create required database tables if they don't exist
 * Include this file in index.php and other important entry points
 */
if (!defined('INCLUDED')) {
    // Only check for direct access if INCLUDED isn't defined yet
    exit('Direct access not permitted');
}

require_once __DIR__ . '/../config/db.php';

// Check if notifications table exists and create it if it doesn't
try {
    $notifCheckSql = "SHOW TABLES LIKE 'notifications'";
    $notifResult = $conn->query($notifCheckSql);
    
    if ($notifResult->num_rows == 0) {
        // Table doesn't exist, create it
        $createNotificationsTable = "
        CREATE TABLE IF NOT EXISTS notifications (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            type VARCHAR(50) NOT NULL,
            from_user_id INT NULL,
            related_id INT NULL,
            content TEXT NOT NULL,
            is_read TINYINT(1) NOT NULL DEFAULT 0,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ";
        
        if ($conn->query($createNotificationsTable)) {
            error_log("Notifications table created successfully");
            
            // Create indexes for better performance
            $createIndexes = "
            ALTER TABLE notifications 
            ADD INDEX idx_user_read (user_id, is_read),
            ADD INDEX idx_type (type),
            ADD INDEX idx_created (created_at);
            ";
            
            try {
                $conn->query($createIndexes);
                error_log("Notification indexes created successfully");
            } catch (Exception $e) {
                error_log("Error creating notification indexes: " . $e->getMessage());
            }
        } else {
            error_log("Error creating notifications table: " . $conn->error);
        }
    }
} catch (Exception $e) {
    error_log("Error checking notifications table: " . $e->getMessage());
}

// Check if messages table has is_read column, add if it doesn't
try {
    $msgColumnCheckSql = "SHOW COLUMNS FROM messages LIKE 'is_read'";
    $msgColumnResult = $conn->query($msgColumnCheckSql);
    
    if ($msgColumnResult->num_rows == 0) {
        // Column doesn't exist, add it
        $addIsReadColumn = "ALTER TABLE messages ADD COLUMN is_read TINYINT(1) NOT NULL DEFAULT 0";
        if ($conn->query($addIsReadColumn)) {
            error_log("is_read column added to messages table");
        } else {
            error_log("Error adding is_read column to messages table: " . $conn->error);
        }
    }
} catch (Exception $e) {
    error_log("Error checking messages table: " . $e->getMessage());
}
?>
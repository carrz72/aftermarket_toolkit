<?php
/**
 * Setup script for notification tables
 * 
 * This script creates the necessary tables for the notification system
 */
require_once 'db.php';

echo "<h1>Setting up notification tables</h1>";

// Create notifications table
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
    echo "<p>✅ Notifications table created successfully!</p>";
} else {
    echo "<p>❌ Error creating notifications table: " . $conn->error . "</p>";
}

// Create indexes for better performance
$createIndexes = "
ALTER TABLE notifications 
ADD INDEX idx_user_read (user_id, is_read),
ADD INDEX idx_type (type),
ADD INDEX idx_created (created_at);
";

try {
    $conn->query($createIndexes);
    echo "<p>✅ Notification indexes created successfully!</p>";
} catch (Exception $e) {
    echo "<p>ℹ️ Notification indexes may already exist: " . $e->getMessage() . "</p>";
}

// Add is_read column to messages table if it doesn't exist
$checkMsgColumn = "SHOW COLUMNS FROM messages LIKE 'is_read'";
$columnExists = $conn->query($checkMsgColumn);
if ($columnExists && $columnExists->num_rows == 0) {
    $addMsgColumn = "ALTER TABLE messages ADD COLUMN is_read TINYINT(1) NOT NULL DEFAULT 0";
    if ($conn->query($addMsgColumn)) {
        echo "<p>✅ Added is_read column to messages table</p>";
    } else {
        echo "<p>❌ Error adding is_read column to messages table: " . $conn->error . "</p>";
    }
} else {
    echo "<p>✓ is_read column already exists in messages table</p>";
}

// Add is_read column to forum_replies table if it doesn't exist
$checkForumColumn = "SHOW COLUMNS FROM forum_replies LIKE 'is_read'";
$forumColumnExists = $conn->query($checkForumColumn);
if ($forumColumnExists && $forumColumnExists->num_rows == 0) {
    $addForumColumn = "ALTER TABLE forum_replies ADD COLUMN is_read TINYINT(1) NOT NULL DEFAULT 0";
    if ($conn->query($addForumColumn)) {
        echo "<p>✅ Added is_read column to forum_replies table</p>";
        
        // Mark all existing replies as read
        $markReadSql = "UPDATE forum_replies SET is_read = 1";
        if ($conn->query($markReadSql)) {
            echo "<p>✅ Marked all existing forum replies as read</p>";
        }
    } else {
        echo "<p>❌ Error adding is_read column to forum_replies table: " . $conn->error . "</p>";
    }
} else {
    echo "<p>✓ is_read column already exists in forum_replies table</p>";
}

// Add indexes for performance
$indexes = [
    "ALTER TABLE forum_replies ADD INDEX idx_thread_user_read (thread_id, user_id, is_read)",
    "ALTER TABLE messages ADD INDEX idx_receiver_read (receiver_id, is_read)",
    "ALTER TABLE friend_requests ADD INDEX idx_receiver (receiver_id)"
];

foreach ($indexes as $index) {
    try {
        if ($conn->query($index)) {
            echo "<p>✅ Added index: " . htmlspecialchars($index) . "</p>";
        }
    } catch (Exception $e) {
        // Index might already exist, which is fine
        echo "<p>ℹ️ Note: " . htmlspecialchars($e->getMessage()) . "</p>";
    }
}

echo "<h2>Setup complete!</h2>";
echo "<p>The notification system is now ready to use.</p>";
echo "<p><a href='../index.php'>Return to Homepage</a></p>";
?>
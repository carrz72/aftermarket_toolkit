<?php
// Setup notifications table for the application
require_once 'db.php';

// Create notifications table if it doesn't exist
$notificationsTableQuery = "
CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    type ENUM('friend_request', 'message', 'forum_response') NOT NULL,
    sender_id INT,
    related_id INT,
    content VARCHAR(255),
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
)";

if ($conn->query($notificationsTableQuery)) {
    echo "Notifications table created successfully or already exists.<br>";
} else {
    echo "Error creating notifications table: " . $conn->error . "<br>";
}

// Add index for faster lookups
$indexQuery = "CREATE INDEX idx_user_read ON notifications(user_id, is_read)";
try {
    $conn->query($indexQuery);
    echo "Index created successfully.<br>";
} catch (Exception $e) {
    echo "Index may already exist.<br>";
}

echo "Setup completed.";
?>
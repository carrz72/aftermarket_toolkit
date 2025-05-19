<?php
// File: chat_message_handler.php
// Handler for chat messages, including email notifications

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/notification_email.php';
require_once __DIR__ . '/../../includes/notification_handler.php';

session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    // Return error as JSON for AJAX requests
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit();
}

$sender_id = $_SESSION['user_id'];

// Process new message
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['recipient_id'], $_POST['message'])) {
    $recipient_id = filter_input(INPUT_POST, 'recipient_id', FILTER_SANITIZE_NUMBER_INT);
    $message = trim($_POST['message']);
    
    // Validate input
    if (empty($message)) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Message cannot be empty']);
        exit();
    }
    
    // Check if these users are friends
    $checkFriendship = $conn->prepare("
        SELECT * FROM friends 
        WHERE (user_id = ? AND friend_id = ?) 
        OR (user_id = ? AND friend_id = ?)
    ");
    $checkFriendship->bind_param("iiii", $sender_id, $recipient_id, $recipient_id, $sender_id);
    $checkFriendship->execute();
    $friendshipResult = $checkFriendship->get_result();
    
    if ($friendshipResult->num_rows == 0) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'You can only message users who are your friends']);
        exit();
    }
    
    // Insert message
    $stmt = $conn->prepare("
        INSERT INTO messages (sender_id, recipient_id, message, created_at, is_read) 
        VALUES (?, ?, ?, NOW(), 0)
    ");
    $stmt->bind_param("iis", $sender_id, $recipient_id, $message);
    
    if ($stmt->execute()) {
        $message_id = $stmt->insert_id;
          // Get sender username and profile picture
        $userQuery = "SELECT username, profile_picture FROM users WHERE id = ?";
        $userStmt = $conn->prepare($userQuery);
        $userStmt->bind_param("i", $sender_id);
        $userStmt->execute();
        $userResult = $userStmt->get_result();
        $userData = $userResult->fetch_assoc();
        $sender_username = $userData['username'];
        
        // Create notification
        $notification_content = "New message from {$sender_username}";
        
        // Check which notification function is available
        if (function_exists('createNotification')) {
            // Use existing notification function
            createNotification($conn, $recipient_id, 'message', $notification_content, $sender_id);
        } else if (function_exists('sendNotification')) {
            // Use the sendNotification function if available
            sendNotification(
                $conn,
                $recipient_id,
                'message',
                $sender_id,
                $message_id,
                $notification_content
            );
        } else {
            // Fall back to direct notification creation
            $notifyStmt = $conn->prepare("
                INSERT INTO notifications (user_id, type, content, related_id, created_at) 
                VALUES (?, 'message', ?, ?, NOW())
            ");
            $notifyStmt->bind_param("isi", $recipient_id, $notification_content, $sender_id);
            $notifyStmt->execute();
        }
        
        // Send email notification
        sendNotificationEmail($recipient_id, 'message', $notification_content, $conn);
        
        // Return success response
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true, 
            'message_id' => $message_id,
            'created_at' => date('Y-m-d H:i:s')
        ]);
    } else {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Error sending message: ' . $conn->error]);
    }
    exit();
}

// Mark message as read
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'mark_read' && isset($_POST['message_id'])) {
    $message_id = filter_input(INPUT_POST, 'message_id', FILTER_SANITIZE_NUMBER_INT);
    
    // Only mark as read if the user is the recipient
    $stmt = $conn->prepare("UPDATE messages SET is_read = 1 WHERE id = ? AND recipient_id = ?");
    $stmt->bind_param("ii", $message_id, $sender_id);
    
    if ($stmt->execute()) {
        header('Content-Type: application/json');
        echo json_encode(['success' => true]);
    } else {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Error marking message as read']);
    }
    exit();
}

// Default response for invalid requests
header('Content-Type: application/json');
echo json_encode(['success' => false, 'message' => 'Invalid request']);
exit();
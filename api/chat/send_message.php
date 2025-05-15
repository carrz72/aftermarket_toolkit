<?php
session_start();
require_once '../../config/db.php';
require_once '../../includes/notification_handler.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit;
}

// Get sender ID from session
$sender_id = $_SESSION['user_id'];

// Validate input
if (!isset($_POST['receiver_id']) || !isset($_POST['message'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

$receiver_id = (int)$_POST['receiver_id'];
$message = trim($_POST['message']);

// Check if receiver exists
$check_user = $conn->prepare("SELECT id FROM users WHERE id = ?");
$check_user->bind_param("i", $receiver_id);
$check_user->execute();
$result = $check_user->get_result();

if ($result->num_rows == 0) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Receiver not found']);
    exit;
}

if (empty($message)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Message cannot be empty']);
    exit;
}

// Insert message into database
$stmt = $conn->prepare("INSERT INTO messages (sender_id, receiver_id, message, created_at, is_read) VALUES (?, ?, ?, NOW(), 0)");
$stmt->bind_param("iis", $sender_id, $receiver_id, $message);

if ($stmt->execute()) {
    $message_id = $conn->insert_id;
    
    // Create notification for the receiver
    try {
        // Only create notification if the function exists
        if (function_exists('createChatMessageNotification')) {
            createChatMessageNotification($conn, $message_id, $sender_id, $receiver_id, $message);
        } else {
            // Alternative using sendNotification if available
            if (function_exists('sendNotification')) {
                sendNotification($conn, $receiver_id, 'message', $sender_id, $message_id);
            }
        }
    } catch (Exception $e) {
        // Log error but don't stop the message from sending
        error_log("Error creating chat notification: " . $e->getMessage());
    }
    
    // Get the sender's info to return with the response
    $sender_query = $conn->prepare("SELECT username, profile_picture FROM users WHERE id = ?");
    $sender_query->bind_param("i", $sender_id);
    $sender_query->execute();
    $sender_info = $sender_query->get_result()->fetch_assoc();
    
    http_response_code(200);
    echo json_encode([
        'success' => true, 
        'message' => 'Message sent successfully',
        'message_data' => [
            'id' => $message_id,
            'sender_id' => $sender_id,
            'sender_username' => $sender_info['username'],
            'sender_profile_picture' => $sender_info['profile_picture'],
            'message' => $message,
            'created_at' => date('Y-m-d H:i:s')
        ]
    ]);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to send message']);
}
?>
<?php
session_start();
require_once '../../config/db.php';
require_once '../../includes/image_helper.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'You must be logged in to send messages']);
    exit;
}

// Validate message data
if (!isset($_POST['receiver_id']) || !isset($_POST['message']) || empty($_POST['message'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

// Sanitize inputs
$sender_id = $_SESSION['user_id'];
$receiver_id = (int)$_POST['receiver_id'];
$message = trim($_POST['message']);

// Optional listing ID if the message is related to a listing
$listing_id = isset($_POST['listing_id']) ? (int)$_POST['listing_id'] : null;

// Check if receiver exists
$check_user_sql = "SELECT id FROM users WHERE id = ?";
$check_user_stmt = $conn->prepare($check_user_sql);
$check_user_stmt->bind_param("i", $receiver_id);
$check_user_stmt->execute();
$user_result = $check_user_stmt->get_result();

if ($user_result->num_rows === 0) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Receiver not found']);
    exit;
}

// Don't allow sending messages to yourself
if ($sender_id === $receiver_id) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'You cannot send messages to yourself']);
    exit;
}

// Insert the message
$sql = "INSERT INTO messages (sender_id, receiver_id, message, related_listing_id, created_at, is_read) VALUES (?, ?, ?, ?, NOW(), 0)";
$stmt = $conn->prepare($sql);
$stmt->bind_param("iisi", $sender_id, $receiver_id, $message, $listing_id);

if ($stmt->execute()) {
    $message_id = $stmt->insert_id;
    
    // Create notification for new message
    $senderQuery = "SELECT username FROM users WHERE id = ?";
    $senderStmt = $conn->prepare($senderQuery);
    $senderStmt->bind_param("i", $sender_id);
    $senderStmt->execute();
    $senderResult = $senderStmt->get_result();
    $senderRow = $senderResult->fetch_assoc();
    $senderUsername = $senderRow['username'];
    
    // Create notification
    $notificationContent = "New message from " . $senderUsername;
    createNotification($conn, $receiver_id, 'message', $message_id, $notificationContent);
    
    // Get the current time for display
    $time = date('g:i A');
    
    // Get the chat ID to include in the response
    $chat_id = max($sender_id, $receiver_id) . "_" . min($sender_id, $receiver_id);
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true, 
        'message' => 'Message sent successfully', 
        'time' => $time,
        'chat_id' => $chat_id,
        'message_id' => $message_id
    ]);
} else {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Error sending message: ' . $conn->error]);
}
?>
<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/notification_handler.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit();
}

// Check if it's a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit();
}

// Check required parameters
if (empty($_POST['message']) || empty($_POST['receiver_id'])) {
    echo json_encode(['success' => false, 'error' => 'Missing required parameters']);
    exit();
}

$senderId = $_SESSION['user_id'];
$receiverId = (int)$_POST['receiver_id'];
$message = trim($_POST['message']);
$listingId = null;

// Extract listing ID from the message text if it exists
if (preg_match('/\[LISTING_ID:(\d+)\]/', $message, $matches)) {
    $listingId = (int)$matches[1]; 
}

// Validate receiver exists
$checkUser = $conn->prepare("SELECT id FROM users WHERE id = ?");
$checkUser->bind_param("i", $receiverId);
$checkUser->execute();
$result = $checkUser->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid receiver']);
    exit();
}

// Insert message into database with listing_id
$stmt = $conn->prepare("INSERT INTO messages (sender_id, receiver_id, message, listing_id, is_read, sent_at) VALUES (?, ?, ?, ?, 0, NOW())");
$stmt->bind_param("iisi", $senderId, $receiverId, $message, $listingId);

if ($stmt->execute()) {
    $messageId = $conn->insert_id;
    
    // Check if notification already exists for this message to prevent duplicates
    $checkSql = "SELECT id FROM notifications WHERE type = 'message' AND related_id = ?";
    $checkStmt = $conn->prepare($checkSql);
    $checkStmt->bind_param("i", $messageId);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    
    // Only create notification if it doesn't already exist
    if ($checkResult->num_rows == 0) {
        createChatMessageNotification(
          $conn,
          $messageId,
          $senderId,
          $receiverId,
          $message
        );
    }

    echo json_encode([
        'success'    => true,
        'message_id' => $messageId,
        'sent_at'    => date('Y-m-d H:i:s'),
        'listing_id' => $listingId
    ]);
} else {
    echo json_encode(['success' => false, 'error' => 'Failed to send message: '.$conn->error]);
}
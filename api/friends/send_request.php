<?php
session_start();
require_once '../../config/db.php';
require_once '../../includes/image_helper.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'You must be logged in to add a friend']);
    exit;
}

// Get current user ID
$sender_id = $_SESSION['user_id'];

// Get the target user ID
if (isset($_POST['user_id']) && !empty($_POST['user_id'])) {
    $receiver_id = (int)$_POST['user_id'];
} else if (isset($_POST['username']) && !empty($_POST['username'])) {
    // Look up user ID from username
    $username = $_POST['username'];
    $stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $receiver_id = $row['id'];
    } else {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'User not found']);
        exit;
    }
} else {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'No user specified']);
    exit;
}

// Prevent sending a request to yourself
if ($sender_id == $receiver_id) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'You cannot add yourself as a friend']);
    exit;
}

// Check if friend request already exists or if they are already friends
$check_sql = "
    SELECT * FROM friend_requests 
    WHERE (sender_id = ? AND receiver_id = ?) 
    OR (sender_id = ? AND receiver_id = ?)
";
$check_stmt = $conn->prepare($check_sql);
$check_stmt->bind_param("iiii", $sender_id, $receiver_id, $receiver_id, $sender_id);
$check_stmt->execute();
$check_result = $check_stmt->get_result();

if ($check_result->num_rows > 0) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'A friend request already exists between you and this user']);
    exit;
}

// Check if they are already friends
$friends_check_sql = "
    SELECT * FROM friends 
    WHERE (user_id1 = ? AND user_id2 = ?) 
    OR (user_id1 = ? AND user_id2 = ?)
";
$friends_check_stmt = $conn->prepare($friends_check_sql);
$friends_check_stmt->bind_param("iiii", $sender_id, $receiver_id, $receiver_id, $sender_id);
$friends_check_stmt->execute();
$friends_check_result = $friends_check_stmt->get_result();

if ($friends_check_result->num_rows > 0) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'You are already friends with this user']);
    exit;
}

// Create the friend request
$sql = "INSERT INTO friend_requests (sender_id, receiver_id, created_at) VALUES (?, ?, NOW())";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $sender_id, $receiver_id);

if ($stmt->execute()) {
    $request_id = $stmt->insert_id;
    
    // Get sender's username for notification
    $usernameQuery = "SELECT username FROM users WHERE id = ?";
    $usernameStmt = $conn->prepare($usernameQuery);
    $usernameStmt->bind_param("i", $sender_id);
    $usernameStmt->execute();
    $usernameResult = $usernameStmt->get_result();
    $usernameRow = $usernameResult->fetch_assoc();
    $senderUsername = $usernameRow['username'];
    
    // Create notification
    $notificationContent = $senderUsername . " has sent you a friend request";
    createNotification($conn, $receiver_id, 'friend_request', $request_id, $notificationContent);
    
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'message' => 'Friend request sent successfully']);
} else {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Error sending friend request: ' . $conn->error]);
}
?>
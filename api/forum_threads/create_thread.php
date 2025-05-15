<?php
session_start();
require_once '../../config/db.php';
require_once '../../includes/notification_handler.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit;
}

// Check if all required data is present
if (!isset($_POST['title']) || !isset($_POST['body']) || !isset($_POST['category'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

$userId = $_SESSION['user_id'];
$title = trim($_POST['title']);
$body = trim($_POST['body']);
$category = trim($_POST['category']);

// Validate input
if (empty($title)) {
    echo json_encode(['success' => false, 'message' => 'Title cannot be empty']);
    exit;
}

if (empty($body)) {
    echo json_encode(['success' => false, 'message' => 'Content cannot be empty']);
    exit;
}

if (empty($category)) {
    echo json_encode(['success' => false, 'message' => 'Category must be selected']);
    exit;
}

// Insert thread into database
try {
    $stmt = $conn->prepare("INSERT INTO forum_threads (user_id, title, body, category, created_at) VALUES (?, ?, ?, ?, NOW())");
    $stmt->bind_param("isss", $userId, $title, $body, $category);
    
    if ($stmt->execute()) {
        $threadId = $conn->insert_id;
        
        // Notify followers or subscribers if applicable
        // This is where you could add notification code for users who follow this category
        
        echo json_encode([
            'success' => true, 
            'message' => 'Thread created successfully', 
            'thread_id' => $threadId
        ]);
    } else {
        echo json_encode([
            'success' => false, 
            'message' => 'Failed to create thread: ' . $conn->error
        ]);
    }
} catch (Exception $e) {
    echo json_encode([
        'success' => false, 
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>
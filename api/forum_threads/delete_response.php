<?php
// filepath: c:\xampp\htdocs\aftermarket_toolkit\api\forum_threads\delete_response.php
session_start();
require_once __DIR__ . '/../../config/db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    $_SESSION['error'] = "You must be logged in to delete a response.";
    header('Location: ../../public/login.php');
    exit();
}

// Check if form was submitted with required data
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['response_id']) && isset($_POST['thread_id'])) {
    $userId = $_SESSION['user_id'];
    $responseId = (int)$_POST['response_id'];
    $threadId = (int)$_POST['thread_id'];
    
    // Verify the response exists and belongs to this user
    $checkQuery = "SELECT id FROM forum_replies WHERE id = ? AND user_id = ?";
    $checkStmt = $conn->prepare($checkQuery);
    $checkStmt->bind_param("ii", $responseId, $userId);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    
    if ($result->num_rows === 0) {
        $_SESSION['error'] = "You do not have permission to delete this response.";
        header('Location: ../../public/forum.php?thread=' . $threadId);
        exit();
    }
    
    // Delete the response
    $deleteQuery = "DELETE FROM forum_replies WHERE id = ? AND user_id = ?";
    $deleteStmt = $conn->prepare($deleteQuery);
    $deleteStmt->bind_param("ii", $responseId, $userId);
    
    if ($deleteStmt->execute()) {
        $_SESSION['success'] = "Your response has been deleted.";
    } else {
        $_SESSION['error'] = "Failed to delete the response. Please try again.";
    }
    
    // Redirect back to the thread
    header('Location: ../../public/forum.php?thread=' . $threadId);
    exit();
} else {
    // Missing required parameters
    $_SESSION['error'] = "Invalid request.";
    header('Location: ../../public/forum.php');
    exit();
}
?>
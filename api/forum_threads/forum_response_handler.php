<?php
// File: forum_response_handler.php
// Handler for forum responses, including email notifications

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/notification_email.php';
require_once __DIR__ . '/../../includes/notification_handler.php';

session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../public/login.php");
    exit();
}

// Process the form submission for adding a response
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['thread_id'], $_POST['response_body'])) {
    $thread_id = filter_input(INPUT_POST, 'thread_id', FILTER_SANITIZE_NUMBER_INT);
    $response_body = trim($_POST['response_body']);
    $user_id = $_SESSION['user_id'];
    
    // Validate input
    if (empty($response_body)) {
        $_SESSION['error'] = "Response body cannot be empty.";
        header("Location: ../../public/forum.php?thread=$thread_id");
        exit();
    }
    
    // Get thread information to identify the thread owner
    $threadQuery = "SELECT user_id, title FROM forum_threads WHERE id = ?";
    $threadStmt = $conn->prepare($threadQuery);
    $threadStmt->bind_param("i", $thread_id);
    $threadStmt->execute();
    $threadResult = $threadStmt->get_result();
    
    if ($threadResult->num_rows > 0) {
        $threadData = $threadResult->fetch_assoc();
        $thread_owner_id = $threadData['user_id'];
        $thread_title = $threadData['title'];
        
        // Insert the response
        $stmt = $conn->prepare("INSERT INTO forum_replies (thread_id, user_id, body, created_at) VALUES (?, ?, ?, NOW())");
        $stmt->bind_param("iis", $thread_id, $user_id, $response_body);
        
        if ($stmt->execute()) {
            $response_id = $stmt->insert_id;
            
            // Only create notification if the responder is not the thread owner
            if ($user_id != $thread_owner_id) {
                // Get username of the responder
                $userQuery = "SELECT username FROM users WHERE id = ?";
                $userStmt = $conn->prepare($userQuery);
                $userStmt->bind_param("i", $user_id);
                $userStmt->execute();
                $userResult = $userStmt->get_result();
                $userData = $userResult->fetch_assoc();
                $responder_username = $userData['username'];
                  // Create notification
                $notification_content = "{$responder_username} replied to your thread \"{$thread_title}\"";
                
                // Check if notification_handler.php defines the createNotification function
                if (function_exists('createNotification')) {
                    // Use existing notification function
                    createNotification($conn, $thread_owner_id, 'forum_response', $notification_content, $thread_id);
                } else if (function_exists('sendNotification')) {
                    // Use the sendNotification function if available
                    sendNotification(
                        $conn,
                        $thread_owner_id,
                        'forum_response',
                        $user_id,
                        $thread_id,
                        $notification_content
                    );
                } else {
                    // Fall back to direct notification creation
                    $notifyStmt = $conn->prepare("
                        INSERT INTO notifications (user_id, type, content, related_id, created_at) 
                        VALUES (?, 'forum_response', ?, ?, NOW())
                    ");
                    $notifyStmt->bind_param("isi", $thread_owner_id, $notification_content, $thread_id);
                    $notifyStmt->execute();
                }
                
                // Send email notification
                sendNotificationEmail($thread_owner_id, 'forum_response', $notification_content, $conn);
            }
            
            $_SESSION['success'] = "Response added successfully!";
        } else {
            $_SESSION['error'] = "Error adding response: " . $conn->error;
        }
    } else {
        $_SESSION['error'] = "Thread not found.";
    }
    
    // Redirect back to the thread
    header("Location: ../../public/forum.php?thread=$thread_id");
    exit();
}

// Process delete request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete' && isset($_POST['response_id'])) {
    // Handle deletion logic here
    // ...
}

// If we get here, it's an invalid request
header("Location: ../../public/forum.php");
exit();
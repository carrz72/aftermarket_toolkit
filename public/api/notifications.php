<?php
/**
 * Notifications API
 * Endpoint for handling notification operations
 */

// Initialize session and includes
session_start();
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/image_helper.php';
require_once __DIR__ . '/../../includes/notification_handler.php';

// Set content type to JSON
header('Content-Type: application/json');

// Default API response
$response = [
    'success' => false,
    'message' => 'An error occurred',
    'notifications' => [],
    'counts' => []
];

// Validate login
if (!isset($_SESSION['user_id'])) {
    $response['message'] = 'Unauthorized access';
    echo json_encode($response);
    exit;
}
$userId = $_SESSION['user_id'];

// GET: retrieve notifications
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
        $unreadOnly = isset($_GET['unread_only']) && $_GET['unread_only'] == 1;
        $notifications = getNotifications($conn, $userId, $limit, $unreadOnly);
        $counts = countUnreadNotifications($conn, $userId);
        
        // Debug output to check what's being returned
        error_log("Notifications API: Found " . count($notifications) . " notifications for user $userId");
        error_log("Notification counts: " . json_encode($counts));
        
        $response['success'] = true;
        $response['message'] = 'Notifications retrieved successfully';
        $response['notifications'] = $notifications;
        $response['counts'] = $counts;
    } catch (Exception $e) {
        error_log("Notifications API error: " . $e->getMessage());
        $response['message'] = 'Failed to retrieve notifications: ' . $e->getMessage();
    }
}

// POST: mark read actions
else if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!isset($_POST['action'])) {
            throw new Exception('Action parameter is required');
        }
        
        $action = $_POST['action'];
        
        switch ($action) {
            case 'mark_read':
                // Mark a specific notification as read
                if (!isset($_POST['notification_id'])) {
                    throw new Exception('Notification ID is required');
                }
                
                $notificationId = (int)$_POST['notification_id'];
                $success = markNotificationAsRead($conn, $notificationId, $userId);
                
                if ($success) {
                    $response['success'] = true;
                    $response['message'] = 'Notification marked as read';
                    $response['counts'] = countUnreadNotifications($conn, $userId);
                } else {
                    throw new Exception('Failed to mark notification as read');
                }
                break;
                
            case 'mark_all_read':
                // Mark all notifications as read
                $success = markAllNotificationsAsRead($conn, $userId);
                
                if ($success) {
                    $response['success'] = true;
                    $response['message'] = 'All notifications marked as read';
                    $response['counts'] = countUnreadNotifications($conn, $userId);
                } else {
                    throw new Exception('Failed to mark notifications as read');
                }
                break;
                
            default:
                throw new Exception('Invalid action');
        }
    } catch (Exception $e) {
        $response['message'] = 'Action failed: ' . $e->getMessage();
    }
}

echo json_encode($response);
?>
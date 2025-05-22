<?php
/**
 * Notifications API
 * Endpoint for handling notification operations
 */

// Initialize session and includes
session_start();
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/image_helper.php';

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

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Mark all as read
    if (isset($_POST['action']) && $_POST['action'] === 'mark_all_read') {
        $updateStmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
        $updateStmt->bind_param("i", $userId);
        $success = $updateStmt->execute();
        
        $response = [
            'success' => $success,
            'counts' => ['total' => 0]
        ];
        echo json_encode($response);
        exit;
    }
    
    // Mark single notification as read
    if (isset($_POST['action']) && $_POST['action'] === 'mark_read' && isset($_POST['notification_id'])) {
        $notificationId = (int)$_POST['notification_id'];
        $updateStmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
        $updateStmt->bind_param("ii", $notificationId, $userId);
        $success = $updateStmt->execute();
        
        // Get updated notification counts
        $countQuery = "SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0";
        $countStmt = $conn->prepare($countQuery);
        $countStmt->bind_param("i", $userId);
        $countStmt->execute();
        $result = $countStmt->get_result();
        $unreadCount = $result->fetch_assoc()['count'];
        
        $response = [
            'success' => $success,
            'counts' => ['total' => $unreadCount]
        ];
        echo json_encode($response);
        exit;
    }
    
    // Invalid action
    $response['message'] = 'Invalid action';
    echo json_encode($response);
    exit;
}

try {
    // GET request - return notifications
    // Get unread count
    $countQuery = "SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0";
    $countStmt = $conn->prepare($countQuery);
    $countStmt->bind_param("i", $userId);
    $countStmt->execute();
    $unreadCount = $countStmt->get_result()->fetch_assoc()['count'];

    // Get counts by type
    $countsByType = [];
    $typesQuery = "SELECT type, COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0 GROUP BY type";
    $typesStmt = $conn->prepare($typesQuery);
    $typesStmt->bind_param("i", $userId);
    $typesStmt->execute();
    $typesResult = $typesStmt->get_result();

    while ($row = $typesResult->fetch_assoc()) {
        $countsByType[$row['type']] = $row['count'];
    }

    // Get recent notifications - use fewer JOINs for simplicity
    $notificationQuery = "
        SELECT n.* 
        FROM notifications n
        WHERE n.user_id = ?
        ORDER BY n.created_at DESC
        LIMIT 10
    ";
    $notificationStmt = $conn->prepare($notificationQuery);
    $notificationStmt->bind_param("i", $userId);
    $notificationStmt->execute();
    $notificationsResult = $notificationStmt->get_result();

    $notifications = [];
    while ($notification = $notificationsResult->fetch_assoc()) {
        // Generate appropriate link for each notification type
        $link = '#';
        switch ($notification['type']) {
            case 'friend_request':
                $link = './friends.php';
                break;
            case 'message':
                $link = $notification['related_id'] ? "./chat.php?chat={$notification['related_id']}" : './chat.php';
                break;
            case 'forum_response':
                $link = $notification['related_id'] ? "./forum.php?thread={$notification['related_id']}" : './forum.php';
                break;
            case 'listing_comment':
                $link = $notification['related_id'] ? "../api/listings/listing.php?id={$notification['related_id']}" : './marketplace.php';
                break;
            default:
                $link = './notifications.php';
        }
        
        $notification['link'] = $link;
        $notifications[] = $notification;
    }

    // Prepare response
    $response = [
        'success' => true,
        'counts' => [
            'total' => $unreadCount,
            'messages' => $countsByType['message'] ?? 0,
            'friend_requests' => $countsByType['friend_request'] ?? 0,
            'forum_responses' => $countsByType['forum_response'] ?? 0
        ],
        'notifications' => $notifications
    ];
} catch (Exception $e) {
    $response['message'] = "Database error: " . $e->getMessage();
}

echo json_encode($response);
?>
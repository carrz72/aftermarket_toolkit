<?php
/**
 * Notification Handler
 * Manages all notification operations for the Aftermarket Toolkit application
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/notification_handler.php';
require_once __DIR__ . '/notification_email.php';

/**
 * Send notification to user
 * 
 * @param int $userId Recipient user ID
 * @param string $type Notification type (friend_request, message, forum_response)
 * @param int $senderId Sender user ID (if applicable)
 * @param int $relatedId ID of related content (request_id, message_id, forum_reply_id)
 * @param string $content Notification message
 * @param string $link Custom link for the notification
 * @return bool Success or failure
 */
function sendNotification($conn, $userId, $type, $senderId = null, $relatedId = null, $content = '', $link = null) {
    // Don't send notifications to yourself
    if ($senderId == $userId) {
        return false;
    }
    
    // Generate content if not provided
    if (empty($content)) {
        $content = generateNotificationContent($conn, $type, $senderId, $relatedId);
    }
    
    // Determine a default link based on type if not provided
    if (empty($link)) {
        if ($type == 'message') {
            $link = '/aftermarket_toolkit/public/chat.php?chat=' . $senderId;
        } else {
            $link = '/aftermarket_toolkit/public/notifications.php';
        }
    }
    // Insert notification including link column
    $sql = "INSERT INTO notifications (user_id, type, sender_id, related_id, content, link, is_read, created_at)
            VALUES (?, ?, ?, ?, ?, ?, 0, NOW())";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("isisss", $userId, $type, $senderId, $relatedId, $content, $link);
    $success = $stmt->execute();
    
    // Send email notification if the function exists
    if (function_exists('sendNotificationEmail')) {
        sendNotificationEmail($userId, $type, $content, $conn);
    }
    
    return $success;
}

/**
 * Generate notification content based on type
 * 
 * @param object $conn Database connection
 * @param string $type Notification type
 * @param int $senderId User ID of sender
 * @param int $relatedId ID of related content
 * @return string Generated content
 */
function generateNotificationContent($conn, $type, $senderId, $relatedId) {
    // Get sender username
    $username = 'Someone';
    if ($senderId) {
        $userStmt = $conn->prepare("SELECT username FROM users WHERE id = ?");
        $userStmt->bind_param("i", $senderId);
        $userStmt->execute();
        $userResult = $userStmt->get_result();
        if ($row = $userResult->fetch_assoc()) {
            $username = $row['username'];
        }
    }
    
    // Generate content based on type
    switch ($type) {
        case 'friend_request':
            return "$username sent you a friend request";
            
        case 'message':
            // Get message preview
            $preview = '';
            if ($relatedId) {
                $msgStmt = $conn->prepare("SELECT message FROM messages WHERE id = ? LIMIT 1");
                $msgStmt->bind_param("i", $relatedId);
                $msgStmt->execute();
                $msgResult = $msgStmt->get_result();
                if ($row = $msgResult->fetch_assoc()) {
                    $preview = substr($row['message'], 0, 30) . (strlen($row['message']) > 30 ? '...' : '');
                }
            }
            return "$username sent you a message: \"$preview\"";
            
        case 'forum_response':
            // Get thread title
            $title = 'a forum thread';
            if ($relatedId) {
                $threadStmt = $conn->prepare("
                    SELECT t.title 
                    FROM forum_threads t 
                    JOIN forum_replies r ON t.id = r.thread_id 
                    WHERE r.id = ?
                ");
                $threadStmt->bind_param("i", $relatedId);
                $threadStmt->execute();
                $threadResult = $threadStmt->get_result();
                if ($row = $threadResult->fetch_assoc()) {
                    $title = $row['title'];
                }
            }
            return "$username responded to your thread \"$title\"";
            
        default:
            return "You have a new notification";
    }
}

/**
 * Get notifications for a user
 * 
 * @param object $conn Database connection
 * @param int $userId User ID
 * @param int $limit Maximum notifications to return (0 for unlimited)
 * @param bool $unreadOnly Show only unread notifications
 * @return array Notifications with additional details
 */
function getNotifications($conn, $userId, $limit = 10, $unreadOnly = false) {
    $limitClause = $limit > 0 ? "LIMIT $limit" : "";
    $unreadClause = $unreadOnly ? "AND is_read = 0" : "";
    
    $sql = "
        SELECT n.*, u.username, u.profile_picture 
        FROM notifications n
        LEFT JOIN users u ON n.sender_id = u.id
        WHERE n.user_id = ? $unreadClause
        ORDER BY n.created_at DESC
        $limitClause
    ";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $notifications = [];
    while ($row = $result->fetch_assoc()) {
        // Enhance with additional details based on notification type
        $row = enhanceNotificationDetails($conn, $row);
        $notifications[] = $row;
    }
    
    return $notifications;
}

/**
 * Add additional details to notification based on its type
 * 
 * @param object $conn Database connection
 * @param array $notification Notification data
 * @return array Enhanced notification with additional details
 */
function enhanceNotificationDetails($conn, $notification) {
    switch ($notification['type']) {
        case 'friend_request':
            $notification['link'] = "/aftermarket_toolkit/public/friends.php";
            break;
            
        case 'message':
            $notification['link'] = "/aftermarket_toolkit/public/chat.php";
            if ($notification['sender_id']) {
                $notification['chat_id'] = $notification['sender_id'];
            }
            break;
            
        case 'forum_response':
            // Get the thread ID from the reply
            if ($notification['related_id']) {
                $threadStmt = $conn->prepare("
                    SELECT thread_id FROM forum_replies WHERE id = ?
                ");
                $threadStmt->bind_param("i", $notification['related_id']);
                $threadStmt->execute();
                $result = $threadStmt->get_result();
                if ($thread = $result->fetch_assoc()) {
                    $notification['thread_id'] = $thread['thread_id'];
                    $notification['link'] = "/aftermarket_toolkit/public/forum.php?thread=" . $thread['thread_id'];
                } else {
                    $notification['link'] = "/aftermarket_toolkit/public/forum.php";
                }
            } else {
                $notification['link'] = "/aftermarket_toolkit/public/forum.php";
            }
            break;
            
        default:
            $notification['link'] = "/aftermarket_toolkit/index.php";
            break;
    }
    
    // Format the relative time
    $notification['time_ago'] = getTimeAgo($notification['created_at']);
    
    return $notification;
}

/**
 * Mark notifications as read by deleting them from the database
 * 
 * @param object $conn Database connection
 * @param int $userId User ID
 * @param string $type Optional notification type to filter
 * @param int $notificationId Optional specific notification ID
 * @return bool Success or failure
 */
function markNotificationsRead($conn, $userId, $type = null, $notificationId = null) {
    $params = [$userId];
    $types = "i";
    $sql = "DELETE FROM notifications WHERE user_id = ?";
    
    if ($type !== null) {
        $sql .= " AND type = ?";
        $params[] = $type;
        $types .= "s";
    }
    
    if ($notificationId !== null) {
        $sql .= " AND id = ?";
        $params[] = $notificationId;
        $types .= "i";
    }
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    return $stmt->execute();
}

/**
 * Count unread notifications by type
 * 
 * @param object $conn Database connection
 * @param int $userId User ID
 * @return array Count of unread notifications by type
 */
function countUnreadNotifications($conn, $userId) {
    $counts = [
        'friend_request' => 0,
        'message' => 0,
        'forum_response' => 0,
        'total' => 0
    ];
    
    // Count unread messages from the messages table
    try {
        $msgQuery = "SELECT COUNT(*) AS count FROM messages WHERE receiver_id = ? AND is_read = 0";
        $msgStmt = $conn->prepare($msgQuery);
        $msgStmt->bind_param("i", $userId);
        $msgStmt->execute();
        $result = $msgStmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $counts['message'] = (int)$row['count'];
            $counts['total'] += $counts['message'];
        }
    } catch (Exception $e) {
        error_log("Error counting messages: " . $e->getMessage());
    }
    
    // Count pending friend requests
    try {
        $frQuery = "SELECT COUNT(*) AS count FROM friend_requests WHERE receiver_id = ? AND status = 'pending'";
        $frStmt = $conn->prepare($frQuery);
        $frStmt->bind_param("i", $userId);
        $frStmt->execute();
        $result = $frStmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $counts['friend_request'] = (int)$row['count'];
            $counts['total'] += $counts['friend_request'];
        }
    } catch (Exception $e) {
        error_log("Error counting friend requests: " . $e->getMessage());
    }
      // Count unread forum responses ONLY from the notifications table to avoid duplicates
    try {
        $checkTableSql = "SHOW TABLES LIKE 'notifications'";
        $result = $conn->query($checkTableSql);
        if ($result->num_rows > 0) {
            $forumQuery = "
                SELECT COUNT(*) AS count 
                FROM notifications
                WHERE user_id = ? AND type = 'forum_response' AND is_read = 0";
            $forumStmt = $conn->prepare($forumQuery);
            $forumStmt->bind_param("i", $userId);
            $forumStmt->execute();
            $result = $forumStmt->get_result();
            if ($row = $result->fetch_assoc()) {
                $counts['forum_response'] = (int)$row['count'];
                $counts['total'] += $counts['forum_response'];
            }
        }
    } catch (Exception $e) {
        error_log("Error counting forum responses: " . $e->getMessage());
    }
    
    // Check notifications table for any additional notifications
    // But avoid double-counting types we've already counted
    try {
        $checkTableSql = "SHOW TABLES LIKE 'notifications'";
        $result = $conn->query($checkTableSql);
        if ($result->num_rows > 0) {
            $sql = "
                SELECT type, COUNT(*) as count
                FROM notifications
                WHERE user_id = ? AND is_read = 0 AND type NOT IN ('message', 'forum_response')
                GROUP BY type
            ";
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            while ($row = $result->fetch_assoc()) {
                if (isset($counts[$row['type']])) {
                    $counts[$row['type']] += (int)$row['count'];
                    $counts['total'] += (int)$row['count'];
                }
            }
        }
    } catch (Exception $e) {
        error_log("Error checking notifications table: " . $e->getMessage());
    }
    
    return $counts;
}

/**
 * Get time ago string from timestamp
 * 
 * @param string $datetime MySQL datetime string
 * @return string Human-readable time ago
 */
function getTimeAgo($datetime) {
    $time = strtotime($datetime);
    $now = time();
    $diff = $now - $time;
    
    if ($diff < 60) {
        return "just now";
    } else if ($diff < 3600) {
        $mins = floor($diff / 60);
        return $mins . " minute" . ($mins > 1 ? "s" : "") . " ago";
    } else if ($diff < 86400) {
        $hours = floor($diff / 3600);
        return $hours . " hour" . ($hours > 1 ? "s" : "") . " ago";
    } else if ($diff < 604800) {
        $days = floor($diff / 86400);
        return $days . " day" . ($days > 1 ? "s" : "") . " ago";
    } else if ($diff < 2592000) {
        $weeks = floor($diff / 604800);
        return $weeks . " week" . ($weeks > 1 ? "s" : "") . " ago";
    } else if ($diff < 31536000) {
        $months = floor($diff / 2592000);
        return $months . " month" . ($months > 1 ? "s" : "") . " ago";
    } else {
        $years = floor($diff / 31536000);
        return $years . " year" . ($years > 1 ? "s" : "") . " ago";
    }
}

/**
 * Get notification counts by type
 * 
 * @param object $conn Database connection
 * @param int $userId User ID
 * @param bool $includeTotal Include a 'total' count
 * @return array Counts by notification type
 */
function getNotificationCountsByType($conn, $userId, $includeTotal = false) {
    $counts = [];
    
    $sql = "
        SELECT type, COUNT(*) as count
        FROM notifications
        WHERE user_id = ? AND is_read = 0
        GROUP BY type
    ";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $total = 0;
    while ($row = $result->fetch_assoc()) {
        $counts[$row['type']] = (int)$row['count'];
        $total += (int)$row['count'];
    }
    
    if ($includeTotal) {
        $counts['total'] = $total;
    }
    
    return $counts;
}

/**
 * Get user notifications
 * 
 * @param object $conn Database connection
 * @param int $userId User ID
 * @param int $limit Maximum number of notifications to return
 * @param bool $unreadOnly Whether to return only unread notifications
 * @return array List of notifications
 */
function getUserNotifications($conn, $userId, $limit = 10, $unreadOnly = false) {
    // First check if the notifications table exists
    try {
        $checkTableSql = "SHOW TABLES LIKE 'notifications'";
        $result = $conn->query($checkTableSql);
        if ($result->num_rows == 0) {
            // Table doesn't exist
            return [];
        }
    } catch (Exception $e) {
        // Error checking table
        return [];
    }
    
    // Query for notifications with user details
    $sql = "
        SELECT n.*, u.username, u.profile_picture 
        FROM notifications n
        LEFT JOIN users u ON n.from_user_id = u.id
        WHERE n.user_id = ? " . 
        ($unreadOnly ? "AND n.is_read = 0 " : "") . "
        ORDER BY n.created_at DESC " . 
        ($limit > 0 ? "LIMIT $limit" : "");
    
    try {
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $notifications = [];
        while ($row = $result->fetch_assoc()) {
            // Enhance with additional details based on notification type
            $row = enhanceNotificationDetails($conn, $row);
            $notifications[] = $row;
        }
        
        return $notifications;
    } catch (Exception $e) {
        // Return empty array on error
        return [];
    }
}

/**
 * Mark a single notification as read by deleting it from the database
 * 
 * @param object $conn Database connection
 * @param int $notificationId Notification ID
 * @param int $userId User ID (for security)
 * @return bool Success or failure
 */
function markNotificationAsRead($conn, $notificationId, $userId) {
    $sql = "DELETE FROM notifications WHERE id = ? AND user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $notificationId, $userId);
    return $stmt->execute();
}

/**
 * Mark all notifications as read by deleting them from the database
 * 
 * @param object $conn Database connection
 * @param int $userId User ID
 * @param string $type Optional notification type to filter
 * @return bool Success or failure
 */
function markAllNotificationsAsRead($conn, $userId, $type = null) {
    if ($type) {
        $sql = "DELETE FROM notifications WHERE user_id = ? AND type = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("is", $userId, $type);
    } else {
        $sql = "DELETE FROM notifications WHERE user_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $userId);
    }
    
    return $stmt->execute();
}

/**
 * Delete a notification
 * 
 * @param object $conn Database connection
 * @param int $notificationId Notification ID
 * @param int $userId User ID (for security)
 * @return bool Success or failure
 */
function deleteNotification($conn, $notificationId, $userId) {
    $sql = "DELETE FROM notifications WHERE id = ? AND user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $notificationId, $userId);
    return $stmt->execute();
}

/**
 * Delete all notifications
 * 
 * @param object $conn Database connection
 * @param int $userId User ID
 * @param string $type Optional notification type to filter
 * @return bool Success or failure
 */
function deleteAllNotifications($conn, $userId, $type = null) {
    if ($type) {
        $sql = "DELETE FROM notifications WHERE user_id = ? AND type = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("is", $userId, $type);
    } else {
        $sql = "DELETE FROM notifications WHERE user_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $userId);
    }
    
    return $stmt->execute();
}

/**
 * Create a "message" notification for chat
 */
function createChatMessageNotification($conn, $messageId, $senderId, $receiverId, $messageText) {
    // don't notify yourself
    if ($senderId === $receiverId) {
        error_log("Chat notification not created: sender and receiver are the same user");
        return false;
    }
    
    // Check for existing notification for this message
    $checkSql = "SELECT id FROM notifications WHERE type = 'message' AND related_id = ? AND user_id = ?";
    $checkStmt = $conn->prepare($checkSql);
    $checkStmt->bind_param("ii", $messageId, $receiverId);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    
    // If notification already exists, don't create another one
    if ($result->num_rows > 0) {
        error_log("Chat notification not created: notification for message ID $messageId already exists");
        return false;
    }
    
    // Also check for any recent messages from this sender to this receiver
    // This helps prevent multiple notifications when multiple messages are sent in quick succession
    $recentCheckSql = "SELECT id FROM notifications WHERE type = 'message' AND user_id = ? AND sender_id = ? AND created_at > NOW() - INTERVAL 5 SECOND";
    $recentCheckStmt = $conn->prepare($recentCheckSql);
    $recentCheckStmt->bind_param("ii", $receiverId, $senderId);
    $recentCheckStmt->execute();
    $recentResult = $recentCheckStmt->get_result();
    
    // If there's a recent notification from this sender, don't create another one
    if ($recentResult->num_rows > 0) {
        error_log("Chat notification not created: recent notification from sender $senderId to receiver $receiverId already exists");
        return false;
    }

    // optional: generate preview content
    $content = mb_strimwidth($messageText, 0, 50, '…');
    
    error_log("Creating chat notification: sender=$senderId, receiver=$receiverId, message=$messageId, preview=$content");

    // build a link directly into the chat thread
    $link = '/aftermarket_toolkit/public/chat.php?chat='.$senderId;

    // use the general sendNotification helper
    return sendNotification(
        $conn,
        $receiverId,
        'message',
        $senderId,
        $messageId,
        $content,
        $link
    );
}

/**
 * Get notification icon class by type
 *
 * @param string $type Notification type
 * @return string CSS class for the icon
 */
function getNotificationIconClass($type) {
    switch($type) {
        case 'friend_request':
            return 'fa-user-plus';
        case 'message':
            return 'fa-envelope';
        case 'forum_response':
            return 'fa-comments';
        case 'listing_comment':
            return 'fa-tag';
        default:
            return 'fa-bell';
    }
}
?>
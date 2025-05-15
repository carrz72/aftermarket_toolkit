<?php
/**
 * Convert database image path to display URL
 * 
 * @param string $imagePath The image path stored in database
 * @param bool $absolute Whether to return an absolute path
 * @return string The URL for display in HTML
 */
function getImageUrl($imagePath, $absolute = false) {
    if (empty($imagePath)) {
        return '/aftermarket_toolkit/public/assets/images/default-image.jpg';
    }
    
    // For paths starting with /aftermarket_toolkit/
    if (strpos($imagePath, '/aftermarket_toolkit/') === 0) {
        return $imagePath;
    }
    
    // For paths from listing_images and uploads folder
    if (strpos($imagePath, '/uploads/') === 0) {
        return '/aftermarket_toolkit' . $imagePath;
    }
    
    // For absolute paths starting with /
    if (strpos($imagePath, '/') === 0) {
        return '/aftermarket_toolkit' . $imagePath;
    }
    
    // For relative paths starting with ./
    if (strpos($imagePath, './') === 0) {
        return '/aftermarket_toolkit' . substr($imagePath, 1);
    }
    
    // For any other path format
    return '/aftermarket_toolkit/' . $imagePath;
}

/**
 * Convert database image path to filesystem path
 * 
 * @param string $imagePath The image path stored in database
 * @return string The path to the file on disk
 */
function getImageFilePath($imagePath) {
    $basePath = __DIR__ . '/../';
    
    // For paths starting with /aftermarket_toolkit/
    if (strpos($imagePath, '/aftermarket_toolkit/') === 0) {
        return $basePath . substr($imagePath, 18); // Remove /aftermarket_toolkit/
    }
    
    // For paths from uploads folder (most common for user uploads)
    if (strpos($imagePath, '/uploads/') === 0) {
        return $basePath . substr($imagePath, 1);
    }
    
    // For absolute paths starting with /
    if (strpos($imagePath, '/') === 0) {
        return $basePath . substr($imagePath, 1);
    }
    
    // For relative paths starting with ./
    if (strpos($imagePath, './') === 0) {
        return $basePath . substr($imagePath, 2);
    }
    
    // For any other path format
    return $basePath . $imagePath;
}

/**
 * Get image thumbnail URL (for listing cards)
 * 
 * @param string $imagePath The image path stored in database
 * @return string The URL for the thumbnail
 */
function getImageThumbnail($imagePath) {
    // For now, just return the normal image
    // You could implement thumbnail generation/caching later
    return getImageUrl($imagePath);
}

/**
 * Check if an image exists and return a valid path or default
 * 
 * @param string $imagePath The image path to check
 * @param string $default Default image to use if not found
 * @return string A valid image URL
 */
function getValidImageUrl($imagePath, $default = null) {
    if (empty($imagePath)) {
        return $default ?: '/aftermarket_toolkit/public/assets/images/default-image.jpg';
    }
    
    $filePath = getImageFilePath($imagePath);
    
    if (file_exists($filePath)) {
        return getImageUrl($imagePath);
    }
    
    return $default ?: '/aftermarket_toolkit/public/assets/images/default-image.jpg';
}

/**
 * Generate a standardized path for newly uploaded images
 * 
 * @param string $filename The name of the uploaded file
 * @param string $directory Optional subdirectory within uploads
 * @return string The standardized path for database storage
 */
function getUploadedImagePath($filename, $directory = '') {
    $subdir = empty($directory) ? '' : trim($directory, '/') . '/';
    return "/uploads/{$subdir}{$filename}";
}

/**
 * Get the physical upload directory path
 *
 * @param string $directory Optional subdirectory within uploads
 * @return string The filesystem path to the upload directory
 */
function getUploadDirectory($directory = '') {
    $basePath = __DIR__ . '/../uploads/';
    $subdir = empty($directory) ? '' : trim($directory, '/') . '/';
    $fullPath = $basePath . $subdir;
    
    // Ensure directory exists
    if (!file_exists($fullPath)) {
        mkdir($fullPath, 0777, true);
    }
    
    return $fullPath;
}

/**
 * Get profile picture with fallback to default
 * 
 * @param string $profilePic The profile picture path
 * @return string Valid profile picture URL
 */
function getProfilePicture($profilePic) {
    return getValidImageUrl($profilePic, '/aftermarket_toolkit/public/assets/images/default-profile.jpg');
}

/**
 * Count unread notifications for a user
 * 
 * @param int $userId The ID of the user
 * @param object $conn Database connection object
 * @return array Array containing counts for different notification types
 */
function getNotificationCounts($userId, $conn) {
    $counts = [
        'messages' => 0,
        'friend_requests' => 0,
        'forum_responses' => 0,
        'total' => 0
    ];
    
    // Count unread messages
    $msgQuery = "SELECT COUNT(*) AS count FROM messages WHERE receiver_id = ? AND is_read = 0";
    $msgStmt = $conn->prepare($msgQuery);
    $msgStmt->bind_param("i", $userId);
    $msgStmt->execute();
    $result = $msgStmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $counts['messages'] = (int)$row['count'];
    }
    
    // Count pending friend requests
    $frQuery = "SELECT COUNT(*) AS count FROM friend_requests WHERE receiver_id = ?";
    $frStmt = $conn->prepare($frQuery);
    $frStmt->bind_param("i", $userId);
    $frStmt->execute();
    $result = $frStmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $counts['friend_requests'] = (int)$row['count'];
    }
    
    // Count unread forum responses to user's threads
    $forumQuery = "
        SELECT COUNT(*) AS count 
        FROM forum_replies r
        JOIN forum_threads t ON r.thread_id = t.id
        WHERE t.user_id = ? AND r.user_id != ? AND r.is_read = 0";
    $forumStmt = $conn->prepare($forumQuery);
    $forumStmt->bind_param("ii", $userId, $userId);
    $forumStmt->execute();
    $result = $forumStmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $counts['forum_responses'] = (int)$row['count'];
    }
    
    // Calculate total
    $counts['total'] = $counts['messages'] + $counts['friend_requests'] + $counts['forum_responses'];
    
    return $counts;
}

/**
 * Generate HTML for notification badge
 * 
 * @param int $count Number of notifications
 * @param string $type Type of notification (for specific styling)
 * @return string HTML for the notification badge
 */
function getNotificationBadgeHTML($count, $type = 'default') {
    if ($count <= 0) {
        return '';
    }
    
    $classNames = 'notification-badge';
    if ($type) {
        $classNames .= ' badge-' . $type;
    }
    
    return '<span class="' . $classNames . '">' . $count . '</span>';
}

/**
 * Create a notification
 * 
 * @param object $conn Database connection
 * @param int $userId User ID to notify
 * @param string $type Type of notification (friend_request, message, forum_response)
 * @param int $relatedId ID of the related item (request, message, or response)
 * @param string $content Description of the notification
 * @return bool Success or failure
 */
function createNotification($conn, $userId, $type, $relatedId, $content) {
    $stmt = $conn->prepare("
        INSERT INTO notifications (user_id, type, related_id, content) 
        VALUES (?, ?, ?, ?)
    ");
    $stmt->bind_param("isis", $userId, $type, $relatedId, $content);
    return $stmt->execute();
}

/**
 * Mark notifications as read by deleting them from the database
 * 
 * @param object $conn Database connection
 * @param int $userId User ID
 * @param string $type Optional notification type to mark as read
 * @param int $relatedId Optional specific notification to mark as read
 * @return bool Success or failure
 */
function markNotificationsAsRead($conn, $userId, $type = null, $relatedId = null) {
    $params = [$userId];
    $sql = "DELETE FROM notifications WHERE user_id = ?";
    
    if ($type) {
        $sql .= " AND type = ?";
        $params[] = $type;
    }
    
    if ($relatedId) {
        $sql .= " AND related_id = ?";
        $params[] = $relatedId;
    }
    
    $stmt = $conn->prepare($sql);
    
    $types = str_repeat("i", count($params));
    if (strpos($types, "s") !== false) {
        $types = "i" . substr($types, 1);
    }
    
    $stmt->bind_param($types, ...$params);
    return $stmt->execute();
}

/**
 * Get user notifications (simple version for backward compatibility)
 * 
 * @param object $conn Database connection
 * @param int $userId User ID
 * @param int $limit Maximum number of notifications to return
 * @param bool $unreadOnly Whether to return only unread notifications
 * @return array List of notifications
 */
function getSimpleNotifications($conn, $userId, $limit = 10, $unreadOnly = false) {
    // Check if notification handler is available
    if (function_exists('getUserNotifications')) {
        return getUserNotifications($conn, $userId, $limit, $unreadOnly);
    }
    
    // Fallback implementation
    $sql = "
        SELECT * FROM notifications 
        WHERE user_id = ? 
        " . ($unreadOnly ? "AND is_read = 0 " : "") . "
        ORDER BY created_at DESC
        " . ($limit > 0 ? "LIMIT $limit" : "");
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

/**
 * Format notification content for display
 * 
 * @param array $notification The notification data
 * @return string Formatted notification text
 */
function formatNotification($notification) {
    switch ($notification['type']) {
        case 'friend_request':
            if (isset($notification['sender'])) {
                return '<strong>' . htmlspecialchars($notification['sender']['username']) . '</strong> sent you a friend request';
            } else {
                return 'You received a friend request';
            }
            
        case 'message':
            if (isset($notification['sender'])) {
                return '<strong>' . htmlspecialchars($notification['sender']['username']) . '</strong> sent you a message: "' . 
                       htmlspecialchars($notification['preview']) . '"';
            } else {
                return 'You received a new message';
            }
            
        case 'forum_response':
            if (isset($notification['forum'])) {
                return '<strong>' . htmlspecialchars($notification['forum']['username']) . '</strong> replied to your thread: "' . 
                       htmlspecialchars($notification['forum']['title']) . '"';
            } else {
                return 'Someone replied to your forum thread';
            }
            
        default:
            return htmlspecialchars($notification['content']);
    }
}
?>
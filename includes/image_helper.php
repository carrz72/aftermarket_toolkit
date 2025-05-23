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
 * Image helper functions for handling profile pictures and other images
 */

/**
 * Get profile picture URL with default fallback
 * 
 * @param string|null $profilePicPath The profile picture path from database
 * @return string The URL to use for the profile picture
 */
function getProfilePicture($profilePicPath = null) {
    if (!empty($profilePicPath) && file_exists($_SERVER['DOCUMENT_ROOT'] . $profilePicPath)) {
        return $profilePicPath;
    }
    return '/aftermarket_toolkit/public/assets/images/default-profile.jpg';
}

/**
 * Get icon image URL with fallback
 * 
 * @param string $iconName The name of the icon without extension
 * @return string The URL to use for the icon
 */
function getIconImage($iconName) {
    $iconPath = '/aftermarket_toolkit/public/assets/images/' . $iconName . '.svg';
    if (file_exists($_SERVER['DOCUMENT_ROOT'] . $iconPath)) {
        return $iconPath;
    }
    return '/aftermarket_toolkit/public/assets/images/default-icon.svg';
}

/**
 * Generate proper image HTML with alt text and classes
 * 
 * @param string $src The source URL of the image
 * @param string $alt Alt text for the image
 * @param string $classes Optional CSS classes to add
 * @return string HTML for the image
 */
function generateImageHTML($src, $alt, $classes = '') {
    return '<img src="' . htmlspecialchars($src) . '" alt="' . htmlspecialchars($alt) . '"' .
           (!empty($classes) ? ' class="' . htmlspecialchars($classes) . '"' : '') . '>';
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

/**
 * Get the URL for a listing image
 *
 * @param string|null $imageName The name of the image file, or null for default
 * @return string The URL to the image
 */
function getListingImageUrl($imageName = null) {
    // Base URL for image assets
    $baseUrl = '/aftermarket_toolkit/public/assets/images/listings/';
    
    if (empty($imageName)) {
        // Return default listing image if none specified
        return $baseUrl . 'default-listing.jpg';
    }
    
    // Check if file exists in listings directory
    $imagePath = __DIR__ . '/../public/assets/images/listings/' . $imageName;
    if (file_exists($imagePath)) {
        return $baseUrl . $imageName;
    }
    
    // Return default if file doesn't exist
    return $baseUrl . 'default-listing.jpg';
}

/**
 * Resize an image while maintaining aspect ratio
 *
 * @param string $sourcePath Path to the source image
 * @param string $targetPath Path where the resized image will be saved
 * @param int $maxWidth Maximum width of the resized image
 * @param int $maxHeight Maximum height of the resized image
 * @return bool True on success, false on failure
 */
function resizeImage($sourcePath, $targetPath, $maxWidth, $maxHeight) {
    // Check if the GD extension is available
    if (!extension_loaded('gd')) {
        return false;
    }
    
    // Get image information
    list($origWidth, $origHeight, $type) = getimagesize($sourcePath);
    
    // Calculate new dimensions while maintaining aspect ratio
    if ($maxWidth / $origWidth < $maxHeight / $origHeight) {
        $newWidth = $maxWidth;
        $newHeight = $origHeight * ($maxWidth / $origWidth);
    } else {
        $newWidth = $origWidth * ($maxHeight / $origHeight);
        $newHeight = $maxHeight;
    }
    
    // Create a new image
    $newImage = imagecreatetruecolor($newWidth, $newHeight);
    
    // Load the source image based on its type
    switch ($type) {
        case IMAGETYPE_JPEG:
            $sourceImage = imagecreatefromjpeg($sourcePath);
            break;
        case IMAGETYPE_PNG:
            $sourceImage = imagecreatefrompng($sourcePath);
            // Preserve transparency
            imagealphablending($newImage, false);
            imagesavealpha($newImage, true);
            break;
        case IMAGETYPE_GIF:
            $sourceImage = imagecreatefromgif($sourcePath);
            break;
        default:
            return false;
    }
    
    // Resize the image
    imagecopyresampled($newImage, $sourceImage, 0, 0, 0, 0, $newWidth, $newHeight, $origWidth, $origHeight);
    
    // Save the resized image
    $result = false;
    switch ($type) {
        case IMAGETYPE_JPEG:
            $result = imagejpeg($newImage, $targetPath, 90); // 90% quality
            break;
        case IMAGETYPE_PNG:
            $result = imagepng($newImage, $targetPath, 9); // Maximum compression
            break;
        case IMAGETYPE_GIF:
            $result = imagegif($newImage, $targetPath);
            break;
    }
    
    // Free up memory
    imagedestroy($sourceImage);
    imagedestroy($newImage);
    
    return $result;
}
?>
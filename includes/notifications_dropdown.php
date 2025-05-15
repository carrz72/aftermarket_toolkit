<?php
// Ensure this file is included, not directly accessed
if (!defined('INCLUDED')) {
    header('HTTP/1.0 403 Forbidden');
    exit;
}

/**
 * Renders the notification dropdown HTML
 * 
 * @param int $userId The user ID to get notifications for
 * @param object $conn Database connection
 * @return string HTML for notification dropdown
 */
function renderNotificationDropdown($userId, $conn) {
    // Get notification counts
    $counts = getNotificationCounts($userId, $conn);
    
    // Get user notifications (limited to 5)
    $notifications = getNotifications($userId, $conn, 5);
    
    ob_start();
    ?>
    <div class="notifications-container">
        <button id="notificationsBtn" class="notification-btn">
            <i class="fas fa-bell"></i>
            <?php if ($counts['total'] > 0): ?>
            <span id="notification-badge"><?= $counts['total'] ?></span>
            <?php endif; ?>
        </button>
        <div id="notificationsDropdown" class="notifications-dropdown">
            <div class="notifications-header">
                <h3>Notifications</h3>
                <?php if ($counts['total'] > 0): ?>
                <button id="markAllReadBtn" class="mark-all-read">Mark all as read</button>
                <?php endif; ?>
            </div>
            <div class="notifications-list">
                <?php if (empty($notifications)): ?>
                    <div class="no-notifications">No new notifications</div>
                <?php else: ?>
                    <?php foreach ($notifications as $notification): ?>
                        <?php $unreadClass = $notification['is_read'] ? '' : 'unread'; ?>
                        <div class="notification-item <?= $unreadClass ?>" 
                             data-id="<?= $notification['id'] ?>" 
                             data-type="<?= $notification['type'] ?>" 
                             data-related-id="<?= $notification['related_id'] ?>">
                             
                            <div class="notification-icon">
                                <i class="fas <?= getNotificationIconClass($notification['type']) ?>"></i>
                            </div>
                            
                            <div class="notification-content">
                                <div class="notification-text"><?= htmlspecialchars($notification['content']) ?></div>
                                <div class="notification-time"><?= formatTimeAgo($notification['created_at']) ?></div>
                            </div>
                            
                            <?php if (!$notification['is_read']): ?>
                                <div class="notification-mark-read"><i class="fas fa-check"></i></div>
                            <?php endif; ?>
                            
                            <a href="<?= $notification['link'] ?>" class="notification-link"></a>
                        </div>
                    <?php endforeach; ?>
                    
                    <?php if (count($notifications) < $counts['total']): ?>
                        <div class="notification-item show-all">
                            <a href="./public/notifications.php">View all notifications</a>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

/**
 * Returns appropriate icon class for notification type
 * 
 * @param string $type Notification type
 * @return string Font Awesome icon class
 */
function getNotificationIconClass($type) {
    switch ($type) {
        case 'message':
            return 'fa-envelope';
        case 'friend_request':
            return 'fa-user-plus';
        case 'forum_response':
            return 'fa-comments';
        default:
            return 'fa-bell';
    }
}

/**
 * Convert timestamp to "time ago" format
 * 
 * @param string $timestamp The timestamp to format
 * @return string Formatted time ago string
 */
function formatTimeAgo($timestamp) {
    $time = strtotime($timestamp);
    $time_difference = time() - $time;

    if ($time_difference < 1) { return 'just now'; }
    $condition = [
        12 * 30 * 24 * 60 * 60 => 'year',
        30 * 24 * 60 * 60 => 'month',
        24 * 60 * 60 => 'day',
        60 * 60 => 'hour',
        60 => 'minute',
        1 => 'second'
    ];

    foreach ($condition as $secs => $str) {
        $d = $time_difference / $secs;
        if ($d >= 1) {
            $t = round($d);
            return $t . ' ' . $str . ($t > 1 ? 's' : '') . ' ago';
        }
    }
    return 'just now';
}
?>
<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/image_helper.php';
require_once __DIR__ . '/../includes/notification_handler.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$userId = $_SESSION['user_id'];

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['mark_all_read'])) {
        markNotificationsRead($conn, $userId);
        header('Location: notifications.php');
        exit();
    }
    
    if (isset($_POST['delete_notification']) && isset($_POST['notification_id'])) {
        $notificationId = (int)$_POST['notification_id'];
        $stmt = $conn->prepare("DELETE FROM notifications WHERE id = ? AND user_id = ?");
        $stmt->bind_param("ii", $notificationId, $userId);
        $stmt->execute();
        header('Location: notifications.php');
        exit();
    }
    
    if (isset($_POST['delete_all'])) {
        $stmt = $conn->prepare("DELETE FROM notifications WHERE user_id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        header('Location: notifications.php');
        exit();
    }
}

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Filter
$filterType = isset($_GET['type']) ? $_GET['type'] : '';
$showUnread = isset($_GET['unread']) && $_GET['unread'] == '1';

// Get total count for pagination
$countSql = "SELECT COUNT(*) as total FROM notifications WHERE user_id = ?";
$countParams = [$userId];
$countTypes = "i";

if (!empty($filterType)) {
    $countSql .= " AND type = ?";
    $countParams[] = $filterType;
    $countTypes .= "s";
}

if ($showUnread) {
    $countSql .= " AND is_read = 0";
}

$countStmt = $conn->prepare($countSql);
$countStmt->bind_param($countTypes, ...$countParams);
$countStmt->execute();
$countResult = $countStmt->get_result();
$totalCount = $countResult->fetch_assoc()['total'];
$totalPages = ceil($totalCount / $limit);

// Get notifications
$sql = "
    SELECT n.*, u.username, u.profile_picture 
    FROM notifications n
    LEFT JOIN users u ON n.from_user_id = u.id
    WHERE n.user_id = ?
";
$params = [$userId];
$types = "i";

if (!empty($filterType)) {
    $sql .= " AND n.type = ?";
    $params[] = $filterType;
    $types .= "s";
}

if ($showUnread) {
    $sql .= " AND n.is_read = 0";
}

$sql .= " ORDER BY n.created_at DESC LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;
$types .= "ii";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

// Get notification counts for filter display
$countsByType = [];
$typesQuery = "
    SELECT type, COUNT(*) as count 
    FROM notifications 
    WHERE user_id = ? 
    GROUP BY type
";
$typesStmt = $conn->prepare($typesQuery);
$typesStmt->bind_param("i", $userId);
$typesStmt->execute();
$typesResult = $typesStmt->get_result();

while ($row = $typesResult->fetch_assoc()) {
    $countsByType[$row['type']] = $row['count'];
}

$unreadCount = 0;
$unreadQuery = "SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0";
$unreadStmt = $conn->prepare($unreadQuery);
$unreadStmt->bind_param("i", $userId);
$unreadStmt->execute();
$unreadRow = $unreadStmt->get_result()->fetch_assoc();
$unreadCount = $unreadRow['count'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications - Aftermarket Toolbox</title>
    <link rel="stylesheet" href="./assets/css/notifications.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
    <?php 
    $current_section = 'notifications';
    define('INCLUDED', true);
    require_once __DIR__ . '/../includes/navigation.php';
    ?>
    
    <div class="notifications-page">
        <div class="page-header">
            <h1>Notifications</h1>
            
            <div class="header-actions">
                <?php if ($totalCount > 0): ?>
                    <form class="inline-form" method="POST">
                        <?php if ($unreadCount > 0): ?>
                            <button type="submit" name="mark_all_read" class="btn btn-primary">
                                <i class="fas fa-check-double"></i> Mark All as Read
                            </button>
                        <?php endif; ?>
                        <button type="submit" name="delete_all" class="btn btn-danger" onclick="return confirm('Are you sure you want to delete all notifications?')">
                            <i class="fas fa-trash"></i> Clear All
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Filter options -->
        <div class="filter-container">
            <a href="notifications.php" class="filter-link <?php echo empty($filterType) && !$showUnread ? 'active' : ''; ?>">
                All (<?php echo $totalCount; ?>)
            </a>
            <?php if ($unreadCount > 0): ?>
                <a href="notifications.php?unread=1" class="filter-link <?php echo $showUnread ? 'active' : ''; ?>">
                    Unread (<?php echo $unreadCount; ?>)
                </a>
            <?php endif; ?>
            <?php foreach ($countsByType as $type => $count): ?>
                <a href="notifications.php?type=<?php echo $type; ?>" class="filter-link <?php echo $filterType === $type ? 'active' : ''; ?>">
                    <?php echo ucfirst(str_replace('_', ' ', $type)); ?> (<?php echo $count; ?>)
                </a>
            <?php endforeach; ?>
        </div>
        
        <div class="notifications-container-page">
            <?php if ($result->num_rows > 0): ?>
                <div class="notifications-list-page">
                    <?php while ($notification = $result->fetch_assoc()): 
                        // Enhance notification with additional details
                        $notification = enhanceNotificationDetails($conn, $notification);
                    ?>
                        <div class="notification-item <?php echo $notification['is_read'] ? '' : 'unread'; ?>">
                            <div class="notification-icon">
                                <i class="fas <?php echo getNotificationIconClass($notification['type']); ?>"></i>
                            </div>
                            
                            <?php if ($notification['from_user_id']): ?>
                            <div class="notification-sender">
                                <img src="<?php echo getProfilePicture($notification['profile_picture']); ?>" alt="<?php echo htmlspecialchars($notification['username']); ?>" class="sender-pic">
                                <div class="sender-name"><?php echo htmlspecialchars($notification['username']); ?></div>
                            </div>
                            <?php endif; ?>
                            
                            <div class="notification-content">
                                <div class="notification-text"><?php echo htmlspecialchars($notification['content']); ?></div>
                                <div class="notification-time"><?php echo formatTimeAgo($notification['created_at']); ?></div>
                            </div>
                            
                            <div class="notification-actions">
                                <form method="POST" class="inline-form">
                                    <input type="hidden" name="notification_id" value="<?php echo $notification['id']; ?>">
                                    <button type="submit" name="delete_notification" class="btn btn-circle btn-sm btn-danger" title="Delete">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                                
                                <a href="<?php echo $notification['link']; ?>" class="btn btn-circle btn-sm btn-secondary" title="View">
                                    <i class="fas fa-arrow-right"></i>
                                </a>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
                
                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?php echo $page - 1; ?><?php echo !empty($filterType) ? '&type=' . $filterType : ''; ?><?php echo $showUnread ? '&unread=1' : ''; ?>" class="pagination-link">&laquo; Previous</a>
                    <?php endif; ?>
                    
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <?php if (
                            $i == 1 || 
                            $i == $totalPages || 
                            ($i >= $page - 2 && $i <= $page + 2)
                        ): ?>
                            <a href="?page=<?php echo $i; ?><?php echo !empty($filterType) ? '&type=' . $filterType : ''; ?><?php echo $showUnread ? '&unread=1' : ''; ?>" 
                               class="pagination-link <?php echo $i === $page ? 'active' : ''; ?>">
                                <?php echo $i; ?>
                            </a>
                        <?php elseif (
                            ($i == 2 && $page > 4) || 
                            ($i == $totalPages - 1 && $page < $totalPages - 3)
                        ): ?>
                            <span class="pagination-ellipsis">&hellip;</span>
                        <?php endif; ?>
                    <?php endfor; ?>
                    
                    <?php if ($page < $totalPages): ?>
                        <a href="?page=<?php echo $page + 1; ?><?php echo !empty($filterType) ? '&type=' . $filterType : ''; ?><?php echo $showUnread ? '&unread=1' : ''; ?>" class="pagination-link">Next &raquo;</a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                
            <?php else: ?>
                <div class="empty-state">
                    <div class="empty-icon">
                        <i class="fas fa-bell-slash"></i>
                    </div>
                    <h2>No notifications</h2>
                    <p>You don't have any notifications<?php echo $showUnread ? ' that are unread' : ''; ?><?php echo !empty($filterType) ? ' of this type' : ''; ?> at the moment.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        // Mark notifications as read when they become visible
        document.addEventListener('DOMContentLoaded', function() {
            const unreadItems = document.querySelectorAll('.notification-item.unread');
            if (unreadItems.length > 0) {
                // Get all notification IDs
                const ids = Array.from(unreadItems).map(item => item.querySelector('input[name="notification_id"]').value);
                
                // Mark them as read via AJAX
                fetch('./api/notifications.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=mark_all_read`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        unreadItems.forEach(item => {
                            item.classList.remove('unread');
                        });
                    }
                })
                .catch(error => console.error('Error marking notifications as read:', error));
            }
        });
    </script>
</body>
</html>
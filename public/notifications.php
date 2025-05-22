<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/image_helper.php';
require_once __DIR__ . '/../includes/notification_handler.php';

// Define current section for navigation highlighting
$current_section = 'notifications';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Format timestamp as time ago text
function formatTimeAgo($timestamp) {
    $date = new DateTime($timestamp);
    $now = new DateTime();
    $interval = $date->diff($now);
    $seconds = $interval->s + ($interval->i * 60) + ($interval->h * 3600) + ($interval->days * 86400);
    
    if ($seconds < 60) {
        return 'just now';
    }
    
    $minutes = floor($seconds / 60);
    if ($minutes < 60) {
        return $minutes . ' minute' . ($minutes !== 1 ? 's' : '') . ' ago';
    }
    
    $hours = floor($minutes / 60);
    if ($hours < 24) {
        return $hours . ' hour' . ($hours !== 1 ? 's' : '') . ' ago';
    }
    
    $days = $interval->days;
    if ($days < 30) {
        return $days . ' day' . ($days !== 1 ? 's' : '') . ' ago';
    }
    
    $months = floor($days / 30);
    if ($months < 12) {
        return $months . ' month' . ($months !== 1 ? 's' : '') . ' ago';
    }
    
    $years = floor($months / 12);
    return $years . ' year' . ($years !== 1 ? 's' : '') . ' ago';
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
  LEFT JOIN users u ON n.user_id = u.id
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

// Get notification counts for the notification badges in navigation
$notificationCounts = [
    'total' => $unreadCount,
    'messages' => $countsByType['message'] ?? 0,
    'friend_requests' => $countsByType['friend_request'] ?? 0,
    'forum_responses' => $countsByType['forum_response'] ?? 0
];
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
   <div class="menu">
  <a href="../index.php" class="link">
    <span class="link-icon">
      <img src="./assets/images/home-icon.svg" alt="Home">
    </span>
    <span class="link-title">Home</span>
  </a>

  <!-- Market with dropdown -->
  <div class="profile-container">
    <a href="#" class="link" onclick="toggleDropdown(this, event)">
      <span class="link-icon">
        <img src="./assets/images/market.svg" alt="Market">
      </span>
      <span class="link-title">Market</span>
    </a>    <div class="dropdown-content">
      <button class="value" onclick="window.location.href='./marketplace.php?view=explore';"><img src="./assets/images/exploreicon.svg" alt="Explore">Explore</button>
      <button class="value" onclick="window.location.href='../api/listings/view_listings.php';"><img src="./assets/images/view_listingicon.svg" alt="View Listings">View Listings</button>
      <button class="value" onclick="window.location.href='../api/listings/create_listing.php';"><img src="./assets/images/list_itemicon.svg" alt="Create Listing">List Item</button>
      <button class="value" onclick="window.location.href='./saved_listings.php';"><img src="./assets/images/savedicons.svg" alt="Saved">Saved Items</button>
    </div>
  </div>
  
  <!-- Forum dropdown -->
  <div class="profile-container">
    <a href="#" class="link" onclick="toggleDropdown(this, event)">      <span class="link-icon">
        <img src="./assets/images/forum-icon.svg" alt="Forum">
        <?php if (isset($_SESSION['user_id']) && isset($notificationCounts['forum_responses']) && $notificationCounts['forum_responses'] > 0): ?>
          <span class="notification-badge forum"><?= $notificationCounts['forum_responses'] ?></span>
        <?php endif; ?>
      </span>
      <span class="link-title">Forum</span>
    </a>    <div class="dropdown-content">
      <button class="value" onclick="window.location.href='./forum.php?view=threads';"><img src="./assets/images/view_threadicon.svg" alt="Forum">View Threads</button>
      <button class="value" onclick="window.location.href='./forum.php?view=start_thread';"><img src="./assets/images/start_threadicon.svg" alt="Start Thread">Start Thread</button>
      <button class="value" onclick="window.location.href='./forum.php?view=post_question';"><img src="./assets/images/start_threadicon.svg" alt="Post Question">Post Question</button>
    </div>
  </div>

  <!-- Profile dropdown -->
  <div class="profile-container">
    <a href="#" class="link" onclick="toggleDropdown(this, event)">
      <span class="link-icon">
        <img src="./assets/images/profile-icon.svg" alt="Profile">
      </span>
      <span class="link-title">Profile</span>
    </a>
    <div id="profileDropdown" class="dropdown-content">
      <?php if (isset($_SESSION['user_id'])): ?>        <button class="value" onclick="window.location.href='./profile.php';">
          <img src="./assets/images/profile-icon.svg" alt="Profile">Account
        </button>
        <button class="value" onclick="window.location.href='../api/listings/view_listings.php';"><img src="./assets/images/mylistingicon.svg" alt="Market">My Listings</button>
        <button class="value" onclick="window.location.href='./saved_listings.php';"><img src="./assets/images/savedicons.svg" alt="Saved">Saved Items</button>
        <button class="value" onclick="window.location.href='./friends.php';"><img src="./assets/images/friendsicon.svg" alt="Friends">Friends
          <?php if (isset($_SESSION['user_id']) && isset($notificationCounts['friend_requests']) && $notificationCounts['friend_requests'] > 0): ?>
            <span class="notification-badge friends"><?= $notificationCounts['friend_requests'] ?></span>
          <?php endif; ?>
        </button>
        <button class="value" onclick="window.location.href='./logout.php';"><img src="./assets/images/Log_Outicon.svg" alt="Logout">Logout</button>
      <?php else: ?>
        <button class="value" onclick="window.location.href='./login.php';">Login</button>
        <button class="value" onclick="window.location.href='./register.php';">Register</button>
      <?php endif; ?>
    </div>
  </div>

  <?php if (isset($_SESSION['user_id'])): ?>    <a href="./chat.php" class="link">
      <span class="link-icon">
        <img src="./assets/images/chat-icon.svg" alt="Chat">
        <?php if (isset($_SESSION['user_id']) && isset($notificationCounts['messages']) && $notificationCounts['messages'] > 0): ?>
          <span class="notification-badge messages"><?= $notificationCounts['messages'] ?></span>
        <?php endif; ?>
      </span>
      <span class="link-title">Chat</span>    </a>

    <div class="profile-container">
    <a href="#" class="link <?= $current_section === 'jobs' ? 'active' : '' ?>" onclick="toggleDropdown(this, event)">
      <span class="link-icon">
        <img src="./assets/images/job-icon.svg" alt="Jobs">
      </span>
      <span class="link-title">Jobs</span>
    </a>
    <div class="dropdown-content">
      <button class="value" onclick="window.location.href='./jobs.php';"><img src="./assets/images/exploreicon.svg" alt="Explore">
        Explore</button>
      <button class="value" onclick="window.location.href='./jobs.php?action=post';"><img src="./assets/images/post_job_icon.svg" alt="Create Job">
        Post Job</button>
      <button class="value" onclick="window.location.href='./jobs.php?action=my_applications';"><img src="./assets/images/my_applications_icon.svg" alt="My Applications">
        My Applications</button>
    </div>
  </div>
    
    <!-- Notifications Dropdown -->
    <div class="notifications-container">
      <button id="notificationsBtn" class="notification-btn">
        <i class="fas fa-bell"></i>
        <?php if (isset($notificationCounts['total']) && $notificationCounts['total'] > 0): ?>
          <span id="notification-badge"><?= $notificationCounts['total'] ?></span>
        <?php endif; ?>
      </button>
      <div id="notificationsDropdown" class="notifications-dropdown">
        <div class="notifications-header">
          <h3>Notifications</h3>
          <?php if (isset($notificationCounts['total']) && $notificationCounts['total'] > 0): ?>
            <button id="markAllReadBtn" class="mark-all-read">Mark all as read</button>
          <?php endif; ?>
        </div>
        <div class="notifications-list">
          <!-- Notifications will be loaded here via JavaScript -->
          <div class="no-notifications">Loading notifications...</div>
        </div>
      </div>
    </div>
    <?php endif; ?>
  </div>
    
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
                              <?php if (isset($notification['source_user_id']) && $notification['source_user_id']): ?>
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
    
<script>
        const delay = 100; // Delay in milliseconds for dropdown effects
        
        // Apply event listeners to all profile containers
        document.querySelectorAll('.profile-container').forEach(container => {
            let timeoutId = null;
            
            container.addEventListener('mouseenter', () => {
                if (timeoutId) {
                    clearTimeout(timeoutId);
                    timeoutId = null;
                }
                timeoutId = setTimeout(() => {
                    container.classList.add('active');
                }, delay);
            });
            
            container.addEventListener('mouseleave', () => {
                if (timeoutId) {
                    clearTimeout(timeoutId);
                    timeoutId = null;
                }
                timeoutId = setTimeout(() => {
                    container.classList.remove('active');
                }, delay);
            });
        });
        
        // Toggle dropdown with a delay
        function toggleDropdown(element, event) {
            event.preventDefault();
            const container = element.closest('.profile-container');
            setTimeout(() => {
                container.classList.toggle('active');
            }, delay);
        }
        
        // Close all dropdowns with a delay when clicking outside
        document.addEventListener('click', function(e) {
            document.querySelectorAll('.profile-container').forEach(container => {
                if (!container.contains(e.target)) {
                    setTimeout(() => {
                        container.classList.remove('active');
                    }, delay);
                }
            });
        });
        
        // Initialize notification system if notifications button exists
        if (document.getElementById('notificationsBtn')) {
            initNotificationSystem();
            // Initial fetch
            fetchNotifications();
            // Poll for notifications every 60 seconds
            setInterval(fetchNotifications, 60000);
        }
        
        // Fetch notifications via AJAX
        function fetchNotifications() {
            const baseUrl = window.location.pathname.includes('/public/') ? '..' : '/aftermarket_toolkit';
            
            fetch(`${baseUrl}/public/api/notifications.php`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        updateNotificationBadge(data.counts.total || 0);
                        updateNotificationDropdown(data.notifications || []);
                    }
                })
                .catch(error => {
                    console.error('Error fetching notifications:', error);
                });
        }
        
        // Update the notification badge count
        function updateNotificationBadge(count) {
            const badge = document.getElementById('notification-badge');
            
            if (!badge) return;
            
            if (count > 0) {
                badge.style.display = 'inline-flex';
                badge.textContent = count;
            } else {
                badge.style.display = 'none';
            }
        }
        
        // Update the notification dropdown content
        function updateNotificationDropdown(notifications) {
            const list = document.querySelector('.notifications-list');
            
            if (!list) return;
            
            // If no notifications, show a message
            if (!notifications || notifications.length === 0) {
                list.innerHTML = '<div class="no-notifications">No new notifications</div>';
                return;
            }
            
            let html = '';
            const maxToShow = 5;
            
            // Build notification items HTML
            for (let i = 0; i < Math.min(notifications.length, maxToShow); i++) {
                const notification = notifications[i];
                const isUnread = !notification.is_read;
                const unreadClass = isUnread ? 'unread' : '';
                
                html += `<div class="notification-item ${unreadClass}" data-id="${notification.id}" data-type="${notification.type}" data-related-id="${notification.related_id || ''}">`;
                
                // Notification icon based on type
                let iconClass = 'fa-bell';
                switch (notification.type) {
                    case 'friend_request': iconClass = 'fa-user-plus'; break;
                    case 'message': iconClass = 'fa-envelope'; break;
                    case 'forum_response': iconClass = 'fa-comments'; break;
                    case 'listing_comment': iconClass = 'fa-tag'; break;
                }
                
                html += `<div class="notification-icon"><i class="fas ${iconClass}"></i></div>`;
                html += '<div class="notification-content">';
                html += `<div class="notification-text">${notification.content}</div>`;
                html += `<div class="notification-time">${formatTimeAgo(notification.created_at)}</div>`;
                html += '</div>';
                
                if (isUnread) {
                    html += '<div class="notification-mark-read"><i class="fas fa-check"></i></div>';
                }
                
                html += `<a href="${getNotificationLink(notification.type, notification.related_id)}" class="notification-link"></a>`;
                html += '</div>';
            }
            
            // If there are more notifications than we're showing, add a "view all" link
            if (notifications.length > maxToShow) {
                const baseUrl = window.location.pathname.includes('/public/') ? '.' : './public';
                html += '<div class="notification-item show-all">';
                html += `<a href="${baseUrl}/notifications.php">View all notifications</a>`;
                html += '</div>';
            }
            
            list.innerHTML = html;
        }
        
        // Get the appropriate link for a notification
        function getNotificationLink(type, relatedId) {
            const baseUrl = window.location.pathname.includes('/public/') ? '.' : './public';
            
            switch(type) {
                case 'friend_request':
                    return `${baseUrl}/friends.php`;
                case 'message':
                    return `${baseUrl}/chat.php?chat=${relatedId}`;
                case 'forum_response':
                    return relatedId ? `${baseUrl}/forum.php?thread=${relatedId}` : `${baseUrl}/forum.php`;
                case 'listing_comment':
                    return relatedId ? `${baseUrl}/marketplace.php?listing=${relatedId}` : `${baseUrl}/marketplace.php`;
                default:
                    return `${baseUrl}/notifications.php`;
            }
        }
        
        // Format timestamp as "time ago" text
        function formatTimeAgo(timestamp) {
            const date = new Date(timestamp);
            const now = new Date();
            const seconds = Math.floor((now - date) / 1000);
            
            if (seconds < 60) {
                return 'just now';
            }
            
            const minutes = Math.floor(seconds / 60);
            if (minutes < 60) {
                return minutes + ' minute' + (minutes !== 1 ? 's' : '') + ' ago';
            }
            
            const hours = Math.floor(minutes / 60);
            if (hours < 24) {
                return hours + ' hour' + (hours !== 1 ? 's' : '') + ' ago';
            }
            
            const days = Math.floor(hours / 24);
            if (days < 30) {
                return days + ' day' + (days !== 1 ? 's' : '') + ' ago';
            }
            
            const months = Math.floor(days / 30);
            if (months < 12) {
                return months + ' month' + (months !== 1 ? 's' : '') + ' ago';
            }
            
            return Math.floor(months / 12) + ' year' + (Math.floor(months / 12) !== 1 ? 's' : '') + ' ago';
        }
        
        // Initialize notification system
        function initNotificationSystem() {
            const notificationsBtn = document.getElementById('notificationsBtn');
            const notificationsDropdown = document.getElementById('notificationsDropdown');
            const markAllReadBtn = document.getElementById('markAllReadBtn');
            
            // Toggle dropdown when clicking on the notification button
            if (notificationsBtn) {
                notificationsBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    notificationsDropdown.classList.toggle('show');
                    
                    // Fetch fresh notifications when opening dropdown
                    if (notificationsDropdown.classList.contains('show')) {
                        fetchNotifications();
                    }
                });
            }
            
            // Close dropdown when clicking outside
            document.addEventListener('click', function(e) {
                if (notificationsDropdown && 
                    !notificationsBtn.contains(e.target) && 
                    !notificationsDropdown.contains(e.target)) {
                    notificationsDropdown.classList.remove('show');
                }
            });
            
            // Mark all notifications as read
            if (markAllReadBtn) {
                markAllReadBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    
                    const baseUrl = window.location.pathname.includes('/public/') ? '..' : '/aftermarket_toolkit';
                    
                    fetch(`${baseUrl}/public/api/notifications.php`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: 'action=mark_all_read'
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // Update UI
                            document.querySelectorAll('.notification-item.unread').forEach(item => {
                                item.classList.remove('unread');
                            });
                            document.querySelectorAll('.notification-mark-read').forEach(btn => {
                                btn.remove();
                            });
                            updateNotificationBadge(0);
                        }
                    })
                    .catch(error => console.error('Error marking all as read:', error));
                });
            }
        }
    </script>

    

</body>
</html>
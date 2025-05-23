<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/image_helper.php';
session_start();

// Define INCLUDED constant for included files
define('INCLUDED', true);
require_once __DIR__ . '/../includes/notification_handler.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ./login.php');
    exit();
}

$userId = $_SESSION['user_id'];

// Get notification counts if user is logged in
$notificationCounts = [
    'total' => 0,
    'messages' => 0,
    'friend_requests' => 0,
    'forum_responses' => 0
];

if (isset($_SESSION['user_id'])) {
    // Get unread notification count
    $unreadQuery = "SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0";
    $unreadStmt = $conn->prepare($unreadQuery);
    $unreadStmt->bind_param("i", $userId);
    $unreadStmt->execute();
    $unreadRow = $unreadStmt->get_result()->fetch_assoc();
    $unreadCount = $unreadRow['count'];
    
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
    
    $notificationCounts = [
        'total' => $unreadCount,
        'messages' => $countsByType['message'] ?? 0,
        'friend_requests' => $countsByType['friend_request'] ?? 0,
        'forum_responses' => $countsByType['forum_response'] ?? 0
    ];
}

// Handle removal of saved listing
if (isset($_POST['remove']) && isset($_POST['listing_id'])) {
    $listingId = (int)$_POST['listing_id'];
    
    $removeQuery = "DELETE FROM saved_listings WHERE user_id = ? AND listing_id = ?";
    $removeStmt = $conn->prepare($removeQuery);
    $removeStmt->bind_param('ii', $userId, $listingId);
    $removeStmt->execute();
    
    // Redirect to prevent form resubmission
    header('Location: ./saved_listings.php');
    exit();
}

// Debug function to test image paths
function debug_image_path($path) {
    error_log("Profile image path: " . print_r($path, true));
    return $path;
}

// Fetch saved listings with all details
$query = "
    SELECT l.*, u.username, u.profile_picture, sl.saved_at  
    FROM saved_listings sl
    JOIN listings l ON sl.listing_id = l.id
    JOIN users u ON l.user_id = u.id
    WHERE sl.user_id = ?
    ORDER BY sl.saved_at DESC
";
$stmt = $conn->prepare($query);
$stmt->bind_param('i', $userId);
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Saved Items - Aftermarket Toolbox</title>
    <link rel="stylesheet" href="./assets/css/saved_items.css">
    <link rel="stylesheet" href="./assets/css/notifications.css">
    <!-- Add Font Awesome for notification icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
    <!-- Menu bar with consistent styling and functionality -->
    <div class="menu">
        <a href="../index.php" class="link">
            <span class="link-icon">
                <img src="./assets/images/home-icon.svg" alt="Home">
            </span>
            <span class="link-title">Home</span>
        </a>

        <!-- Market with dropdown -->
        <div class="profile-container">
            <a href="#" class="link active" onclick="toggleDropdown(this, event)">
                <span class="link-icon">
                    <img src="./assets/images/market.svg" alt="Market">
                </span>
                <span class="link-title">Market</span>
            </a>            <div class="dropdown-content">
                <button class="value" onclick="window.location.href='./marketplace.php?view=explore';"><img src="./assets/images/exploreicon.svg" alt="Explore">Explore</button>
                <button class="value" onclick="window.location.href='../api/listings/view_listings.php';"><img src="./assets/images/view_listingicon.svg" alt="View Listings">My Listings</button>
                <button class="value" onclick="window.location.href='../api/listings/create_listing.php';"><img src="./assets/images/list_itemicon.svg" alt="Create Listing">List Item</button>
                <button class="value" onclick="window.location.href='./saved_listings.php';"><img src="./assets/images/savedicons.svg" alt="Saved">Saved Items</button>
            </div>
        </div>
        
        <!-- Forum dropdown -->
        <div class="profile-container">
            <a href="#" class="link" onclick="toggleDropdown(this, event)">                <span class="link-icon">
                    <img src="./assets/images/forum-icon.svg" alt="Forum">
                    <?php if (isset($_SESSION['user_id']) && isset($notificationCounts['forum_responses']) && $notificationCounts['forum_responses'] > 0): ?>
                        <span class="notification-badge forum"><?= $notificationCounts['forum_responses'] ?></span>
                    <?php endif; ?>
                </span>
                <span class="link-title">Forum</span>
            </a>            <div class="dropdown-content">
                <button class="value" onclick="window.location.href='./forum.php?view=threads';"><img src="./assets/images/view_threadicon.svg" alt="Forum">View Threads</button>
                <button class="value" onclick="window.location.href='./forum.php?view=start_thread';"><img src="./assets/images/start_threadicon.svg" alt="Start Thread">Start Thread</button>
                <button class="value" onclick="window.location.href='./forum.php?view=post_question';"><img src="./assets/images/start_threadicon.svg" alt="Post Question">Ask Question</button>
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
            <div class="dropdown-content">
                <?php if (isset($_SESSION['user_id'])): ?>
                    <button class="value" onclick="window.location.href='./profile.php';">
                        <img src="./assets/images/profile-icon.svg" alt="Profile">Account
                    </button>                    <button class="value" onclick="window.location.href='../api/listings/view_listings.php';"><img src="./assets/images/mylistingicon.svg" alt="Market">My Listings</button>
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
          <?php if (isset($_SESSION['user_id'])): ?>            <a href="./chat.php" class="link">
                <span class="link-icon">
                    <img src="./assets/images/chat-icon.svg" alt="Chat">
                    <?php if (isset($_SESSION['user_id']) && isset($notificationCounts['messages']) && $notificationCounts['messages'] > 0): ?>
                        <span class="notification-badge messages"><?= $notificationCounts['messages'] ?></span>
                    <?php endif; ?>
                </span>
                <span class="link-title">Chat</span>
            </a>
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
            <!-- Notifications Dropdown -->            <div class="notifications-container">
                <button id="notificationsBtn" class="notification-btn">
                    <i class="fas fa-bell"></i>
                    <?php if (isset($notificationCounts) && $notificationCounts['total'] > 0): ?>
                      <span id="notification-badge"><?= $notificationCounts['total'] ?></span>
                    <?php endif; ?>
                </button><div id="notificationsDropdown" class="notifications-dropdown">
                    <div class="notifications-header">
                        <h3>Notifications</h3>
                        <?php if ($notificationCounts['total'] > 0): ?>
                        <button id="markAllReadBtn" class="mark-all-read">Mark all as read</button>
                        <?php endif; ?>
                    </div>
                    <div class="notifications-list">
                        <!-- Notifications will be loaded here via JavaScript -->
                        <div class="no-notifications">Loading notifications...</div>
                    </div>
                    <div class="notifications-footer">
                        <a href="./notifications.php" class="view-all-link">See All Notifications</a>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <div class="saved-items-container">
        <h1 class="saved-header">Your Saved Items</h1>
        
        <div class="card-container">
            <?php if ($result && $result->num_rows > 0): ?>
                <?php while ($row = $result->fetch_assoc()): ?>                    <div class="card">                        <a href="../api/listings/listing.php?id=<?= $row['id'] ?>" class="card-link">                            <div class="card-header">
                                <img class="user-pic" src="<?=htmlspecialchars(getImageUrl($row['profile_picture']) ?: '/aftermarket_toolkit/uploads/default_profile.jpg') ?>" alt="User"/>
                                <span class="username"><?= htmlspecialchars($row['username']) ?></span>
                            </div>
                            <img class="listing-img" src="<?= htmlspecialchars(getImageUrl($row['image']) ?: '../assets/images/default-image.jpg') ?>" alt="<?= htmlspecialchars($row['title']) ?>" />
                            <div class="card-body">
                                <h3><?= htmlspecialchars($row['title']) ?></h3>
                                <div class="card-meta">
                                    <p class="price">£<?= number_format($row['price'], 2) ?></p>
                                    <?php if (!empty($row['condition'])): ?>
                                        <span class="condition-badge <?= strtolower(str_replace(' ', '-', $row['condition'])) ?>">
                                            <?= htmlspecialchars($row['condition']) ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <p class="description"><?= htmlspecialchars(substr($row['description'], 0, 100)) . (strlen($row['description']) > 100 ? '...' : '') ?></p>
                                <div class="card-actions">
                                    <form method="post" class="remove-form">
                                        <input type="hidden" name="listing_id" value="<?= $row['id'] ?>">
                                        <button type="submit" name="remove" class="remove-btn">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                                                <path d="M14.5 3a1 1 0 0 1-1 1H13v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V4h-.5a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1H6a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1h3.5a1 1 0 0 1 1 1v1ZM4.118 4 4 4.059V13a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1V4.059L11.882 4H4.118ZM2.5 3h11V2h-11v1Z"/>
                                            </svg>
                                            Remove
                                        </button>
                                    </form>
                                    <span class="date-saved">Saved: <?= date('M j, Y', strtotime($row['saved_at'])) ?></span>
                                </div>
                            </div>
                        </a>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="no-saved-items">
                    <svg width="64" height="64" fill="currentColor" viewBox="0 0 16 16">
                        <path d="M2 2a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v13.5a.5.5 0 0 1-.777.416L8 13.101l-5.223 2.815A.5.5 0 0 1 2 15.5V2zm2-1a1 1 0 0 0-1 1v12.566l4.723-2.482a.5.5 0 0 1 .554 0L13 14.566V2a1 1 0 0 0-1-1H4z"/>
                    </svg>
                    <p>You haven't saved any items yet.</p>
                    <a href="./marketplace.php" class="browse-btn">Browse Marketplace</a>
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
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok: ' + response.status);
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        updateNotificationBadge(data.counts.total || 0);
                        updateNotificationDropdown(data.notifications || []);
                        
                        // Also update specific notification badges
                        updateSpecificBadge('forum_responses', data.counts.forum_responses || 0);
                        updateSpecificBadge('messages', data.counts.messages || 0);
                        updateSpecificBadge('friend_requests', data.counts.friend_requests || 0);
                    } else {
                        console.error('Notification fetch failed:', data.message);
                    }
                })
                .catch(error => {
                    console.error('Error fetching notifications:', error);
                });
        }
        
        // Update specific notification badge (forum, messages, friends)
        function updateSpecificBadge(type, count) {
            // Map notification type to badge class
            let badgeClass;
            switch(type) {
                case 'forum_responses':
                    badgeClass = 'forum';
                    break;
                case 'messages':
                    badgeClass = 'messages';
                    break;
                case 'friend_requests':
                    badgeClass = 'friends';
                    break;
                default:
                    badgeClass = type.replace('_', '-');
            }
            
            const badges = document.querySelectorAll(`.notification-badge.${badgeClass}`);
            if (badges.length > 0) {
                badges.forEach(badge => {
                    if (count > 0) {
                        badge.style.display = 'inline-flex';
                        badge.textContent = count;
                    } else {
                        badge.style.display = 'none';
                    }
                });
            }
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
                const isUnread = notification.is_read === '0';
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
                
                html += `<a href="${notification.link || getNotificationLink(notification.type, notification.related_id)}" class="notification-link"></a>`;
                html += '</div>';
            }
            
           
            list.innerHTML = html;
            
            // Add event listeners to mark notifications as read
            list.querySelectorAll('.notification-mark-read').forEach(btn => {
                btn.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    
                    const item = this.closest('.notification-item');
                    const id = item.dataset.id;
                    const baseUrl = window.location.pathname.includes('/public/') ? '..' : '/aftermarket_toolkit';
                    
                    fetch(`${baseUrl}/public/api/notifications.php`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `action=mark_read&notification_id=${id}`
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            item.classList.remove('unread');
                            this.remove();
                            updateNotificationBadge(data.counts.total);
                        }
                    })
                    .catch(error => console.error('Error marking as read:', error));                });
            });
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
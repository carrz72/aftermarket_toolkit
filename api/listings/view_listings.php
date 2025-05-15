<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/image_helper.php'; // Add this line
// Define INCLUDED constant for included files
define('INCLUDED', true);
require_once __DIR__ . '/../../includes/notification_handler.php';
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../public/login.php');
    exit();
}

$userId = $_SESSION['user_id'];

// Get notification counts if user is logged in
$notificationCounts = [
    'messages' => 0,
    'friend_requests' => 0,
    'forum_responses' => 0,
    'total' => 0
];

if (function_exists('countUnreadNotifications')) {
    $notificationCounts = countUnreadNotifications($conn, $userId);
} else if (function_exists('getNotificationCounts')) {
    $notificationCounts = getNotificationCounts($userId, $conn);
}

// Fetch user listings
$query = "
    SELECT id, title, description, price, image, category, created_at 
    FROM listings 
    WHERE user_id = ? 
    ORDER BY created_at DESC
";
$stmt = $conn->prepare($query);
$stmt->bind_param('i', $userId);
$stmt->execute();
$result = $stmt->get_result();

$listings = [];
while ($row = $result->fetch_assoc()) {
    $listings[] = $row;
}

// Set active section for navigation
$current_section = 'market';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your Listings - Aftermarket Toolbox</title>
    <link rel="stylesheet" href="../../public/assets/css/view_listings.css">
    <link rel="stylesheet" href="../../public/assets/css/notifications.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body>
    <div class="menu">
        <a href="../../index.php" class="link">
            <span class="link-icon">
                <img src="../../public/assets/images/home-icon.svg" alt="Home">
            </span>
            <span class="link-title">Home</span>
        </a>

        <!-- Market dropdown -->
        <div class="profile-container">
            <a href="#" class="link active" onclick="toggleDropdown(this, event)">
                <span class="link-icon">
                    <img src="../../public/assets/images/market.svg" alt="Market">
                </span>
                <span class="link-title">Market</span>
            </a>
            <div class="dropdown-content">
                <button class="value" onclick="window.location.href='../../public/marketplace.php?view=explore';">Explore</button>
                <button class="value" onclick="window.location.href='../listings/view_listings.php';">My Listings</button>
                <button class="value" onclick="window.location.href='../listings/create_listing.php';">List Item</button>
                <button class="value" onclick="window.location.href='../../public/saved_listings.php';">Saved Items</button>
            </div>
        </div>
        
        <!-- Forum dropdown -->
        <div class="profile-container">
            <a href="#" class="link" onclick="toggleDropdown(this, event)">
                <span class="link-icon">
                    <img src="../../public/assets/images/forum-icon.svg" alt="Forum">
                </span>
                <span class="link-title">Forum</span>
            </a>
            <div class="dropdown-content">
                <button class="value" onclick="window.location.href='../../public/forum.php?view=threads';">View Threads</button>
                <button class="value" onclick="window.location.href='../../public/forum.php?view=start_thread';">Start Thread</button>
                <button class="value" onclick="window.location.href='../../public/forum.php?view=post_question';">Post Question</button>
            </div>
        </div>

        <!-- Profile dropdown -->
        <div class="profile-container">
            <a href="#" class="link" onclick="toggleDropdown(this, event)">
                <span class="link-icon">
                    <img src="../../public/assets/images/profile-icon.svg" alt="Profile">
                </span>
                <span class="link-title">Profile</span>
            </a>
            <div class="dropdown-content">
                <?php if (isset($_SESSION['user_id'])): ?>
                    <button class="value" onclick="window.location.href='../../public/profile.php';">
                        <img src="../../public/assets/images/profile-icon.svg" alt="Profile">Account
                    </button>
                    <button class="value" onclick="window.location.href='../listings/view_listings.php';">My Listings</button>
                    <button class="value" onclick="window.location.href='../../public/saved_listings.php';">Saved Items</button>
                    <button class="value" onclick="window.location.href='../../public/friends.php';">Friends</button>
                    <button class="value" onclick="window.location.href='../../public/logout.php';">Logout</button>
                <?php else: ?>
                    <button class="value" onclick="window.location.href='../../public/login.php';">Login</button>
                    <button class="value" onclick="window.location.href='../../public/register.php';">Register</button>
                <?php endif; ?>
            </div>
        </div>
          <?php if (isset($_SESSION['user_id'])): ?>
            <a href="../../public/chat.php" class="link">
                <span class="link-icon">
                    <img src="../../public/assets/images/chat-icon.svg" alt="Chat">
                </span>
                <span class="link-title">Chat</span>
            </a>
            
            <!-- Notifications Dropdown -->
            <div class="notifications-container">
              <button id="notificationsBtn" class="notification-btn">
                <i class="fas fa-bell"></i>
                <?php if (isset($notificationCounts) && $notificationCounts['total'] > 0): ?>
                  <span id="notification-badge"><?= $notificationCounts['total'] ?></span>
                <?php endif; ?>
              </button>
              <div id="notificationsDropdown" class="notifications-dropdown">
                <div class="notifications-header">
                  <h3>Notifications</h3>
                  <?php if (isset($notificationCounts) && $notificationCounts['total'] > 0): ?>
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

    <div class="listings-container">
        <h1>Your Listings</h1>
        
        <div class="create-listing">
            <a href="create_listing.php" class="create-btn">Create New Listing</a>
        </div>
        
        <?php if (!empty($listings)): ?>
            <div class="listings-grid">
                <?php foreach ($listings as $listing): ?>
                    <div class="listing-card">
                        <!-- Use the correct helper function -->
                        <img src="<?= htmlspecialchars(getImageUrl($listing['image']) ?: '../../public/assets/images/default-image.jpg') ?>" 
                             alt="<?= htmlspecialchars($listing['title']) ?>" 
                             class="listing-image">
                        <div class="listing-info">
                            <h2><?= htmlspecialchars($listing['title']) ?></h2>
                            <p class="listing-category"><?= htmlspecialchars($listing['category'] ?? 'Uncategorized') ?></p>
                            <p class="listing-price">£<?= number_format($listing['price'], 2) ?></p>
                            <p class="listing-date">Posted on: <?= date('F j, Y', strtotime($listing['created_at'])) ?></p>
                            <div class="listing-actions">
                                <a href="edit_listing.php?id=<?= $listing['id'] ?>" class="edit-btn">Edit</a>
                                <a href="delete_listing.php?id=<?= $listing['id'] ?>" class="delete-btn" onclick="return confirm('Are you sure you want to delete this listing?');">Delete</a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="no-listings">
                <p>You haven't created any listings yet.</p>
                <p>Start selling your items by creating your first listing!</p>
            </div>
        <?php endif; ?>
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
            // Initialize notification dropdown behavior
            const notificationsBtn = document.getElementById('notificationsBtn');
            const notificationsDropdown = document.getElementById('notificationsDropdown');
            const markAllReadBtn = document.getElementById('markAllReadBtn');
            
            // Toggle dropdown when clicking on the notification button
            notificationsBtn.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                notificationsDropdown.classList.toggle('show');
                
                // Fetch fresh notifications when opening dropdown
                if (notificationsDropdown.classList.contains('show')) {
                    fetchNotifications();
                }
            });
            
            // Close dropdown when clicking outside
            document.addEventListener('click', function(e) {
                if (!notificationsBtn.contains(e.target) && !notificationsDropdown.contains(e.target)) {
                    notificationsDropdown.classList.remove('show');
                }
            });
            
            // Mark all notifications as read
            if (markAllReadBtn) {
                markAllReadBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    
                    fetch('../../public/api/notifications.php', {
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
                            updateNotificationBadge(0);
                        }
                    })
                    .catch(error => console.error('Error marking all as read:', error));
                });
            }
            
            // Fetch notifications on page load
            fetchNotifications();
            
            // Poll for new notifications every 60 seconds
            setInterval(fetchNotifications, 60000);
        }
        
        // Fetch notifications via AJAX
        function fetchNotifications() {
            fetch('../../public/api/notifications.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        updateNotificationBadge(data.counts.total || 0);
                        updateNotificationDropdown(data.notifications || []);
                    }
                })
                .catch(error => console.error('Error fetching notifications:', error));
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
                
                html += `<a href="${notification.link || '../../public/notifications.php'}" class="notification-link"></a>`;
                html += '</div>';
            }
            
            // If there are more notifications than we're showing, add a "view all" link
            if (notifications.length > maxToShow) {
                html += '<div class="notification-item show-all">';
                html += '<a href="../../public/notifications.php">View all notifications</a>';
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
                    
                    fetch('../../public/api/notifications.php', {
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
                    .catch(error => console.error('Error marking as read:', error));
                });
            });
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

        // Handle notification messages (if any)
        <?php if (isset($_GET['message'])): ?>
        const message = "<?= htmlspecialchars($_GET['message']) ?>";
        const status = "<?= htmlspecialchars($_GET['status'] ?? 'success') ?>";
        
        const notification = document.createElement('div');
        notification.className = `notification ${status}`;
        
        const notificationContent = document.createElement('div');
        notificationContent.className = 'notification-content';
        notificationContent.innerHTML = `
            ${message}
            <button onclick="this.parentElement.parentElement.remove();">&times;</button>
        `;
        
        notification.appendChild(notificationContent);
        document.body.appendChild(notification);
        
        // Add the show class after a small delay for the animation
        setTimeout(() => {
            notification.classList.add('show');
        }, 10);
        
        // Auto-hide after 5 seconds
        setTimeout(() => {
            notification.classList.remove('show');
            setTimeout(() => {
                notification.remove();
            }, 300);
        }, 5000);
        <?php endif; ?>
    </script>
</body>
</html>
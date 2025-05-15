<?php
session_start();
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/image_helper.php';

// Define INCLUDED constant for included files
define('INCLUDED', true);

// Ensure necessary tables and structures exist
require_once __DIR__ . '/includes/ensure_tables.php';

// Set current section for menu highlighting
$current_section = 'home';

// Get featured listings for marketplace preview (limit to 6)
$sql = "
  SELECT listings.*, users.username, users.profile_picture 
  FROM listings 
  JOIN users ON listings.user_id = users.id
  ORDER BY listings.created_at DESC LIMIT 6
";

$stmt = $conn->prepare($sql);
$stmt->execute();
$featuredListings = $stmt->get_result();

// Get recent forum threads for preview (limit to 3)
$forumSql = "
  SELECT forum_threads.*, users.username, users.profile_picture AS profile_pic,
         COUNT(forum_replies.id) AS reply_count
  FROM forum_threads 
  JOIN users ON forum_threads.user_id = users.id
  LEFT JOIN forum_replies ON forum_threads.id = forum_replies.thread_id
  GROUP BY forum_threads.id
  ORDER BY forum_threads.created_at DESC 
  LIMIT 3
";
$forumStmt = $conn->prepare($forumSql);
$forumStmt->execute();
$featuredThreads = $forumStmt->get_result();

// Get stats for the platform
$statsSql = "SELECT 
    (SELECT COUNT(*) FROM listings) as total_listings,
    (SELECT COUNT(*) FROM users) as total_users,
    (SELECT COUNT(*) FROM forum_threads) as total_threads";
$statsResult = $conn->query($statsSql);
$stats = $statsResult->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Aftermarket Toolbox - Home</title>  
  <link rel="stylesheet" href="./public/assets/css/index.css">
  <link rel="stylesheet" href="./public/assets/css/notifications.css">
  <!-- Add Font Awesome for icons -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body>
<?php 
// INCLUDED constant is already defined at the top of this file
require_once __DIR__ . '/includes/notification_handler.php';
require_once __DIR__ . '/includes/navigation.php';
?>

<div class="sidebar">
    <button id="sidebarToggle" class="sidebar-toggle">☰</button>
  <h2>Quick Navigation</h2>
  <ul>
    <li><a href="./public/marketplace.php">Marketplace</a></li>
    <li><a href="./public/forum.php">Community Forum</a></li>
    <?php if (isset($_SESSION['user_id'])): ?>
      <li><a href="./public/chat.php">Messages</a></li>
      <li><a href="./public/profile.php">My Profile</a></li>
      <li><a href="./public/saved_listings.php">Saved Items</a></li>
    <?php else: ?>
      <li><a href="./public/login.php">Login</a></li>
      <li><a href="./public/register.php">Register</a></li>
    <?php endif; ?>
  </ul>
</div>

<main class="main-content">
  <!-- Welcome Banner -->
  <div class="welcome-banner">
    <h1>Welcome to Aftermarket Toolbox</h1>
    <p>Your one-stop platform for buying, selling, and discussing aftermarket parts and tools. Join our community today!</p>
    
    <?php if (!isset($_SESSION['user_id'])): ?>
      <div class="cta-buttons">
        <button class="cta-btn cta-primary" onclick="window.location.href='./public/register.php'">Join Now</button>
        <button class="cta-btn cta-secondary" onclick="window.location.href='./public/login.php'">Login</button>
      </div>
    <?php endif; ?>
  </div>
    
  <!-- Quick Access Cards -->
  <div class="top-cards">
    <div class="sub-card">
      <div class="smallIcon">
        <div class="Name-icon">
          <div class="Icon">
            <img src="./public/assets/images/market.svg" alt="Market" />
          </div>
          <div class="Name">
            <h3>Marketplace</h3>
          </div>
        </div>
        <div class="Descripion">
          <button onclick="window.location.href='./api/listings/view_listings.php';">View Listings</button>
          <button onclick="window.location.href='./public/marketplace.php';">Explore</button>
          <button onclick="window.location.href='./api/listings/create_listing.php';">List Item</button>
          <button onclick="window.location.href='./public/saved_listings.php';">Saved Items</button>
        </div>
      </div>
    </div>
          
    <div class="sub-card">
      <div class="smallIcon">
        <div class="Name-icon">
          <div class="Icon">
            <img src="./public/assets/images/forum-icon.svg" alt="Forum" />
          </div>
          <div class="Name">
            <h3>Forum</h3>
          </div>
        </div>
        <div class="Descripion">  
          <button onclick="window.location.href='./public/forum.php?view=threads';">View Threads</button>
          <button onclick="window.location.href='./public/forum.php?view=start_thread';">Start Thread</button>
          <button onclick="window.location.href='./public/forum.php?view=post_question';">Post Question</button>
        </div>
      </div>
    </div>

    <div class="sub-card">
      <div class="smallIcon">
        <div class="Name-icon">
          <div class="Icon">
            <img src="./public/assets/images/chat-icon.svg" alt="Chat" />
          </div>
          <div class="Name">
            <h3>Chat</h3>
          </div>
        </div>
        <div class="Descripion">
          <button onclick="window.location.href='./public/chat.php';">View Messages</button>
          <?php if (isset($_SESSION['user_id'])): ?>
            <button onclick="window.location.href='./public/account.php';">Account Settings</button>
          <?php else: ?>
            <button onclick="window.location.href='./public/login.php';">Login to Chat</button>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>

  <!-- Marketplace Preview Section -->
  <div class="marketplace">
    <div class="section-header">
      <h2 class="section-title">Featured Listings</h2>
    </div>

    <div class="card-container">
      <?php if ($featuredListings && $featuredListings->num_rows > 0): ?>
        <?php while ($row = $featuredListings->fetch_assoc()): ?>
          <a href="./api/listings/listing.php?id=<?= $row['id'] ?>" class="card-link">
            <div class="card">
              <div class="card-header">
                <img class="user-pic" src="<?= htmlspecialchars(getImageUrl($row['profile_picture']) ?: '/aftermarket_toolkit/uploads/default_profile.jpg') ?>" alt="User" />
                <span class="username"><?= htmlspecialchars($row['username']) ?></span>
              </div>
              <img class="listing-img" src="<?= htmlspecialchars(getImageUrl($row['image']) ?: '/aftermarket_toolkit/uploads/default_profile.jpg') ?>" alt="<?= htmlspecialchars($row['title']) ?>" />
              <div class="card-body">
                <h3><?= htmlspecialchars($row['title']) ?></h3>
                <div class="card-meta">
                  <p class="price">£<?= number_format($row['price'], 2) ?></p>
                  <?php if (!empty($row['condition'])): ?>
                    <span class="condition-badge" data-condition="<?= htmlspecialchars($row['condition']) ?>">
                      <?= htmlspecialchars($row['condition']) ?>
                    </span>
                  <?php endif; ?>
                </div>
                <p class="description"><?= htmlspecialchars(substr($row['description'], 0, 100)) . (strlen($row['description']) > 100 ? '...' : '') ?></p>
              </div>
              <div class="card-footer">
                <?php if (isset($_SESSION['user_id'])): ?>
                <button class="bookmark" onclick="event.stopPropagation(); saveBookmark(<?= $row['id'] ?>); return false;">
                  <img src="./public/assets/images/bookmark.svg" alt="Bookmark" />
                </button>
                <?php endif; ?>
                <span class="date-added"><?= date('M j', strtotime($row['created_at'])) ?></span>
              </div>
            </div>
          </a>
        <?php endwhile; ?>
      <?php else: ?>
        <div class="no-items">
          <p>No listings available at the moment.</p>
          <p>Be the first to list an item!</p>
        </div>
      <?php endif; ?>
    </div>
    <div class="view-all">
      <button class="view-all-btn" onclick="window.location.href='./public/marketplace.php'">View All Listings</button>  
    </div>
  </div>

  <?php if (!isset($_SESSION['user_id'])): ?>
  <!-- Call-to-action for non-logged in users -->
  <div class="cta-container">
    <h3 class="cta-title">Ready to buy or sell aftermarket parts?</h3>
    <p>Join our community today to access all features!</p>
    <div class="cta-buttons">
      <button class="cta-btn cta-primary" onclick="window.location.href='./public/register.php'">Create Account</button>
      <button class="cta-btn cta-secondary" onclick="window.location.href='./public/login.php'">Login</button>
    </div>
  </div>
  <?php endif; ?>

  <!-- Forum Preview Section -->
  <div class="forum">
    <div class="forum-container">
      <div class="section-header">
        <h2 class="section-title">Recent Discussions</h2>
      </div>
      <section class="forum-section">
        <div class="forum-preview">
          <?php if ($featuredThreads && $featuredThreads->num_rows > 0): ?>
            <div class="forum-threads">
              <?php while ($thread = $featuredThreads->fetch_assoc()): ?>
                <div class="forumcard">
                  <div class="card-body">
                    <div class="forum-profile">
                      <img 
                        src="<?= htmlspecialchars(getImageUrl($thread['profile_pic']) ?: './public/assets/images/default-profile.jpg') ?>" 
                        alt="<?= htmlspecialchars($thread['username']) ?>" 
                        class="profile-pic" 
                      >
                      <div class="pro-details">
                        <a href="./public/profile.php?user_id=<?= $thread['user_id'] ?>"><?= htmlspecialchars($thread['username']) ?></a><br>
                        <?= date('M j, Y', strtotime($thread['created_at'])) ?>
                      </div>
                    </div>
                    <div class="forum-content">
                      <h5 class="card-title"><?= htmlspecialchars($thread['title']) ?></h5>
                      <p class="card-text"><?= nl2br(htmlspecialchars(substr($thread['body'], 0, 150))) ?>
                        <?php if (strlen($thread['body']) > 150): ?>...<?php endif; ?>
                      </p>
                      <div class="forum-actions">
                        <a href="./public/forum.php?thread=<?= $thread['id'] ?>" class="forum-link">
                          Read more and reply (<?= $thread['reply_count'] ?> responses)
                        </a>
                      </div>
                    </div>
                  </div>
                </div>
              <?php endwhile; ?>
            </div>
          <?php else: ?>
            <div class="no-items">
              <p>No forum discussions yet.</p>
              <p>Be the first to start a conversation!</p>
            </div>
          <?php endif; ?>
        </div>
      </section>
    </div>
    <div class="view-all">
      <button class="view-all-btn" onclick="window.location.href='./public/forum.php'">View All Discussions</button>  
    </div>
  </div>
</main>

<script>
  const delay = 100; // Delay in milliseconds
  
  // Sidebar toggle functionality
  document.getElementById('sidebarToggle').addEventListener('click', function() {
    const sidebar = document.querySelector('.sidebar');
    const body = document.body;
    
    sidebar.classList.toggle('active');
    body.classList.toggle('sidebar-active');
  });
  
  // Click outside sidebar to close it
  document.addEventListener('click', function(e) {
    const sidebar = document.querySelector('.sidebar');
    const sidebarToggle = document.getElementById('sidebarToggle');
    
    if (sidebar.classList.contains('active') && 
        !sidebar.contains(e.target) && 
        e.target !== sidebarToggle) {
      sidebar.classList.remove('active');
      document.body.classList.remove('sidebar-active');
    }
  });

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

  // Bookmark functionality
  function saveBookmark(listingId) {
    // Get the current button to update its state
    const button = event.currentTarget;
    const isCurrentlyBookmarked = button.classList.contains('bookmarked');
    
    // Set action based on current state
    const action = isCurrentlyBookmarked ? 'remove' : 'add';
    
    fetch('./api/bookmarks/toggle_bookmark.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded',
      },
      body: `listing_id=${listingId}&action=${action}`
    })
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        console.log(`Bookmark ${action}ed for listing: ${listingId}`);
        
        // Update button appearance based on new state
        if (action === 'add') {
          // Bookmark added
          button.classList.add('bookmarked');
          
          const bookmarkImg = button.querySelector('img');
          if (bookmarkImg) {
            bookmarkImg.src = './public/assets/images/bookmark-filled.svg';
            bookmarkImg.style.filter = 'none';
          }
          
        } else {
          // Bookmark removed
          button.classList.remove('bookmarked');
          
          const bookmarkImg = button.querySelector('img');
          if (bookmarkImg) {
            bookmarkImg.src = './public/assets/images/bookmark.svg';
            bookmarkImg.style.filter = 'grayscale(100%)';
          }
        }
      } else {
        console.error("Error toggling bookmark:", data.message);
        if (data.message === "User not logged in") {
          alert("Please log in to save items");
          window.location.href = './public/login.php';
        } else {
          alert("Error: " + data.message);
        }
      }
    })
    .catch(error => {
      console.error('Error:', error);
      alert("Something went wrong. Please try again.");
    });
  }
    // Add at the bottom of your script section
  document.addEventListener('DOMContentLoaded', function() {
    // Check if user is logged in and initialize saved bookmarks
    fetch('./api/bookmarks/get_bookmarks.php')
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          // Update bookmark icons for saved listings
          data.bookmarks.forEach(listingId => {
            const bookmarkButton = document.querySelector(`.bookmark-btn[data-listing-id="${listingId}"]`);
            if (bookmarkButton) {
              bookmarkButton.classList.add('bookmarked');
              bookmarkButton.innerHTML = '<i class="fas fa-bookmark"></i>';
            }
          });
        }
      })
      .catch(error => {
        console.log('Error fetching bookmarks:', error);
      });
      
    // Initialize notification system
    if (document.getElementById('notificationsBtn')) {
      initNotificationSystem();
    }
  });
  // Function to fetch notifications
  const notifApiUrl = '<?= $root_path ?>public/api/notifications.php';

  function fetchNotifications() {
    fetch(notifApiUrl, { credentials: 'same-origin' })
      .then(response => {
        console.log('Notification response status:', response.status);
        return response.json();
      })
      .then(data => {
        console.log('Notification data:', data);
        if (data.success) {
          updateNotificationBadge(data.counts.total || 0);
          updateNotificationDropdown(data.notifications || []);
        } else {
          console.error('Error in notification response:', data.message);
          // Show error in dropdown if there's an issue
          const list = document.querySelector('.notifications-list');
          if (list) {
            list.innerHTML = '<div class="no-notifications">Unable to load notifications</div>';
          }
        }
      })
      .catch(error => {
        console.error('Error fetching notifications:', error);
        // Show error in dropdown if there's an exception
        const list = document.querySelector('.notifications-list');
        if (list) {
          list.innerHTML = '<div class="no-notifications">Error loading notifications</div>';
        }
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
    
    if (!Array.isArray(notifications) || notifications.length === 0) {
      list.innerHTML = '<div class="no-notifications">No new notifications</div>';
      return;
    }
    
    let html = '';
    const maxToShow = 5;
    
    for (let i = 0; i < Math.min(notifications.length, maxToShow); i++) {
      const notification = notifications[i];
      const unreadClass = notification.is_read ? '' : 'unread';
      
      html += `<div class="notification-item ${unreadClass}" data-id="${notification.id}" data-type="${notification.type}" data-related-id="${notification.related_id || ''}">`;
      
      // Icon based on type
      let iconClass = getNotificationIconClass(notification.type);
      
      html += `<div class="notification-icon"><i class="fas ${iconClass}"></i></div>`;
      html += `<div class="notification-content">`;
      html += `<div class="notification-text">${notification.content}</div>`;
      html += `<div class="notification-time">${formatTimeAgo(notification.created_at)}</div>`;
      html += `</div>`;
      
      if (!notification.is_read) {
        html += `<div class="notification-mark-read"><i class="fas fa-check"></i></div>`;
      }
      
      // Add a proper link for navigation
      const link = getNotificationLink(notification.type, notification.related_id);
      html += `<a href="${link}" class="notification-link"></a>`;
      
      html += `</div>`;
    }
    
    // "View all" button if there are more notifications
    if (notifications.length > maxToShow) {
      html += `<div class="notification-item show-all">`;
      html += `<a href="./public/notifications.php">View all notifications</a>`;
      html += `</div>`;
    }
    
    list.innerHTML = html;
  }
  
  // Get the appropriate link for a notification
  function getNotificationLink(type, relatedId) {
    switch(type) {
      case 'friend_request':
        return './public/friends.php';
      case 'message':
        return './public/chat.php';
      case 'forum_response':
        return relatedId ? `./public/forum.php?thread=${relatedId}` : './public/forum.php';
      default:
        return './public/notifications.php';
    }
  }
  
  // Format timestamp as time ago
  function formatTimeAgo(timestamp) {
    const date = new Date(timestamp);
    const now = new Date();
    const secondsPast = (now.getTime() - date.getTime()) / 1000;
    
    if (secondsPast < 60) {
      return 'just now';
    }
    if (secondsPast < 3600) {
      return Math.round(secondsPast / 60) + ' minutes ago';
    }
    if (secondsPast <= 86400) {
      return Math.round(secondsPast / 3600) + ' hours ago';
    }
    if (secondsPast <= 2592000) {
      return Math.round(secondsPast / 86400) + ' days ago';
    }
    if (secondsPast <= 31536000) {
      return Math.round(secondsPast / 2592000) + ' months ago';
    }
    return Math.round(secondsPast / 31536000) + ' years ago';
  }
  
  // Initialize notification system
  function initNotificationSystem() {
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
        
        fetch(notifApiUrl, {
          credentials: 'same-origin',
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
    
    // Mark individual notification as read and handle clicks
    document.addEventListener('click', function(e) {
      // Handle mark as read button clicks
      const markReadBtn = e.target.closest('.notification-mark-read');
      if (markReadBtn) {
        e.preventDefault();
        e.stopPropagation();
        
        const notificationItem = markReadBtn.closest('.notification-item');
        const notificationId = notificationItem.dataset.id;
        
        fetch(notifApiUrl, {
          credentials: 'same-origin',
          method: 'POST',
          headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
          },
          body: `action=mark_read&notification_id=${notificationId}`
        })
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            notificationItem.classList.remove('unread');
            markReadBtn.remove();
            updateNotificationBadge(data.counts.total);
          }
        })
        .catch(error => console.error('Error marking as read:', error));
      }
      
      // Handle clicking on notification item
      const notificationItem = e.target.closest('.notification-item:not(.show-all)');
      if (notificationItem && !e.target.closest('.notification-mark-read')) {
        const notificationId = notificationItem.dataset.id;
        const notificationType = notificationItem.dataset.type;
        const relatedId = notificationItem.dataset.relatedId;
        
        // If unread, mark as read before navigating
        if (notificationItem.classList.contains('unread')) {
          e.preventDefault();
          
          fetch(notifApiUrl, {
            credentials: 'same-origin',
            method: 'POST',
            headers: {
              'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=mark_read&notification_id=${notificationId}`
          })
          .then(() => {
            // Navigate to the appropriate link
            window.location.href = getNotificationLink(notificationType, relatedId);
          })
          .catch(() => {
            // Still navigate even if there was an error
            window.location.href = getNotificationLink(notificationType, relatedId);
          });
        }
      }
    });
    
    // Initial notification fetch
    fetchNotifications();
    
    // Poll for new notifications every minute
    setInterval(fetchNotifications, 60000);
  }
  
  // Navigate to appropriate page based on notification type
  function navigateToNotification(type, relatedId) {
    switch(type) {
      case 'friend_request':
        window.location.href = './public/friends.php';
        break;
      case 'message':
        window.location.href = './public/chat.php';
        break;
      case 'forum_response':
        window.location.href = `./public/forum.php?thread=${relatedId}`;
        break;
      default:
        // Default action is to go to notifications page
        window.location.href = './public/notifications.php';
    }
  }

  // Helper function to get the appropriate icon class for a notification type
  function getNotificationIconClass(type) {
    switch(type) {
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
  // Notifications are now handled by initNotificationSystem function
  // Removed old notification initialization code
</script>
</body>
</html>

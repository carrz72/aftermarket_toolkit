<?php
// Helper function to determine if a menu item should be active
function isActive($sectionName) {
    global $current_section;
    
    // If we're on the index page but current_section isn't explicitly set
    if ($sectionName === 'home' && 
        (!isset($current_section) || empty($current_section)) && 
        (strpos($_SERVER['PHP_SELF'], 'index.php') !== false || $_SERVER['PHP_SELF'] === '/aftermarket_toolkit/')) {
        return 'active';
    }
    
    return (isset($current_section) && $current_section === $sectionName) ? 'active' : '';
}

// Determine relative path for icons
$root_path = '';
$current_path = $_SERVER['SCRIPT_NAME'];
if (strpos($current_path, '/public/') !== false || strpos($current_path, '/api/') !== false) {
    $root_path = '../../';
}

// Get notification counts if user is logged in
$notificationCounts = [
    'messages' => 0,
    'friend_requests' => 0,
    'forum_responses' => 0,
    'total' => 0
];

if (isset($_SESSION['user_id'])) {
    if (function_exists('countUnreadNotifications')) {
        $notificationCounts = countUnreadNotifications($conn, $_SESSION['user_id']);
    } else {
        $notificationCounts = getNotificationCounts($_SESSION['user_id'], $conn);
    }
}
?>

<div class="menu">
  <a href="<?= $root_path ?>index.php" class="link <?= isActive('home') ?>">
    <span class="link-icon">
      <img src="<?= $root_path ?>public/assets/images/home-icon.svg" alt="Home">
    </span>
    <span class="link-title">Home</span>
  </a>

  <!-- Market with dropdown -->
  <div class="profile-container">
    <a href="#" class="link <?= isActive('market') ?>" onclick="toggleDropdown(this, event)">
      <span class="link-icon">
        <img src="<?= $root_path ?>public/assets/images/market.svg" alt="Market">
      </span>
      <span class="link-title">Market</span>
    </a>
    <div class="dropdown-content">
      <button class="value" onclick="window.location.href='<?= $root_path ?>public/marketplace.php?view=explore';">Explore</button>
      <button class="value" onclick="window.location.href='<?= $root_path ?>api/listings/view_listings.php';">View Listings</button>
      <button class="value" onclick="window.location.href='<?= $root_path ?>api/listings/create_listing.php';">List Item</button>
      <button class="value" onclick="window.location.href='<?= $root_path ?>public/saved_listings.php';">Saved Items</button>
    </div>
  </div>
  
  <!-- Forum dropdown -->
  <div class="profile-container">
    <a href="#" class="link <?= isActive('forum') ?>" onclick="toggleDropdown(this, event)">
      <span class="link-icon">
        <img src="<?= $root_path ?>public/assets/images/forum-icon.svg" alt="Forum">
        <?php if (isset($_SESSION['user_id']) && $notificationCounts['forum_responses'] > 0): ?>
          <?= getNotificationBadgeHTML($notificationCounts['forum_responses'], 'forum') ?>
        <?php endif; ?>
      </span>
      <span class="link-title">Forum</span>
    </a>
    <div class="dropdown-content">
      <button class="value" onclick="window.location.href='<?= $root_path ?>public/forum.php?view=threads';">View Threads</button>
      <button class="value" onclick="window.location.href='<?= $root_path ?>public/forum.php?view=start_thread';">Start Thread</button>
      <button class="value" onclick="window.location.href='<?= $root_path ?>public/forum.php?view=post_question';">Post Question</button>
    </div>
  </div>

  <!-- Profile dropdown -->
  <div class="profile-container">
    <a href="#" class="link <?= isActive('profile') ?>" onclick="toggleDropdown(this, event)">
      <span class="link-icon">
        <img src="<?= $root_path ?>public/assets/images/profile-icon.svg" alt="Profile">
      </span>
      <span class="link-title">Profile</span>
    </a>
    <div id="profileDropdown" class="dropdown-content">
      <?php if (isset($_SESSION['user_id'])): ?>
        <button class="value" onclick="window.location.href='<?= $root_path ?>public/profile.php';">
          <img src="<?= $root_path ?>public/assets/images/profile-icon.svg" alt="Profile">Account
        </button>
        <button class="value" onclick="window.location.href='<?= $root_path ?>api/listings/view_listings.php';">My Listings</button>
        <button class="value" onclick="window.location.href='<?= $root_path ?>public/saved_listings.php';">Saved Items</button>        <button class="value" onclick="window.location.href='<?= $root_path ?>public/friends.php';">
          Friends
          <?php if (isset($_SESSION['user_id']) && isset($notificationCounts['friend_requests']) && $notificationCounts['friend_requests'] > 0): ?>
            <?= getNotificationBadgeHTML($notificationCounts['friend_requests'], 'friends') ?>
          <?php endif; ?>
        </button>
        <button class="value" onclick="window.location.href='<?= $root_path ?>public/logout.php';">Logout</button>
      <?php else: ?>
        <button class="value" onclick="window.location.href='<?= $root_path ?>public/login.php';">Login</button>
        <button class="value" onclick="window.location.href='<?= $root_path ?>public/register.php';">Register</button>
      <?php endif; ?>
    </div>
  </div>
    <?php if (isset($_SESSION['user_id'])): ?>
    <a href="<?= $root_path ?>public/chat.php" class="link <?= isActive('chat') ?>">
      <span class="link-icon">
        <img src="<?= $root_path ?>public/assets/images/chat-icon.svg" alt="Chat">
        <?php if ($notificationCounts['messages'] > 0): ?>
          <?= getNotificationBadgeHTML($notificationCounts['messages'], 'messages') ?>
        <?php endif; ?>
      </span>
      <span class="link-title">Chat</span>
    </a>
      <!-- Notifications Dropdown -->
    <div class="notifications-container">
      <button id="notificationsBtn" class="notification-btn">
        <i class="fas fa-bell"></i>
        <?php if ($notificationCounts['total'] > 0): ?>
        <span id="notification-badge"><?= $notificationCounts['total'] ?></span>
        <?php endif; ?>
      </button>
      <div id="notificationsDropdown" class="notifications-dropdown">
        <div class="notifications-header">
          <h3>Notifications</h3>
          <?php if ($notificationCounts['total'] > 0): ?>
          <button id="markAllReadBtn" class="mark-all-read">Mark all as read</button>
          <?php endif; ?>
        </div>
        <div class="notifications-list">
          <!-- Notifications will be loaded here via JavaScript -->
          <?php if (isset($_SESSION['user_id'])): ?>
            <div class="no-notifications">Loading notifications...</div>
          <?php else: ?>
            <div class="no-notifications">Please log in to view notifications</div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  <?php endif; ?>
</div>
<?php
// Helper function to get the notification badge HTML

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

// Include image helper
require_once __DIR__ . '/image_helper.php';

// Determine relative path for icons
$root_path = '';
$current_path = $_SERVER['SCRIPT_NAME'];
if (strpos($current_path, '/public/') !== false || strpos($current_path, '/api/') !== false) {
    $root_path = '../../';
}

//Initialize notification filter variables
$filterType = '';
$showUnread = false;
$limit = 10;
$offset = 0;

// Define current section for navigation highlighting
$current_section = 'notifications';
$userId = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 0;
$message = '';
$searchResults = [];


// Get notification counts if user is logged in
$notificationCounts = [    'total' => 0,
    'messages' => 0,
    'friend_requests' => 0,
    'forum_responses' => 0,
    'job_applications' => 0
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



<div class="menu">
  <a href="" class="link active">
    <span class="link-icon">
      <img src="public/assets/images/home-icon.svg" alt="Home">
    </span>
    <span class="link-title">Home</span>
  </a>

  <!-- Market with dropdown -->
  <div class="profile-container">
    <a href="#" class="link" onclick="toggleDropdown(this, event)">
      <span class="link-icon">
        <img src="public/assets/images/market.svg" alt="Market">
      </span>
      <span class="link-title">Market</span>
    </a>    <div class="dropdown-content">
      <button class="value" onclick="window.location.href='public/marketplace.php?view=explore';"><img src="public/assets/images/exploreicon.svg" alt="Explore">Explore</button>
      <button class="value" onclick="window.location.href='api/listings/view_listings.php';"><img src="public//assets/images/view_listingicon.svg" alt="View Listings">View Listings</button>
      <button class="value" onclick="window.location.href='api/listings/create_listing.php';"><img src="public/assets/images/list_itemicon.svg" alt="Create Listing">List Item</button>
      <button class="value" onclick="window.location.href='public/saved_listings.php';"><img src="public/assets/images/savedicons.svg" alt="Saved">Saved Items</button>
    </div>
  </div>
  
  <!-- Forum dropdown -->
  <div class="profile-container">
    <a href="#" class="link" onclick="toggleDropdown(this, event)">      <span class="link-icon">
        <img src="public/assets/images/forum-icon.svg" alt="Forum">
        <?php if (isset($_SESSION['user_id']) && isset($notificationCounts['forum_responses']) && $notificationCounts['forum_responses'] > 0): ?>
        <?php endif; ?>
      </span>
      <span class="link-title">Forum</span>
    </a>    <div class="dropdown-content">
      <button class="value" onclick="window.location.href='public/forum.php?view=threads';"><img src="public/assets/images/view_threadicon.svg" alt="Forum">View Threads</button>
      <button class="value" onclick="window.location.href='public/create_forum.php?type=thread';"><img src="public/assets/images/start_threadicon.svg" alt="Start Thread">Start Thread</button>
      <button class="value" onclick="window.location.href='public/create_forum.php?type=thread';"><img src="public/assets/images/start_threadicon.svg" alt="Post Question">Post Question</button>
    </div>
  </div>

  <!-- Profile dropdown -->
  <div class="profile-container">
    <a href="#" class="link" onclick="toggleDropdown(this, event)">
      <span class="link-icon">
        <img src="public/assets/images/profile-icon.svg" alt="Profile">
      </span>
      <span class="link-title">Profile</span>
    </a>
    <div id="profileDropdown" class="dropdown-content">
      <?php if (isset($_SESSION['user_id'])): ?>        <button class="value" onclick="window.location.href='public/profile.php';">
          <img src="public/assets/images/profile-icon.svg" alt="Profile">Account
        </button>
        <button class="value" onclick="window.location.href='api/listings/view_listings.php';"><img src="public/assets/images/mylistingicon.svg" alt="Market">My Listings</button>
        <button class="value" onclick="window.location.href='public/saved_listings.php';"><img src="public/assets/images/savedicons.svg" alt="Saved">Saved Items</button>
        <button class="value" onclick="window.location.href='public/friends.php';"><img src="public/assets/images/friendsicon.svg" alt="Friends">Friends
          <?php if (isset($_SESSION['user_id']) && isset($notificationCounts['friend_requests']) && $notificationCounts['friend_requests'] > 0): ?>
            <span class="notification-badge friends"><?= $notificationCounts['friend_requests'] ?></span>
          <?php endif; ?>
        </button>
        <button class="value" onclick="window.location.href='public/logout.php';"><img src="public/assets/images/Log_Outicon.svg" alt="Logout">Logout</button>
      <?php else: ?>
        <button class="value" onclick="window.location.href='public/login.php';">Login</button>
        <button class="value" onclick="window.location.href='public/register.php';">Register</button>
      <?php endif; ?>
    </div>
  </div>

  <?php if (isset($_SESSION['user_id'])): ?>    <a href="public/chat.php" class="link">
      <span class="link-icon">
        <img src="public/assets/images/chat-icon.svg" alt="Chat">
        <?php if (isset($_SESSION['user_id']) && isset($notificationCounts['messages']) && $notificationCounts['messages'] > 0): ?>
          <span class="notification-badge messages"><?= $notificationCounts['messages'] ?></span>
        <?php endif; ?>
      </span>
      <span class="link-title">Chat</span>
    </a>
    <div class="profile-container">    <a href="#" class="link <?= $current_section === 'jobs' ? 'active' : '' ?>" onclick="toggleDropdown(this, event)">
      <span class="link-icon">
        <img src="public/assets/images/job-icon.svg" alt="Jobs">
        <?php if (isset($_SESSION['user_id']) && isset($notificationCounts['job_applications']) && $notificationCounts['job_applications'] > 0): ?>
          <span class="notification-badge jobs"><?= $notificationCounts['job_applications'] ?></span>
        <?php endif; ?>
      </span>
      <span class="link-title">Jobs</span>
    </a>
    <div class="dropdown-content">
      <button class="value" onclick="window.location.href='public/jobs.php';"><img src="public/assets/images/exploreicon.svg" alt="Explore">
        Explore</button>
      <button class="value" onclick="window.location.href='public/jobs.php?action=post';"><img src="public/assets/images/post_job_icon.svg" alt="Create Job">
        Post Job</button>
      <button class="value" onclick="window.location.href='public/jobs.php?action=my_applications';"><img src="public/assets/images/my_applications_icon.svg" alt="My Applications">
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
        </div>        <div class="notifications-list">
          <!-- Notifications will be loaded here via JavaScript -->
          <div class="no-notifications">Loading notifications...</div>
        </div>
      </div>
    </div>
  <?php endif; ?>
</div>




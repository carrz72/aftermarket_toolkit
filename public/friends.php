<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/image_helper.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$userId = $_SESSION['user_id'];
$message = '';
$searchResults = [];

// Handle friend actions (add, accept, decline, remove)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && isset($_POST['friend_id'])) {
        $friendId = (int)$_POST['friend_id'];
        $action = $_POST['action'];
        
        switch ($action) {
            case 'send_request':
                // Check if request already exists
                $checkStmt = $conn->prepare("SELECT * FROM friend_requests WHERE (sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?)");
                $checkStmt->bind_param("iiii", $userId, $friendId, $friendId, $userId);
                $checkStmt->execute();
                $checkResult = $checkStmt->get_result();
                
                // Check if already friends
                $checkFriendStmt = $conn->prepare("SELECT * FROM friends WHERE (user_id = ? AND friend_id = ?) OR (user_id = ? AND friend_id = ?)");
                $checkFriendStmt->bind_param("iiii", $userId, $friendId, $friendId, $userId);
                $checkFriendStmt->execute();
                $checkFriendResult = $checkFriendStmt->get_result();
                
                if ($checkResult->num_rows === 0 && $checkFriendResult->num_rows === 0) {
                    $requestStmt = $conn->prepare("INSERT INTO friend_requests (sender_id, receiver_id, created_at) VALUES (?, ?, NOW())");                    $requestStmt->bind_param("ii", $userId, $friendId);
                    if ($requestStmt->execute()) {
                        // grab the new request ID
                        $newRequestId = $conn->insert_id;
                          // fire the notification
                        require_once __DIR__ . '/../includes/notification_handler.php';
                        try {
                            // Make sure the notification function exists
                            if (function_exists('sendNotification')) {
                                sendNotification(
                                  $conn,
                                  $friendId,          // recipient
                                  'friend_request',   // type
                                  $userId,            // sender
                                  $newRequestId,      // related_id
                                  '',                 // content (empty = auto-generate)
                                  '/aftermarket_toolkit/public/friends.php'  // link to friends page
                                );
                            } else {
                                // Log error if function doesn't exist
                                error_log("sendNotification function not found");
                            }
                        } catch (Exception $e) {
                            error_log("Failed to send friend request notification: " . $e->getMessage());
                            // Continue even if notification fails - thefriend request was still created
                        }

                        $message = "Friend request sent successfully.";
                    } else {
                        $message = "Error sending friend request.";
                    }
                } else if ($checkFriendResult->num_rows > 0) {
                    $message = "You are already friends with this user.";
                } else {
                    $message = "A friend request already exists between you and this user.";
                }
                break;
                
            case 'accept':
                // Verify that request exists
                $checkStmt = $conn->prepare("SELECT * FRO
                M friend_requests WHERE sender_id = ? AND receiver_id = ?");
                $checkStmt->bind_param("ii", $friendId, $userId);
                $checkStmt->execute();
                
                if ($checkStmt->get_result()->num_rows > 0) {
                    // Add to friends table (both ways to make querying easier)
                    $conn->begin_transaction();
                    try {
                        $addFriendStmt1 = $conn->prepare("INSERT INTO friends (user_id, friend_id, created_at) VALUES (?, ?, NOW())");
                        $addFriendStmt1->bind_param("ii", $userId, $friendId);
                        $addFriendStmt1->execute();
                        
                        $addFriendStmt2 = $conn->prepare("INSERT INTO friends (user_id, friend_id, created_at) VALUES (?, ?, NOW())");
                        $addFriendStmt2->bind_param("ii", $friendId, $userId);
                        $addFriendStmt2->execute();
                        
                        // Remove the request
                        $deleteReqStmt = $conn->prepare("DELETE FROM friend_requests WHERE sender_id = ? AND receiver_id = ?");
                        $deleteReqStmt->bind_param("ii", $friendId, $userId);
                        $deleteReqStmt->execute();
                        
                        $conn->commit();
                        $message = "Friend request accepted.";
                    } catch (Exception $e) {
                        $conn->rollback();
                        $message = "Error accepting friend request: " . $e->getMessage();
                    }
                } else {
                    $message = "Friend request not found.";
                }
                break;
                
            case 'decline':
                $declineStmt = $conn->prepare("DELETE FROM friend_requests WHERE sender_id = ? AND receiver_id = ?");
                $declineStmt->bind_param("ii", $friendId, $userId);
                if ($declineStmt->execute()) {
                    $message = "Friend request declined.";
                } else {
                    $message = "Error declining friend request.";
                }
                break;
                
            case 'remove':
                $conn->begin_transaction();
                try {
                    $removeStmt1 = $conn->prepare("DELETE FROM friends WHERE user_id = ? AND friend_id = ?");
                    $removeStmt1->bind_param("ii", $userId, $friendId);
                    $removeStmt1->execute();
                    
                    $removeStmt2 = $conn->prepare("DELETE FROM friends WHERE user_id = ? AND friend_id = ?");
                    $removeStmt2->bind_param("ii", $friendId, $userId);
                    $removeStmt2->execute();
                    
                    $conn->commit();
                    $message = "Friend removed successfully.";
                } catch (Exception $e) {
                    $conn->rollback();
                    $message = "Error removing friend: " . $e->getMessage();
                }
                break;
        }
    }
}

// Handle search for users
if (isset($_GET['search']) && !empty($_GET['search'])) {
    $searchTerm = '%' . $_GET['search'] . '%';
    $searchStmt = $conn->prepare("
        SELECT id, username, profile_picture 
        FROM users 
        WHERE id != ? AND username LIKE ? 
        LIMIT 10
    ");
    $searchStmt->bind_param("is", $userId, $searchTerm);
    $searchStmt->execute();
    $searchResults = $searchStmt->get_result();
}

// Get friend requests
$requestsStmt = $conn->prepare("
    SELECT fr.*, u.username, u.profile_picture 
    FROM friend_requests fr 
    JOIN users u ON fr.sender_id = u.id 
    WHERE fr.receiver_id = ? 
    ORDER BY fr.created_at DESC
");
$requestsStmt->bind_param("i", $userId);
$requestsStmt->execute();
$friendRequests = $requestsStmt->get_result();

// Get current friends
$friendsStmt = $conn->prepare("
    SELECT f.*, u.username, u.profile_picture, u.location, u.bio 
    FROM friends f 
    JOIN users u ON f.friend_id = u.id 
    WHERE f.user_id = ? 
    ORDER BY u.username ASC
");
$friendsStmt->bind_param("i", $userId);
$friendsStmt->execute();
$friends = $friendsStmt->get_result();

// Get number of related items (count of their listings, forum posts)
// We'll use this to show some stats for each friend
function getFriendStats($conn, $friendId) {
    $stats = [];
    
    // Get listing count
    $listingStmt = $conn->prepare("SELECT COUNT(*) as count FROM listings WHERE user_id = ?");
    $listingStmt->bind_param("i", $friendId);
    $listingStmt->execute();
    $result = $listingStmt->get_result();
    $stats['listings'] = $result->fetch_assoc()['count'];
    
    // Get forum threads count
    $forumStmt = $conn->prepare("SELECT COUNT(*) as count FROM forum_threads WHERE user_id = ?");
    $forumStmt->bind_param("i", $friendId);
    $forumStmt->execute();
    $result = $forumStmt->get_result();
    $stats['forum_threads'] = $result->fetch_assoc()['count'];
    
    return $stats;
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Friends - Aftermarket Toolbox</title>
    <link rel="stylesheet" href="./assets/css/forum.css">
    <link rel="stylesheet" href="./assets/css/notifications.css">
    <!-- Add Font Awesome for notification icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        .friends-container {
            max-width: 1000px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .search-section {
            background-color: rgba(255, 255, 255, 0.9);
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }
        
        .search-form {
            display: flex;
            gap: 10px;
        }
        
        .search-form input {
            flex: 1;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 16px;
        }
        
        .search-form button {
            background-color: #189dc5;
            color: white;
            border: none;
            border-radius: 5px;
            padding: 10px 15px;
            cursor: pointer;
            font-size: 16px;
            transition: background-color 0.2s;
        }
        
        .search-form button:hover {
            background-color: #157a9e;
        }
        
        .search-results {
            margin-top: 15px;
        }
        
        .user-card {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: white;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 10px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
            transition: transform 0.2s;
        }
        
        .user-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .user-pic {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            object-fit: cover;
        }
        
        .friend-stats {
            color: #666;
            font-size: 0.9em;
            margin-top: 5px;
        }
        
        .friend-action form {
            display: inline;
        }
        
        .btn {
            border: none;
            border-radius: 5px;
            padding: 8px 15px;
            cursor: pointer;
            font-size: 14px;
            transition: background-color 0.2s;
        }
        
        .btn-add {
            background-color: #189dc5;
            color: white;
        }
        
        .btn-add:hover {
            background-color: #157a9e;
        }
        
        .btn-accept {
            background-color: #28a745;
            color: white;
        }
        
        .btn-accept:hover {
            background-color: #218838;
        }
        
        .btn-decline, .btn-remove {
            background-color: #dc3545;
            color: white;
        }
        
        .btn-decline:hover, .btn-remove:hover {
            background-color: #c82333;
        }
        
        .friends-section, .requests-section {
            background-color: rgba(255, 255, 255, 0.9);
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }
        
        .section-title {
            font-size: 24px;
            color: #343a40;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #dee2e6;
        }
        
        .no-items {
            text-align: center;
            padding: 30px;
            color: #6c757d;
        }
        
        .friend-location {
            display: flex;
            align-items: center;
            gap: 5px;
            color: #495057;
            font-size: 0.85em;
            margin-top: 3px;
        }
        
        .location-icon {
            width: 14px;
            height: 14px;
        }
        
        .message-box {
            background-color: #d4edda;
            color: #155724;
            padding: 10px 15px;
            margin-bottom: 20px;
            border-radius: 5px;
            text-align: center;
        }
        
        .error-box {
            background-color: #f8d7da;
            color: #721c24;
        }
        
        /* Username links styling */
        .user-info h3 a {
            color: #189dc5;
            font-weight: bold;
            text-decoration: none;
            transition: all 0.2s ease;
        }
        
        .user-info h3 a:hover {
            color: #0f758e;
            text-decoration: underline;
        }
    </style>
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
    </a>
    <div class="dropdown-content">
      <button class="value" onclick="window.location.href='./marketplace.php?view=explore';">Explore</button>
      <button class="value" onclick="window.location.href='../api/listings/view_listings.php';">View Listings</button>
      <button class="value" onclick="window.location.href='../api/listings/create_listing.php';">List Item</button>
      <button class="value" onclick="window.location.href='./saved_listings.php';">Saved Items</button>
    </div>
  </div>
  
  <!-- Forum dropdown -->
  <div class="profile-container">
    <a href="#" class="link" onclick="toggleDropdown(this, event)">
      <span class="link-icon">
        <img src="./assets/images/forum-icon.svg" alt="Forum">
      </span>
      <span class="link-title">Forum</span>
    </a>
    <div class="dropdown-content">
      <button class="value" onclick="window.location.href='./forum.php?view=threads';">View Threads</button>
      <button class="value" onclick="window.location.href='./forum.php?view=start_thread';">Start Thread</button>
      <button class="value" onclick="window.location.href='./forum.php?view=post_question';">Post Question</button>
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
      <?php if (isset($_SESSION['user_id'])): ?>
        <button class="value" onclick="window.location.href='./profile.php';">
          <img src="./assets/images/profile-icon.svg" alt="Profile">Account
        </button>
        <button class="value" onclick="window.location.href='../api/listings/view_listings.php';">My Listings</button>
        <button class="value" onclick="window.location.href='./saved_listings.php';">Saved Items</button>
        <button class="value" onclick="window.location.href='./friends.php';">Friends</button>
        <button class="value" onclick="window.location.href='./account.php';">Account Settings</button>
        <button class="value" onclick="window.location.href='./logout.php';">Logout</button>
      <?php else: ?>
        <button class="value" onclick="window.location.href='./login.php';">Login</button>
        <button class="value" onclick="window.location.href='./register.php';">Register</button>
      <?php endif; ?>
    </div>
  </div>

  <?php if (isset($_SESSION['user_id'])): ?>
    <a href="./chat.php" class="link">
      <span class="link-icon">
        <img src="./assets/images/chat-icon.svg" alt="Chat">
      </span>
      <span class="link-title">Chat</span>
    </a>
    
    <!-- Notifications Dropdown -->
    <div class="notifications-container">
      <button id="notificationsBtn" class="notification-btn">
        <i class="fas fa-bell"></i>
        <?php 
          // Get notification counts - use existing function if available
          $notificationCounts = [];
          if (function_exists('countUnreadNotifications')) {
              $notificationCounts = countUnreadNotifications($conn, $_SESSION['user_id']);
          } else if (function_exists('getNotificationCounts')) {
              $notificationCounts = getNotificationCounts($_SESSION['user_id'], $conn);
          }
          
          if (!empty($notificationCounts) && isset($notificationCounts['total']) && $notificationCounts['total'] > 0): 
        ?>
        <span id="notification-badge"><?= $notificationCounts['total'] ?></span>
        <?php endif; ?>
      </button>
      <div id="notificationsDropdown" class="notifications-dropdown">
        <div class="notifications-header">
          <h3>Notifications</h3>
          <?php if (!empty($notificationCounts) && isset($notificationCounts['total']) && $notificationCounts['total'] > 0): ?>
          <button id="markAllReadBtn" class="mark-all-read">Mark all as read</button>
          <?php endif; ?>
        </div>
        <div class="notifications-list">
          <div class="no-notifications">Loading notifications...</div>
        </div>
      </div>
    </div>
  <?php endif; ?>
</div>

<div class="friends-container">
    <h1 class="friends-h1">Friends</h1>
    
    <?php if (!empty($message)): ?>
        <div class="message-box <?= strpos($message, 'Error') !== false ? 'error-box' : '' ?>">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>
    
    <div class="search-section">
        <h2 class="section-title">Find Friends</h2>
        <form method="GET" action="friends.php" class="search-form">
            <input type="text" name="search" placeholder="Search for users..." value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
            <button type="submit">Search</button>
        </form>
        
        <?php if (isset($_GET['search']) && !empty($_GET['search'])): ?>
            <div class="search-results">
                <h3>Search Results</h3>
                
                <?php if ($searchResults && $searchResults->num_rows > 0): ?>
                    <?php while ($user = $searchResults->fetch_assoc()): ?>
                        <?php
                            // Check if a friend request already exists
                            $checkRequestStmt = $conn->prepare("
                                SELECT * FROM friend_requests 
                                WHERE (sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?)
                            ");
                            $checkRequestStmt->bind_param("iiii", $userId, $user['id'], $user['id'], $userId);
                            $checkRequestStmt->execute();
                            $requestExists = $checkRequestStmt->get_result()->num_rows > 0;
                            
                            // Check if already friends
                            $checkFriendStmt = $conn->prepare("
                                SELECT * FROM friends 
                                WHERE (user_id = ? AND friend_id = ?) OR (user_id = ? AND friend_id = ?)
                            ");
                            $checkFriendStmt->bind_param("iiii", $userId, $user['id'], $user['id'], $userId);
                            $checkFriendStmt->execute();
                            $alreadyFriends = $checkFriendStmt->get_result()->num_rows > 0;
                            
                            // If receiver has sent request
                            $checkSentToMeStmt = $conn->prepare("
                                SELECT * FROM friend_requests 
                                WHERE sender_id = ? AND receiver_id = ?
                            ");
                            $checkSentToMeStmt->bind_param("ii", $user['id'], $userId);
                            $checkSentToMeStmt->execute();
                            $pendingRequestFromUser = $checkSentToMeStmt->get_result()->num_rows > 0;
                            
                            // If I've sent request
                            $checkSentByMeStmt = $conn->prepare("
                                SELECT * FROM friend_requests 
                                WHERE sender_id = ? AND receiver_id = ?
                            ");
                            $checkSentByMeStmt->bind_param("ii", $userId, $user['id']);
                            $checkSentByMeStmt->execute();
                            $pendingRequestToUser = $checkSentByMeStmt->get_result()->num_rows > 0;
                        ?>
                        <div class="user-card">
                            <div class="user-info">
                                <img src="<?= htmlspecialchars($user['profile_picture'] ?? './assets/images/default-profile.jpg') ?>" alt="<?= htmlspecialchars($user['username']) ?>" class="user-pic">
                                <div>
                                    <h3><a href="profile.php?user_id=<?= $user['id'] ?>"><?= htmlspecialchars($user['username']) ?></a></h3>
                                </div>
                            </div>
                            <div class="friend-action">
                                <?php if ($alreadyFriends): ?>
                                    <form method="POST">
                                        <input type="hidden" name="friend_id" value="<?= $user['id'] ?>">
                                        <input type="hidden" name="action" value="remove">
                                        <button type="submit" class="btn btn-remove">Unfriend</button>
                                    </form>
                                <?php elseif ($pendingRequestFromUser): ?>
                                    <form method="POST" style="display:inline-block;">
                                        <input type="hidden" name="friend_id" value="<?= $user['id'] ?>">
                                        <input type="hidden" name="action" value="accept">
                                        <button type="submit" class="btn btn-accept">Accept Request</button>
                                    </form>
                                    <form method="POST" style="display:inline-block;">
                                        <input type="hidden" name="friend_id" value="<?= $user['id'] ?>">
                                        <input type="hidden" name="action" value="decline">
                                        <button type="submit" class="btn btn-decline">Decline</button>
                                    </form>
                                <?php elseif ($pendingRequestToUser): ?>
                                    <span>Request Sent</span>
                                <?php else: ?>
                                    <form method="POST">
                                        <input type="hidden" name="friend_id" value="<?= $user['id'] ?>">
                                        <input type="hidden" name="action" value="send_request">
                                        <button type="submit" class="btn btn-add">Add Friend</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="no-items">No users found matching your search.</div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
    
    <div class="requests-section">
        <h2 class="section-title">Friend Requests</h2>
        
        <?php if ($friendRequests && $friendRequests->num_rows > 0): ?>
            <?php while ($request = $friendRequests->fetch_assoc()): ?>
                <div class="user-card">
                    <div class="user-info">
                        <img src="<?= htmlspecialchars($request['profile_picture'] ?? './assets/images/default-profile.jpg') ?>" alt="<?= htmlspecialchars($request['username']) ?>" class="user-pic">
                        <div>
                            <h3><a href="profile.php?user_id=<?= $request['sender_id'] ?>"><?= htmlspecialchars($request['username']) ?></a></h3>
                            <div class="friend-stats">Sent request: <?= date('M j, Y', strtotime($request['created_at'])) ?></div>
                        </div>
                    </div>
                    <div class="friend-action">
                        <form method="POST" style="display:inline-block;">
                            <input type="hidden" name="friend_id" value="<?= $request['sender_id'] ?>">
                            <input type="hidden" name="action" value="accept">
                            <button type="submit" class="btn btn-accept">Accept</button>
                        </form>
                        <form method="POST" style="display:inline-block;">
                            <input type="hidden" name="friend_id" value="<?= $request['sender_id'] ?>">
                            <input type="hidden" name="action" value="decline">
                            <button type="submit" class="btn btn-decline">Decline</button>
                        </form>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="no-items">No pending friend requests.</div>
        <?php endif; ?>
    </div>
    
    <div class="friends-section">
        <h2 class="section-title">Your Friends</h2>
        
        <?php if ($friends && $friends->num_rows > 0): ?>
            <?php while ($friend = $friends->fetch_assoc()): ?>
                <?php $stats = getFriendStats($conn, $friend['friend_id']); ?>
                <div class="user-card">
                    <div class="user-info">
                        <img src="<?= htmlspecialchars($friend['profile_picture'] ?? './assets/images/default-profile.jpg') ?>" alt="<?= htmlspecialchars($friend['username']) ?>" class="user-pic">
                        <div>
                            <h3><a href="profile.php?user_id=<?= $friend['friend_id'] ?>"><?= htmlspecialchars($friend['username']) ?></a></h3>
                            <?php if (!empty($friend['location'])): ?>
                                <div class="friend-location">
                                    <img src="./assets/images/location-icon.svg" alt="Location" class="location-icon"> 
                                    <?= htmlspecialchars($friend['location']) ?>
                                </div>
                            <?php endif; ?>
                            <div class="friend-stats">
                                <?= $stats['listings'] ?> listings · <?= $stats['forum_threads'] ?> forum posts · 
                                Friends since <?= date('M j, Y', strtotime($friend['created_at'])) ?>
                            </div>
                        </div>
                    </div>
                    <div class="friend-action">
                        <button class="btn btn-add" onclick="window.location.href='chat.php?chat=<?= $friend['friend_id'] ?>'">
                            Message
                        </button>
                        <form method="POST">
                            <input type="hidden" name="friend_id" value="<?= $friend['friend_id'] ?>">
                            <input type="hidden" name="action" value="remove">
                            <button type="submit" class="btn btn-remove">Remove</button>
                        </form>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="no-items">
                <p>You don't have any friends yet.</p>
                <p>Use the search function above to find and add friends!</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
  const delay = 100; // Delay in milliseconds

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
        
        fetch('/aftermarket_toolkit/public/api/notifications.php', {
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
    
    // Handle notification item clicks
    document.addEventListener('click', function(e) {
      // Mark individual notification as read
      const markReadBtn = e.target.closest('.notification-mark-read');
      if (markReadBtn) {
        e.preventDefault();
        e.stopPropagation();
        
        const notificationItem = markReadBtn.closest('.notification-item');
        const notificationId = notificationItem.dataset.id;
        
        fetch('/aftermarket_toolkit/public/api/notifications.php', {
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
          }
        })
        .catch(error => console.error('Error marking as read:', error));
      }
    });
  }
</script>
</body>
</html>
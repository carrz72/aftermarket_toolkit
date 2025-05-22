<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/image_helper.php'; 

// Define INCLUDED constant for included files
define('INCLUDED', true);
require_once __DIR__ . '/../includes/notification_handler.php';

// Get notification counts if user is logged in
$notificationCounts = [
    'total' => 0,
    'messages' => 0,
    'friend_requests' => 0,
    'forum_responses' => 0
];

if (isset($_SESSION['user_id'])) {
    // Get notification counts
    $userId = $_SESSION['user_id'];
    
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

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Determine if viewing own profile or someone else's
$viewingSelf = true;
$viewedUserId = $_SESSION['user_id'];
$isMyFriend = false;

// If a user_id is provided in the URL, we're viewing someone else's profile
if (isset($_GET['user_id']) && is_numeric($_GET['user_id'])) {
    $requestedUserId = (int)$_GET['user_id'];
    
    // Don't process if trying to view your own profile with the user_id parameter
    if ($requestedUserId !== $_SESSION['user_id']) {
        $viewingSelf = false;
        $viewedUserId = $requestedUserId;
        
        // Check if this user is a friend
        $checkFriendStmt = $conn->prepare("
            SELECT * FROM friends 
            WHERE user_id = ? AND friend_id = ?
        ");
        $checkFriendStmt->bind_param("ii", $_SESSION['user_id'], $viewedUserId);
        $checkFriendStmt->execute();
        $isMyFriend = $checkFriendStmt->get_result()->num_rows > 0;
    }
}

// Get user data
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $viewedUserId);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

// If user doesn't exist, redirect to own profile
if (!$user) {
    header("Location: profile.php");
    exit();
}

// Handle profile update (only for own profile)
$message = '';
if ($viewingSelf && $_SERVER['REQUEST_METHOD'] === 'POST') {    if (isset($_POST['update_profile'])) {
        $bio = trim($_POST['bio']);
        $location = trim($_POST['location']);
        $emailNotifications = isset($_POST['email_notifications']) ? 1 : 0;
        
        // Fix: Use the correct variable $viewedUserId instead of $userId
        $updateStmt = $conn->prepare("UPDATE users SET bio = ?, location = ?, email_notifications = ? WHERE id = ?");
        $updateStmt->bind_param("ssii", $bio, $location, $emailNotifications, $viewedUserId);
        
        if ($updateStmt->execute()) {
            $message = "Profile updated successfully!";
            // Refresh user data
            $stmt->execute();
            $result = $stmt->get_result();
            $user = $result->fetch_assoc();
        } else {
            $message = "Error updating profile: " . $conn->error;
        }
        $updateStmt->close();
    }
    
    // Handle profile picture upload
    if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] == 0) {
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];
        $filename = $_FILES['profile_picture']['name'];
        $filetype = pathinfo($filename, PATHINFO_EXTENSION);
        
        if (in_array(strtolower($filetype), $allowed)) {
            $newFilename = "user_" . $userId . "_" . time() . "." . $filetype;
            $uploadPath = __DIR__ . "/../uploads/" . $newFilename;
            
            if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $uploadPath)) {
                $profilePicPath = "/aftermarket_toolkit/uploads/" . $newFilename;
                
                $picStmt = $conn->prepare("UPDATE users SET profile_picture = ? WHERE id = ?");
                $picStmt->bind_param("si", $profilePicPath, $userId);
                
                if ($picStmt->execute()) {
                    $message = "Profile picture updated successfully!";
                    // Refresh user data
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $user = $result->fetch_assoc();
                } else {
                    $message = "Error updating profile picture: " . $conn->error;
                }
                $picStmt->close();
            } else {
                $message = "Error uploading file.";
            }
        } else {
            $message = "Invalid file type. Please upload JPG, JPEG, PNG or GIF.";
        }
    }
}

// Get user's listings
$listingsStmt = $conn->prepare("SELECT * FROM listings WHERE user_id = ? ORDER BY created_at DESC");
$listingsStmt->bind_param("i", $viewedUserId);
$listingsStmt->execute();
$listingsResult = $listingsStmt->get_result();

// Get user's forum posts
$forumStmt = $conn->prepare("SELECT * FROM forum_threads WHERE user_id = ? ORDER BY created_at DESC");
$forumStmt->bind_param("i", $viewedUserId);
$forumStmt->execute();
$forumResult = $forumStmt->get_result();

// Close statement objects after getting the results
$stmt->close();
$listingsStmt->close();
$forumStmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile - Aftermarket Toolbox</title>
    <link rel="stylesheet" href="./assets/css/profile.css">
     <link rel="stylesheet" href="./assets/css/notifications.css">
    <!-- Add Font Awesome for notification icons -->
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

        <!-- Market dropdown -->
        <div class="profile-container">
            <a href="#" class="link" onclick="toggleDropdown(this, event)">
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

        <!-- Forum dropdown -->        <div class="profile-container">
            <a href="#" class="link" onclick="toggleDropdown(this, event)">
                <span class="link-icon">
                    <img src="./assets/images/forum-icon.svg" alt="Forum">
                    <?php if (isset($_SESSION['user_id']) && isset($notificationCounts['forum_responses']) && $notificationCounts['forum_responses'] > 0): ?>
                        <span class="notification-badge forum"><?= $notificationCounts['forum_responses'] ?></span>
                    <?php endif; ?>
                </span>
                <span class="link-title">Forum</span>
            </a><div class="dropdown-content">
                <button class="value" onclick="window.location.href='./forum.php?view=threads';"><img src="./assets/images/view_threadicon.svg" alt="Forum">View Threads</button>
                <button class="value" onclick="window.location.href='./forum.php?view=start_thread';"><img src="./assets/images/start_threadicon.svg" alt="Start Thread">Start Thread</button>
                <button class="value" onclick="window.location.href='./forum.php?view=post_question';"><img src="./assets/images/start_threadicon.svg" alt="Post Question">Ask Question</button>
            </div>
        </div>
        
        <!-- Profile dropdown -->
        <div class="profile-container">
            <a href="#" class="link active" onclick="toggleDropdown(this, event)">
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
                    <button class="value" onclick="window.location.href='./friends.php';"><img src="./assets/images/friendsicon.svg" alt="Friends">Friends</button>
                    <button class="value" onclick="window.location.href='./logout.php';"><img src="./assets/images/Log_Outicon.svg" alt="Logout">Logout</button>                <?php else: ?>
                    <button class="value" onclick="window.location.href='./login.php';"><img src="./assets/images/Log_Outicon.svg" alt="Login">Login</button>
                    <button class="value" onclick="window.location.href='./register.php';"><img src="./assets/images/friendsicon.svg" alt="Register">Register</button>
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
            </a>            <div class="profile-container">
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
  </div>               <!-- Notifications Dropdown -->
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
                    <div class="notifications-footer">
                        <a href="./notifications.php" class="view-all-link">See All Notifications</a>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <div class="profile-header">
        <div class="profile-banner"></div>
        <div class="profile-info-container">
            <div class="profile-avatar">
                <?php if (!empty($user['profile_picture'])): ?>
                    <img src="<?= htmlspecialchars($user['profile_picture']) ?>" alt="Profile Picture">
                <?php else: ?>
                    <img src="./assets/images/default-profile.jpg" alt="Default Profile">
                <?php endif; ?>
            </div>
            <div class="profile-details">
                <h1><?= htmlspecialchars($user['username']) ?></h1>
                <p class="username">@<?= htmlspecialchars($user['username']) ?></p>
                <?php if (!empty($user['location'])): ?>
                    <p class="location"> <?= htmlspecialchars($user['location']) ?></p>
                <?php endif; ?>
                <p class="member-since">Member since: <?= date('F Y', strtotime($user['created_at'])) ?></p>
                
                <?php if (!$viewingSelf): ?>
                    <div class="profile-actions">
                        <?php if ($isMyFriend): ?>
                            <a href="chat.php?chat=<?= $viewedUserId ?>" class="action-btn message-btn">Send Message</a>
                            <form method="POST" action="friends.php" style="display: inline;">
                                <input type="hidden" name="friend_id" value="<?= $viewedUserId ?>">
                                <input type="hidden" name="action" value="remove">
                                <button type="submit" class="action-btn unfriend-btn">Unfriend</button>
                            </form>
                        <?php else: ?>
                            <form method="POST" action="friends.php" style="display: inline;">
                                <input type="hidden" name="friend_id" value="<?= $viewedUserId ?>">
                                <input type="hidden" name="action" value="send_request">
                                <button type="submit" class="action-btn add-friend-btn">Add Friend</button>
                            </form>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php if (!empty($message)): ?>
        <div class="message <?= strpos($message, 'Error') !== false ? 'error' : 'success' ?>">
            <?= $message ?>
        </div>
    <?php endif; ?>

    <div class="profile-content">                <div class="profile-nav">            <ul>
                <li class="active"><a href="#about" data-section="about">About</a></li>
                <li><a href="#listings" data-section="listings">Listings</a></li>
                <li><a href="#forums" data-section="forums">Forum Posts</a></li>
                <li><a href="#tradesperson" data-section="tradesperson">Tradesperson Profile</a></li>
                <?php if ($viewingSelf): ?>
                <li><a href="#settings" data-section="settings">Settings</a></li>
                <?php endif; ?>
            </ul>
        </div>

        <div class="profile-sections">            <!-- About Section -->
            <section id="about" class="profile-section active">
                <h2>About Me</h2>
                <?php if (!empty($user['location'])): ?>
                    <div class="profile-location">
                        <h3>Location</h3>
                        <p> <?= htmlspecialchars($user['location']) ?></p>
                    </div>
                <?php endif; ?>
                <div class="profile-bio">
                    <h3>Bio</h3>
                    <p><?= !empty($user['bio']) ? htmlspecialchars($user['bio']) : 'No bio yet.' ?></p>
                </div>
            </section>

            <!-- Listings Section -->
            <section id="listings" class="profile-section">
                <?php if ($viewingSelf): ?>
                    <h2>My Listings</h2>
                <?php else: ?>
                    <h2><?= htmlspecialchars($user['username']) ?>'s Listings</h2>
                <?php endif; ?>
                <div class="listings-container">
                    <?php if ($listingsResult && $listingsResult->num_rows > 0): ?>
                        <?php while ($listing = $listingsResult->fetch_assoc()): ?>
                            <div class="listing-card">
                                <div class="listing-image">
                                    <?php if (!empty($listing['image'])): ?>
                                        <img src="<?= htmlspecialchars(getImageUrl($listing['image']) ?: './assets/images/default-image.jpg') ?>" 
                                             alt="<?= htmlspecialchars($listing['title']) ?>">
                                    <?php else: ?>
                                        <img src="./assets/images/default-image.jpg" alt="Default Listing Image">
                                    <?php endif; ?>
                                </div>
                                <div class="listing-details">
                                    <h3><?= htmlspecialchars($listing['title']) ?></h3>
                                    <p class="listing-price">£<?= number_format($listing['price'], 2) ?></p>
                                    <p class="listing-date">Listed: <?= date('M j, Y', strtotime($listing['created_at'])) ?></p>
                                    <div class="listing-actions">
                                        <a href="marketplace.php?listing=<?= $listing['id'] ?>" class="view-btn">View</a>
                                        <a href="../api/listings/edit_listing.php?id=<?= $listing['id'] ?>" class="edit-btn">Edit</a>
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <p class="no-items">You haven't posted any listings yet.</p>
                    <?php endif; ?>
                </div>
            </section>

            <!-- Forums Section -->
            <section id="forums" class="profile-section">
                <h2>My Forum Posts</h2>
                <div class="forums-container">
                    <?php if ($forumResult && $forumResult->num_rows > 0): ?>
                        <?php while ($thread = $forumResult->fetch_assoc()): ?>
                            <div class="forum-post">
                                <h3><?= htmlspecialchars($thread['title']) ?></h3>
                                <div class="post-content"><?= nl2br(htmlspecialchars(substr($thread['body'], 0, 150))) ?>
                                    <?php if (strlen($thread['body']) > 150): ?>...<?php endif; ?>
                                </div>
                                <div class="post-meta">
                                    <span class="post-date">Posted: <?= date('M j, Y', strtotime($thread['created_at'])) ?></span>
                                    <a href="forum.php?thread=<?= $thread['id'] ?>" class="view-post-btn">View Full Post</a>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <p class="no-items">You haven't created any forum posts yet.</p>
                    <?php endif; ?>
                </div>            </section>

            <!-- Tradesperson Profile Section -->
            <section id="tradesperson" class="profile-section">
                <h2>Tradesperson Profile</h2>
                <div class="tradesperson-container">
                    <div class="tradesperson-info">
                        <?php if ($viewingSelf): ?>
                            <p>Manage your skills, certifications, and job history with your tradesperson profile.</p>
                            <div class="tradesperson-actions">
                                <a href="./tradesperson_profile.php" class="btn btn-primary">Go to Tradesperson Profile</a>
                            </div>
                        <?php else: ?>
                            <p>View <?= htmlspecialchars($user['username']) ?>'s skills, certifications, and job history.</p>
                            <div class="tradesperson-actions">
                                <a href="./tradesperson_profile.php?user_id=<?= $viewedUserId ?>" class="btn btn-primary">View Tradesperson Profile</a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </section>
    

            <!-- Settings Section -->
            <section id="settings" class="profile-section">                <h2>Edit Profile</h2>
                <div class="profile-preview">
                    <h3>Current Information</h3>
                    <div class="preview-item">
                        <span class="preview-label">Username:</span>
                        <span class="preview-value"><?= htmlspecialchars($user['username']) ?></span>
                    </div>
                    <div class="preview-item">
                        <span class="preview-label">Location:</span>
                        <span class="preview-value"><?= !empty($user['location']) ? htmlspecialchars($user['location']) : 'Not specified' ?></span>
                    </div>
                    <div class="preview-item">
                        <span class="preview-label">Bio:</span>
                        <span class="preview-value"><?= !empty($user['bio']) ? htmlspecialchars($user['bio']) : 'No bio yet.' ?></span>
                    </div>
                    <div class="preview-item">
                        <span class="preview-label">Member since:</span>
                        <span class="preview-value"><?= date('F Y', strtotime($user['created_at'])) ?></span>
                    </div>
                </div>
                
                
                <form action="profile.php" method="POST" enctype="multipart/form-data">
                    <div class="form-group">
                        <label for="profile_picture">Profile Picture</label>
                        <div class="profile-pic-upload">
                            <?php if (!empty($user['profile_picture'])): ?>
                                <img src="<?= htmlspecialchars($user['profile_picture']) ?>" alt="Current Profile Picture" class="current-pic">
                            <?php else: ?>
                                <img src="./assets/images/default_image.jpg" alt="Default Profile" class="current-pic">
                            <?php endif; ?>
                            <input type="file" name="profile_picture" id="profile_picture">
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="location">Location</label>
                        <input type="text" id="location" name="location" value="<?= htmlspecialchars($user['location'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label for="bio">Bio</label>
                        <textarea id="bio" name="bio" rows="5"><?= htmlspecialchars($user['bio'] ?? '') ?></textarea>
                    </div>
                    <div class="form-group checkbox-group">
                        <label class="checkbox-container">
                            <input type="checkbox" id="email_notifications" name="email_notifications" value="1" <?= (!isset($user['email_notifications']) || $user['email_notifications'] == 1) ? 'checked' : '' ?>>
                            <span class="checkmark"></span>
                            Receive email notifications
                        </label>
                        <p class="help-text">You'll receive emails for forum replies, messages, and other important updates.</p>
                    </div>
                    <button type="submit" name="update_profile" class="submit-btn">Update Profile</button>
                </form>
                
                <div class="password-section">
                    <h3>Change Password</h3>
                    <form action="update_password.php" method="POST">
                        <div class="form-group">
                            <label for="current_password">Current Password</label>
                            <input type="password" id="current_password" name="current_password" required>
                        </div>
                        <div class="form-group">
                            <label for="new_password">New Password</label>
                            <input type="password" id="new_password" name="new_password" required>
                        </div>
                        <div class="form-group">
                            <label for="confirm_password">Confirm New Password</label>
                            <input type="password" id="confirm_password" name="confirm_password" required>
                        </div>
                        <button type="submit" name="update_password" class="submit-btn">Change Password</button>
                    </form>
                </div>
            </section>

            
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

        // Tab switching functionality
        document.querySelectorAll('.profile-nav a').forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                
                // Remove active class from all links and sections
                document.querySelectorAll('.profile-nav li').forEach(item => item.classList.remove('active'));
                document.querySelectorAll('.profile-section').forEach(section => section.classList.remove('active'));
                
                // Add active class to clicked link
                this.parentElement.classList.add('active');
                
                // Show selected section
                const sectionId = this.getAttribute('data-section');
                document.getElementById(sectionId).classList.add('active');
            });
        });

        // Profile picture preview
        document.getElementById('profile_picture').addEventListener('change', function(e) {
            const file = this.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(event) {
                    // Create preview or update existing
                    let preview = document.querySelector('.current-pic');
                    if (!preview) {
                        preview = document.createElement('img');
                        preview.classList.add('current-pic');
                        document.querySelector('.profile-pic-upload').prepend(preview);
                    }
                    preview.src = event.target.result;
                };
                reader.readAsDataURL(file);
            }
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
            fetch('./api/notifications.php')
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok: ' + response.status);
                    }
                    return response.json();
                })
                .then(data => {
                    console.log('Notification data:', data);
                    if (data.success) {
                        // Make sure to update both the dropdown and any notification badges in the navigation
                        updateNotificationBadge(data.counts.total || 0);
                        updateNotificationDropdown(data.notifications || []);
                        
                        // Also update the forum, message, and friend request badges if they exist
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
          // Update specific notification badge (forum, messages, friends)
        function updateSpecificBadge(type, count) {
            // Map notification type to badge class
            let badgeClass;
            switch(type) {
                case 'forum_responses':
                case 'forum_response':
                    badgeClass = 'forum';
                    break;
                case 'messages':
                case 'message':
                    badgeClass = 'messages';
                    break;
                case 'friend_requests':
                case 'friend_request':
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
<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/image_helper.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Get user data
$userId = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

// Handle form submissions
$message = '';
$messageType = '';

// Update account settings handler - around line 22
if (isset($_POST['update_account'])) {
    $email = trim($_POST['email']);
    $location = trim($_POST['location']); // Add location field processing
    $notificationPrefs = isset($_POST['notification_preferences']) ? 1 : 0;
    
    // Update SQL query to include location but not display_name
    $updateStmt = $conn->prepare("UPDATE users SET email = ?, location = ?, notification_preferences = ? WHERE id = ?");
    $updateStmt->bind_param("ssii", $email, $location, $notificationPrefs, $userId);
    
    if ($updateStmt->execute()) {
        $message = "Account settings updated successfully!";
        $messageType = "success";
        
        // Refresh user data
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
    } else {
        $message = "Error updating account settings: " . $conn->error;
        $messageType = "error";
    }
}

// Get account statistics
// Count listings
$listingsStmt = $conn->prepare("SELECT COUNT(*) AS total_listings FROM listings WHERE user_id = ?");
$listingsStmt->bind_param("i", $userId);
$listingsStmt->execute();
$listingsResult = $listingsStmt->get_result()->fetch_assoc();
$totalListings = $listingsResult['total_listings'];

// Count saved listings
$savedStmt = $conn->prepare("SELECT COUNT(*) AS total_saved FROM saved_listings WHERE user_id = ?");
$savedStmt->bind_param("i", $userId);
$savedStmt->execute();
$savedResult = $savedStmt->get_result()->fetch_assoc();
$totalSaved = $savedResult['total_saved'];

// Count forum posts
$forumStmt = $conn->prepare("SELECT COUNT(*) AS total_threads FROM forum_threads WHERE user_id = ?");
$forumStmt->bind_param("i", $userId);
$forumStmt->execute();
$forumResult = $forumStmt->get_result()->fetch_assoc();
$totalThreads = $forumResult['total_threads'];

// Count forum replies
$repliesStmt = $conn->prepare("SELECT COUNT(*) AS total_replies FROM forum_replies WHERE user_id = ?");
$repliesStmt->bind_param("i", $userId);
$repliesStmt->execute();
$repliesResult = $repliesStmt->get_result()->fetch_assoc();
$totalReplies = $repliesResult['total_replies'];

// Get recent activities (last 5 listings) - updated to include images
$recentStmt = $conn->prepare("
    SELECT 'listing' as type, title, created_at, id, image, additional_images
    FROM listings 
    WHERE user_id = ? 
    ORDER BY created_at DESC 
    LIMIT 5
");
$recentStmt->bind_param("i", $userId);
$recentStmt->execute();
$recentActivities = $recentStmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Settings - Aftermarket Toolbox</title>
    <link rel="stylesheet" href="./assets/css/account.css">
</head>
<body>
    <div class="menu">
        <a href="../index.php" class="link">
            <span class="link-icon">
                <img src="./assets/images/home-icon.svg" alt="Home">
            </span>
            <span class="link-title">Home</span>
        </a>

        <a href="./marketplace.php" class="link">
            <span class="link-icon">
                <img src="./assets/images/market.svg" alt="Market">
            </span>
            <span class="link-title">Market</span>
        </a>
        
        <a href="./forum.php" class="link">
            <span class="link-icon">
                <img src="./assets/images/forum-icon.svg" alt="Forum">
            </span>
            <span class="link-title">Forum</span>
        </a>
        
        <div class="profile-container">
            <a href="#" class="link active">
                <span class="link-icon">
                    <img src="./assets/images/profile-icon.svg" alt="Profile">
                </span>
                <span class="link-title">Profile</span>
            </a>
        </div>
        
        <?php if (isset($_SESSION['user_id'])): ?>
            <a href="./chat.php" class="link">
                <span class="link-icon">
                    <img src="./assets/images/chat-icon.svg" alt="Chat">
                </span>
                <span class="link-title">Chat</span>
            </a>
        <?php endif; ?>
    </div>

    <div class="account-container">
        <div class="account-sidebar">
            <div class="user-profile">
                <img src="<?= htmlspecialchars(getImageUrl($user['profile_picture']) ?: './assets/images/default-profile.jpg') ?>" 
                     alt="Profile Picture" 
                     class="profile-picture">
                <h3><?= htmlspecialchars($user['username']) ?></h3>
                <p class="member-since">Member since <?= date('M Y', strtotime($user['created_at'])) ?></p>
            </div>
            
            <nav class="account-nav">
                <ul>
                    <li><a href="#account-overview" class="active" data-section="account-overview">Account Overview</a></li>
                    <li><a href="#account-settings" data-section="account-settings">Account Settings</a></li>
                    <li><a href="#security" data-section="security">Security</a></li>
                    <li><a href="#notifications" data-section="notifications">Notifications</a></li>
                    <li><a href="./profile.php">View Public Profile</a></li>
                </ul>
            </nav>
        </div>
        
        <div class="account-content">
            <?php if (!empty($message)): ?>
                <div class="alert alert-<?= $messageType ?>">
                    <?= htmlspecialchars($message) ?>
                </div>
            <?php endif; ?>
            
            <section id="account-overview" class="account-section active">
                <h2>Account Overview</h2>
                
                <div class="stats-cards">
                    <div class="stat-card">
                        <div class="stat-icon listings-icon">
                            <svg width="24" height="24" viewBox="0 0 24 24">
                                <path d="M19 5v14H5V5h14m0-2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-4.86 8.86l-3 3.87L9 13.14 6 17h12l-3.86-5.14z"/>
                            </svg>
                        </div>
                        <div class="stat-details">
                            <h3><?= $totalListings ?></h3>
                            <p>Listings</p>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon saved-icon">
                            <svg width="24" height="24" viewBox="0 0 24 24">
                                <path d="M17 3H7c-1.1 0-2 .9-2 2v16l7-3 7 3V5c0-1.1-.9-2-2-2z"/>
                            </svg>
                        </div>
                        <div class="stat-details">
                            <h3><?= $totalSaved ?></h3>
                            <p>Saved</p>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon forum-icon">
                            <svg width="24" height="24" viewBox="0 0 24 24">
                                <path d="M21 6h-2v9H6v2c0 .55.45 1 1 1h11l4 4V7c0-.55-.45-1-1-1zm-4 6V3c0-.55-.45-1-1-1H3c-.55 0-1 .45-1 1v14l4-4h10c.55 0 1-.45 1-1z"/>
                            </svg>
                        </div>
                        <div class="stat-details">
                            <h3><?= $totalThreads + $totalReplies ?></h3>
                            <p>Forum Posts</p>
                        </div>
                    </div>
                </div>
                
                <div class="recent-activity">
                    <h3>Recent Activity</h3>
                    <?php if ($recentActivities->num_rows > 0): ?>
                        <ul class="activity-list">
                            <?php while ($activity = $recentActivities->fetch_assoc()): ?>
                                <li>
                                    <div class="activity-icon">
                                        <?php if (!empty($activity['image'])): ?>
                                            <!-- REMOVE the manual path manipulation and let getImageUrl() handle it -->
                                            <img src="<?= htmlspecialchars(getImageUrl($activity['image'])) ?>"
                                                 alt="<?= htmlspecialchars($activity['title']) ?>"
                                                 class="activity-thumbnail"
                                                 onerror="console.log('Image failed to load:', this.src)">
                                        <?php else: ?>
                                            <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                                                <path d="M19 5v14H5V5h14m0-2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0-2-2-2-2V5c0-1.1-.9-2-2-2z"/>
                                            </svg>
                                        <?php endif; ?>
                                    </div>
                                    <div class="activity-details">
                                        <p>You listed <a href="../api/listings/listing.php?id=<?= $activity['id'] ?>"><?= htmlspecialchars($activity['title']) ?></a></p>
                                        <span class="activity-date"><?= date('M j, Y', strtotime($activity['created_at'])) ?></span>
                                    </div>
                                </li>
                            <?php endwhile; ?>
                        </ul>
                    <?php else: ?>
                        <p class="no-activity">No recent activity found.</p>
                    <?php endif; ?>
                </div>
            </section>
            
            <section id="account-settings" class="account-section">
                <h2>Account Settings</h2>
                <form method="POST" action="" class="settings-form">
                    <div class="form-group">
                        <label for="username">Username</label>
                        <input type="text" id="username" value="<?= htmlspecialchars($user['username']) ?>" disabled>
                        <small>Username cannot be changed.</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="email">Email Address</label>
                        <input type="email" id="email" name="email" value="<?= htmlspecialchars($user['email']) ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="location">Location</label>
                        <input type="text" id="location" name="location" value="<?= htmlspecialchars($user['location'] ?? '') ?>">
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" name="update_account" class="btn btn-primary">Save Changes</button>
                    </div>
                </form>
            </section>
            
            <section id="security" class="account-section">
                <h2>Security</h2>
                
                <div class="password-section">
                    <h3>Change Password</h3>
                    <form action="update_password.php" method="POST" class="settings-form">
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
                        
                        <div class="form-actions">
                            <button type="submit" name="update_password" class="btn btn-primary">Change Password</button>
                        </div>
                    </form>
                </div>
                
                <div class="session-section">
                    <h3>Active Sessions</h3>
                    <div class="session-info">
                        <div class="session-device">
                            <svg width="24" height="24" viewBox="0 0 24 24">
                                <path d="M21 3H3c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h18c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 16H3V5h18v14z"/>
                            </svg>
                            <div>
                                <p><strong>Current Session</strong></p>
                                <p>Browser: <?= htmlspecialchars($_SERVER['HTTP_USER_AGENT']) ?></p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="form-actions">
                    <form action="logout.php" method="POST">
                        <button type="submit" class="btn btn-danger">Sign Out From All Devices</button>
                    </form>
                </div>
            </section>
            
            <section id="notifications" class="account-section">
                <h2>Notifications</h2>
                <form method="POST" action="" class="settings-form">
                    <div class="form-group checkbox-group">
                        <input type="checkbox" id="notification_preferences" name="notification_preferences" <?= ($user['notification_preferences'] ?? 0) ? 'checked' : '' ?>>
                        <label for="notification_preferences">Receive email notifications about listing activities</label>
                    </div>
                    
                    <div class="form-group checkbox-group">
                        <input type="checkbox" id="forum_notifications" name="forum_notifications" <?= ($user['forum_notifications'] ?? 0) ? 'checked' : '' ?>>
                        <label for="forum_notifications">Receive email notifications about forum replies</label>
                    </div>
                    
                    <div class="form-group checkbox-group">
                        <input type="checkbox" id="message_notifications" name="message_notifications" <?= ($user['message_notifications'] ?? 0) ? 'checked' : '' ?>>
                        <label for="message_notifications">Receive email notifications about new messages</label>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" name="update_notifications" class="btn btn-primary">Save Preferences</button>
                    </div>
                </form>
            </section>
        </div>
    </div>

    <script>
        // Tab switching functionality
        document.querySelectorAll('.account-nav a').forEach(link => {
            if (link.getAttribute('href').startsWith('#')) {
                link.addEventListener('click', function(e) {
                    e.preventDefault();
                    
                    // Remove active class from all links
                    document.querySelectorAll('.account-nav a').forEach(item => {
                        item.classList.remove('active');
                    });
                    
                    // Add active class to clicked link
                    this.classList.add('active');
                    
                    // Hide all sections
                    document.querySelectorAll('.account-section').forEach(section => {
                        section.classList.remove('active');
                    });
                    
                    // Show selected section
                    const sectionId = this.getAttribute('data-section');
                    document.getElementById(sectionId).classList.add('active');
                });
            }
        });
    </script>
</body>
</html>
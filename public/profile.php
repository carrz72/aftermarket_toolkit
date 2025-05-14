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

// Handle profile update
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_profile'])) {
        $bio = trim($_POST['bio']);
        $location = trim($_POST['location']);
        
        $updateStmt = $conn->prepare("UPDATE users SET bio = ?, location = ? WHERE id = ?");
        $updateStmt->bind_param("ssi", $bio, $location, $userId);
        
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
$listingsStmt->bind_param("i", $userId);
$listingsStmt->execute();
$listingsResult = $listingsStmt->get_result();

// Get user's forum posts
$forumStmt = $conn->prepare("SELECT * FROM forum_threads WHERE user_id = ? ORDER BY created_at DESC");
$forumStmt->bind_param("i", $userId);
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
            </a>
            <div class="dropdown-content">
                <button class="value" onclick="window.location.href='./marketplace.php?view=explore';">Explore</button>
                <button class="value" onclick="window.location.href='../api/listings/view_listings.php';">My Listings</button>
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
                <button class="value" onclick="window.location.href='./forum.php?view=post_question';">Ask Question</button>
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
                    </button>
                    <button class="value" onclick="window.location.href='../api/listings/view_listings.php';">My Listings</button>
                    <button class="value" onclick="window.location.href='./saved_listings.php';">Saved Items</button>
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
        <?php endif; ?>
    </div>

    <div class="profile-header">
        <div class="profile-banner"></div>
        <div class="profile-info-container">
            <div class="profile-avatar">
                <?php if (!empty($user['profile_picture'])): ?>
                    <img src="<?= htmlspecialchars($user['profile_picture']) ?>" alt="Profile Picture">
                <?php else: ?>
                    <img src="./assets/images/default_profile.jpg" alt="Default Profile">
                <?php endif; ?>
            </div>
            <div class="profile-details">
                <h1><?= htmlspecialchars($user['username']) ?></h1>
                <p class="username">@<?= htmlspecialchars($user['username']) ?></p>
                <?php if (!empty($user['location'])): ?>
                    <p class="location"><img src="./assets/images/location-icon.svg" alt="Location"> <?= htmlspecialchars($user['location']) ?></p>
                <?php endif; ?>
                <p class="member-since">Member since: <?= date('F Y', strtotime($user['created_at'])) ?></p>
            </div>
        </div>
    </div>

    <?php if (!empty($message)): ?>
        <div class="message <?= strpos($message, 'Error') !== false ? 'error' : 'success' ?>">
            <?= $message ?>
        </div>
    <?php endif; ?>

    <div class="profile-content">
        <div class="profile-nav">
            <ul>
                <li class="active"><a href="#about" data-section="about">About</a></li>
                <li><a href="#listings" data-section="listings">Listings</a></li>
                <li><a href="#forums" data-section="forums">Forum Posts</a></li>
                <li><a href="#settings" data-section="settings">Settings</a></li>
            </ul>
        </div>

        <div class="profile-sections">
            <!-- About Section -->
            <section id="about" class="profile-section active">
                <h2>About Me</h2>
                <p><?= !empty($user['bio']) ? htmlspecialchars($user['bio']) : 'No bio yet.' ?></p>
            </section>

            <!-- Listings Section -->
            <section id="listings" class="profile-section">
                <h2>My Listings</h2>
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
                </div>
            </section>

            <!-- Settings Section -->
            <section id="settings" class="profile-section">
                <h2>Edit Profile</h2>
                <form action="profile.php" method="POST" enctype="multipart/form-data">
                    <div class="form-group">
                        <label for="profile_picture">Profile Picture</label>
                        <div class="profile-pic-upload">
                            <?php if (!empty($user['profile_picture'])): ?>
                                <img src="<?= htmlspecialchars($user['profile_picture']) ?>" alt="Current Profile Picture" class="current-pic">
                            <?php else: ?>
                                <img src="./assets/images/default-profile.jpg" alt="Default Profile" class="current-pic">
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
    </script>
</body>
</html>
<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/image_helper.php'; // Add this line
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../public/login.php');
    exit();
}

$userId = $_SESSION['user_id'];
$errors = [];
$success = false;

// Get categories for dropdown
$categoryQuery = "SELECT DISTINCT category FROM listings ORDER BY category ASC";
$categoryResult = $conn->query($categoryQuery);
$categories = [];
while ($row = $categoryResult->fetch_assoc()) {
    if (!empty($row['category'])) {
        $categories[] = $row['category'];
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize and validate inputs
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $price = trim($_POST['price'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $condition = trim($_POST['condition'] ?? '');
    
    // Handle custom category if "other" is selected
    if ($category === 'other' && !empty($_POST['otherCategory'])) {
        $category = trim($_POST['otherCategory']);
    }
    
    // Validation
    if (empty($title)) {
        $errors[] = 'Title is required';
    }
    
    if (empty($description)) {
        $errors[] = 'Description is required';
    }
    
    if (empty($price) || !is_numeric($price) || $price <= 0) {
        $errors[] = 'Price must be a positive number';
    }
    
    if (empty($category)) {
        $errors[] = 'Category is required';
    }

    // Handle main image upload - UPDATED
    $mainImage = ''; // Path to main image
    if (isset($_FILES['image']) && $_FILES['image']['error'] === 0) {
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];
        $filename = $_FILES['image']['name'];
        $filetype = pathinfo($filename, PATHINFO_EXTENSION);
        
        // Verify file extension
        if (!in_array(strtolower($filetype), $allowed)) {
            $errors[] = 'Only JPG, JPEG, PNG, and GIF files are allowed';
        } else {
            // Create unique filename
            $newFilename = uniqid() . '.' . $filetype;
            
            // Get upload directory using helper function
            $uploadDir = getUploadDirectory();
            $destination = $uploadDir . $newFilename;
            
            // Move uploaded file
            if (move_uploaded_file($_FILES['image']['tmp_name'], $destination)) {
                // Store standardized path in database
                $mainImage = getUploadedImagePath($newFilename);
            } else {
                $errors[] = 'Error uploading image';
            }
        }
    }

    // If no errors, save listing to database
    if (empty($errors)) {
        // Begin transaction
        $conn->begin_transaction();
        try {
            // Insert main listing details
            $insertQuery = "INSERT INTO listings (user_id, title, description, price, image, category, `condition`, created_at) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
            $stmt = $conn->prepare($insertQuery);
            $stmt->bind_param('issdsss', $userId, $title, $description, $price, $mainImage, $category, $condition);
            $stmt->execute();
            
            $listingId = $conn->insert_id;
            
            // Process additional images - UPDATED
            if (isset($_FILES['additional_images']) && !empty($_FILES['additional_images']['name'][0])) {
                for ($i = 0; $i < count($_FILES['additional_images']['name']); $i++) {
                    if ($_FILES['additional_images']['error'][$i] === 0) {
                        $filename = $_FILES['additional_images']['name'][$i];
                        $filetype = pathinfo($filename, PATHINFO_EXTENSION);
                        
                        // Verify file extension
                        if (in_array(strtolower($filetype), $allowed)) {
                            $newFilename = uniqid() . '_' . $i . '.' . $filetype;
                            $uploadDir = getUploadDirectory();
                            $destination = $uploadDir . $newFilename;
                            
                            if (move_uploaded_file($_FILES['additional_images']['tmp_name'][$i], $destination)) {
                                // Store standardized path in database
                                $additionalImage = getUploadedImagePath($newFilename);
                                
                                // Insert additional image
                                $imageQuery = "INSERT INTO listing_images (listing_id, image_path, display_order) VALUES (?, ?, ?)";
                                $imageStmt = $conn->prepare($imageQuery);
                                $displayOrder = $i + 1; // Start from 1 since 0 is main image
                                $imageStmt->bind_param('isi', $listingId, $additionalImage, $displayOrder);
                                $imageStmt->execute();
                            }
                        }
                    }
                }
            }
            
            // Commit transaction
            $conn->commit();
            $success = true;
            // Redirect after successful creation
            header('Location: view_listings.php');
            exit();
        } catch (Exception $e) {
            // Roll back transaction on error
            $conn->rollback();
            $errors[] = 'Database error: ' . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Listing - Aftermarket Toolbox</title>    
    <link rel="stylesheet" href="../../public/assets/css/create_listing.css">
    <link rel="stylesheet" href="../../public/assets/css/notifications.css">
    <!-- Add Font Awesome for notification icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        .image-preview {
            max-width: 150px;
            max-height: 150px;
            width: 150px;
            height: 150px;
            object-fit: cover;
            border: 1px solid #ddd;
            border-radius: 4px;
            margin: 5px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            transition: transform 0.2s ease;
        }

        .image-preview:hover {
            transform: scale(1.05);
        }

        .image-preview-container {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 10px;
        }

        #mainImagePreview {
            max-width: 300px;
            max-height: 200px;
            object-fit: contain;
            border: 1px solid #ddd;
            border-radius: 4px;
            margin-top: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
    </style>
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
            </a>            <div class="dropdown-content">
                <button class="value" onclick="window.location.href='../../public/marketplace.php?view=explore';"><img src="../../public/assets/images/exploreicon.svg" alt="Explore">Explore</button>
                <button class="value" onclick="window.location.href='../listings/view_listings.php';"><img src="../../public/assets/images/view_listingicon.svg" alt="View Listings">My Listings</button>
                <button class="value" onclick="window.location.href='../listings/create_listing.php';"><img src="../../public/assets/images/list_itemicon.svg" alt="Create Listing">List Item</button>
                <button class="value" onclick="window.location.href='../../public/saved_listings.php';"><img src="../../public/assets/images/savedicons.svg" alt="Saved">Saved Items</button>
            </div>
        </div>
        
        <!-- Forum dropdown -->
        <div class="profile-container">
            <a href="#" class="link" onclick="toggleDropdown(this, event)">
                <span class="link-icon">
                    <img src="../../public/assets/images/forum-icon.svg" alt="Forum">
                </span>
                <span class="link-title">Forum</span>
            </a>            <div class="dropdown-content">
                <button class="value" onclick="window.location.href='../../public/forum.php?view=threads';"><img src="../../public/assets/images/view_threadicon.svg" alt="Forum">View Threads</button>
                <button class="value" onclick="window.location.href='../../public/forum.php?view=start_thread';"><img src="../../public/assets/images/start_threadicon.svg" alt="Start Thread">Start Thread</button>
                <button class="value" onclick="window.location.href='../../public/forum.php?view=post_question';"><img src="../../public/assets/images/start_threadicon.svg" alt="Post Question">Ask Question</button>
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
            <div class="dropdown-content">                <?php if (isset($_SESSION['user_id'])): ?>
                    <button class="value" onclick="window.location.href='../../public/profile.php';">
                        <img src="../../public/assets/images/profile-icon.svg" alt="Profile">Account
                    </button>
                    <button class="value" onclick="window.location.href='../listings/view_listings.php';"><img src="../../public/assets/images/mylistingicon.svg" alt="Market">My Listings</button>
                    <button class="value" onclick="window.location.href='../../public/saved_listings.php';"><img src="../../public/assets/images/savedicons.svg" alt="Saved">Saved Items</button>
                    <button class="value" onclick="window.location.href='../../public/friends.php';"><img src="../../public/assets/images/profile-icon.svg" alt="Account">Friends</button>
                    <button class="value" onclick="window.location.href='../../public/logout.php';"><img src="../../public/assets/images/Log_Outicon.svg" alt="Logout">Logout</button>
                <?php else: ?>
                    <button class="value" onclick="window.location.href='../../public/login.php';">Login</button>
                    <button class="value" onclick="window.location.href='../../public/register.php';">Register</button>
                <?php endif; ?>
            </div>
        </div>        <?php if (isset($_SESSION['user_id'])): ?>
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
                    <?php 
                    // Get notification counts if the function exists
                    $notificationCount = 0;
                    if (function_exists('countUnreadNotifications')) {
                        $counts = countUnreadNotifications($conn, $_SESSION['user_id']);
                        $notificationCount = $counts['total'];
                    }
                    if ($notificationCount > 0): 
                    ?>
                    <span id="notification-badge"><?= $notificationCount ?></span>
                    <?php endif; ?>
                </button>
                <div id="notificationsDropdown" class="notifications-dropdown">
                    <div class="notifications-header">
                        <h3>Notifications</h3>
                        <?php if ($notificationCount > 0): ?>
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

    <div class="create-listing-container">
        <h1>Create a New Listing</h1>
        
        <?php if (!empty($errors)): ?>
            <div class="error-messages">
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?= htmlspecialchars($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="success-message">
                Listing created successfully!
            </div>
        <?php endif; ?>
        
        <form method="POST" enctype="multipart/form-data">
            <div class="form-group">
                <label for="title">Title*</label>
                <input type="text" id="title" name="title" required>
            </div>
            
            <div class="form-group">
                <label for="description">Description*</label>
                <textarea id="description" name="description" rows="6" required></textarea>
            </div>
            
            <div class="form-group">
                <label for="price">Price (£)*</label>
                <input type="number" id="price" name="price" step="0.01" required min="0.01">
            </div>
            
            <div class="form-group">
                <label for="category">Category*</label>
                <select id="category" name="category" required>
                    <option value="">Select a category</option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?= htmlspecialchars($category) ?>"><?= htmlspecialchars($category) ?></option>
                    <?php endforeach; ?>
                    <option value="other">Other</option>
                </select>
                <div id="otherCategoryContainer" style="display:none">
                    <input type="text" id="otherCategory" name="otherCategory" placeholder="Specify category">
                </div>
            </div>
            
            <div class="form-group">
                <label for="condition">Condition*</label>
                <select id="condition" name="condition" required>
                    <option value="">Select condition</option>
                    <option value="New">New</option>
                    <option value="Like New">Like New</option>
                    <option value="Good">Good</option>
                    <option value="Fair">Fair</option>
                    <option value="Poor">Poor</option>
                </select>
            </div>
            
            <!-- First get additional images -->
            <?php
            $additionalImagesQuery = "SELECT id, image_path FROM listing_images WHERE listing_id = ? ORDER BY display_order ASC";
            $additionalImagesStmt = $conn->prepare($additionalImagesQuery);
            $additionalImagesStmt->bind_param('i', $listingId);
            $additionalImagesStmt->execute();
            $additionalImagesResult = $additionalImagesStmt->get_result();
            $additionalImages = [];
            while ($img = $additionalImagesResult->fetch_assoc()) {
                $additionalImages[] = $img;
            }
            ?>

            <div class="form-group">
                <label for="image">Main Image</label>
                <input type="file" id="image" name="image" class="form-control" accept="image/*">
                <img id="mainImagePreview" style="display:none; max-width: 300px; margin-top: 10px;" class="image-preview">
            </div>

            <div class="form-group">
                <label>Additional Images</label>
                <div class="additional-images-container">
                    <?php foreach ($additionalImages as $img): ?>
                        <div class="additional-image">
                            <?php
                            $imgPath = $img['image_path'];
                            if (strpos($imgPath, '/') === 0) {
                                $displayPath = $imgPath;
                            } else if (strpos($imgPath, './assets/') === 0) {
                                $displayPath = '../../public/' . str_replace('./assets/', 'assets/', $imgPath);
                            } else {
                                $displayPath = "../../public/{$imgPath}";
                            }
                            ?>
                            <img src="<?= htmlspecialchars($displayPath) ?>" alt="Additional Image" class="image-preview">
                            <label><input type="checkbox" name="remove_images[]" value="<?= $img['id'] ?>"> Remove</label>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <label for="additional_images">Add More Images</label>
                <input type="file" id="additional_images" name="additional_images[]" multiple class="form-control" accept="image/*">
                <div id="additionalImagesPreview" class="image-preview-container"></div>
            </div>
            
            <div class="form-buttons">
                <button type="submit" class="submit-btn">Create Listing</button>
                <a href="view_listings.php" class="cancel-btn">Cancel</a>
            </div>
        </form>
    </div>

    <script>
        // Preview image before upload
        document.getElementById('image').addEventListener('change', function(e) {
            const preview = document.getElementById('mainImagePreview');
            const file = e.target.files[0];
            const reader = new FileReader();
            
            reader.onload = function(e) {
                preview.src = e.target.result;
                preview.style.display = 'block';
                preview.style.maxWidth = '300px';
                preview.style.maxHeight = '200px';
                preview.style.objectFit = 'contain';
                preview.style.border = '1px solid #ddd';
                preview.style.borderRadius = '4px';
            }
            if (file) {
                reader.readAsDataURL(file);
            }
        });
        
        // Handle "Other" category option
        document.getElementById('category').addEventListener('change', function(e) {
            const otherContainer = document.getElementById('otherCategoryContainer');
            if (e.target.value === 'other') {
                otherContainer.style.display = 'block';
            } else {
                otherContainer.style.display = 'none';
            }
        });

        document.getElementById('additional_images').addEventListener('change', function(e) {
            const previewContainer = document.getElementById('additionalImagesPreview');
            previewContainer.innerHTML = '';
            
            Array.from(this.files).forEach(file => {
                const reader = new FileReader();
                const preview = document.createElement('img');
                preview.className = 'image-preview';
                
                // Add specific size constraints
                preview.style.width = '150px';
                preview.style.height = '150px';
                preview.style.objectFit = 'cover';
                preview.style.margin = '5px';
                preview.style.border = '1px solid #ddd';
                preview.style.borderRadius = '4px';
                
                reader.onload = function(e) {
                    preview.src = e.target.result;
                }
                
                if (file) {
                    reader.readAsDataURL(file);
                    previewContainer.appendChild(preview);
                }
            });
        });
        
        // Dropdown menu functionality
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
                }            });
        });
        
        // Initialize notification system
        if (document.getElementById('notificationsBtn')) {
            initNotificationSystem();
            // Initial fetch
            fetchNotifications();
            // Poll for notifications every 60 seconds
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
                html += '<div class="notification-item show-all">';
                html += `<a href="../../public/notifications.php">View all notifications</a>`;
                html += '</div>';
            }
            
            list.innerHTML = html;
        }
        
        // Get the appropriate link for a notification
        function getNotificationLink(type, relatedId) {
            switch(type) {
                case 'friend_request':
                    return '../../public/friends.php';
                case 'message':
                    return relatedId ? `../../public/chat.php?chat=${relatedId}` : '../../public/chat.php';
                case 'forum_response':
                    return relatedId ? `../../public/forum.php?thread=${relatedId}` : '../../public/forum.php';
                case 'listing_comment':
                    return relatedId ? `../../public/marketplace.php?listing=${relatedId}` : '../../public/marketplace.php';
                default:
                    return '../../public/notifications.php';
            }
        }
        
        // Format timestamp as time ago text
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
                // Mark individual notification as read
                const markReadBtn = e.target.closest('.notification-mark-read');
                if (markReadBtn) {
                    e.preventDefault();
                    e.stopPropagation();
                    
                    const notificationItem = markReadBtn.closest('.notification-item');
                    const notificationId = notificationItem.dataset.id;
                    
                    fetch('../../public/api/notifications.php', {
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
                        
                        fetch('../../public/api/notifications.php', {
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
        }
    </script>
</body>
</html>
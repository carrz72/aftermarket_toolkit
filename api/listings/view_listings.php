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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
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
                    <button class="value" onclick="window.location.href='../../public/account.php';">Account Settings</button>
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
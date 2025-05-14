<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/image_helper.php';
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ./login.php');
    exit();
}

$userId = $_SESSION['user_id'];

// Handle removal of saved listing
if (isset($_POST['remove']) && isset($_POST['listing_id'])) {
    $listingId = (int)$_POST['listing_id'];
    
    $removeQuery = "DELETE FROM saved_listings WHERE user_id = ? AND listing_id = ?";
    $removeStmt = $conn->prepare($removeQuery);
    $removeStmt->bind_param('ii', $userId, $listingId);
    $removeStmt->execute();
    
    // Redirect to prevent form resubmission
    header('Location: ./saved_listings.php');
    exit();
}

// Fetch saved listings with all details
$query = "
    SELECT l.*, u.username, sl.saved_at  
    FROM saved_listings sl
    JOIN listings l ON sl.listing_id = l.id
    JOIN users u ON l.user_id = u.id
    WHERE sl.user_id = ?
    ORDER BY sl.saved_at DESC
";
$stmt = $conn->prepare($query);
$stmt->bind_param('i', $userId);
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Saved Items - Aftermarket Toolbox</title>
    <link rel="stylesheet" href="./assets/css/saved_items.css">
</head>
<body>
    <!-- Menu bar with consistent styling and functionality -->
    <div class="menu">
        <a href="../index.php" class="link">
            <span class="link-icon">
                <img src="./assets/images/home-icon.svg" alt="Home">
            </span>
            <span class="link-title">Home</span>
        </a>

        <!-- Market with dropdown -->
        <div class="profile-container">
            <a href="#" class="link active" onclick="toggleDropdown(this, event)">
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
            <a href="#" class="link" onclick="toggleDropdown(this, event)">
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

    <div class="saved-items-container">
        <h1 class="saved-header">Your Saved Items</h1>
        
        <div class="card-container">
            <?php if ($result && $result->num_rows > 0): ?>
                <?php while ($row = $result->fetch_assoc()): ?>
                    <div class="card">
                        <a href="../api/listings/listing.php?id=<?= $row['id'] ?>" class="card-link">
                            <div class="card-header">
                                <img class="user-pic" src="<?= htmlspecialchars(getImageUrl($row['profile_picture'] ?? null) ?: './assets/images/default-user.jpg') ?>" alt="User" />
                                <span class="username"><?= htmlspecialchars($row['username']) ?></span>
                            </div>
                            <img class="listing-img" src="<?= htmlspecialchars(getImageUrl($row['image']) ?: './assets/images/default-image.jpg') ?>" alt="<?= htmlspecialchars($row['title']) ?>" />
                            <div class="card-body">
                                <h3><?= htmlspecialchars($row['title']) ?></h3>
                                <div class="card-meta">
                                    <p class="price">£<?= number_format($row['price'], 2) ?></p>
                                    <?php if (!empty($row['condition'])): ?>
                                        <span class="condition-badge <?= strtolower(str_replace(' ', '-', $row['condition'])) ?>">
                                            <?= htmlspecialchars($row['condition']) ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <p class="description"><?= htmlspecialchars(substr($row['description'], 0, 100)) . (strlen($row['description']) > 100 ? '...' : '') ?></p>
                                <div class="card-actions">
                                    <form method="post" class="remove-form">
                                        <input type="hidden" name="listing_id" value="<?= $row['id'] ?>">
                                        <button type="submit" name="remove" class="remove-btn">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                                                <path d="M14.5 3a1 1 0 0 1-1 1H13v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V4h-.5a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1H6a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1h3.5a1 1 0 0 1 1 1v1ZM4.118 4 4 4.059V13a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1V4.059L11.882 4H4.118ZM2.5 3h11V2h-11v1Z"/>
                                            </svg>
                                            Remove
                                        </button>
                                    </form>
                                    <span class="date-saved">Saved: <?= date('M j, Y', strtotime($row['saved_at'])) ?></span>
                                </div>
                            </div>
                        </a>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="no-saved-items">
                    <svg width="64" height="64" fill="currentColor" viewBox="0 0 16 16">
                        <path d="M2 2a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v13.5a.5.5 0 0 1-.777.416L8 13.101l-5.223 2.815A.5.5 0 0 1 2 15.5V2zm2-1a1 1 0 0 0-1 1v12.566l4.723-2.482a.5.5 0 0 1 .554 0L13 14.566V2a1 1 0 0 0-1-1H4z"/>
                    </svg>
                    <p>You haven't saved any items yet.</p>
                    <a href="./marketplace.php" class="browse-btn">Browse Marketplace</a>
                </div>
            <?php endif; ?>
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
    </script>
</body>
</html>
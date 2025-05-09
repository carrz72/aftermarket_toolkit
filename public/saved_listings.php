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
            <a href="#" class="link" onclick="toggleProfileDropdown(event)">
                <span class="link-icon">
                    <img src="./assets/images/profile-icon.svg" alt="Profile">
                </span>
                <span class="link-title">Profile</span>
            </a>
            <div id="profileDropdown" class="dropdown-content">
                <button class="value" onclick="window.location.href='./profile.php';">
                    <img src="./assets/images/profile-icon.svg" alt="Profile">Account
                </button>
                <button class="value" onclick="window.location.href='../api/listings/view_listings.php';">My Listings</button>
                <button class="value" onclick="window.location.href='./saved_listings.php';">Saved Items</button>
                <button class="value" onclick="window.location.href='./logout.php';">Logout</button>
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
                            <img src="<?= htmlspecialchars(getImageUrl($row['image']) ?: './assets/images/default-image.jpg') ?>" 
                                alt="<?= htmlspecialchars($row['title']) ?>" 
                                class="listing-img">
                            
                            <div class="card-body">
                                <h2 class="card-title"><?= htmlspecialchars($row['title']) ?></h2>
                                
                                <div class="card-meta">
                                    <p class="price">£<?= number_format($row['price'], 2) ?></p>
                                    
                                    <?php if (!empty($row['condition'])): ?>
                                        <span class="condition-badge <?= strtolower(str_replace(' ', '-', $row['condition'])) ?>">
                                            <?= htmlspecialchars($row['condition']) ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                                
                                <p class="description"><?= htmlspecialchars(substr($row['description'], 0, 100)) . (strlen($row['description']) > 100 ? '...' : '') ?></p>
                                
                                <div class="card-footer">
                                    <form method="POST" action="" onsubmit="return confirm('Are you sure you want to remove this item?');">
                                        <input type="hidden" name="listing_id" value="<?= $row['id'] ?>">
                                        <button type="submit" name="remove" class="remove-btn">
                                            <svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                                                <path d="M5.5 5.5A.5.5 0 0 1 6 6v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5Zm2.5 0a.5.5 0 0 1 .5.5v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5Zm3 .5a.5.5 0 0 0-1 0v6a.5.5 0 0 0 1 0V6Z"/>
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
                        <path d="M8 4.41c1.387-1.425 4.854 1.07 0 4.277C3.146 5.48 6.613 2.986 8 4.412z"/>
                        <path d="M2 2a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v13.5a.5.5 0 0 1-.777.416L8 13.101l-5.223 2.815A.5.5 0 0 1 2 15.5V2zm2-1a1 1 0 0 0-1 1v12.566l4.723-2.482a.5.5 0 0 1 .554 0L13 14.566V2a1 1 0 0 0-1-1H4z"/>
                    </svg>
                    <p>You haven't saved any items yet.</p>
                    <a href="./marketplace.php" class="browse-btn">Browse Marketplace</a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function toggleProfileDropdown(event) {
            event.preventDefault();
            document.querySelector('.profile-container').classList.toggle('active');
        }
        
        document.addEventListener('click', function(e) {
            const profileContainer = document.querySelector('.profile-container');
            if (!profileContainer.contains(e.target)) {
                profileContainer.classList.remove('active');
            }
        });
    </script>
</body>
</html>
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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your Listings - Aftermarket Toolbox</title>
    <link rel="stylesheet" href="../../public/assets/css/view_listings.css">
</head>
<body>
    <div class="menu">
        <a href="../../index.php" class="link">
            <span class="link-icon">
                <img src="../../public/assets/images/home-icon.svg" alt="Home">
            </span>
            <span class="link-title">Home</span>
        </a>

        <a href="../../public/marketplace.php" class="link">
            <span class="link-icon">
                <img src="../../public/assets/images/market.svg" alt="Market">
            </span>
            <span class="link-title">Market</span>
        </a>
        
        <a href="../../public/forum.php" class="link">
            <span class="link-icon">
                <img src="../../public/assets/images/forum-icon.svg" alt="Forum">
            </span>
            <span class="link-title">Forum</span>
        </a>
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
                        <!-- UPDATED: Use the helper function -->
                        <img src="<?= htmlspecialchars(getValidImageUrl($listing['image'], '../../public/assets/images/default-image.jpg')) ?>" 
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
        // Optional: Add any JavaScript functionality here
        document.addEventListener('DOMContentLoaded', function() {
            console.log('Listings page loaded');
        });
    </script>
</body>
</html>
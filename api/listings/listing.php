<?php
// filepath: c:\xampp\htdocs\aftermarket_toolkit\api\listings\listing.php
require_once __DIR__ . '/../../config/db.php';
session_start();

// Get listing ID from URL
$listingId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$listingId) {
    header('Location: ../../public/marketplace.php');
    exit();
}

// Fetch listing details
$query = "
    SELECT l.*, u.username, u.profile_picture, u.id AS seller_id
    FROM listings l
    JOIN users u ON l.user_id = u.id
    WHERE l.id = ?
";
$stmt = $conn->prepare($query);
$stmt->bind_param('i', $listingId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header('Location: ../../public/marketplace.php');
    exit();
}

$listing = $result->fetch_assoc();

// Check if listing is saved by current user
$isSaved = false;
if (isset($_SESSION['user_id'])) {
    $checkSavedQuery = "
        SELECT * FROM saved_listings 
        WHERE user_id = ? AND listing_id = ?
    ";
    $checkStmt = $conn->prepare($checkSavedQuery);
    $checkStmt->bind_param('ii', $_SESSION['user_id'], $listingId);
    $checkStmt->execute();
    $isSaved = $checkStmt->get_result()->num_rows > 0;
}

// Handle save/unsave action
if (isset($_POST['action']) && isset($_SESSION['user_id'])) {
    $userId = $_SESSION['user_id'];
    
    if ($_POST['action'] === 'save') {
        if (!$isSaved) {
            $saveQuery = "
                INSERT INTO saved_listings (user_id, listing_id, saved_at)
                VALUES (?, ?, NOW())
            ";
            $saveStmt = $conn->prepare($saveQuery);
            $saveStmt->bind_param('ii', $userId, $listingId);
            $saveStmt->execute();
            $isSaved = true;
        }
    } elseif ($_POST['action'] === 'unsave') {
        if ($isSaved) {
            $unsaveQuery = "
                DELETE FROM saved_listings 
                WHERE user_id = ? AND listing_id = ?
            ";
            $unsaveStmt = $conn->prepare($unsaveQuery);
            $unsaveStmt->bind_param('ii', $userId, $listingId);
            $unsaveStmt->execute();
            $isSaved = false;
        }
    }
}

// Get additional images (if any)
$imagesQuery = "
    SELECT image_path 
    FROM listing_images 
    WHERE listing_id = ?
    ORDER BY display_order ASC
";
$imagesStmt = $conn->prepare($imagesQuery);
$imagesStmt->bind_param('i', $listingId);
$imagesStmt->execute();
$imagesResult = $imagesStmt->get_result();

$additionalImages = [];
while ($image = $imagesResult->fetch_assoc()) {
    $additionalImages[] = $image['image_path'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($listing['title']) ?> - Aftermarket Toolbox</title>
    <link rel="stylesheet" href="../../public/assets/css/marketplace.css">
    <style>
        .listing-container {
            max-width: 1200px;
            margin: 30px auto;
            display: flex;
            flex-wrap: wrap;
            gap: 30px;
            padding: 20px;
        }
        
        .gallery-container {
            flex: 1;
            min-width: 300px;
            max-width: 600px;
            position: relative;
            background-color: #f4f4f4;
            border-radius: 10px;
            overflow: hidden;
        }
        
        .main-image {
            width: 100%;
            height: 400px;
            object-fit: contain;
            background-color: #fff;
            display: block;
        }
        
        .thumbnail-container {
            display: flex;
            gap: 10px;
            margin-top: 10px;
            overflow-x: auto;
            padding: 10px 0;
        }
        
        .thumbnail {
            width: 80px;
            height: 80px;
            object-fit: cover;
            cursor: pointer;
            border: 2px solid transparent;
            border-radius: 5px;
            transition: border-color 0.3s;
        }
        
        .thumbnail.active {
            border-color: #189dc5;
        }
        
        .gallery-nav {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            background-color: rgba(0,0,0,0.5);
            color: white;
            border: none;
            font-size: 24px;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 2;
        }
        
        .gallery-prev {
            left: 10px;
        }
        
        .gallery-next {
            right: 10px;
        }
        
        .listing-details {
            flex: 1;
            min-width: 300px;
        }
        
        .listing-title {
            background-color: #189dc5;
            color: #fff;
            padding: 15px;
            border-radius: 10px;
            box-shadow: 0px 6px rgb(5, 5, 5);
            margin-bottom: 20px;
            font-size: 24px;
        }
        
        .listing-price {
            font-size: 28px;
            font-weight: bold;
            color: #189dc5;
            margin: 20px 0;
        }
        
        .listing-meta {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
            color: #555;
        }
        
        .listing-meta span {
            background-color: #f4f4f4;
            padding: 5px 10px;
            border-radius: 5px;
        }
        
        .listing-description {
            background-color: #f4f4f4;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            line-height: 1.6;
        }
        
        .seller-container {
            display: flex;
            align-items: center;
            gap: 15px;
            margin: 20px 0;
            padding: 15px;
            background-color: #f4f4f4;
            border-radius: 10px;
        }
        
        .seller-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            object-fit: cover;
        }
        
        .seller-name {
            font-weight: bold;
            font-size: 18px;
        }
        
        .action-buttons {
            display: flex;
            gap: 15px;
            margin-top: 20px;
        }
        
        .btn {
            padding: 12px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: bold;
            font-size: 16px;
            transition: background-color 0.3s, transform 0.2s;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn:hover {
            transform: translateY(-2px);
        }
        
        .btn-message {
            background-color: #189dc5;
            color: white;
            flex: 1;
        }
        
        .btn-message:hover {
            background-color: #157a9e;
        }
        
        .btn-save {
            background-color: #28a745;
            color: white;
        }
        
        .btn-save:hover {
            background-color: #218838;
        }
        
        .btn-saved {
            background-color: #ffc107;
            color: #212529;
        }
        
        .btn-saved:hover {
            background-color: #e0a800;
        }
        
        .back-link {
            display: inline-block;
            margin-bottom: 20px;
            color: #189dc5;
            text-decoration: none;
            font-weight: bold;
        }
        
        .back-link:hover {
            text-decoration: underline;
        }
        
        @media (max-width: 768px) {
            .listing-container {
                flex-direction: column;
            }
            
            .gallery-container, .listing-details {
                max-width: 100%;
            }
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
        
        <?php if (isset($_SESSION['user_id'])): ?>
            <a href="../../public/chat.php" class="link">
                <span class="link-icon">
                    <img src="../../public/assets/images/chat-icon.svg" alt="Chat">
                </span>
                <span class="link-title">Chat</span>
            </a>
        <?php endif; ?>
    </div>

    <div class="listing-container">
        <div class="gallery-container">
            <?php 
            // Combine main image with additional images
            $allImages = [$listing['image']];
            if (!empty($additionalImages)) {
                $allImages = array_merge($allImages, $additionalImages);
            }
            
            // Display main image (first image in array)
            $mainImage = !empty($allImages[0]) ? $allImages[0] : '../../public/assets/images/default-image.jpg';
            
            // Handle different image path formats
            $mainImagePath = $mainImage;
            if (strpos($mainImage, '/') === 0) {
                // Absolute path starting with /
                $mainImagePath = $mainImage; 
            } else if (strpos($mainImage, './assets/') === 0) {
                // Relative path starting with ./assets/
                $mainImagePath = '../../public/' . str_replace('./assets/', 'assets/', $mainImage);
            } else {
                // Any other format
                $mainImagePath = '../../public/' . $mainImage;
            }
            ?>
            
            <img src="<?= htmlspecialchars($mainImagePath) ?>" alt="<?= htmlspecialchars($listing['title']) ?>" class="main-image" id="mainImage">
            
            <?php if (count($allImages) > 1): ?>
                <button class="gallery-nav gallery-prev" onclick="prevImage()">&lt;</button>
                <button class="gallery-nav gallery-next" onclick="nextImage()">&gt;</button>
                
                <div class="thumbnail-container">
                    <?php foreach ($allImages as $index => $img): 
                        // Handle different image path formats
                        $imgPath = $img;
                        if (strpos($img, '/') === 0) {
                            $imgPath = $img;
                        } else if (strpos($img, './assets/') === 0) {
                            $imgPath = '../../public/' . str_replace('./assets/', 'assets/', $img);
                        } else {
                            $imgPath = '../../public/' . $img;
                        }
                    ?>
                        <img 
                            src="<?= htmlspecialchars($imgPath) ?>" 
                            alt="Thumbnail" 
                            class="thumbnail <?= $index === 0 ? 'active' : '' ?>" 
                            onclick="changeImage(<?= $index ?>, this)">
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="listing-details">
            <a href="../../public/marketplace.php" class="back-link">← Back to Marketplace</a>
            
            <h1 class="listing-title"><?= htmlspecialchars($listing['title']) ?></h1>
            
            <div class="listing-price">£<?= number_format($listing['price'], 2) ?></div>
            
            <div class="listing-meta">
                <span>Category: <?= htmlspecialchars($listing['category']) ?></span>
                <span>Condition: <?= htmlspecialchars($listing['condition']) ?></span>
                <span>Posted: <?= date('F j, Y', strtotime($listing['created_at'])) ?></span>
                <?php if (!empty($listing['location'])): ?>
                    <span>Location: <?= htmlspecialchars($listing['location']) ?></span>
                <?php endif; ?>
            </div>
            
            <div class="listing-description">
                <?= nl2br(htmlspecialchars($listing['description'])) ?>
            </div>
            
            <div class="form-group">
                <label for="location">Location</label>
                <input type="text" id="location" name="location" class="form-control" 
                       value="<?= htmlspecialchars($listing['location'] ?? '') ?>" 
                       placeholder="City, Region or Postcode">
            </div>
            
            <div class="seller-container">
                <?php 
                $profilePic = !empty($listing['profile_picture']) 
                    ? $listing['profile_picture'] 
                    : '../../public/assets/images/default-profile.jpg'; 
                ?>
                <img src="<?= htmlspecialchars($profilePic) ?>" alt="Seller" class="seller-avatar">
                <div>
                    <div class="seller-name"><?= htmlspecialchars($listing['username']) ?></div>
                    <div>Seller</div>
                </div>
            </div>
            
            <div class="action-buttons">
                <?php if (isset($_SESSION['user_id']) && $_SESSION['user_id'] !== $listing['seller_id']): ?>
                    <a href="../../public/chat.php?chat=<?= $listing['seller_id'] ?>&listing=<?= $listingId ?>&title=<?= urlencode($listing['title']) ?>" class="btn btn-message">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M20 2H4c-1.1 0-1.99.9-1.99 2L2 22l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm-2 12H6v-2h12v2zm0-3H6V9h12v2zm0-3H6V6h12v2z"/>
                        </svg>
                        Message Seller
                    </a>
                    
                    <form method="POST" style="display: inline;">
                        <?php if ($isSaved): ?>
                            <input type="hidden" name="action" value="unsave">
                            <button type="submit" class="btn btn-saved">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M17 3H7c-1.1 0-2 .9-2 2v16l7-3 7 3V5c0-1.1-.9-2-2-2z"/>
                                </svg>
                                Saved
                            </button>
                        <?php else: ?>
                            <input type="hidden" name="action" value="save">
                            <button type="submit" class="btn btn-save">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M17 3H7c-1.1 0-2 .9-2 2v16l7-3 7 3V5c0-1.1-.9-2-2-2zm0 15l-5-2.18L7 18V5h10v13z"/>
                                </svg>
                                Save
                            </button>
                        <?php endif; ?>
                    </form>
                <?php elseif (!isset($_SESSION['user_id'])): ?>
                    <a href="../../public/login.php" class="btn btn-message">Login to contact seller</a>
                <?php endif; ?>
                
                <?php if (isset($_SESSION['user_id']) && $_SESSION['user_id'] === $listing['seller_id']): ?>
                    <a href="edit_listing.php?id=<?= $listingId ?>" class="btn btn-save">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04c.39-.39.39-1.02 0-1.41l-2.34-2.34c-.39-.39-1.02-.39-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/>
                        </svg>
                        Edit Listing
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        // Image gallery functionality
        const images = <?= json_encode($allImages) ?>;
        let currentImageIndex = 0;
        const mainImage = document.getElementById('mainImage');
        
        // Process image path for display
        function processImagePath(path) {
            if (path.startsWith('/')) {
                return path; // Absolute path
            } else if (path.startsWith('./assets/')) {
                return '../../public/' + path.replace('./assets/', 'assets/');
            } else {
                return '../../public/' + path;
            }
        }
        
        // Change displayed image
        function changeImage(index, thumbnail) {
            if (index >= 0 && index < images.length) {
                currentImageIndex = index;
                mainImage.src = processImagePath(images[index]);
                
                // Update active thumbnail
                document.querySelectorAll('.thumbnail').forEach(thumb => {
                    thumb.classList.remove('active');
                });
                
                if (thumbnail) {
                    thumbnail.classList.add('active');
                } else {
                    document.querySelectorAll('.thumbnail')[index].classList.add('active');
                }
            }
        }
        
        // Navigation functions
        function nextImage() {
            const nextIndex = (currentImageIndex + 1) % images.length;
            changeImage(nextIndex);
        }
        
        function prevImage() {
            const prevIndex = (currentImageIndex - 1 + images.length) % images.length;
            changeImage(prevIndex);
        }
        
        // Keyboard navigation
        document.addEventListener('keydown', function(e) {
            if (e.key === 'ArrowRight') {
                nextImage();
            } else if (e.key === 'ArrowLeft') {
                prevImage();
            }
        });
    </script>
</body>
</html>
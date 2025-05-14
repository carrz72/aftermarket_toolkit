<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/image_helper.php';
session_start();

// Retrieve categories (optional extension)
$categorySql = "SELECT DISTINCT category FROM listings";
$categoriesResult = $conn->query($categorySql);

// Retrieve listings
$search = $_GET['search'] ?? '';
$categoryFilter = $_GET['category'] ?? '';
$condition = $_GET['condition'] ?? '';

$sql = "
  SELECT listings.*, users.username, users.profile_picture 
  FROM listings 
  JOIN users ON listings.user_id = users.id 
  WHERE (listings.title LIKE ? OR listings.description LIKE ?)
";
$params = ["%$search%", "%$search%"];
$types = "ss";

if (!empty($categoryFilter)) {
  $sql .= " AND listings.category = ?";
  $params[] = $categoryFilter;
  $types .= "s";
}

if (!empty($condition)) {
  $sql .= " AND listings.condition = ?";
  $params[] = $condition;
  $types .= "s";
}

$sql .= " ORDER BY listings.created_at DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

// Function to get CSS class for condition badge
function getConditionClass($condition) {
    $condition = strtolower($condition);
    switch ($condition) {
        case 'new': return 'new';
        case 'like new': return 'like-new';
        case 'good': return 'good';
        case 'fair': return 'fair';
        case 'poor': 
        case 'used': 
            return 'used';
        default: return '';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Marketplace - Aftermarket Toolbox</title>
  <link rel="stylesheet" href="./assets/css/marketplace.css" />
</head>
<body>
   
<div class="menu">
  <a href="../index.php" class="link">
    <span class="link-icon">
      <img src="./assets/images/home-icon.svg" alt="Home">
    </span>
    <span class="link-title">Home</span>
  </a>

  <!-- Market with dropdown like Profile -->
  <div class="profile-container">
    <a href="#" class="link active" onclick="toggleDropdown(this, event)">
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
  
  <!-- Forum with dropdown -->
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

  <!-- Search and Filter -->
  <section class="market-header">
    <h1>Aftermarket Toolbox Marketplace</h1>
    <form method="GET" class="search-filter">
      <input type="text" name="search" placeholder="Search listings..." value="<?= htmlspecialchars($search) ?>" />
      
      <div class="filter-group">
        <select name="category">
          <option value="">All Categories</option>
          <?php 
          $categoriesResult->data_seek(0); // Reset result pointer
          while ($cat = $categoriesResult->fetch_assoc()): 
          ?>
            <option value="<?= htmlspecialchars($cat['category']) ?>" <?= $categoryFilter === $cat['category'] ? 'selected' : '' ?>>
              <?= htmlspecialchars($cat['category']) ?>
            </option>
          <?php endwhile; ?>
        </select>
        
        <select name="condition">
          <option value="">All Conditions</option>
          <option value="New" <?= $condition === 'New' ? 'selected' : '' ?>>New</option>
          <option value="Like New" <?= $condition === 'Like New' ? 'selected' : '' ?>>Like New</option>
          <option value="Good" <?= $condition === 'Good' ? 'selected' : '' ?>>Good</option>
          <option value="Fair" <?= $condition === 'Fair' ? 'selected' : '' ?>>Fair</option>
          <option value="Poor" <?= $condition === 'Poor' ? 'selected' : '' ?>>Poor</option>
        </select>
      </div>
      
      <button type="submit" class="search-button">
        <svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
          <path d="M11.742 10.344a6.5 6.5 0 1 0-1.397 1.398h-.001c.03.04.062.078.098.115l3.85 3.85a1 1 0 0 0 1.415-1.414l-3.85-3.85a1.007 1.007 0 0 0-.115-.1zM12 6.5a5.5 5.5 0 1 1-11 0 5.5 5.5 0 0 1 11 0z"/>
        </svg>
        Search
      </button>
      
      <?php if (!empty($search) || !empty($categoryFilter) || !empty($condition)): ?>
        <a href="./marketplace.php" class="clear-filter">Clear Filters</a>
      <?php endif; ?>
    </form>
    
    <?php if (isset($_SESSION['user_id'])): ?>
      <a href="../api/listings/create_listing.php" class="create-listing-btn">
        <svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
          <path d="M8 4a.5.5 0 0 1 .5.5v3h3a.5.5 0 0 1 0 1h-3v3a.5.5 0 0 1-1 0v-3h-3a.5.5 0 0 1 0-1h3v-3A.5.5 0 0 1 8 4z"/>
        </svg>
        List New Item
      </a>
    <?php endif; ?>
  </section>

  <!-- Listings -->
  <div class="card-container">
    <?php if ($result && $result->num_rows > 0): ?>
      <?php while ($row = $result->fetch_assoc()): ?>
        <a href="../api/listings/listing.php?id=<?= $row['id'] ?>" class="card-link">
          <div class="card">
            <div class="card-header">
              <img class="user-pic" 
                   src="<?= htmlspecialchars(getImageUrl($row['profile_picture']) ?: './assets/images/default-user.jpg') ?>" 
                   alt="User" />
              <span class="username"><?= htmlspecialchars($row['username']) ?></span>
            </div>
            <img class="listing-img" 
                 src="<?= htmlspecialchars(getImageUrl($row['image']) ?: './assets/images/default-image.jpg') ?>" 
                 alt="<?= htmlspecialchars($row['title']) ?>" />
            <div class="card-body">
              <h3><?= htmlspecialchars($row['title']) ?></h3>
              <div class="card-meta">
                <p class="price">£<?= number_format($row['price'], 2) ?></p>
                <?php if (!empty($row['condition'])): ?>
                  <span class="condition-badge <?= getConditionClass($row['condition']) ?>">
                    <?= htmlspecialchars($row['condition']) ?>
                  </span>
                <?php endif; ?>
              </div>
              <p class="description"><?= htmlspecialchars(substr($row['description'], 0, 100)) . (strlen($row['description']) > 100 ? '...' : '') ?></p>
            </div>
            <div class="card-footer">
              <?php if (isset($_SESSION['user_id'])): ?>
                <button class="bookmark" onclick="event.stopPropagation(); saveBookmark(<?= $row['id'] ?>); return false;">
                  <img src="./assets/images/bookmark.svg" alt="Bookmark" />
                </button>
              <?php endif; ?>
              <span class="date-added"><?= date('M j', strtotime($row['created_at'])) ?></span>
            </div>
          </div>
        </a>
      <?php endwhile; ?>
    <?php else: ?>
      <div class="no-results">
        <svg width="64" height="64" fill="currentColor" viewBox="0 0 16 16">
          <path d="M8.982 1.566a1.13 1.13 0 0 0-1.96 0L.165 13.233c-.457.778.091 1.767.98 1.767h13.713c.889 0 1.438-.99.98-1.767L8.982 1.566zM8 5c.535 0 .954.462.9.995l-.35 3.507a.552.552 0 0 1-1.1 0L7.1 5.995A.905.905 0 0 1 8 5zm.002 6a1 1 0 1 1 0 2 1 1 0 0 1 0-2z"/>
        </svg>
        <p>No listings found matching your criteria.</p>
        <a href="./marketplace.php" class="reset-search">Clear search and see all listings</a>
      </div>
    <?php endif; ?>
  </div>

  <script>
    // Add the delay variable (missing from your current code)
    const delay = 100; // Delay in milliseconds

    function toggleProfileDropdown(event) {
      event.preventDefault();
      document.querySelector('.profile-container').classList.toggle('active');
    }
    
    document.addEventListener('click', function(e) {
      const profileContainer = document.querySelector('.profile-container');
      if (!profileContainer.contains(e.target)) profileContainer.classList.remove('active');
    });
    
    function saveBookmark(listingId) {
      // Get the current button to update its state
      const button = event.currentTarget;
      const isCurrentlyBookmarked = button.classList.contains('bookmarked');
      
      // Set action based on current state
      const action = isCurrentlyBookmarked ? 'remove' : 'add';
      
      // Send an AJAX request to toggle bookmark
      fetch('../api/bookmarks/toggle_bookmark.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `listing_id=${listingId}&action=${action}`
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          console.log(`Bookmark ${action}ed for listing: ${listingId}`);
          
          // Update button appearance based on new state
          if (action === 'add') {
            // Bookmark added
            button.classList.add('bookmarked');
            
            const bookmarkImg = button.querySelector('img');
            if (bookmarkImg) {
              bookmarkImg.src = './assets/images/bookmark-filled.svg';
              bookmarkImg.style.filter = 'none'; // Remove grayscale
            }
            
          } else {
            // Bookmark removed
            button.classList.remove('bookmarked');
            
            const bookmarkImg = button.querySelector('img');
            if (bookmarkImg) {
              bookmarkImg.src = './assets/images/bookmark.svg';
              bookmarkImg.style.filter = 'grayscale(100%)'; // Add grayscale
            }
            
          }
        } else {
          console.error("Error toggling bookmark:", data.message);
          if (data.message === "User not logged in") {
            alert("Please log in to save items");
            window.location.href = './login.php';
          } else {
            alert("Error: " + data.message);
          }
        }
      })
      .catch(error => {
        console.error('Error:', error);
        alert("Something went wrong. Please try again.");
      });
    }
    
    // Initialize bookmarks on page load
    document.addEventListener('DOMContentLoaded', function() {
      // Check if user is logged in and initialize saved bookmarks
      fetch('../api/bookmarks/get_bookmarks.php')
        .then(response => response.json())
        .then(data => {
          if (data.success && data.bookmarks) {
            // Mark already bookmarked items
            data.bookmarks.forEach(bookmarkId => {
              const bookmarkBtn = document.querySelector(`button.bookmark[onclick*="saveBookmark(${bookmarkId})"]`);
              if (bookmarkBtn) {
                bookmarkBtn.classList.add('bookmarked');
                const img = bookmarkBtn.querySelector('img');
                if (img) {
                  img.src = './assets/images/bookmark-filled.svg';
                  img.style.filter = 'none';
                }
              }
            });
          }
        })
        .catch(error => console.error('Error loading bookmarks:', error));
    });

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
<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/image_helper.php'; // Add this line
session_start();

$search = $_GET['search'] ?? '';
$category = $_GET['category'] ?? '';

$sql = "
  SELECT listings.*, users.username, users.profile_picture 
  FROM listings 
  JOIN users ON listings.user_id = users.id 
  WHERE 1=1
";

$params = [];

if (!empty($search)) {
    $sql .= " AND (listings.title LIKE ? OR listings.description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($category)) {
    $sql .= " AND listings.category = ?";
    $params[] = $category;
}

$sql .= " ORDER BY listings.created_at DESC LIMIT 20";

$stmt = $conn->prepare($sql);

if (!empty($params)) {
    $types = str_repeat('s', count($params));
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Aftermarket Toolbox</title>
  <link rel="stylesheet" href="./public/assets/css/index.css">
  <style>
    .card-link {
      text-decoration: none;
      color: inherit;
      display: block;
      cursor: pointer;
      transition: transform 0.2s ease;
    }

    .card-link:hover {
      transform: translateY(-5px);
    }

    .card-link:hover .card {
      box-shadow: 0 8px 16px rgba(0,0,0,0.2);
    }

    /* Make sure bookmark button doesn't look like part of the link */
    .bookmark {
      position: relative;
      z-index: 2;
    }
  </style>
</head>
<body>
<div class="menu">
  <a href="#" class="link">
    <span class="link-icon">
      <img src="./public/assets/images/home-icon.svg" alt="">
    </span>
    <span class="link-title">Home</span>
  </a>

  <!-- Market with dropdown like Profile -->
  <div class="profile-container">
    <a href="#" class="link" onclick="toggleDropdown(this, event)">
      <span class="link-icon">
        <img src="./public/assets/images/market.svg" alt="">
      </span>
      <span class="link-title">Market</span>
    </a>
    <div class="dropdown-content">
      <button class="value" onclick="window.location.href='./public/marketplace.php?view=explore';">Explore</button>
      <button class="value" onclick="window.location.href='./api/listings/view_listings.php';">View Listings</button>
      <button class="value" onclick="window.location.href='./api/listings/create_listing.php';">List Item</button>
      <button class="value" onclick="window.location.href='./public/saved_listings.php';">Saved Items</button>
    </div>
  </div>
  
  <!-- Forum with dropdown like Profile -->
  <div class="profile-container">
    <a href="#" class="link" onclick="toggleDropdown(this, event)">
      <span class="link-icon">
        <img src="./public/assets/images/forum-icon.svg" alt="">
      </span>
      <span class="link-title">Forum</span>
    </a>
    <div class="dropdown-content">
      <button class="value" onclick="window.location.href='./public/forum.php?view=threads';">View Threads</button>
      <button class="value" onclick="window.location.href='./public/forum.php?view=start_thread';">Start Thread</button>
      <button class="value" onclick="window.location.href='./public/forum.php?view=post_question';">Post Question</button>
    </div>
  </div>

  <div class="profile-container">
    <a href="#" class="link" onclick="toggleDropdown(this, event)">
      <span class="link-icon">
        <img src="./public/assets/images/profile-icon.svg" alt="">
      </span>
      <span class="link-title">Profile</span>
    </a>
    <div id="profileDropdown" class="dropdown-content">
    <?php if (isset($_SESSION['user_id'])): ?>
      <button class="value"><img src="./public/assets/images/profile-icon.svg" alt="">Account</button>
      <button class="value">Appearance</button>
      <button class="value">Accessibility</button>
      <button class="value" onclick="window.location.href='./public/logout.php';">Logout</button>
      <?php else: ?>
        <button class="value" onclick="window.location.href='./public/login.php';">Login</button>
        <button class="value" onclick="window.location.href='./public/register.php';">Register</button>
      <?php endif; ?>
    </div>
  </div>

  <?php if (isset($_SESSION['user_id'])): ?>
    <a href="./public/chat.php" class="link">
      <span class="link-icon">
        <img src="./public/assets/images/chat-icon.svg" alt="">
      </span>
      <span class="link-title">Chat</span>
    </a>
  <?php endif; ?>
</div>

<div class="sidebar">
    <!-- Add this button for sidebar toggle -->
    <button id="sidebarToggle" class="sidebar-toggle">☰</button>
  <h2>Sidebar</h2>
  <ul>
    <li><a href="#">Sidebar Link 1</a></li>
    <li><a href="#">Sidebar Link 2</a></li>
    <li><a href="#">Sidebar Link 3</a></li>
  </ul>
</div>

<main class="main-content">

    
<div class="top-cards">
<div class="sub-card">
  <div class="smallIcon">
    <div class="Name-icon">
    <div class="Icon">
    <img src="./public/assets/images/market.svg" alt="Market" />
    </div>
    <div class="Name">
    <h3>Marketplace</h3>
    </div>
    </div>
    <div class="Descripion">
    <button>View Listings</button>
            <button>Explore</button>
            <button onclick="window.location.href='./public/marketplace.php?view=list_item';">List item</button>
            <button>chart</button>
    </div>
  </div>
</div>
        
<div class="sub-card">
<div class="smallIcon">
    <div class="Name-icon">
    <div class="Icon">
    <img src="./public/assets/images/forum-icon.svg" alt="Forum" />
    </div>
    <div class="Name">
    <h3>Forum</h3>
    </div>
    </div>
            <div class="Descripion">  
            <button>View Threads</button>
            <button>Start Thread</button>
            <button>Post Question</button>
            </div>
          </div>
          </div>

          <div class="sub-card">
          <div class="smallIcon">
          <div class="Name-icon">
          <div class="Icon">
            <img src="./public/assets/images/chat-icon.svg" alt="Chat" />
          </div>
          <div class="Name">
            <h3>Chat</h3>
          </div>
          </div>
            <div class="Descripion">
            <button>Start Chat</button>
            <button>View Chats</button>
            <button>Settings</button>
            </div>
            </div>
          </div>

        </div>

<div class="marketplace">

<h1>Aftermarket toolkit marketpalace</h1>

  <!-- Search and filtering form -->
  <div class="listing-filters">
    <form action="index.php" method="GET" class="search-form"></form></div>
  <div class="card-container">
  <?php if ($result && $result->num_rows > 0): ?>
    <?php while ($row = $result->fetch_assoc()): ?>
      <a href="./api/listings/listing.php?id=<?= $row['id'] ?>" class="card-link">
        <div class="card">
          <div class="card-header">
            <img class="user-pic" src="<?= htmlspecialchars(getImageUrl($row['profile_picture']) ?: './public/assets/images/default-user.jpg') ?>" alt="User" />
            <span class="username"><?= htmlspecialchars($row['username']) ?></span>
          </div>
          <img class="listing-img" src="<?= htmlspecialchars(getImageUrl($row['image']) ?: './public/assets/images/default-image.jpg') ?>" alt="<?= htmlspecialchars($row['title']) ?>" />
          <div class="card-body">
  <h3><?= htmlspecialchars($row['title']) ?></h3>
  <div class="card-meta">
    <p class="price">£<?= number_format($row['price'], 2) ?></p>
    <?php if (!empty($row['condition'])): ?>
      <span class="condition-badge" data-condition="<?= htmlspecialchars($row['condition']) ?>">
        <?= htmlspecialchars($row['condition']) ?>
      </span>
    <?php endif; ?>
  </div>
  <p class="description"><?= htmlspecialchars(substr($row['description'], 0, 100)) . (strlen($row['description']) > 100 ? '...' : '') ?></p>
</div>
          <div class="card-footer">
            <button class="bookmark" onclick="event.stopPropagation(); saveBookmark(<?= $row['id'] ?>); return false;">
              <img src="./public/assets/images/bookmark.svg" alt="Bookmark" />
            </button>
            <span class="date-added"><?= date('M j', strtotime($row['created_at'])) ?></span>
          </div>
        </div>
      </a>
    <?php endwhile; ?>
  <?php else: ?>
    <p>No listings found.</p>
  <?php endif; ?>
</div>
<div class="view-all">
      <button class="view-all-btn">Veiw All</button>  
  </div>
  </div>

<div class="forum">
  <div class="forum-container">
    <h2>Community Forum</h2>
    <section class="forum-section">
      <div class="container">
<!-- Existing forum thread loop -->
<div class="forum-threads">
  <?php
    require_once __DIR__ . '/config/db.php';

    $sql = "
      SELECT forum_threads.*, users.username, users.profile_picture AS profile_pic 
      FROM forum_threads 
      JOIN users ON forum_threads.user_id = users.id 
      ORDER BY forum_threads.created_at DESC 
      LIMIT 10
    ";
    $result = $conn->query($sql);

    if ($result && $result->num_rows > 0):
      while ($row = $result->fetch_assoc()):
        // Store the current thread ID
        $thread_id = $row['id'];
  ?>
  <div class="forumcard">
    <div class="card-body">
      <div class="forum-profile">
        <img 
          src="<?= htmlspecialchars($row['profile_pic']) ?>" 
          alt="<?= htmlspecialchars($row['username']) ?>" 
          class="profile-pic" 
        >
        <div class="pro-details">
        <?= htmlspecialchars($row['username']) ?><br>
        <?= date('M j, Y', strtotime($row['created_at'])) ?>
        </div>
      </div>
      <div class="forum-content">
        <h5 class="card-title"><?= htmlspecialchars($row['title']) ?></h5>
        <div class="user-info"></div>
        <p class="card-text"><?= nl2br(htmlspecialchars($row['body'])) ?></p>
      </div>
      
      <!-- Fetch and display responses for this forum thread -->
      <div class="forum-responses">
      <?php
// Prepare a query to get all responses for the current thread
$sql_res = "
  SELECT responses.*, users.username, users.profile_picture AS response_profile_pic 
  FROM forum_replies AS responses 
  JOIN users ON responses.user_id = users.id 
  WHERE responses.thread_id = ? 
  ORDER BY responses.created_at ASC
";
if ($stmt = $conn->prepare($sql_res)) {
  $stmt->bind_param("i", $thread_id);
  $stmt->execute();
  $res_result = $stmt->get_result();

  if ($res_result && $res_result->num_rows > 0):
    while ($response = $res_result->fetch_assoc()):
?>
<div class="forum-response">
  <img src="<?= htmlspecialchars(getImageUrl($response['response_profile_pic']) ?: './public/assets/images/default-profile.jpg') ?>" 
       alt="<?= htmlspecialchars($response['username']) ?>" 
       class="response-profile-pic" 
       width="30" height="30">
  <span class="response-username"><?= htmlspecialchars($response['username']) ?></span>
  <p class="response-body"><?= nl2br(htmlspecialchars($response['body'])) ?></p>
</div>
<?php 
    endwhile;
  else:
    echo "<p class='no-responses'>No responses yet.</p>";
  endif;
  $stmt->close();
}
        ?>
      </div>
      <!-- End responses -->
    </div>
  </div>
  <?php 
      endwhile;
    else:
      echo "<p>No forum posts yet. Be the first to ask a question!</p>";
    endif;
  ?>
</div>
      </div>
    </section>
  </div>
  <div class="view-all">
      <button class="view-all-btn">Veiw All</button>  
  </div>
</div>
</div>
</main>
</div>
<script>
  const delay = 100; // Delay in milliseconds
  
  // Sidebar toggle functionality
  document.getElementById('sidebarToggle').addEventListener('click', function() {
    const sidebar = document.querySelector('.sidebar');
    const body = document.body;
    
    sidebar.classList.toggle('active');
    body.classList.toggle('sidebar-active');
  });
  
  // Click outside sidebar to close it (optional)
  document.addEventListener('click', function(e) {
    const sidebar = document.querySelector('.sidebar');
    const sidebarToggle = document.getElementById('sidebarToggle');
    
    if (sidebar.classList.contains('active') && 
        !sidebar.contains(e.target) && 
        e.target !== sidebarToggle) {
      sidebar.classList.remove('active');
      document.body.classList.remove('sidebar-active');
    }
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

 

function saveBookmark(listingId) {
  // Get the current button to update its state
  const button = event.currentTarget;
  const isCurrentlyBookmarked = button.classList.contains('bookmarked');
  
  // Set action based on current state
  const action = isCurrentlyBookmarked ? 'remove' : 'add';
  
  // Send an AJAX request to toggle bookmark
  fetch('./api/bookmarks/toggle_bookmark.php', {
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
          bookmarkImg.src = './public/assets/images/bookmark-filled.svg'; // Change from .png to .svg
          bookmarkImg.style.filter = 'none'; // Remove grayscale
        }
        
      } else {
        // Bookmark removed
        button.classList.remove('bookmarked');
        
        const bookmarkImg = button.querySelector('img');
        if (bookmarkImg) {
          bookmarkImg.src = './public/assets/images/bookmark.svg';
          bookmarkImg.style.filter = 'grayscale(100%)'; // Add grayscale
        }
        
      }
    } else {
      console.error("Error toggling bookmark:", data.message);
      if (data.message === "User not logged in") {
        alert("Please log in to save items");
        window.location.href = './public/login.php';
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
  
// Add at the bottom of your script section
document.addEventListener('DOMContentLoaded', function() {
  // Check if user is logged in and initialize saved bookmarks
  fetch('./api/bookmarks/get_bookmarks.php')
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
              img.src = './public/assets/images/bookmark-filled.svg';
              img.style.filter = 'none';
            }
          }
        });
      }
    })
    .catch(error => console.error('Error loading bookmarks:', error));
});
</script>

</body>
</html>

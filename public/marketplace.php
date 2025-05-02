<?php
require_once __DIR__ . '/../config/db.php';

// Retrieve categories (optional extension)
$categorySql = "SELECT DISTINCT category FROM listings";
$categoriesResult = $conn->query($categorySql);

// Retrieve listings
$search = $_GET['search'] ?? '';
$categoryFilter = $_GET['category'] ?? '';

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

$sql .= " ORDER BY listings.created_at DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
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
      <img src="../public/assets/images/home-icon.svg" alt="">
    </span>
    <span class="link-title">Home</span>
  </a>

  <a href="../public/marketplace.php" class="link">
    <span class="link-icon">
      <img src="../public/assets/images/market.svg" alt="">
    </span>
    <span class="link-title">Market</span>
  </a>
  
  <a href="../public/forum.php" class="link">
    <span class="link-icon">
      <img src="../public/assets/images/forum-icon.svg" alt="">
    </span>
    <span class="link-title">Forum</span>
  </a>

  
  <?php if (isset($_SESSION['user_id'])): ?>
    <a href="#" class="link">
      <span class="link-icon">
        <img src="../public/assets/images/chat-icon.svg" alt="">
      </span>
      <span class="link-title">Chat</span>
    </a>
  <?php endif; ?>
  
  <div class="profile-container">
    <a href="#" class="link" onclick="toggleProfileDropdown(event)">
      <span class="link-icon">
        <img src="../public/assets/images/profile-icon.svg" alt="">
      </span>
      <span class="link-title">Profile</span>
    </a>
    <div id="profileDropdown" class="dropdown-content">
    <?php if (isset($_SESSION['user_id'])): ?>
      <button class="value"><img src="./public/assets/images/profile-icon.svg" alt="">Account</button>
      <button class="value">Appearance</button>
      <button class="value">Accessibility</button>
      <?php else: ?>
        <button class="value">Login</button>
        <button class="value">Register</button>
        <?php endif; ?>
    </div>
  </div>
</div>

  <!-- Search and Filter -->
  <section class="market-header">
    <h1>Aftermarket Toolbox Marketplace</h1>
    <form method="GET" class="search-filter">
      <input type="text" name="search" placeholder="Search listings..." value="<?= htmlspecialchars($search) ?>" />
      <select name="category">
        <option value="">All Categories</option>
        <?php while ($cat = $categoriesResult->fetch_assoc()): ?>
          <option value="<?= htmlspecialchars($cat['category']) ?>" <?= $categoryFilter === $cat['category'] ? 'selected' : '' ?>>
            <?= htmlspecialchars($cat['category']) ?>
          </option>
        <?php endwhile; ?>
      </select>
      <button type="submit">Search</button>
    </form>
  </section>

  <!-- Listings -->
  <div class="card-container">
    <?php if ($result && $result->num_rows > 0): ?>
      <?php while ($row = $result->fetch_assoc()): ?>
        <div class="card">
          <div class="card-header">
            <img class="user-pic" src="<?= htmlspecialchars($row['profile_picture'] ?: './assets/images/default-user.jpg') ?>" alt="User" />
            <span class="username"><?= htmlspecialchars($row['username']) ?></span>
          </div>
          <img class="listing-img" src="<?= htmlspecialchars($row['image'] ?: './assets/images/default-image.jpg') ?>" alt="<?= htmlspecialchars($row['title']) ?>" />
          <div class="card-body">
            <h3><?= htmlspecialchars($row['title']) ?></h3>
            <p class="description"><?= htmlspecialchars($row['description']) ?></p>
            <p class="price">£<?= number_format($row['price'], 2) ?></p>
          </div>
          <div class="card-footer">
            <button class="bookmark"><img src="./assets/images/bookmark.svg" alt="Bookmark" /></button>
          </div>
        </div>
      <?php endwhile; ?>
    <?php else: ?>
      <p>No listings found.</p>
    <?php endif; ?>
  </div>

  <script>
    function toggleProfileDropdown(event) {
      event.preventDefault();
      document.querySelector('.profile-container').classList.toggle('active');
    }
    document.addEventListener('click', function(e) {
      const profileContainer = document.querySelector('.profile-container');
      if (!profileContainer.contains(e.target)) profileContainer.classList.remove('active');
    });
  </script>
</body>
</html>

<?php
require_once __DIR__ . '../config/db.php';

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
</head>
<body>

<div class="menu">
  <a href="#" class="link">
    <span class="link-icon">
      <img src="./public/assets/images/home-icon.svg" alt="">
    </span>
    <span class="link-title">Home</span>
  </a>

  <a href="./public/marketplace.php" class="link">
    <span class="link-icon">
      <img src="./public/assets/images/market.svg" alt="">
    </span>
    <span class="link-title">Market</span>
  </a>
  
  <a href="./public/forum.php" class="link">
    <span class="link-icon">
      <img src="./public/assets/images/forum-icon.svg" alt="">
    </span>
    <span class="link-title">Forum</span>
  </a>

  
  <?php if (isset($_SESSION['user_id'])): ?>
    <a href="#" class="link">
      <span class="link-icon">
        <img src="./public/assets/images/chat-icon.svg" alt="">
      </span>
      <span class="link-title">Chat</span>
    </a>
  <?php endif; ?>
  
  <div class="profile-container">
    <a href="#" class="link" onclick="toggleProfileDropdown(event)">
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
      <?php else: ?>
        <button class="value">Login</button>
        <button class="value">Register</button>
        <?php endif; ?>
    </div>
  </div>
</div>

<div class="sidebar">
  <h2>Sidebar</h2>
  <ul>
    <li><a href="#">Sidebar Link 1</a></li>
    <li><a href="#">Sidebar Link 2</a></li>
    <li><a href="#">Sidebar Link 3</a></li>
  </ul>
</div>

<main class="main-content">
  <div class="inputBox_container">
    <svg class="search_icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48" alt="search icon">
      <path d="M46.599 46.599a4.498 4.498 0 0 1-6.363 0l-7.941-7.941C29.028 40.749 25.167 42 21 42 9.402 42 0 32.598 0 21S9.402 0 21 0s21 9.402 21 21c0 4.167-1.251 8.028-3.342 11.295l7.941 7.941a4.498 4.498 0 0 1 0 6.363zM21 6C12.717 6 6 12.714 6 21s6.717 15 15 15c8.286 0 15-6.714 15-15S29.286 6 21 6z"></path>
    </svg>
    <input class="inputBox" id="inputBox" type="text" placeholder="Search For Products">
  </div>

<div class="marketplace">

<h1>Aftermarket toolkit marketpalace</h1>

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
            <button class="bookmark"><img src="./public/assets/images/bookmark.svg" alt="Bookmark" /></button>
          </div>
        </div>
      <?php endwhile; ?>
    <?php else: ?>
      <p>No listings found.</p>
    <?php endif; ?>
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
  <img src="<?= htmlspecialchars($response['response_profile_pic']) ?>" 
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
</div>
</div>
<script>
  function toggleProfileDropdown(event) {
    event.preventDefault();
    const profileContainer = document.querySelector('.profile-container');
    profileContainer.classList.toggle('active');
  }

  // Remove active state if click is outside the profile container
  document.addEventListener('click', function(e) {
    const profileContainer = document.querySelector('.profile-container');
    if (!profileContainer.contains(e.target)) {
      profileContainer.classList.remove('active');
    }
  });

  // Remove active state when the window loses focus (click off to another page)
  window.addEventListener('blur', function() {
    const profileContainer = document.querySelector('.profile-container');
    profileContainer.classList.remove('active');
  });
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

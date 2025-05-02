<?php
session_start();
require_once __DIR__ . '/../config/db.php';

// Get filter parameters
$search = $_GET['search'] ?? '';
$filter_category = $_GET['category'] ?? '';

// Build the WHERE clause dynamically
$where = "WHERE 1=1";
$types = "";
$params = [];

if (!empty($search)) {
    $where .= " AND forum_threads.title LIKE ?";
    $types .= "s";
    $params[] = "%{$search}%";
}

if (!empty($filter_category)) {
    $where .= " AND forum_threads.category = ?";
    $types .= "s";
    $params[] = $filter_category;
}

// Pagination variables
$limit = 3;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// Count total rows
$count_sql = "SELECT COUNT(*) AS total FROM forum_threads $where";
$stmt_count = $conn->prepare($count_sql);
if (!empty($types)) {
    $stmt_count->bind_param($types, ...$params);
}
$stmt_count->execute();
$count_result = $stmt_count->get_result();
$totalRows = 0;
if ($row = $count_result->fetch_assoc()) {
    $totalRows = $row['total'];
}
$totalPages = ceil($totalRows / $limit);

// Retrieve threads with pagination
$sql = "
  SELECT forum_threads.*, users.username, users.profile_picture AS profile_pic 
  FROM forum_threads 
  JOIN users ON forum_threads.user_id = users.id 
  {$where} 
  ORDER BY forum_threads.created_at DESC 
  LIMIT ? OFFSET ?
";
if (!empty($types)) {
    $newTypes = $types . "ii";
    $params[] = $limit;
    $params[] = $offset;
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($newTypes, ...$params);
} else {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $limit, $offset);
}
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Community Forum - Aftermarket Toolbox</title>
  <link rel="stylesheet" href="./assets/css/forum.css">
</head>
<body>
<!-- Navigation / Menu -->
<div class="menu">
  <a href="../index.php" class="link">
    <span class="link-icon">
      <img src="./assets/images/home-icon.svg" alt="Home">
    </span>
    <span class="link-title">Home</span>
  </a>

  <a href="#" class="link">
    <span class="link-icon">
      <img src="../public/assets/images/market.svg" alt="">
    </span>
    <span class="link-title">Market</span>
  </a>

  <a href="forum.php" class="link">
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
      <?php if (isset($_SESSION['user_id'])): ?>
        <a href="profile.php">My Profile</a>
        <a href="logout.php">Logout</a>
      <?php else: ?>
        <a href="login.php">Login</a>
        <a href="register.php">Register</a>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- Forum Section -->
<div class="forum">
  <div class="forum-container">
    <h2>Community Forum</h2>
    <section class="forum-section">
      <div class="container">
        <!-- Thread creation form (only for logged in users) -->
        <?php if (isset($_SESSION['user_id'])): ?>
          <form action="create_thread.php" method="POST" class="mb-4">
            <div class="mb-3">
              <input type="text" name="title" class="form-control" placeholder="Thread Title" required>
            </div>
            <div class="mb-3">
              <textarea name="body" class="form-control" placeholder="Your question or discussion topic..." rows="4" required></textarea>
            </div>
            <!-- Add category field to the creation form -->
            <div class="mb-3">
              <label for="category">Category:</label>
              <select name="category" id="category" class="form-control" required>
                <option value="general">General</option>
                <option value="announcements">Announcements</option>
                <option value="questions">Questions</option>
                <!-- Add more options as needed -->
              </select>
            </div>
            <button type="submit" class="btn btn-primary">Post Thread</button>
          </form>
        <?php else: ?>
          <p class="login-in">Please <a href="login.php">log in</a> to post a thread.</p>
        <?php endif; ?>
 
        <!-- Filter Form -->
        <form method="GET" action="forum.php" style="margin-bottom: 20px;">
          <input type="text" name="search" placeholder="Search threads..." value="<?= htmlspecialchars($search) ?>">
          <select name="category">
            <option value="">All Categories</option>
            <option value="announcements" <?= ($filter_category == 'announcements') ? 'selected' : '' ?>>Announcements</option>
            <option value="questions" <?= ($filter_category == 'questions') ? 'selected' : '' ?>>Questions</option>
            <!-- Add more options as needed -->
          </select>
          <button type="submit">Apply Filters</button>
        </form>
        
        <!-- Forum Threads List -->
        <div class="forum-threads">
          <?php
            if ($result && $result->num_rows > 0):
              while ($row = $result->fetch_assoc()):
                $thread_id = $row['id'];
                $profile_pic = !empty($row['profile_pic'])
                  ? $row['profile_pic']
                  : '/aftermarket_toolbox/uploads/default_profile.jpg';
          ?>
          <div class="forumcard">
            <div class="card-body">
              <div class="forum-profile">
                <img src="<?= htmlspecialchars($profile_pic) ?>" 
                     alt="<?= htmlspecialchars($row['username']) ?>" 
                     class="profile-pic">
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
              
              <!-- Responses for this thread -->
              <div class="forum-responses">
                <?php
                  $sql_res = "
                    SELECT responses.*, users.username, users.profile_picture AS response_profile_pic 
                    FROM forum_replies AS responses 
                    JOIN users ON responses.user_id = users.id 
                    WHERE responses.thread_id = ? 
                    ORDER BY responses.created_at ASC
                  ";
                  if ($stmt_res = $conn->prepare($sql_res)) {
                    $stmt_res->bind_param("i", $thread_id);
                    $stmt_res->execute();
                    $res_result = $stmt_res->get_result();
                    
                    if ($res_result && $res_result->num_rows > 0):
                      while ($response = $res_result->fetch_assoc()):
                        $response_pic = !empty($response['response_profile_pic'])
                          ? $response['response_profile_pic']
                          : '/aftermarket_toolbox/uploads/default_profile.jpg';
                ?>
                <div class="forum-response">
                  <img src="<?= htmlspecialchars($response_pic) ?>" 
                       alt="<?= htmlspecialchars($response['username']) ?>" 
                       class="response-profile-pic" width="30" height="30">
                  <span class="response-username"><?= htmlspecialchars($response['username']) ?></span>
                  <p class="response-body"><?= nl2br(htmlspecialchars($response['body'])) ?></p>
                </div>
                <?php 
                      endwhile;
                    else:
                      echo "<p class='no-responses'>No responses yet.</p>";
                    endif;
                    $stmt_res->close();
                  }
                ?>
              </div>
              <!-- End Responses -->
            </div>
          </div>
          <?php 
              endwhile;
            else:
              echo "<p>No forum posts yet. Be the first to ask a question!</p>";
            endif;
          ?>
        </div>

        <!-- Pagination -->
        <div class="pagination">
          <?php if ($page > 1): ?>
            <a href="forum.php?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>&category=<?= urlencode($filter_category) ?>"> Back</a>
          <?php endif; ?>

          <?php for ($i = 1; $i <= $totalPages; $i++): ?>
            <?php if ($i == $page): ?>
              <strong><?= $i ?></strong>
            <?php else: ?>
              <a href="forum.php?page=<?= $i ?>&search=<?= urlencode($search) ?>&category=<?= urlencode($filter_category) ?>"><?= $i ?></a>
            <?php endif; ?>
          <?php endfor; ?>

          <?php if ($page < $totalPages): ?>
            <a href="forum.php?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>&category=<?= urlencode($filter_category) ?>">Next </a>
          <?php endif; ?>
        </div>

      </div>
    </section>
  </div>
</div>

<!-- Profile Dropdown JS -->
<script>
  function toggleProfileDropdown(event) {
    event.preventDefault();
    const profileContainer = document.querySelector('.profile-container');
    profileContainer.classList.toggle('active');
  }
  
  document.addEventListener('click', function(e) {
    const profileContainer = document.querySelector('.profile-container');
    if (!profileContainer.contains(e.target)) {
      profileContainer.classList.remove('active');
    }
  });
  
  window.addEventListener('blur', function() {
    document.querySelector('.profile-container').classList.remove('active');
  });
</script>
</body>
</html>
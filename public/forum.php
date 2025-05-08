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
  <style>
    /* Delete response button styling */
    .delete-response-btn {
      background-color: transparent;
      border: none;
      color: #dc3545;
      cursor: pointer;
      padding: 2px 4px;
      position: absolute;
      top: 8px;
      right: 8px;
      opacity: 0.7;
      transition: opacity 0.2s;
    }

    .delete-response-btn:hover {
      opacity: 1;
    }

    .forum-response {
      position: relative;
    }

    .delete-response-form {
      position: absolute;
      top: 5px;
      right: 5px;
    }
  </style>
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

  <a href="../public/marketplace.php" class="link">
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
        <!-- Display success/error messages -->
        <?php if (isset($_SESSION['success'])): ?>
          <div class="alert alert-success">
            <?= htmlspecialchars($_SESSION['success']) ?>
            <?php unset($_SESSION['success']); ?>
          </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
          <div class="alert alert-danger">
            <?= htmlspecialchars($_SESSION['error']) ?>
            <?php unset($_SESSION['error']); ?>
          </div>
        <?php endif; ?>

        <!-- Thread creation form (only for logged in users) -->
        <?php if (isset($_SESSION['user_id'])): ?>
            <div class="create-thread-section">
            <a href="create_forum.php" class="btn btn-primary">Post a Thread</a>
            </div>
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

                <!-- Add Response Button (only for logged-in users) -->
                <?php if (isset($_SESSION['user_id'])): ?>
                  <div class="response-section">
                    <button class="response-btn" onclick="toggleResponseForm('form-<?= $thread_id ?>')">Add Response</button>
                    
                    <form id="form-<?= $thread_id ?>" class="response-form" style="display: none;" method="POST" action="../api/forum_threads/add_response.php">
                      <input type="hidden" name="thread_id" value="<?= $thread_id ?>">
                      <textarea name="response_body" rows="3" placeholder="Type your response here..." required></textarea>
                      <button type="submit" class="submit-response">Submit</button>
                      <button type="button" class="cancel-response" onclick="toggleResponseForm('form-<?= $thread_id ?>')">Cancel</button>
                    </form>
                  </div>
                <?php endif; ?>
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
                  
                  <?php if (isset($_SESSION['user_id']) && $_SESSION['user_id'] == $response['user_id']): ?>
                    <form method="POST" action="../api/forum_threads/delete_response.php" class="delete-response-form" onsubmit="return confirm('Are you sure you want to delete this response?');">
                      <input type="hidden" name="response_id" value="<?= $response['id'] ?>">
                      <input type="hidden" name="thread_id" value="<?= $thread_id ?>">
                      <button type="submit" class="delete-response-btn">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor">
                          <path d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"/>
                        </svg>
                      </button>
                    </form>
                  <?php endif; ?>
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

  function toggleResponseForm(formId) {
    const form = document.getElementById(formId);
    if (form.style.display === "none" || !form.style.display) {
      form.style.display = "block";
    } else {
      form.style.display = "none";
    }
  }
</script>
</body>
</html>
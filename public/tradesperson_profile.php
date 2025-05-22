<?php
// File: tradesperson_profile.php
// Profile page for tradespeople to manage their skills and certifications

session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/image_helper.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    $_SESSION['error'] = "You must be logged in to view this page.";
    header("Location: login.php");
    exit();
}

// Determine if viewing own profile or someone else's
$viewingSelf = true;
$viewedUserId = $_SESSION['user_id'];

// If a user_id is provided in the URL, we're viewing someone else's profile
if (isset($_GET['user_id']) && is_numeric($_GET['user_id'])) {
    $requestedUserId = (int)$_GET['user_id'];
    
    // Don't process if trying to view your own profile with the user_id parameter
    if ($requestedUserId !== $_SESSION['user_id']) {
        $viewingSelf = false;
        $viewedUserId = $requestedUserId;
    }
}

// Get user details
$userStmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$userStmt->bind_param("i", $viewedUserId);
$userStmt->execute();
$user = $userStmt->get_result()->fetch_assoc();

// If user doesn't exist, redirect to own profile
if (!$user) {
    $_SESSION['error'] = "User not found.";
    header("Location: tradesperson_profile.php");
    exit();
}

// Get user skills
$skillsStmt = $conn->prepare("
    SELECT * FROM tradesperson_skills
    WHERE user_id = ?
    ORDER BY created_at DESC
");
$skillsStmt->bind_param("i", $viewedUserId);
$skillsStmt->execute();
$skills = $skillsStmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get application statistics
$statsStmt = $conn->prepare("
    SELECT 
        COUNT(*) AS total_applications,
        SUM(CASE WHEN status = 'accepted' THEN 1 ELSE 0 END) AS accepted_jobs,
        SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) AS rejected_jobs,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) AS pending_jobs
    FROM job_applications
    WHERE user_id = ?
");
$statsStmt->bind_param("i", $viewedUserId);
$statsStmt->execute();
$stats = $statsStmt->get_result()->fetch_assoc();

// Current section for nav highlighting
$current_section = 'profile';
?>



<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tradesperson Profile - Aftermarket Toolbox</title>
    <link rel="stylesheet" href="./assets/css/tradesperson_profile.css">
    <link rel="stylesheet" href="./assets/css/notifications.css">
    <!-- Add Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
    <div class="menu">
  <a href="../index.php" class="link">
    <span class="link-icon">
      <img src="./assets/images/home-icon.svg" alt="Home">
    </span>
    <span class="link-title">Home</span>
  </a>

  <!-- Market with dropdown -->
  <div class="profile-container">
    <a href="#" class="link" onclick="toggleDropdown(this, event)">
      <span class="link-icon">
        <img src="./assets/images/market.svg" alt="Market">
      </span>
      <span class="link-title">Market</span>
    </a>    <div class="dropdown-content">
      <button class="value" onclick="window.location.href='./marketplace.php?view=explore';"><img src="./assets/images/exploreicon.svg" alt="Explore">Explore</button>
      <button class="value" onclick="window.location.href='../api/listings/view_listings.php';"><img src="./assets/images/view_listingicon.svg" alt="View Listings">View Listings</button>
      <button class="value" onclick="window.location.href='../api/listings/create_listing.php';"><img src="./assets/images/list_itemicon.svg" alt="Create Listing">List Item</button>
      <button class="value" onclick="window.location.href='./saved_listings.php';"><img src="./assets/images/savedicons.svg" alt="Saved">Saved Items</button>
    </div>
  </div>
  
  <!-- Forum dropdown -->
  <div class="profile-container">
    <a href="#" class="link" onclick="toggleDropdown(this, event)">      <span class="link-icon">
        <img src="./assets/images/forum-icon.svg" alt="Forum">
        <?php if (isset($_SESSION['user_id']) && isset($notificationCounts['forum_responses']) && $notificationCounts['forum_responses'] > 0): ?>
          <span class="notification-badge forum"><?= $notificationCounts['forum_responses'] ?></span>
        <?php endif; ?>
      </span>
      <span class="link-title">Forum</span>
    </a>    <div class="dropdown-content">
      <button class="value" onclick="window.location.href='./forum.php?view=threads';"><img src="./assets/images/view_threadicon.svg" alt="Forum">View Threads</button>
      <button class="value" onclick="window.location.href='./forum.php?view=start_thread';"><img src="./assets/images/start_threadicon.svg" alt="Start Thread">Start Thread</button>
      <button class="value" onclick="window.location.href='./forum.php?view=post_question';"><img src="./assets/images/start_threadicon.svg" alt="Post Question">Post Question</button>
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
    <div id="profileDropdown" class="dropdown-content">
      <?php if (isset($_SESSION['user_id'])): ?>        <button class="value" onclick="window.location.href='./profile.php';">
          <img src="./assets/images/profile-icon.svg" alt="Profile">Account
        </button>
        <button class="value" onclick="window.location.href='../api/listings/view_listings.php';"><img src="./assets/images/mylistingicon.svg" alt="Market">My Listings</button>
        <button class="value" onclick="window.location.href='./saved_listings.php';"><img src="./assets/images/savedicons.svg" alt="Saved">Saved Items</button>
        <button class="value" onclick="window.location.href='./friends.php';"><img src="./assets/images/friendsicon.svg" alt="Friends">Friends
          <?php if (isset($_SESSION['user_id']) && isset($notificationCounts['friend_requests']) && $notificationCounts['friend_requests'] > 0): ?>
            <span class="notification-badge friends"><?= $notificationCounts['friend_requests'] ?></span>
          <?php endif; ?>
        </button>
        <button class="value" onclick="window.location.href='./logout.php';"><img src="./assets/images/Log_Outicon.svg" alt="Logout">Logout</button>
      <?php else: ?>
        <button class="value" onclick="window.location.href='./login.php';">Login</button>
        <button class="value" onclick="window.location.href='./register.php';">Register</button>
      <?php endif; ?>
    </div>
  </div>

  <?php if (isset($_SESSION['user_id'])): ?>    <a href="./chat.php" class="link">
      <span class="link-icon">
        <img src="./assets/images/chat-icon.svg" alt="Chat">
        <?php if (isset($_SESSION['user_id']) && isset($notificationCounts['messages']) && $notificationCounts['messages'] > 0): ?>
          <span class="notification-badge messages"><?= $notificationCounts['messages'] ?></span>
        <?php endif; ?>
      </span>
      <span class="link-title">Chat</span>
    </a>

    <div class="profile-container">
    <a href="#" class="link <?= $current_section === 'jobs' ? 'active' : '' ?>" onclick="toggleDropdown(this, event)">
      <span class="link-icon">
        <img src="./assets/images/job-icon.svg" alt="Jobs">
      </span>
      <span class="link-title">Jobs</span>
    </a>
    <div class="dropdown-content">
      <button class="value" onclick="window.location.href='./jobs.php';"><img src="./assets/images/exploreicon.svg" alt="Explore">
        Explore</button>
      <button class="value" onclick="window.location.href='./jobs.php?action=post';"><img src="./assets/images/post_job_icon.svg" alt="Create Job">
        Post Job</button>
      <button class="value" onclick="window.location.href='./jobs.php?action=my_applications';"><img src="./assets/images/my_applications_icon.svg" alt="My Applications">
        My Applications</button>
    </div>
  </div>
    <!-- Notifications Dropdown -->
    <div class="notifications-container">
      <button id="notificationsBtn" class="notification-btn">
        <i class="fas fa-bell"></i>
        <?php if (isset($notificationCounts['total']) && $notificationCounts['total'] > 0): ?>
          <span id="notification-badge"><?= $notificationCounts['total'] ?></span>
        <?php endif; ?>
      </button>
      <div id="notificationsDropdown" class="notifications-dropdown">
        <div class="notifications-header">
          <h3>Notifications</h3>
          <?php if (isset($notificationCounts['total']) && $notificationCounts['total'] > 0): ?>
            <button id="markAllReadBtn" class="mark-all-read">Mark all as read</button>
          <?php endif; ?>
        </div>        <div class="notifications-list">
          <!-- Notifications will be loaded here via JavaScript -->
          <div class="no-notifications">Loading notifications...</div>
        </div>
        <div class="notifications-footer">
          <a href="./notifications.php" class="view-all-link">See All Notifications</a>
        </div>
      </div>
    </div>
    <?php endif; ?>

  </div>

    <div class="main-content">
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
        
        <div class="profile-header">
            <div class="profile-avatar">
                <img src="<?= getProfilePicture($user['profile_picture']) ?>" alt="<?= htmlspecialchars($user['username']) ?>" class="avatar-img">
            </div>
            <div class="profile-info">
                <h1><?= htmlspecialchars($user['username']) ?>'s Profile</h1>
                <p><i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($user['location'] ?? 'Location not set') ?></p>
                <p><i class="fas fa-envelope"></i> <?= htmlspecialchars($user['email']) ?></p>
                <div class="profile-stats">
                    <div class="stat-item">
                        <span class="stat-value"><?= $stats['total_applications'] ?? 0 ?></span>
                        <span class="stat-label">Total Applications</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-value"><?= $stats['accepted_jobs'] ?? 0 ?></span>
                        <span class="stat-label">Jobs Completed</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-value"><?= $stats['pending_jobs'] ?? 0 ?></span>
                        <span class="stat-label">Pending Applications</span>
                    </div>
                </div>
            </div>                <div class="profile-actions">
                    <a href="javascript:history.back()" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Back</a>
                    <?php if ($viewingSelf): ?>
                        <a href="profile_edit.php" class="btn btn-secondary"><i class="fas fa-edit"></i> Edit Profile</a>
                        <a href="jobs.php?my=applications" class="btn btn-primary"><i class="fas fa-briefcase"></i> My Job Applications</a>
                    <?php else: ?>
                        <a href="profile.php?user_id=<?= $viewedUserId ?>" class="btn btn-secondary"><i class="fas fa-user"></i> View Full Profile</a>
                        <a href="chat.php?chat=<?= $viewedUserId ?>" class="btn btn-primary"><i class="fas fa-envelope"></i> Send Message</a>
                    <?php endif; ?>
                </div>
        </div>
        
        <!-- Skills Section -->        <div class="skills-section">
            <h2>Skills & Certifications</h2>
            <div class="skills-container">
                <?php if (empty($skills)): ?>
                    <div class="no-skills-message">
                        <?php if ($viewingSelf): ?>
                            <p>You haven't added any skills yet. Add your skills and certifications to improve your chances of getting hired.</p>
                        <?php else: ?>
                            <p><?= htmlspecialchars($user['username']) ?> hasn't added any skills yet.</p>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <table class="skills-list-table">
                        <thead>
                            <tr>
                                <th>Skill</th>
                                <th>Experience Level</th>
                                <th>Certification</th>
                                <th>Verified</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($skills as $skill): ?>
                                <tr>
                                    <td><?= htmlspecialchars($skill['skill_name']) ?></td>
                                    <td><?= htmlspecialchars($skill['experience_level']) ?></td>
                                    <td>
                                        <?php if (!empty($skill['certification_file'])): ?>
                                            <a href="<?= htmlspecialchars($skill['certification_file']) ?>" class="certification-link" target="_blank">
                                                <i class="fas fa-file-alt"></i> View Certificate
                                            </a>
                                        <?php else: ?>
                                            <span class="no-cert">No certification uploaded</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($skill['is_verified']): ?>
                                            <span class="verified-badge" title="Verified skill"><i class="fas fa-check-circle"></i> Verified</span>
                                        <?php else: ?>
                                            <span class="unverified-badge">Not verified</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <form action="../api/jobs/manage_skills.php" method="POST">
                                            <input type="hidden" name="action" value="delete_skill">
                                            <input type="hidden" name="skill_id" value="<?= $skill['id'] ?>">
                                            <button type="submit" class="delete-skill-btn" onclick="return confirm('Are you sure you want to delete this skill?');">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
                  <!-- Add Skill Form -->
                <div class="add-skill-form">
                    <?php if ($viewingSelf): ?>
                    <h3>Add New Skill</h3>
                    <form action="../api/jobs/manage_skills.php" method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="add_skill">
                        
                        <div class="form-group">
                            <label for="skill_name">Skill Name</label>
                            <input type="text" id="skill_name" name="skill_name" required placeholder="e.g., Automotive Repair, Welding, Carpentry">
                        </div>
                        
                        <div class="form-group">
                            <label for="experience_level">Experience Level</label>
                            <select id="experience_level" name="experience_level" required>
                                <option value="">Select your experience level</option>
                                <option value="Beginner">Beginner (0-1 years)</option>
                                <option value="Intermediate">Intermediate (1-3 years)</option>
                                <option value="Advanced">Advanced (3-5 years)</option>
                                <option value="Expert">Expert (5+ years)</option>
                            </select>
                        </div>
                        
                        <div class="form-group certification-upload">
                            <label for="certification_file">Certification (Optional)</label>
                            <input type="file" id="certification_file" name="certification_file">
                            <span class="form-hint">Upload certifications, licenses, or proof of qualifications (PDF, JPG, PNG, DOC).</span>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">Add Skill</button>
                        </div>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Job History Section -->
        <div class="job-history-section">
            <h2>Job History</h2>
            <div class="job-history-container">
                <div class="tab-buttons">
                    <button class="tab-btn active" data-tab="completed">Completed Jobs</button>
                    <button class="tab-btn" data-tab="pending">Pending Applications</button>
                </div>
                  <div class="tab-content active" id="completed-tab">
                    <?php
                    // Get completed jobs
                    $completedQuery = "SELECT ja.*, j.title, j.location, j.compensation, u.username as employer_name
                        FROM job_applications ja
                        JOIN jobs j ON ja.job_id = j.id
                        JOIN users u ON j.user_id = u.id
                        WHERE ja.user_id = ? AND ja.status = 'accepted' AND j.status = 'completed'
                        ORDER BY j.updated_at DESC";
                    $completedStmt = $conn->prepare($completedQuery);
                    $completedStmt->bind_param("i", $viewedUserId);
                    $completedStmt->execute();
                    $completedJobs = $completedStmt->get_result()->fetch_all(MYSQLI_ASSOC);
                    ?>
                    
                    <?php if (empty($completedJobs)): ?>
                        <div class="no-jobs-message">
                            <p>You haven't completed any jobs yet.</p>
                        </div>
                    <?php else: ?>
                        <div class="job-history-list">
                            <?php foreach ($completedJobs as $job): ?>
                                <div class="job-history-item">
                                    <div class="job-history-header">
                                        <h3><?= htmlspecialchars($job['title']) ?></h3>
                                        <div class="job-history-date">
                                            Completed: <?= date('M j, Y', strtotime($job['updated_at'])) ?>
                                        </div>
                                    </div>
                                    <div class="job-history-details">
                                        <div class="detail-item">
                                            <i class="fas fa-user"></i>
                                            <span>Employer: <?= htmlspecialchars($job['employer_name']) ?></span>
                                        </div>
                                        <div class="detail-item">
                                            <i class="fas fa-map-marker-alt"></i>
                                            <span>Location: <?= htmlspecialchars($job['location']) ?></span>
                                        </div>
                                        <div class="detail-item">
                                            <i class="fas fa-dollar-sign"></i>
                                            <span>Compensation: <?= htmlspecialchars($job['compensation']) ?></span>
                                        </div>
                                        <?php if ($job['bid_amount']): ?>
                                            <div class="detail-item">
                                                <i class="fas fa-hand-holding-usd"></i>
                                                <span>Your Bid: $<?= number_format($job['bid_amount'], 2) ?></span>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="job-history-actions">
                                        <a href="jobs.php?job_id=<?= $job['job_id'] ?>" class="btn btn-secondary">View Job Details</a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
                  <div class="tab-content" id="pending-tab">
                    <?php
                    // Get pending applications
                    $pendingQuery = "SELECT ja.*, j.title, j.location, j.compensation, u.username as employer_name
                        FROM job_applications ja
                        JOIN jobs j ON ja.job_id = j.id
                        JOIN users u ON j.user_id = u.id
                        WHERE ja.user_id = ? AND ja.status = 'pending'
                        ORDER BY ja.created_at DESC";
                    $pendingStmt = $conn->prepare($pendingQuery);
                    $pendingStmt->bind_param("i", $viewedUserId);
                    $pendingStmt->execute();
                    $pendingJobs = $pendingStmt->get_result()->fetch_all(MYSQLI_ASSOC);
                    ?>
                    
                    <?php if (empty($pendingJobs)): ?>
                        <div class="no-jobs-message">
                            <p>You don't have any pending applications.</p>
                        </div>
                    <?php else: ?>
                        <div class="job-history-list">
                            <?php foreach ($pendingJobs as $job): ?>
                                <div class="job-history-item">
                                    <div class="job-history-header">
                                        <h3><?= htmlspecialchars($job['title']) ?></h3>
                                        <div class="job-history-date">
                                            Applied: <?= date('M j, Y', strtotime($job['created_at'])) ?>
                                        </div>
                                    </div>
                                    <div class="job-history-details">
                                        <div class="detail-item">
                                            <i class="fas fa-user"></i>
                                            <span>Employer: <?= htmlspecialchars($job['employer_name']) ?></span>
                                        </div>
                                        <div class="detail-item">
                                            <i class="fas fa-map-marker-alt"></i>
                                            <span>Location: <?= htmlspecialchars($job['location']) ?></span>
                                        </div>
                                        <div class="detail-item">
                                            <i class="fas fa-dollar-sign"></i>
                                            <span>Compensation: <?= htmlspecialchars($job['compensation']) ?></span>
                                        </div>
                                        <?php if ($job['bid_amount']): ?>
                                            <div class="detail-item">
                                                <i class="fas fa-hand-holding-usd"></i>
                                                <span>Your Bid: $<?= number_format($job['bid_amount'], 2) ?></span>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="job-history-actions">
                                        <a href="jobs.php?job_id=<?= $job['job_id'] ?>" class="btn btn-secondary">View Job Details</a>
                                        <form action="../api/jobs/withdraw_application.php" method="POST" style="display: inline;">
                                            <input type="hidden" name="application_id" value="<?= $job['id'] ?>">
                                            <button type="submit" class="btn btn-danger" onclick="return confirm('Are you sure you want to withdraw this application?');">
                                                Withdraw Application
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

   
<!-- Profile Dropdown JS -->
<script>
  
  const delay = 100; // Delay in milliseconds

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
  
  // Initialize notification system
  if (document.getElementById('notificationsBtn')) {
    initNotificationSystem();
    // Initial fetch
    fetchNotifications();
    // Poll for notifications every 60 seconds
    setInterval(fetchNotifications, 60000);
  }
  
  // Fetch notifications via AJAX
  function fetchNotifications() {
    fetch('./api/notifications.php')
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          updateNotificationBadge(data.counts.total || 0);
          updateNotificationDropdown(data.notifications || []);
        }
      })
      .catch(error => {
        console.error('Error fetching notifications:', error);
      });
  }
  
  // Update the notification badge count
  function updateNotificationBadge(count) {
    const badge = document.getElementById('notification-badge');
    
    if (!badge) return;
    
    if (count > 0) {
      badge.style.display = 'inline-flex';
      badge.textContent = count;
    } else {
      badge.style.display = 'none';
    }
  }
  
  // Update the notification dropdown content
  function updateNotificationDropdown(notifications) {
    const list = document.querySelector('.notifications-list');
    
    if (!list) return;
    
    // If no notifications, show a message
    if (!notifications || notifications.length === 0) {
      list.innerHTML = '<div class="no-notifications">No new notifications</div>';
      return;
    }
    
    let html = '';
    const maxToShow = 5;
    
    // Build notification items HTML
    for (let i = 0; i < Math.min(notifications.length, maxToShow); i++) {
      const notification = notifications[i];
      const isUnread = !notification.is_read;
      const unreadClass = isUnread ? 'unread' : '';
      
      html += `<div class="notification-item ${unreadClass}" data-id="${notification.id}" data-type="${notification.type}" data-related-id="${notification.related_id || ''}">`;
      
      // Notification icon based on type
      let iconClass = 'fa-bell';
      switch (notification.type) {
        case 'friend_request': iconClass = 'fa-user-plus'; break;
        case 'message': iconClass = 'fa-envelope'; break;
        case 'forum_response': iconClass = 'fa-comments'; break;
        case 'listing_comment': iconClass = 'fa-tag'; break;
      }
      
      html += `<div class="notification-icon"><i class="fas ${iconClass}"></i></div>`;
      html += '<div class="notification-content">';
      html += `<div class="notification-text">${notification.content}</div>`;
      html += `<div class="notification-time">${formatTimeAgo(notification.created_at)}</div>`;
      html += '</div>';
      
      if (isUnread) {
        html += '<div class="notification-mark-read"><i class="fas fa-check"></i></div>';
      }
      
      html += `<a href="${getNotificationLink(notification.type, notification.related_id)}" class="notification-link"></a>`;
      html += '</div>';
    }
    
    // If there are more notifications than we're showing, add a "view all" link
    if (notifications.length > maxToShow) {
      html += '<div class="notification-item show-all">';
      html += `<a href="./notifications.php">View all notifications</a>`;
      html += '</div>';
    }
    
    list.innerHTML = html;
  }
  
  // Get the appropriate link for a notification
  function getNotificationLink(type, relatedId) {
    switch(type) {
      case 'friend_request':
        return './friends.php';
      case 'message':
        return relatedId ? `./chat.php?chat=${relatedId}` : './chat.php';
      case 'forum_response':
        return relatedId ? `./forum.php?thread=${relatedId}` : './forum.php';
      case 'listing_comment':
        return relatedId ? `./marketplace.php?listing=${relatedId}` : './marketplace.php';
      default:
        return './notifications.php';
    }
  }
  
  // Format timestamp as time ago text
  function formatTimeAgo(timestamp) {
    const date = new Date(timestamp);
    const now = new Date();
    const seconds = Math.floor((now - date) / 1000);
    
    if (seconds < 60) {
      return 'just now';
    }
    
    const minutes = Math.floor(seconds / 60);
    if (minutes < 60) {
      return minutes + ' minute' + (minutes !== 1 ? 's' : '') + ' ago';
    }
    
    const hours = Math.floor(minutes / 60);
    if (hours < 24) {
      return hours + ' hour' + (hours !== 1 ? 's' : '') + ' ago';
    }
    
    const days = Math.floor(hours / 24);
    if (days < 30) {
      return days + ' day' + (days !== 1 ? 's' : '') + ' ago';
    }
    
    const months = Math.floor(days / 30);
    if (months < 12) {
      return months + ' month' + (months !== 1 ? 's' : '') + ' ago';
    }
    
    return Math.floor(months / 12) + ' year' + (Math.floor(months / 12) !== 1 ? 's' : '') + ' ago';
  }
  
  // Initialize notification system
  function initNotificationSystem() {
    const notificationsBtn = document.getElementById('notificationsBtn');
    const notificationsDropdown = document.getElementById('notificationsDropdown');
    const markAllReadBtn = document.getElementById('markAllReadBtn');
    
    // Toggle dropdown when clicking on the notification button
    if (notificationsBtn) {
      notificationsBtn.addEventListener('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        notificationsDropdown.classList.toggle('show');
        
        // Fetch fresh notifications when opening dropdown
        if (notificationsDropdown.classList.contains('show')) {
          fetchNotifications();
        }
      });
    }
    
    // Close dropdown when clicking outside
    document.addEventListener('click', function(e) {
      if (notificationsDropdown && 
          !notificationsBtn.contains(e.target) && 
          !notificationsDropdown.contains(e.target)) {
        notificationsDropdown.classList.remove('show');
      }
    });
    
    // Mark all notifications as read
    if (markAllReadBtn) {
      markAllReadBtn.addEventListener('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        fetch('./api/notifications.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
          },
          body: 'action=mark_all_read'
        })
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            // Update UI
            document.querySelectorAll('.notification-item.unread').forEach(item => {
              item.classList.remove('unread');
            });
            document.querySelectorAll('.notification-mark-read').forEach(btn => {
              btn.remove();
            });
            updateNotificationBadge(0);
          }
        })
        .catch(error => console.error('Error marking all as read:', error));
      });
    }
  }
  
  // Toggle response form visibility
  function toggleResponseForm(formId) {
    const form = document.getElementById(formId);
    if (form.style.display === 'none') {
      form.style.display = 'block';
    } else {
      form.style.display = 'none';
    }
  }

  // Toggle remaining responses visibility
  function toggleRemainingResponses(button, threadId) {
    const remainingResponses = document.getElementById('remaining-responses-' + threadId);
    if (remainingResponses.style.display === 'none') {
      remainingResponses.style.display = 'block';
      button.textContent = 'Hide Responses';
    } else {
      remainingResponses.style.display = 'none';
      button.textContent = 'See All Responses';
    }
  }
    // Toggle response text expansion
  function toggleResponseText(button) {
    const responseBody = button.closest('.response-content').querySelector('.response-body');
    const fadeOverlay = button.closest('.response-content').querySelector('.fade-overlay');
    
    if (responseBody.classList.contains('collapsed')) {
      // Expand
      responseBody.classList.remove('collapsed');
      responseBody.classList.add('expanded');
      fadeOverlay.style.display = 'none';
      button.textContent = 'See less';
    } else {
      // Collapse
      responseBody.classList.add('collapsed');
      responseBody.classList.remove('expanded');
      fadeOverlay.style.display = 'block';
      button.textContent = 'See more';
    }
  }
</script>

    <script src="./assets/js/notifications.js"></script>
    <script>
        // Toggle dropdown for filters on mobile
        const filterToggle = document.querySelector('.filter-toggle');
        const filterForm = document.querySelector('.filter-form');
        
        if (filterToggle && filterForm) {
            filterToggle.addEventListener('click', function() {
                filterForm.classList.toggle('active');
            });
        }
        
        // Initialize notification system
        document.addEventListener('DOMContentLoaded', function() {
            if (document.getElementById('notificationsBtn')) {
                initNotificationSystem();
                // Poll for notifications every 60 seconds
                setInterval(fetchNotifications, 60000);
            }
        });
    </script>
    <script>
        // Tab switching functionality
        document.addEventListener('DOMContentLoaded', function() {
            const tabButtons = document.querySelectorAll('.tab-btn');
            const tabContents = document.querySelectorAll('.tab-content');
            
            tabButtons.forEach(button => {
                button.addEventListener('click', function() {
                    // Remove active class from all buttons and contents
                    tabButtons.forEach(btn => btn.classList.remove('active'));
                    tabContents.forEach(content => content.classList.remove('active'));
                    
                    // Add active class to clicked button
                    this.classList.add('active');
                    
                    // Show corresponding content
                    const tabId = this.getAttribute('data-tab');
                    document.getElementById(tabId + '-tab').classList.add('active');
                });
            });
        });
    </script>
</body>
</html>
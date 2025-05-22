<?php
// File: jobs.php
// Main job board page for viewing and interacting with job listings

session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/image_helper.php';

// Set current section for menu highlighting
$current_section = 'jobs';

// Determine the action to take
$action = $_GET['action'] ?? 'list';
$job_id = isset($_GET['job_id']) ? filter_input(INPUT_GET, 'job_id', FILTER_VALIDATE_INT) : null;

// Initialize variables
$job = null;
$applications = [];
$user_has_applied = false;
$is_job_owner = false;

// Get job details if job_id is provided
if ($job_id) {
    $jobStmt = $conn->prepare("
        SELECT j.*, u.username, u.profile_picture, 
               (SELECT COUNT(*) FROM job_applications WHERE job_id = j.id) as application_count 
        FROM jobs j
        JOIN users u ON j.user_id = u.id
        WHERE j.id = ?
    ");
    $jobStmt->bind_param("i", $job_id);
    $jobStmt->execute();
    $jobResult = $jobStmt->get_result();
    
    if ($jobResult->num_rows > 0) {
        $job = $jobResult->fetch_assoc();
        
        // Check if user is the job owner
        if (isset($_SESSION['user_id']) && $_SESSION['user_id'] == $job['user_id']) {
            $is_job_owner = true;
            
            // Get applications for this job if user is the owner
            $appStmt = $conn->prepare("
                SELECT a.*, u.username, u.profile_picture, u.email, u.location,
                       (SELECT COUNT(*) FROM job_applications WHERE user_id = a.user_id AND status = 'accepted') as completed_jobs
                FROM job_applications a
                JOIN users u ON a.user_id = u.id
                WHERE a.job_id = ?
                ORDER BY 
                    CASE a.status 
                        WHEN 'accepted' THEN 1 
                        WHEN 'pending' THEN 2 
                        ELSE 3 
                    END,
                    a.created_at DESC
            ");
            $appStmt->bind_param("i", $job_id);
            $appStmt->execute();
            $applications = $appStmt->get_result()->fetch_all(MYSQLI_ASSOC);
        } 
        // Check if user has already applied to this job
        else if (isset($_SESSION['user_id'])) {
            $checkAppStmt = $conn->prepare("
                SELECT id, status FROM job_applications 
                WHERE job_id = ? AND user_id = ?
            ");
            $checkAppStmt->bind_param("ii", $job_id, $_SESSION['user_id']);
            $checkAppStmt->execute();
            $checkResult = $checkAppStmt->get_result();
            
            if ($checkResult->num_rows > 0) {
                $user_has_applied = true;
                $application = $checkResult->fetch_assoc();
                $application_status = $application['status'];
            }
        }
    }
}

// Function to format dates nicely
function formatTimeAgo($datetime) {
    $time = strtotime($datetime);
    $now = time();
    $diff = $now - $time;
    
    if ($diff < 60) {
        return "just now";
    } else if ($diff < 3600) {
        $mins = floor($diff / 60);
        return $mins . " minute" . ($mins > 1 ? "s" : "") . " ago";
    } else if ($diff < 86400) {
        $hours = floor($diff / 3600);
        return $hours . " hour" . ($hours > 1 ? "s" : "") . " ago";
    } else if ($diff < 2592000) {
        $days = floor($diff / 86400);
        return $days . " day" . ($days > 1 ? "s" : "") . " ago";
    } else if ($diff < 31536000) {
        $months = floor($diff / 2592000);
        return $months . " month" . ($months > 1 ? "s" : "") . " ago";
    } else {
        $years = floor($diff / 31536000);
        return $years . " year" . ($years > 1 ? "s" : "") . " ago";
    }
}

// Function to check if date is expired
function isExpired($expires_at) {
    return strtotime($expires_at) < time();
}

// Get all jobs for listing view
$jobs = [];
if ($action === 'list' && !$job_id) {
    // Determine sorting and filtering
    $sort = $_GET['sort'] ?? 'newest';
    $category = $_GET['category'] ?? '';
    $status = $_GET['status'] ?? 'open';
    
    // Build query
    $query = "
        SELECT j.*, u.username, u.profile_picture, 
               (SELECT COUNT(*) FROM job_applications WHERE job_id = j.id) as application_count 
        FROM jobs j
        JOIN users u ON j.user_id = u.id
        WHERE 1=1
    ";
    
    $params = [];
    $param_types = "";
    
    // Add filters
    if (!empty($category)) {
        $query .= " AND j.category = ?";
        $params[] = $category;
        $param_types .= "s";
    }
    
    if ($status !== 'all') {
        $query .= " AND j.status = ?";
        $params[] = $status;
        $param_types .= "s";
    }
    
    // Add sorting
    switch ($sort) {
        case 'oldest':
            $query .= " ORDER BY j.created_at ASC";
            break;
        case 'highest_pay':
            $query .= " ORDER BY j.compensation DESC";
            break;
        case 'lowest_pay':
            $query .= " ORDER BY j.compensation ASC";
            break;
        case 'expiring_soon':
            $query .= " ORDER BY j.expires_at ASC";
            break;
        case 'newest':
        default:
            $query .= " ORDER BY j.created_at DESC";
    }
    
    $stmt = $conn->prepare($query);
    
    if (!empty($params)) {
        $stmt->bind_param($param_types, ...$params);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    $jobs = $result->fetch_all(MYSQLI_ASSOC);
}

// Get categories for filter dropdown
$categoriesQuery = "SELECT DISTINCT category FROM jobs ORDER BY category";
$categoriesResult = $conn->query($categoriesQuery);
$categories = [];
while ($row = $categoriesResult->fetch_assoc()) {
    $categories[] = $row['category'];
}

// Get my jobs if user is logged in
$my_jobs = [];
if (isset($_SESSION['user_id'])) {
    $myJobsStmt = $conn->prepare("
        SELECT j.*, 
               (SELECT COUNT(*) FROM job_applications WHERE job_id = j.id) as application_count 
        FROM jobs j
        WHERE j.user_id = ?
        ORDER BY j.created_at DESC
    ");
    $myJobsStmt->bind_param("i", $_SESSION['user_id']);
    $myJobsStmt->execute();
    $my_jobs = $myJobsStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    // Get my applications
    $myAppsStmt = $conn->prepare("
        SELECT a.*, j.title as job_title, j.status as job_status, u.username as employer_name
        FROM job_applications a
        JOIN jobs j ON a.job_id = j.id
        JOIN users u ON j.user_id = u.id
        WHERE a.user_id = ?
        ORDER BY a.created_at DESC
    ");
    $myAppsStmt->bind_param("i", $_SESSION['user_id']);
    $myAppsStmt->execute();
    $my_applications = $myAppsStmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Get user skills if user is logged in
$user_skills = [];
if (isset($_SESSION['user_id'])) {
    $skillsStmt = $conn->prepare("
        SELECT * FROM tradesperson_skills
        WHERE user_id = ?
        ORDER BY created_at DESC
    ");
    $skillsStmt->bind_param("i", $_SESSION['user_id']);
    $skillsStmt->execute();
    $user_skills = $skillsStmt->get_result()->fetch_all(MYSQLI_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Job Board - Aftermarket Toolbox</title>
    <link rel="stylesheet" href="./assets/css/jobs.css">
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
        </div>
        <div class="notifications-list">
          <!-- Notifications will be loaded here via JavaScript -->
          <div class="no-notifications">Loading notifications...</div>
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
        
        <?php if ($action === 'post'): ?>
            <!-- Job Posting Form -->
            <div class="job-form-container">
                <h1>Post a New Job</h1>
                <form action="../api/jobs/post_job.php" method="POST" class="job-form">
                    <div class="form-group">
                        <label for="title">Job Title</label>
                        <input type="text" id="title" name="title" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="category">Category</label>
                        <select id="category" name="category" required>
                            <option value="">Select a category</option>
                            <option value="Automotive">Automotive</option>
                            <option value="Construction">Construction</option>
                            <option value="Electrical">Electrical</option>
                            <option value="Plumbing">Plumbing</option>
                            <option value="HVAC">HVAC</option>
                            <option value="Carpentry">Carpentry</option>
                            <option value="Painting">Painting</option>
                            <option value="Welding">Welding</option>
                            <option value="Landscaping">Landscaping</option>
                            <option value="General Labor">General Labor</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="description">Job Description</label>
                        <textarea id="description" name="description" rows="5" required></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="requirements">Requirements</label>
                        <textarea id="requirements" name="requirements" rows="3" required></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="location">Location</label>
                        <input type="text" id="location" name="location" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="compensation">Compensation</label>
                        <input type="text" id="compensation" name="compensation" required placeholder="e.g., $25/hour, $500 flat rate">
                    </div>
                    
                    <div class="form-group">
                        <label for="expires_days">Job Listing Expires In</label>
                        <select id="expires_days" name="expires_days" required>
                            <option value="7">7 days</option>
                            <option value="14" selected>14 days</option>
                            <option value="30">30 days</option>
                            <option value="60">60 days</option>
                        </select>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">Post Job</button>
                        <a href="jobs.php" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        <?php elseif ($action === 'apply' && $job_id && $job && !$user_has_applied): ?>
            <!-- Job Application Form -->
            <div class="job-form-container">
                <h1>Apply for Job: <?= htmlspecialchars($job['title']) ?></h1>
                <div class="job-info-summary">
                    <p><strong>Posted by:</strong> <?= htmlspecialchars($job['username']) ?></p>
                    <p><strong>Location:</strong> <?= htmlspecialchars($job['location']) ?></p>
                    <p><strong>Compensation:</strong> <?= htmlspecialchars($job['compensation']) ?></p>
                </div>
                
                <form action="../api/jobs/apply_job.php" method="POST" class="job-form">
                    <input type="hidden" name="job_id" value="<?= $job_id ?>">
                    
                    <div class="form-group">
                        <label for="cover_letter">Cover Letter / Introduction</label>
                        <textarea id="cover_letter" name="cover_letter" rows="5" required 
                                  placeholder="Introduce yourself and explain why you're a good fit for this job."></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="bid_amount">Your Bid Amount (Optional)</label>
                        <input type="number" id="bid_amount" name="bid_amount" step="0.01" min="0" 
                               placeholder="Enter your bid amount in dollars">
                        <span class="form-hint">Leave blank to accept the job's listed compensation. Enter a custom amount to submit a different bid.</span>
                    </div>
                    
                    <div class="form-group">
                        <h3>Your Skills</h3>
                        <?php if (empty($user_skills)): ?>
                            <p class="no-skills-warning">You haven't added any skills to your profile yet. 
                               <a href="tradesperson_profile.php">Add skills</a> to improve your chances of being selected.</p>
                        <?php else: ?>
                            <div class="skills-list">
                                <?php foreach ($user_skills as $skill): ?>
                                    <div class="skill-item">
                                        <span class="skill-name"><?= htmlspecialchars($skill['skill_name']) ?></span>
                                        <span class="skill-level"><?= htmlspecialchars($skill['experience_level']) ?></span>
                                        <?php if ($skill['is_verified']): ?>
                                            <span class="verified-badge" title="Verified skill"><i class="fas fa-check-circle"></i></span>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">Submit Application</button>
                        <a href="jobs.php?job_id=<?= $job_id ?>" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        <?php elseif ($job_id && $job): ?>
            <!-- Job Details View -->
            <div class="job-details-container">
                <div class="job-details-header">
                    <h1><?= htmlspecialchars($job['title']) ?></h1>
                    <div class="job-status <?= $job['status'] ?>">
                        <?= ucfirst($job['status']) ?>
                    </div>
                </div>
                
                <div class="job-details-content">
                    <div class="job-main-info">
                        <div class="job-poster-info">
                            <img src="<?= getProfilePicture($job['profile_picture']) ?>" alt="<?= htmlspecialchars($job['username']) ?>" class="profile-pic">
                            <div class="poster-details">
                                <h3>Posted by: <?= htmlspecialchars($job['username']) ?></h3>
                                <p>Posted: <?= formatTimeAgo($job['created_at']) ?></p>
                                <p>Expires: <?= isExpired($job['expires_at']) ? 'Expired' : date('M j, Y', strtotime($job['expires_at'])) ?></p>
                            </div>
                        </div>
                        
                        <div class="job-key-details">
                            <div class="key-detail">
                                <i class="fas fa-map-marker-alt"></i>
                                <span><?= htmlspecialchars($job['location']) ?></span>
                            </div>
                            <div class="key-detail">
                                <i class="fas fa-tags"></i>
                                <span><?= htmlspecialchars($job['category']) ?></span>
                            </div>
                            <div class="key-detail">
                                <i class="fas fa-dollar-sign"></i>
                                <span><?= htmlspecialchars($job['compensation']) ?></span>
                            </div>
                            <div class="key-detail">
                                <i class="fas fa-users"></i>
                                <span><?= $job['application_count'] ?> application(s)</span>
                            </div>
                        </div>
                        
                        <div class="job-description">
                            <h3>Description</h3>
                            <p><?= nl2br(htmlspecialchars($job['description'])) ?></p>
                        </div>
                        
                        <div class="job-requirements">
                            <h3>Requirements</h3>
                            <p><?= nl2br(htmlspecialchars($job['requirements'])) ?></p>
                        </div>
                        
                        <?php if (isset($_SESSION['user_id'])): ?>
                            <div class="job-actions">
                                <?php if ($is_job_owner): ?>
                                    <?php if ($job['status'] === 'open'): ?>
                                        <a href="jobs.php?action=edit&job_id=<?= $job_id ?>" class="btn btn-secondary">
                                            <i class="fas fa-edit"></i> Edit Job
                                        </a>
                                        <form action="../api/jobs/delete_job.php" method="POST" 
                                              onsubmit="return confirm('Are you sure you want to cancel this job?');" 
                                              style="display: inline;">
                                            <input type="hidden" name="job_id" value="<?= $job_id ?>">
                                            <button type="submit" class="btn btn-danger">
                                                <i class="fas fa-times-circle"></i> Cancel Job
                                            </button>
                                        </form>
                                    <?php elseif ($job['status'] === 'in_progress'): ?>
                                        <form action="../api/jobs/manage_bids.php" method="POST">
                                            <input type="hidden" name="action" value="complete">
                                            <input type="hidden" name="job_id" value="<?= $job_id ?>">
                                            <input type="hidden" name="application_id" value="<?= $applications[0]['id'] ?>">
                                            <button type="submit" class="btn btn-success">
                                                <i class="fas fa-check-circle"></i> Mark Job Complete
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                <?php elseif ($user_has_applied): ?>
                                    <div class="application-status">
                                        Your application status: <span class="status-badge <?= $application_status ?>"><?= ucfirst($application_status) ?></span>
                                    </div>
                                    <?php if ($application_status === 'pending'): ?>
                                        <form action="../api/jobs/withdraw_application.php" method="POST">
                                            <input type="hidden" name="application_id" value="<?= $application['id'] ?>">
                                            <button type="submit" class="btn btn-secondary">
                                                <i class="fas fa-undo"></i> Withdraw Application
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                <?php elseif ($job['status'] === 'open' && !isExpired($job['expires_at'])): ?>
                                    <a href="jobs.php?action=apply&job_id=<?= $job_id ?>" class="btn btn-primary">
                                        <i class="fas fa-paper-plane"></i> Apply for this Job
                                    </a>
                                <?php else: ?>
                                    <div class="application-status">
                                        This job is <?= $job['status'] === 'open' ? 'expired' : $job['status'] ?> and not accepting applications.
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <div class="login-to-apply">
                                <a href="login.php" class="btn btn-primary">Log in to apply for this job</a>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <?php if ($is_job_owner && !empty($applications)): ?>
                        <div class="job-applications">
                            <h2>Applications (<?= count($applications) ?>)</h2>
                            
                            <?php foreach ($applications as $app): ?>
                                <div class="application-card <?= $app['status'] ?>">
                                    <div class="applicant-header">
                                        <img src="<?= getProfilePicture($app['profile_picture']) ?>" alt="<?= htmlspecialchars($app['username']) ?>" class="profile-pic">
                                        <div class="applicant-info">
                                            <h3><?= htmlspecialchars($app['username']) ?></h3>
                                            <p><i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($app['location']) ?></p>
                                            <p><i class="fas fa-briefcase"></i> <?= $app['completed_jobs'] ?> completed jobs</p>
                                        </div>
                                        <div class="application-meta">
                                            <div class="application-date">Applied <?= formatTimeAgo($app['created_at']) ?></div>
                                            <?php if ($app['bid_amount']): ?>
                                                <div class="bid-amount">Bid: $<?= number_format($app['bid_amount'], 2) ?></div>
                                            <?php else: ?>
                                                <div class="bid-amount">Accepts listed compensation</div>
                                            <?php endif; ?>
                                            <div class="status-badge <?= $app['status'] ?>"><?= ucfirst($app['status']) ?></div>
                                        </div>
                                    </div>
                                    
                                    <div class="application-content">
                                        <h4>Cover Letter</h4>
                                        <p><?= nl2br(htmlspecialchars($app['cover_letter'])) ?></p>
                                    </div>
                                    
                                    <?php if ($job['status'] === 'open' && $app['status'] === 'pending'): ?>
                                        <div class="application-actions">
                                            <form action="../api/jobs/manage_bids.php" method="POST">
                                                <input type="hidden" name="action" value="accept">
                                                <input type="hidden" name="job_id" value="<?= $job_id ?>">
                                                <input type="hidden" name="application_id" value="<?= $app['id'] ?>">
                                                <button type="submit" class="btn btn-success">
                                                    <i class="fas fa-check"></i> Accept
                                                </button>
                                            </form>
                                            <form action="../api/jobs/manage_bids.php" method="POST">
                                                <input type="hidden" name="action" value="reject">
                                                <input type="hidden" name="job_id" value="<?= $job_id ?>">
                                                <input type="hidden" name="application_id" value="<?= $app['id'] ?>">
                                                <button type="submit" class="btn btn-danger">
                                                    <i class="fas fa-times"></i> Reject
                                                </button>
                                            </form>
                                            <a href="mailto:<?= htmlspecialchars($app['email']) ?>" class="btn btn-secondary">
                                                <i class="fas fa-envelope"></i> Contact
                                            </a>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php else: ?>
            <!-- Job Listing View -->
            <div class="jobs-container">
                <div class="jobs-header">
                    <h1>Job Board</h1>
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <a href="jobs.php?action=post" class="btn btn-primary">
                            <i class="fas fa-plus"></i> Post a Job
                        </a>
                    <?php endif; ?>
                </div>
                
                <div class="jobs-tabs">
                    <a href="jobs.php" class="tab <?= $action === 'list' && !isset($_GET['my']) ? 'active' : '' ?>">Browse Jobs</a>
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <a href="jobs.php?my=posted" class="tab <?= isset($_GET['my']) && $_GET['my'] === 'posted' ? 'active' : '' ?>">My Posted Jobs</a>
                        <a href="jobs.php?my=applications" class="tab <?= isset($_GET['my']) && $_GET['my'] === 'applications' ? 'active' : '' ?>">My Applications</a>
                    <?php endif; ?>
                </div>
                
                <!-- My Posted Jobs -->
                <?php if (isset($_GET['my']) && $_GET['my'] === 'posted' && isset($_SESSION['user_id'])): ?>
                    <div class="my-jobs-section">
                        <h2>Jobs You've Posted</h2>
                        
                        <?php if (empty($my_jobs)): ?>
                            <div class="no-jobs-message">
                                <p>You haven't posted any jobs yet.</p>
                                <a href="jobs.php?action=post" class="btn btn-primary">Post Your First Job</a>
                            </div>
                        <?php else: ?>
                            <div class="jobs-grid">
                                <?php foreach ($my_jobs as $job): ?>
                                    <div class="job-card">
                                        <div class="job-status <?= $job['status'] ?>"><?= ucfirst($job['status']) ?></div>
                                        <h3><?= htmlspecialchars($job['title']) ?></h3>
                                        <div class="job-meta">
                                            <div class="job-category"><?= htmlspecialchars($job['category']) ?></div>
                                            <div class="job-location"><i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($job['location']) ?></div>
                                        </div>
                                        <div class="job-compensation"><?= htmlspecialchars($job['compensation']) ?></div>
                                        <div class="job-applications">
                                            <i class="fas fa-users"></i> <?= $job['application_count'] ?> application(s)
                                        </div>
                                        <div class="job-dates">
                                            <div>Posted: <?= formatTimeAgo($job['created_at']) ?></div>
                                            <div>Expires: <?= isExpired($job['expires_at']) ? 'Expired' : date('M j, Y', strtotime($job['expires_at'])) ?></div>
                                        </div>
                                        <div class="job-actions">
                                            <a href="jobs.php?job_id=<?= $job['id'] ?>" class="btn btn-secondary">View Details</a>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                
                <!-- My Applications -->
                <?php elseif (isset($_GET['my']) && $_GET['my'] === 'applications' && isset($_SESSION['user_id'])): ?>
                    <div class="my-applications-section">
                        <h2>Your Job Applications</h2>
                        
                        <?php if (empty($my_applications)): ?>
                            <div class="no-jobs-message">
                                <p>You haven't applied to any jobs yet.</p>
                                <a href="jobs.php" class="btn btn-primary">Browse Available Jobs</a>
                            </div>
                        <?php else: ?>
                            <div class="applications-list">
                                <?php foreach ($my_applications as $app): ?>
                                    <div class="application-item">
                                        <div class="application-header">
                                            <h3><?= htmlspecialchars($app['job_title']) ?></h3>
                                            <div class="status-badge <?= $app['status'] ?>"><?= ucfirst($app['status']) ?></div>
                                        </div>
                                        <div class="application-details">
                                            <div class="application-meta">
                                                <div><i class="fas fa-user"></i> Employer: <?= htmlspecialchars($app['employer_name']) ?></div>
                                                <div><i class="fas fa-calendar-alt"></i> Applied: <?= formatTimeAgo($app['created_at']) ?></div>
                                                <?php if ($app['bid_amount']): ?>
                                                    <div><i class="fas fa-dollar-sign"></i> Your Bid: $<?= number_format($app['bid_amount'], 2) ?></div>
                                                <?php endif; ?>
                                            </div>
                                            <div class="application-actions">
                                                <a href="jobs.php?job_id=<?= $app['job_id'] ?>" class="btn btn-secondary">View Job</a>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                
                <!-- Browse All Jobs -->
                <?php else: ?>
                    <div class="jobs-filter-bar">
                        <form action="jobs.php" method="GET" class="filter-form">
                            <div class="filter-group">
                                <label for="category">Category:</label>
                                <select id="category" name="category">
                                    <option value="">All Categories</option>
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?= htmlspecialchars($cat) ?>" <?= isset($_GET['category']) && $_GET['category'] === $cat ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($cat) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="filter-group">
                                <label for="status">Status:</label>
                                <select id="status" name="status">
                                    <option value="open" <?= (!isset($_GET['status']) || $_GET['status'] === 'open') ? 'selected' : '' ?>>Open</option>
                                    <option value="in_progress" <?= isset($_GET['status']) && $_GET['status'] === 'in_progress' ? 'selected' : '' ?>>In Progress</option>
                                    <option value="completed" <?= isset($_GET['status']) && $_GET['status'] === 'completed' ? 'selected' : '' ?>>Completed</option>
                                    <option value="all" <?= isset($_GET['status']) && $_GET['status'] === 'all' ? 'selected' : '' ?>>All Statuses</option>
                                </select>
                            </div>
                            
                            <div class="filter-group">
                                <label for="sort">Sort By:</label>
                                <select id="sort" name="sort">
                                    <option value="newest" <?= (!isset($_GET['sort']) || $_GET['sort'] === 'newest') ? 'selected' : '' ?>>Newest First</option>
                                    <option value="oldest" <?= isset($_GET['sort']) && $_GET['sort'] === 'oldest' ? 'selected' : '' ?>>Oldest First</option>
                                    <option value="expiring_soon" <?= isset($_GET['sort']) && $_GET['sort'] === 'expiring_soon' ? 'selected' : '' ?>>Expiring Soon</option>
                                </select>
                            </div>
                            
                            <button type="submit" class="btn btn-filter"><i class="fas fa-filter"></i> Filter</button>
                        </form>
                    </div>
                    
                    <?php if (empty($jobs)): ?>
                        <div class="no-jobs-message">
                            <p>No jobs found matching your criteria.</p>
                            <?php if (isset($_SESSION['user_id'])): ?>
                                <a href="jobs.php?action=post" class="btn btn-primary">Post a Job</a>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div class="jobs-grid">
                            <?php foreach ($jobs as $job): ?>
                                <div class="job-card">
                                    <div class="job-status <?= $job['status'] ?>"><?= ucfirst($job['status']) ?></div>
                                    <h3><?= htmlspecialchars($job['title']) ?></h3>
                                    <div class="job-meta">
                                        <div class="job-category"><?= htmlspecialchars($job['category']) ?></div>
                                        <div class="job-location"><i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($job['location']) ?></div>
                                    </div>
                                    <div class="job-compensation"><?= htmlspecialchars($job['compensation']) ?></div>
                                    <div class="job-poster">
                                        <img src="<?= getProfilePicture($job['profile_picture']) ?>" alt="<?= htmlspecialchars($job['username']) ?>" class="profile-pic-small">
                                        <span><?= htmlspecialchars($job['username']) ?></span>
                                    </div>
                                    <div class="job-dates">
                                        <div>Posted: <?= formatTimeAgo($job['created_at']) ?></div>
                                        <div>Expires: <?= isExpired($job['expires_at']) ? 'Expired' : date('M j, Y', strtotime($job['expires_at'])) ?></div>
                                    </div>
                                    <div class="job-actions">
                                        <a href="jobs.php?job_id=<?= $job['id'] ?>" class="btn btn-secondary">View Details</a>
                                        <?php if (isset($_SESSION['user_id']) && $job['status'] === 'open' && !isExpired($job['expires_at']) && $job['user_id'] != $_SESSION['user_id']): ?>
                                            <a href="jobs.php?action=apply&job_id=<?= $job['id'] ?>" class="btn btn-primary">Apply</a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        <?php endif; ?>
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
</body>
</html>
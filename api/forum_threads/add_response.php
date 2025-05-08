<?php
// filepath: c:\xampp\htdocs\aftermarket_toolkit\api\forum_threads\add_response.php
session_start();
require_once __DIR__ . '/../../config/db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    $_SESSION['error'] = "You must be logged in to post a response.";
    header('Location: ../../public/login.php');
    exit();
}

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userId = $_SESSION['user_id'];
    $threadId = isset($_POST['thread_id']) ? (int)$_POST['thread_id'] : 0;
    $responseBody = trim($_POST['response_body'] ?? '');
    
    // Validate input
    $errors = [];
    
    if (empty($threadId)) {
        $errors[] = "Invalid thread.";
    }
    
    if (empty($responseBody)) {
        $errors[] = "Response cannot be empty.";
    }
    
    // If there are errors, redirect back with error message
    if (!empty($errors)) {
        $_SESSION['error'] = implode(' ', $errors);
        header('Location: ../../public/forum.php');
        exit();
    }
    
    // Verify thread exists
    $checkThread = $conn->prepare("SELECT id FROM forum_threads WHERE id = ?");
    $checkThread->bind_param("i", $threadId);
    $checkThread->execute();
    $threadResult = $checkThread->get_result();
    
    if ($threadResult->num_rows === 0) {
        $_SESSION['error'] = "The thread you are trying to respond to does not exist.";
        header('Location: ../../public/forum.php');
        exit();
    }
    
    // Insert response into database
    $stmt = $conn->prepare("INSERT INTO forum_replies (thread_id, user_id, body, created_at) VALUES (?, ?, ?, NOW())");
    $stmt->bind_param("iis", $threadId, $userId, $responseBody);
    
    if ($stmt->execute()) {
    
        $_SESSION['success'] = "Your response has been posted.";
    } else {
        $_SESSION['error'] = "Failed to post your response. Please try again.";
    }
    
    // Redirect back to the thread
    header('Location: ../../public/forum.php?thread=' . $threadId);
    exit();
} else {
    // If someone tries to access this file directly without submitting the form
    header('Location: ../../public/forum.php');
    exit();
}
?>

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

<!-- Update the form action path to point to the correct location -->
<form id="form-<?= $thread_id ?>" class="response-form" style="display: none;" method="POST" action="../api/forum_threads/add_response.php">
  <input type="hidden" name="thread_id" value="<?= $thread_id ?>">
  <textarea name="response_body" rows="3" placeholder="Type your response here..." required></textarea>
  <button type="submit" class="submit-response">Submit</button>
  <button type="button" class="cancel-response" onclick="toggleResponseForm('form-<?= $thread_id ?>')">Cancel</button>
</form>

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

<!-- Alert messages -->
<style>
.alert {
  padding: 15px;
  margin-bottom: 20px;
  border-radius: 5px;
  position: relative;
}

.alert-success {
  background-color: #d4edda;
  color: #155724;
  border: 1px solid #c3e6cb;
}

.alert-danger {
  background-color: #f8d7da;
  color: #721c24;
  border: 1px solid #f5c6cb;
}

/* Delete response button styling */
.delete-response-btn {
  background-color: transparent;
  border: none;
  color: #dc3545;
  cursor: pointer;
  padding: 2px 4px;
  margin-left: auto;
  opacity: 0.7;
  transition: opacity 0.2s;
}

.delete-response-btn:hover {
  opacity: 1;
}

.forum-response {
  position: relative;
  display: flex;
  align-items: center;
}

.delete-response-form {
  margin-left: auto;
}
</style>

<?php
// filepath: c:\xampp\htdocs\aftermarket_toolkit\api\forum_threads\delete_response.php
session_start();
require_once __DIR__ . '/../../config/db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    $_SESSION['error'] = "You must be logged in to delete a response.";
    header('Location: ../../public/login.php');
    exit();
}

// Check if form was submitted with required data
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['response_id']) && isset($_POST['thread_id'])) {
    $userId = $_SESSION['user_id'];
    $responseId = (int)$_POST['response_id'];
    $threadId = (int)$_POST['thread_id'];
    
    // Verify the response exists and belongs to this user
    $checkQuery = "SELECT id FROM forum_replies WHERE id = ? AND user_id = ?";
    $checkStmt = $conn->prepare($checkQuery);
    $checkStmt->bind_param("ii", $responseId, $userId);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    
    if ($result->num_rows === 0) {
        $_SESSION['error'] = "You do not have permission to delete this response.";
        header('Location: ../../public/forum.php?thread=' . $threadId);
        exit();
    }
    
    // Delete the response
    $deleteQuery = "DELETE FROM forum_replies WHERE id = ? AND user_id = ?";
    $deleteStmt = $conn->prepare($deleteQuery);
    $deleteStmt->bind_param("ii", $responseId, $userId);
    
    if ($deleteStmt->execute()) {
        $_SESSION['success'] = "Your response has been deleted.";
    } else {
        $_SESSION['error'] = "Failed to delete the response. Please try again.";
    }
    
    // Redirect back to the thread
    header('Location: ../../public/forum.php?thread=' . $threadId);
    exit();
} else {
    // Missing required parameters
    $_SESSION['error'] = "Invalid request.";
    header('Location: ../../public/forum.php');
    exit();
}
?>
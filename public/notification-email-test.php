<?php
// Test script for notification emails
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/notification_email.php';

// Display form for testing
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notification Email Test - Aftermarket Toolkit</title>
    <link rel="stylesheet" href="./assets/css/index.css">
    <style>
        .email-test-container {
            max-width: 600px;
            margin: 50px auto;
            padding: 20px;
            background-color: #fff;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        input[type="text"], input[type="number"], select, textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 16px;
        }
        textarea {
            height: 100px;
            font-family: Arial, sans-serif;
        }
        .submit-btn {
            background-color: #189dc5;
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: 5px;
            font-size: 16px;
            cursor: pointer;
        }
        .submit-btn:hover {
            background-color: #157a9e;
        }
        .alert {
            padding: 15px;
            margin: 20px 0;
            border-radius: 5px;
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
    </style>
</head>
<body>
    <div class="email-test-container">
        <h1>Notification Email Test</h1>
        <p>Use this form to test the notification email functionality.</p>
        
        <?php
        // Get users for dropdown
        $sql = "SELECT id, username, email FROM users";
        $result = $conn->query($sql);
        $users = [];
        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $users[] = $row;
            }
        }
        
        if (empty($users)) {
            echo '<div class="alert alert-danger">No users found in the database.</div>';
        }
        ?>
        
        <form method="POST" action="">
            <div class="form-group">
                <label for="user_id">Select User:</label>
                <select id="user_id" name="user_id" required>
                    <?php foreach ($users as $user): ?>
                    <option value="<?= $user['id'] ?>"><?= htmlspecialchars($user['username']) ?> (<?= htmlspecialchars($user['email']) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label for="notification_type">Notification Type:</label>
                <select id="notification_type" name="notification_type" required>
                    <option value="forum_response">Forum Response</option>
                    <option value="message">Message</option>
                    <option value="friend_request">Friend Request</option>
                    <option value="listing_comment">Listing Comment</option>
                    <option value="other">Other</option>
                </select>
            </div>
            
            <div class="form-group">
                <label for="content">Notification Content:</label>
                <textarea id="content" name="content" required>User1 replied to your forum thread "Help with my exhaust"</textarea>
            </div>
            
            <button type="submit" class="submit-btn">Send Test Notification Email</button>
        </form>
    </div>
</body>
</html>
<?php
} else {
    // Process the notification email sending
    $userId = filter_input(INPUT_POST, 'user_id', FILTER_SANITIZE_NUMBER_INT);
    $notificationType = filter_input(INPUT_POST, 'notification_type', FILTER_SANITIZE_STRING);
    $content = filter_input(INPUT_POST, 'content', FILTER_SANITIZE_STRING);
    
    $result = sendNotificationEmail($userId, $notificationType, $content, $conn);
    
    // Show result page
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notification Email Test Result - Aftermarket Toolkit</title>
    <link rel="stylesheet" href="./assets/css/index.css">
    <style>
        .email-test-container {
            max-width: 600px;
            margin: 50px auto;
            padding: 20px;
            background-color: #fff;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        .alert {
            padding: 15px;
            margin: 20px 0;
            border-radius: 5px;
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
        .btn {
            display: inline-block;
            background-color: #189dc5;
            color: white;
            border: none;
            padding: 10px 15px;
            border-radius: 5px;
            text-decoration: none;
            font-size: 16px;
            cursor: pointer;
            margin-top: 20px;
        }
        .btn:hover {
            background-color: #157a9e;
        }
    </style>
</head>
<body>
    <div class="email-test-container">
        <h1>Notification Email Test Result</h1>
        
        <?php if ($result): ?>
        <div class="alert alert-success">
            <h3>Success!</h3>
            <p>The notification email was sent successfully.</p>
            <p>Check the recipient's inbox to verify it was received properly.</p>
        </div>
        <?php else: ?>
        <div class="alert alert-danger">
            <h3>Error</h3>
            <p>There was a problem sending the notification email. Check the server logs for more details.</p>
            <p>Make sure the user has a valid email address and email notifications are enabled.</p>
        </div>
        <?php endif; ?>
        
        <a href="notification-email-test.php" class="btn">Test Another Notification</a>
        <a href="email-test.php" class="btn" style="margin-left: 10px;">Basic Email Test</a>
    </div>
</body>
</html>
    <?php
}
?>
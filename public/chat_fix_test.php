<?php
// File: chat_fix_test.php
// Test script to verify chat notification fixes

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/notification_email.php';

echo "<div style='font-family: Arial, sans-serif; max-width: 800px; margin: 0 auto; padding: 20px;'>";
echo "<h1>Chat Notification Test</h1>";

$testResult = false;

// Create a test notification to check if sender info is correctly extracted
if (isset($_GET['test']) && $_GET['test'] == 'run') {
    // Get a test user
    $userQuery = "SELECT id, email FROM users LIMIT 1";
    $result = $conn->query($userQuery);
    
    if ($row = $result->fetch_assoc()) {
        $userId = $row['id'];
        $content = "New message from TestUser";
        
        // Test the notification email function
        $testResult = sendNotificationEmail($userId, 'message', $content, $conn);
        
        if ($testResult) {
            echo "<div style='background-color: #d4edda; color: #155724; padding: 15px; border-radius: 5px; margin: 20px 0;'>";
            echo "<h3>Test Passed!</h3>";
            echo "<p>The notification email was sent successfully to: " . htmlspecialchars($row['email']) . "</p>";
            echo "<p>Check the email to verify that:</p>";
            echo "<ul>";
            echo "<li>The message content is NOT included (for privacy)</li>";
            echo "<li>The sender information is displayed correctly</li>";
            echo "<li>The icon appears correctly</li>";
            echo "</ul>";
            echo "</div>";
        } else {
            echo "<div style='background-color: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; margin: 20px 0;'>";
            echo "<h3>Test Failed</h3>";
            echo "<p>There was an error sending the test notification email.</p>";
            echo "<p>Check that:</p>";
            echo "<ul>";
            echo "<li>The email settings in mailer.php are correct</li>";
            echo "<li>The user has a valid email address</li>";
            echo "<li>Email notifications are enabled for the user</li>";
            echo "</ul>";
            echo "</div>";
        }
    } else {
        echo "<div style='background-color: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; margin: 20px 0;'>";
        echo "<h3>No Users Found</h3>";
        echo "<p>Unable to find any users in the database to test with.</p>";
        echo "</div>";
    }
} else {
    echo "<p>Click the button below to run a test of the chat notification system:</p>";
    echo "<a href='?test=run' style='display: inline-block; background-color: #189dc5; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Run Test</a>";
}

echo "<hr style='margin: 30px 0;'>";
echo "<h2>Chat Notification Code Verification</h2>";

// Check for required files
echo "<h3>1. Required Files Check</h3>";
$files = [
    '../includes/notification_email.php',
    '../api/chat/chat_message_handler.php',
    '../api/chat/enhanced_chat_message_handler.php'
];

$allFilesExist = true;
echo "<ul>";
foreach ($files as $file) {
    $exists = file_exists(__DIR__ . '/' . $file);
    echo "<li style='color: " . ($exists ? "#155724" : "#721c24") . "'>";
    echo $exists ? "✓ " : "✗ ";
    echo htmlspecialchars($file) . " " . ($exists ? "exists" : "is missing");
    echo "</li>";
    
    if (!$exists) {
        $allFilesExist = false;
    }
}
echo "</ul>";

if (!$allFilesExist) {
    echo "<p style='color: #721c24;'>Some required files are missing. Please restore these files first.</p>";
} else {
    echo "<p style='color: #155724;'>All required files exist.</p>";
    
    // Check for proper code in notification_email.php
    echo "<h3>2. Code Check: notification_email.php</h3>";
    $notificationEmailFile = file_get_contents(__DIR__ . '/../includes/notification_email.php');
    $hasCorrectCode = strpos($notificationEmailFile, '($notificationType !== \'message\' ? "<p>{$content}</p>" : "")') !== false;
    
    echo "<div style='color: " . ($hasCorrectCode ? "#155724" : "#721c24") . "'>";
    echo $hasCorrectCode ? "✓ Message content filtering is correctly implemented" : "✗ Message content filtering may not be implemented correctly";
    echo "</div>";
    
    // Check for profile picture retrieval in chat handler
    echo "<h3>3. Code Check: chat_message_handler.php</h3>";
    $chatHandlerFile = file_get_contents(__DIR__ . '/../api/chat/chat_message_handler.php');
    $hasProfilePicture = strpos($chatHandlerFile, 'SELECT username, profile_picture FROM users') !== false;
    
    echo "<div style='color: " . ($hasProfilePicture ? "#155724" : "#721c24") . "'>";
    echo $hasProfilePicture ? "✓ Profile picture retrieval is implemented" : "✗ Profile picture retrieval may be missing";
    echo "</div>";
}

echo "<div style='margin-top: 30px;'>";
echo "<a href='../index.php' style='display: inline-block; background-color: #6c757d; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin-right: 10px;'>Back to Homepage</a>";
echo "<a href='update_chat_notifications.php' style='display: inline-block; background-color: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Run Full Update Script</a>";
echo "</div>";

echo "</div>";
?>
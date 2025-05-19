<?php
// File: update_chat_notifications.php
// Script to update the chat notifications with profile pictures in emails

// Make sure this script is executed from the public directory
require_once __DIR__ . '/../config/db.php';

echo "<div style='font-family: Arial, sans-serif; max-width: 800px; margin: 0 auto; padding: 20px;'>";
echo "<h1>Updating Chat Notifications</h1>";

// 1. Check if email_notifications column exists in users table
echo "<h2>Step 1: Checking database structure</h2>";

$columnQuery = "SHOW COLUMNS FROM `users` LIKE 'email_notifications'";
$columnResult = $conn->query($columnQuery);

if ($columnResult->num_rows == 0) {
    echo "<p style='color: #721c24; background-color: #f8d7da; padding: 10px; border-radius: 5px;'>
          The email_notifications column doesn't exist in the users table. Please run the 
          <a href='setup_email_notifications.php'>setup script</a> first.
          </p>";
    exit();
} else {
    echo "<p style='color: #155724; background-color: #d4edda; padding: 10px; border-radius: 5px;'>
          ✓ Database structure is correct
          </p>";
}

// 2. Check if enhanced_chat_message_handler.php exists
echo "<h2>Step 2: Checking file structure</h2>";

$enhancedHandlerPath = __DIR__ . '/../api/chat/enhanced_chat_message_handler.php';
if (!file_exists($enhancedHandlerPath)) {
    echo "<p style='color: #721c24; background-color: #f8d7da; padding: 10px; border-radius: 5px;'>
          Missing enhanced_chat_message_handler.php file.
          </p>";
    exit();
} else {
    echo "<p style='color: #155724; background-color: #d4edda; padding: 10px; border-radius: 5px;'>
          ✓ Enhanced chat handler is installed
          </p>";
}

// 3. Check chat.js for proper endpoint
echo "<h2>Step 3: Checking chat.js configuration</h2>";

$chatJsPath = __DIR__ . '/assets/js/chat.js';
if (!file_exists($chatJsPath)) {
    echo "<p style='color: #721c24; background-color: #f8d7da; padding: 10px; border-radius: 5px;'>
          chat.js file not found. Please check the file location.
          </p>";
} else {
    $chatJsContent = file_get_contents($chatJsPath);
    if (strpos($chatJsContent, 'enhanced_chat_message_handler.php') !== false) {
        echo "<p style='color: #155724; background-color: #d4edda; padding: 10px; border-radius: 5px;'>
              ✓ chat.js is using the enhanced chat handler
              </p>";
    } else {
        echo "<p style='color: #E7C000; background-color: #FFF9C4; padding: 10px; border-radius: 5px;'>
              chat.js is not using the enhanced chat handler. Updating now...
              </p>";
              
        $updatedJsContent = str_replace(
            'chat_message_handler.php',
            'enhanced_chat_message_handler.php',
            $chatJsContent
        );
        
        if (file_put_contents($chatJsPath, $updatedJsContent)) {
            echo "<p style='color: #155724; background-color: #d4edda; padding: 10px; border-radius: 5px;'>
                  ✓ chat.js has been updated to use the enhanced chat handler
                  </p>";
        } else {
            echo "<p style='color: #721c24; background-color: #f8d7da; padding: 10px; border-radius: 5px;'>
                  Failed to update chat.js. Please check file permissions.
                  </p>";
        }
    }
}

// 4. Final summary
echo "<h2>Summary</h2>";
echo "<p>Chat notification enhancements have been applied. Email notifications now include:</p>";
echo "<ul>
        <li>Sender's profile picture</li>
        <li>Enhanced message formatting</li>
        <li>Message preview (first 50 characters)</li>
      </ul>";

echo "<h2>Testing</h2>";
echo "<p>To test the enhanced notifications:</p>";
echo "<ol>
        <li>Make sure email notifications are enabled in your user profile</li>
        <li>Have a friend send you a chat message</li>
        <li>Check your email for the notification with the profile picture</li>
      </ol>";

echo "<p><a href='../index.php' style='display: inline-block; background-color: #189dc5; color: white; padding: 10px 15px; text-decoration: none; border-radius: 5px;'>Back to Homepage</a></p>";

echo "</div>";
?>
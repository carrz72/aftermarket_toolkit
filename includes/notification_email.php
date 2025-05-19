<?php
require_once __DIR__ . '/mailer.php';

/**
 * Send email notification to a user
 * 
 * @param int $userId User ID to send the notification to
 * @param string $notificationType Type of notification (forum_response, message, friend_request, etc.)
 * @param string $content Notification text content
 * @param mysqli $conn Database connection
 * @return bool Success or failure
 */
function sendNotificationEmail($userId, $notificationType, $content, $conn) {    // Get user email and email preferences
    $stmt = $conn->prepare("SELECT email, email_notifications FROM users WHERE id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        $email = $row['email'];
        $emailNotifications = $row['email_notifications'] ?? 1; // Default to enabled if not set
        
        // Check if user has email notifications enabled
        if (!$emailNotifications) {
            return false; // User has disabled email notifications
        }
          // Create subject based on notification type
        $subject = "New Notification: ";
        $iconColor = "#189dc5";
        $iconName = "bell";
        $senderInfo = '';
          // Get additional info based on notification type
        if ($notificationType == 'message' && strpos($content, 'from') !== false) {
            // Extract username from content
            preg_match('/from (.+)$/', $content, $matches);
            $senderUsername = $matches[1] ?? '';
            
            if (!empty($senderUsername)) {
                // Get sender's profile picture
                $userStmt = $conn->prepare("SELECT profile_picture FROM users WHERE username = ?");
                $userStmt->bind_param("s", $senderUsername);
                $userStmt->execute();
                $userResult = $userStmt->get_result();
                if ($userData = $userResult->fetch_assoc()) {
                    $profilePic = !empty($userData['profile_picture']) 
                        ? 'http://localhost/aftermarket_toolkit' . $userData['profile_picture'] 
                        : 'http://localhost/aftermarket_toolkit/public/assets/images/default-profile.jpg';
                    
                    $senderInfo = "<div style='margin-top: 15px; margin-bottom: 15px; display: flex; align-items: center;'>
                        <img src='{$profilePic}' alt='{$senderUsername}' style='width: 50px; height: 50px; border-radius: 50%; margin-right: 10px; object-fit: cover;'>
                        <strong>You have a new chat from {$senderUsername}</strong>
                    </div>";
                }
            }
        }
        
        switch ($notificationType) {
            case 'forum_response':
                $subject .= "New Forum Response";
                $iconName = "comments";
                $iconColor = "#fd7e14";
                break;
            case 'message':
                $subject .= "New Message";
                $iconName = "envelope";
                $iconColor = "#17a2b8";
                break;
            case 'friend_request':
                $subject .= "Friend Request";
                $iconName = "user-plus";
                $iconColor = "#28a745";
                break;
            case 'listing_comment':
                $subject .= "New Comment on Your Listing";
                $iconName = "comment";
                $iconColor = "#6610f2";
                break;
            default:
                $subject .= "Activity Update";
        }
        
        $body = "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>{$subject}</title>
            <style>
                body {
                    font-family: Arial, sans-serif;
                    line-height: 1.6;
                    color: #333;
                    background-color: #f4f4f4;
                    margin: 0;
                    padding: 0;
                }
                .email-container {
                    max-width: 600px;
                    margin: 20px auto;
                    background: #fff;
                    border-radius: 10px;
                    box-shadow: 0 3px 10px rgba(0,0,0,0.1);
                    overflow: hidden;
                }
                .email-header {
                    background-color: #189dc5;
                    color: white;
                    padding: 20px;
                    text-align: center;
                }
                .email-content {
                    padding: 30px;
                }
                .notification-box {
                    background-color: #f8f9fa;
                    border-left: 4px solid {$iconColor};
                    padding: 15px;
                    margin-bottom: 20px;
                    border-radius: 5px;
                }
                .notification-meta {
                    display: flex;
                    align-items: center;
                    margin-bottom: 10px;
                }
                .notification-icon {
                    width: 40px;
                    height: 40px;
                    background-color: {$iconColor};
                    border-radius: 50%;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    margin-right: 15px;
                    color: white;
                    font-size: 20px;
                }
                .btn {
                    display: inline-block;
                    background-color: #189dc5;
                    color: white;
                    text-decoration: none;
                    padding: 12px 25px;
                    border-radius: 5px;
                    font-weight: bold;
                }
                .footer {
                    background-color: #f8f9fa;
                    padding: 15px;
                    text-align: center;
                    color: #666;
                    font-size: 12px;
                    border-top: 1px solid #eee;
                }
            </style>
        </head>
        <body>
            <div class='email-container'>
            <div class='email-header'>
                <h1>Aftermarket Toolkit</h1>
            </div>
            <div class='email-content'>                <h2>You have a new notification</h2>
                <div class='notification-box'>
                <div class='notification-meta'>
                    <div class='notification-icon' style='background-color: {$iconColor}; color: white; width: fit-content; height: 40px; border-radius: 200px; display: flex; align-items: center; justify-content: center; margin: 4px; text-align: center; line-height: 40px; padding: 4px;'>
                    <!-- Using HTML entity icons that will display in email clients -->
                    " . ($iconName === 'comments' ? '&#128172;' : 
                        ($iconName === 'envelope' ? '&#9993;' : 
                        ($iconName === 'user-plus' ? '&#128100;' : 
                        ($iconName === 'comment' ? '&#128172;' : '&#128276;')))) . "
                    </div>                    <h3 style='margin: 0;'>{$subject}</h3>
                </div>
                
                " . ($notificationType !== 'message' ? "<p>{$content}</p>" : "") . "
                
                " . (!empty($senderInfo) ? $senderInfo : '') . "
                
                <div style='text-align: center; margin-top: 30px;'>
                <a href='http://localhost/aftermarket_toolkit/public/notifications.php' class='btn'>View Notification</a>
                </div>
            </div>
            <div class='footer'>
            <div class='footer'>
                <p>&copy; " . date('Y') . " Aftermarket Toolkit. All rights reserved.</p>
                <p><a href='http://localhost/aftermarket_toolkit/public/profile.php'>Manage your notification settings</a></p>
            </div>
            </div>
        </body>
        </html>";
        
        // Send the email
        return sendEmail($email, $subject, $body);
    }
    
    return false;
}
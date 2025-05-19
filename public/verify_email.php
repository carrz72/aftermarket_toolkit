<?php
// Email verification page
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/email_verification.php';

// Initialize the email verification handler
$emailVerification = new EmailVerification($conn);

// Check if we have a token in the URL
if (isset($_GET['token']) && !empty($_GET['token'])) {
    $token = $_GET['token'];
    
    // Verify the token
    $result = $emailVerification->verifyEmailToken($token);
    
    if ($result['success']) {
        $_SESSION['success_message'] = 'Your email has been verified successfully! You can now login.';
        header('Location: login.php');
        exit();
    } else {
        $_SESSION['error_message'] = $result['message'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Verification - Aftermarket Toolbox</title>
    <link rel="stylesheet" href="./assets/css/index.css">
    <style>
        .verification-container {
            max-width: 600px;
            margin: 50px auto;
            padding: 30px;
            background-color: #fff;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
            text-align: center;
        }
        
        .verification-icon {
            font-size: 60px;
            margin-bottom: 20px;
            color: #189dc5;
        }
        
        .error-icon {
            color: #dc3545;
        }
        
        .verification-title {
            font-size: 24px;
            margin-bottom: 20px;
            color: #333;
        }
        
        .verification-message {
            font-size: 16px;
            line-height: 1.6;
            margin-bottom: 30px;
            color: #666;
        }
        
        .verification-button {
            display: inline-block;
            background-color: #189dc5;
            color: white;
            padding: 12px 30px;
            border-radius: 5px;
            text-decoration: none;
            font-weight: bold;
            transition: background-color 0.3s;
        }
        
        .verification-button:hover {
            background-color: #157a9e;
        }
    </style>
</head>
<body>
    <div class="menu">
        <a href="../index.php" class="link">
            <span class="link-icon">
                <img src="./assets/images/home-icon.svg" alt="Home">
            </span>
            <span class="link-title">Home</span>
        </a>
    </div>
    
    <div class="verification-container">
        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="verification-icon error-icon">❌</div>
            <h1 class="verification-title">Verification Failed</h1>
            <p class="verification-message"><?= htmlspecialchars($_SESSION['error_message']) ?></p>
            <a href="login.php" class="verification-button">Go to Login</a>
            <?php unset($_SESSION['error_message']); ?>
        <?php else: ?>
            <div class="verification-icon">✉️</div>
            <h1 class="verification-title">Email Verification</h1>
            <p class="verification-message">We are processing your verification. If you were not redirected automatically, your token may be invalid or expired.</p>
            <p class="verification-message">Please check your email for a verification link, or request a new one from your profile page after logging in.</p>
            <a href="login.php" class="verification-button">Go to Login</a>
        <?php endif; ?>
    </div>
</body>
</html>
<?php
/**
 * Email Verification Helper Functions
 * 
 * This file contains functions for email verification and password reset
 */

require_once __DIR__ . '/mailer.php';
require_once __DIR__ . '/../config/db.php';

/**
 * Generate a random token
 * 
 * @param int $length Length of token
 * @return string Random token
 */
function generateToken($length = 32) {
    return bin2hex(random_bytes($length / 2));
}

/**
 * Generate a random OTP (One Time Password)
 * 
 * @param int $length Length of OTP
 * @return string Random OTP
 */
function generateOTP($length = 6) {
    $digits = '';
    for ($i = 0; $i < $length; $i++) {
        $digits .= rand(0, 9);
    }
    return $digits;
}

/**
 * Store email verification token in database
 * 
 * @param mysqli $conn Database connection
 * @param int $userId User ID
 * @param string $token Verification token
 * @param string $email User email
 * @return bool Success status
 */
function storeEmailVerificationToken($conn, $userId, $token, $email) {
    // Check if there's an existing token for this user
    $checkStmt = $conn->prepare("SELECT id FROM verification_tokens WHERE user_id = ? AND type = 'email_verification'");
    $checkStmt->bind_param("i", $userId);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    
    // If token exists, update it
    if ($result->num_rows > 0) {
        $stmt = $conn->prepare("UPDATE verification_tokens SET token = ?, expires_at = DATE_ADD(NOW(), INTERVAL 24 HOUR) WHERE user_id = ? AND type = 'email_verification'");
        $stmt->bind_param("si", $token, $userId);
    } else {
        // Otherwise create a new token
        $type = 'email_verification';
        $stmt = $conn->prepare("INSERT INTO verification_tokens (user_id, token, type, email, expires_at) VALUES (?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 24 HOUR))");
        $stmt->bind_param("isss", $userId, $token, $type, $email);
    }
    
    return $stmt->execute();
}

/**
 * Store password reset OTP in database
 * 
 * @param mysqli $conn Database connection
 * @param int $userId User ID
 * @param string $otp One Time Password
 * @param string $email User email
 * @return bool Success status
 */
function storePasswordResetOTP($conn, $userId, $otp, $email) {
    // Check if there's an existing OTP for this user
    $checkStmt = $conn->prepare("SELECT id FROM verification_tokens WHERE user_id = ? AND type = 'password_reset'");
    $checkStmt->bind_param("i", $userId);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    
    // If OTP exists, update it
    if ($result->num_rows > 0) {
        $stmt = $conn->prepare("UPDATE verification_tokens SET token = ?, expires_at = DATE_ADD(NOW(), INTERVAL 1 HOUR) WHERE user_id = ? AND type = 'password_reset'");
        $stmt->bind_param("si", $otp, $userId);
    } else {
        // Otherwise create a new OTP
        $type = 'password_reset';
        $stmt = $conn->prepare("INSERT INTO verification_tokens (user_id, token, type, email, expires_at) VALUES (?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 1 HOUR))");
        $stmt->bind_param("isss", $userId, $otp, $type, $email);
    }
    
    return $stmt->execute();
}

/**
 * Verify token from database
 * 
 * @param mysqli $conn Database connection
 * @param string $token Token to verify
 * @param string $type Token type ('email_verification' or 'password_reset')
 * @return int|false User ID if valid, false otherwise
 */
function verifyToken($conn, $token, $type) {
    $stmt = $conn->prepare("SELECT user_id FROM verification_tokens WHERE token = ? AND type = ? AND expires_at > NOW()");
    $stmt->bind_param("ss", $token, $type);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        return $row['user_id'];
    }
    
    return false;
}

/**
 * Delete token from database
 * 
 * @param mysqli $conn Database connection
 * @param string $token Token to delete
 * @param string $type Token type
 * @return bool Success status
 */
function deleteToken($conn, $token, $type) {
    $stmt = $conn->prepare("DELETE FROM verification_tokens WHERE token = ? AND type = ?");
    $stmt->bind_param("ss", $token, $type);
    return $stmt->execute();
}

/**
 * Send email verification email
 * 
 * @param string $email User email
 * @param string $token Verification token
 * @param string $username User's username
 * @return bool Success status
 */
function sendVerificationEmail($email, $token, $username) {
    $subject = "Verify Your Email - Aftermarket Toolkit";
    $verifyUrl = "http://" . $_SERVER['HTTP_HOST'] . "/aftermarket_toolkit/public/verify_email.php?token=" . $token;
    
    $body = "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <title>Email Verification</title>
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
            <div class='email-content'>
                <h2>Verify Your Email Address</h2>
                <p>Hello $username,</p>
                <p>Thank you for registering with Aftermarket Toolkit. Please click the button below to verify your email address:</p>
                <p style='text-align: center; margin: 30px 0;'>
                    <a href='$verifyUrl' class='btn'>Verify Email</a>
                </p>
                <p>Or copy and paste this URL into your browser:</p>
                <p style='word-break: break-all;'>$verifyUrl</p>
                <p>This link will expire in 24 hours.</p>
                <p>If you did not create an account, please ignore this email.</p>
            </div>
            <div class='footer'>
                <p>&copy; " . date('Y') . " Aftermarket Toolkit. All rights reserved.</p>
            </div>
        </div>
    </body>
    </html>";
    
    return sendEmail($email, $subject, $body);
}

/**
 * Send password reset OTP email
 * 
 * @param string $email User email
 * @param string $otp One Time Password
 * @param string $username User's username
 * @return bool Success status
 */
function sendPasswordResetOTP($email, $otp, $username) {
    $subject = "Password Reset - Aftermarket Toolkit";
    $resetUrl = "http://" . $_SERVER['HTTP_HOST'] . "/aftermarket_toolkit/public/reset_password.php";
    
    $body = "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <title>Password Reset</title>
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
            .otp-box {
                font-size: 24px;
                font-weight: bold;
                text-align: center;
                letter-spacing: 5px;
                padding: 15px;
                background-color: #f8f9fa;
                border-radius: 10px;
                margin: 20px 0;
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
            <div class='email-content'>
                <h2>Password Reset Request</h2>
                <p>Hello $username,</p>
                <p>We received a request to reset your password. Please use the following One-Time Password (OTP) to reset your password:</p>
                <div class='otp-box'>$otp</div>
                <p>This OTP will expire in 1 hour.</p>
                <p style='text-align: center; margin: 30px 0;'>
                    <a href='$resetUrl' class='btn'>Reset Password</a>
                </p>
                <p>If you did not request a password reset, please ignore this email.</p>
            </div>
            <div class='footer'>
                <p>&copy; " . date('Y') . " Aftermarket Toolkit. All rights reserved.</p>
            </div>
        </div>
    </body>
    </html>";
    
    return sendEmail($email, $subject, $body);
}

/**
 * Update user's verification status
 * 
 * @param mysqli $conn Database connection
 * @param int $userId User ID
 * @return bool Success status
 */
function markEmailAsVerified($conn, $userId) {
    $stmt = $conn->prepare("UPDATE users SET email_verified = 1 WHERE id = ?");
    $stmt->bind_param("i", $userId);
    return $stmt->execute();
}
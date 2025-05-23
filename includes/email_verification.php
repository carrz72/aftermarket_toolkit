<?php
/**
 * Email Verification Helper Class
 * Handles email verification and password reset functionality
 * for Aftermarket Toolkit
 */

require_once __DIR__ . '/mailer.php';

class EmailVerification {
    private $conn;
    private $otp_expiry = 15; // OTP expires after 15 minutes
    
    public function __construct($db_connection) {
        $this->conn = $db_connection;
        $this->ensureTablesExist();
    }
    
    /**
     * Create necessary tables if they don't exist
     */
    private function ensureTablesExist() {
        // Create verification_codes table
        $this->conn->query("
            CREATE TABLE IF NOT EXISTS verification_codes (
                id INT AUTO_INCREMENT PRIMARY KEY,
                email VARCHAR(255) NOT NULL,
                code VARCHAR(10) NOT NULL,
                token VARCHAR(255) NOT NULL,
                verified TINYINT(1) DEFAULT 0,
                expires_at DATETIME NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY (email)
            )
        ");
        
        // Create password_reset table
        $this->conn->query("
            CREATE TABLE IF NOT EXISTS password_reset (
                id INT AUTO_INCREMENT PRIMARY KEY,
                email VARCHAR(255) NOT NULL,
                otp VARCHAR(10) NOT NULL,
                token VARCHAR(255) NOT NULL,
                used TINYINT(1) DEFAULT 0,
                expires_at DATETIME NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY (email)
            )
        ");

        // Create email_verification_tokens table
        $this->conn->query("
            CREATE TABLE IF NOT EXISTS email_verification_tokens (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                token VARCHAR(255) NOT NULL,
                expires_at DATETIME NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            )
        ");
    }
    
    /**
     * Generate random numeric verification code
     * 
     * @param int $length Length of the code
     * @return string Random numeric code
     */
    public function generateCode($length = 6) {
        return str_pad(rand(0, pow(10, $length) - 1), $length, '0', STR_PAD_LEFT);
    }
    
    /**
     * Generate a unique token for verification links
     * 
     * @return string Generated token
     */
    public function generateToken() {
        return bin2hex(random_bytes(32));
    }
    
    /**
     * Send verification email to new users
     * 
     * @param string $email User's email address
     * @return array Success status and message
     */
    public function sendVerificationEmail($email) {
        // Check if email already exists and verified
        $stmt = $this->conn->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            return ['success' => false, 'message' => 'Email already registered'];
        }
        
        // Generate verification code
        $verification_code = $this->generateCode();
        $token = $this->generateToken();
        $expires_at = date('Y-m-d H:i:s', strtotime('+' . $this->otp_expiry . ' minutes'));
        
        // Store verification data
        $checkStmt = $this->conn->prepare("SELECT id FROM verification_codes WHERE email = ?");
        $checkStmt->bind_param("s", $email);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();
        
        if ($checkResult->num_rows > 0) {
            $updateStmt = $this->conn->prepare("
                UPDATE verification_codes 
                SET code = ?, token = ?, verified = 0, expires_at = ? 
                WHERE email = ?
            ");
            $updateStmt->bind_param("ssss", $verification_code, $token, $expires_at, $email);
            $updateStmt->execute();
        } else {
            $insertStmt = $this->conn->prepare("
                INSERT INTO verification_codes (email, code, token, expires_at) 
                VALUES (?, ?, ?, ?)
            ");
            $insertStmt->bind_param("ssss", $email, $verification_code, $token, $expires_at);
            $insertStmt->execute();
        }
        
        // Send email with verification code
        $subject = "Verify Your Email - Aftermarket Toolkit";
        $body = $this->getVerificationEmailTemplate($verification_code);
        
        if (sendEmail($email, $subject, $body)) {
            return ['success' => true, 'message' => 'Verification code sent to your email'];
        } else {
            return ['success' => false, 'message' => 'Failed to send verification email'];
        }
    }
    
    /**
     * Verify user email with the provided code
     * 
     * @param string $email User's email address
     * @param string $code Verification code
     * @return array Success status and message
     */
    public function verifyEmail($email, $code) {
        $stmt = $this->conn->prepare("
            SELECT code, expires_at FROM verification_codes 
            WHERE email = ? AND verified = 0
        ");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows == 0) {
            return ['success' => false, 'message' => 'Invalid or expired verification code'];
        }
        
        $row = $result->fetch_assoc();
        $stored_code = $row['code'];
        $expires_at = $row['expires_at'];
        
        // Check if code is expired
        if (strtotime($expires_at) < time()) {
            return ['success' => false, 'message' => 'Verification code has expired'];
        }
        
        // Check if code matches
        if ($code != $stored_code) {
            return ['success' => false, 'message' => 'Invalid verification code'];
        }
        
        // Mark as verified
        $updateStmt = $this->conn->prepare("
            UPDATE verification_codes SET verified = 1 WHERE email = ?
        ");
        $updateStmt->bind_param("s", $email);
        $updateStmt->execute();
        
        return ['success' => true, 'message' => 'Email verified successfully'];
    }
    
    /**
     * Send OTP for password reset
     * 
     * @param string $email User's email address
     * @return array Success status and message
     */
    public function sendPasswordResetOTP($email) {
        // Check if email exists
        $stmt = $this->conn->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows == 0) {
            return ['success' => false, 'message' => 'Email not found in our records'];
        }
        
        // Generate OTP
        $otp = $this->generateCode();
        $token = $this->generateToken();
        $expires_at = date('Y-m-d H:i:s', strtotime('+' . $this->otp_expiry . ' minutes'));
        
        // Store OTP
        $checkStmt = $this->conn->prepare("SELECT id FROM password_reset WHERE email = ?");
        $checkStmt->bind_param("s", $email);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();
        
        if ($checkResult->num_rows > 0) {
            $updateStmt = $this->conn->prepare("
                UPDATE password_reset 
                SET otp = ?, token = ?, used = 0, expires_at = ? 
                WHERE email = ?
            ");
            $updateStmt->bind_param("ssss", $otp, $token, $expires_at, $email);
            $updateStmt->execute();
        } else {
            $insertStmt = $this->conn->prepare("
                INSERT INTO password_reset (email, otp, token, expires_at) 
                VALUES (?, ?, ?, ?)
            ");
            $insertStmt->bind_param("ssss", $email, $otp, $token, $expires_at);
            $insertStmt->execute();
        }
        
        // Send email with OTP
        $subject = "Password Reset - Aftermarket Toolkit";
        $body = $this->getPasswordResetEmailTemplate($otp);
        
        if (sendEmail($email, $subject, $body)) {
            return ['success' => true, 'message' => 'OTP sent to your email'];
        } else {
            return ['success' => false, 'message' => 'Failed to send OTP email'];
        }
    }
    
    /**
     * Verify OTP for password reset
     * 
     * @param string $email User's email address
     * @param string $otp One-Time Password
     * @return array Success status, message, and token if successful
     */
    public function verifyPasswordResetOTP($email, $otp) {
        $stmt = $this->conn->prepare("
            SELECT otp, token, expires_at FROM password_reset 
            WHERE email = ? AND used = 0
        ");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows == 0) {
            return ['success' => false, 'message' => 'Invalid or expired OTP'];
        }
        
        $row = $result->fetch_assoc();
        $stored_otp = $row['otp'];
        $token = $row['token'];
        $expires_at = $row['expires_at'];
        
        // Check if OTP is expired
        if (strtotime($expires_at) < time()) {
            return ['success' => false, 'message' => 'OTP has expired'];
        }
        
        // Check if OTP matches
        if ($otp != $stored_otp) {
            return ['success' => false, 'message' => 'Invalid OTP'];
        }
        
        return [
            'success' => true, 
            'message' => 'OTP verified successfully',
            'token' => $token
        ];
    }
    
    /**
     * Reset user password after OTP verification
     * 
     * @param string $token Reset token
     * @param string $new_password New password
     * @return array Success status and message
     */
    public function resetPassword($token, $new_password) {
        // Get email from token
        $stmt = $this->conn->prepare("
            SELECT email FROM password_reset 
            WHERE token = ? AND used = 0
        ");
        $stmt->bind_param("s", $token);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows == 0) {
            return ['success' => false, 'message' => 'Invalid or expired reset token'];
        }
        
        $row = $result->fetch_assoc();
        $email = $row['email'];
        
        // Hash the new password
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        
        // Update user password
        $updateStmt = $this->conn->prepare("
            UPDATE users SET password = ? WHERE email = ?
        ");
        $updateStmt->bind_param("ss", $hashed_password, $email);
        $updateStmt->execute();
        
        // Mark reset token as used
        $markUsedStmt = $this->conn->prepare("
            UPDATE password_reset SET used = 1 WHERE token = ?
        ");
        $markUsedStmt->bind_param("s", $token);
        $markUsedStmt->execute();
        
        return ['success' => true, 'message' => 'Password reset successfully'];
    }
    
    /**
     * Generate a verification token for a user
     * 
     * @param int $userId User ID
     * @return string The generated token
     */
    public function generateUserVerificationToken($userId) {
        // Generate a unique token
        $token = bin2hex(random_bytes(32));
        $expires_at = date('Y-m-d H:i:s', strtotime('+' . $this->otp_expiry . ' minutes'));
        
        // Store the token in the database
        $stmt = $this->conn->prepare("INSERT INTO email_verification_tokens (user_id, token, expires_at) VALUES (?, ?, ?)");
        $stmt->bind_param("iss", $userId, $token, $expires_at);
        $stmt->execute();
        
        return $token;
    }
    
    /**
     * Send verification email to user
     * 
     * @param int $userId User ID
     * @param string $email User's email
     * @return bool Success or failure
     */
    public function sendUserVerificationEmail($userId, $email) {
        // Generate a token
        $token = $this->generateUserVerificationToken($userId);
        
        // Base URL for verification
        $baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]/aftermarket_toolkit";
        $verificationUrl = "$baseUrl/public/verify_email.php?token=" . $token;
        
        // Email subject
        $subject = "Verify your email address";
        
        // Email message
        $message = "
        <html>
        <head>
            <title>Verify Your Email</title>
        </head>
        <body>
            <div style='max-width: 600px; margin: 0 auto; padding: 20px; font-family: Arial, sans-serif;'>
                <div style='background-color: #189dc5; padding: 15px; text-align: center;'>
                    <h1 style='color: white; margin: 0;'>Aftermarket Toolkit</h1>
                </div>
                <div style='background-color: #f5f5f5; padding: 20px; border-radius: 0 0 5px 5px;'>
                    <h2>Verify Your Email Address</h2>
                    <p>Thank you for registering! Please click the button below to verify your email address:</p>
                    <div style='text-align: center; margin: 30px 0;'>
                        <a href='$verificationUrl' style='background-color: #189dc5; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; font-weight: bold;'>Verify Email</a>
                    </div>
                    <p>Or copy and paste this link into your browser:</p>
                    <p style='word-break: break-all;'><a href='$verificationUrl'>$verificationUrl</a></p>
                    <p>This link will expire in 24 hours.</p>
                    <p>If you did not create an account, please ignore this email.</p>
                </div>
                <div style='text-align: center; margin-top: 20px; color: #666; font-size: 12px;'>
                    <p>This is an automated message, please do not reply.</p>
                </div>
            </div>
        </body>
        </html>
        ";
        
        // Headers for HTML email
        $headers = "MIME-Version: 1.0" . "\r\n";
        $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
        $headers .= 'From: Aftermarket Toolkit <noreply@aftermarkettoolkit.com>' . "\r\n";
        
        // Send the email
        return mail($email, $subject, $message, $headers);
    }
    
    /**
     * Verify a token
     * 
     * @param string $token Token to verify
     * @return array Result with success status and message
     */
    public function verifyUserToken($token) {
        // Check if token exists in database
        $stmt = $this->conn->prepare("SELECT user_id, expires_at FROM email_verification_tokens WHERE token = ?");
        $stmt->bind_param("s", $token);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            return [
                'success' => false,
                'message' => 'Invalid verification token.'
            ];
        }
        
        $tokenData = $result->fetch_assoc();
        $userId = $tokenData['user_id'];
        $expiresAt = $tokenData['expires_at'];
        
        // Check if token is expired
        if (strtotime($expiresAt) < time()) {
            return [
                'success' => false,
                'message' => 'Verification token has expired. Please request a new one.'
            ];
        }
        
        // Update user's email verification status
        $updateStmt = $this->conn->prepare("UPDATE users SET email_verified = 1 WHERE id = ?");
        $updateStmt->bind_param("i", $userId);
        $updateResult = $updateStmt->execute();
        
        if (!$updateResult) {
            return [
                'success' => false,
                'message' => 'Error updating verification status. Please try again.'
            ];
        }
        
        // Delete the used token
        $deleteStmt = $this->conn->prepare("DELETE FROM email_verification_tokens WHERE token = ?");
        $deleteStmt->bind_param("s", $token);
        $deleteStmt->execute();
        
        return [
            'success' => true,
            'message' => 'Email verification successful!'
        ];
    }
    
    /**
     * Check if a user's email is verified
     * 
     * @param int $userId User ID
     * @return bool True if verified, false otherwise
     */
    public function isEmailVerified($userId) {
        $stmt = $this->conn->prepare("SELECT email_verified FROM users WHERE id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            return false;
        }
        
        $userData = $result->fetch_assoc();
        return (bool)$userData['email_verified'];
    }
    
    /**
     * Get HTML template for verification email
     * 
     * @param string $verification_code The verification code
     * @return string HTML email template
     */
    private function getVerificationEmailTemplate($verification_code) {
        return '<!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Verify Your Email</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background-color: #189dc5; color: white; padding: 20px; text-align: center; }
                .content { padding: 20px; background-color: #f9f9f9; }
                .verification-code { font-size: 24px; font-weight: bold; text-align: center; 
                                   margin: 20px 0; letter-spacing: 5px; color: #189dc5; }
                .footer { text-align: center; margin-top: 20px; font-size: 12px; color: #666; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1>Aftermarket Toolkit</h1>
                </div>
                <div class="content">
                    <h2>Verify Your Email Address</h2>
                    <p>Thank you for registering! Please use the verification code below to complete your registration:</p>
                    <div class="verification-code">' . $verification_code . '</div>
                    <p>This code will expire in ' . $this->otp_expiry . ' minutes.</p>
                    <p>If you didn\'t request this verification, please ignore this email.</p>
                </div>
                <div class="footer">
                    <p>&copy; ' . date('Y') . ' Aftermarket Toolkit. All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>';
    }
    
    /**
     * Get HTML template for password reset email
     * 
     * @param string $reset_otp The OTP for password reset
     * @return string HTML email template
     */
    private function getPasswordResetEmailTemplate($reset_otp) {
        return '<!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Password Reset</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background-color: #189dc5; color: white; padding: 20px; text-align: center; }
                .content { padding: 20px; background-color: #f9f9f9; }
                .reset-code { font-size: 24px; font-weight: bold; text-align: center; 
                             margin: 20px 0; letter-spacing: 5px; color: #189dc5; }
                .footer { text-align: center; margin-top: 20px; font-size: 12px; color: #666; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1>Aftermarket Toolkit</h1>
                </div>
                <div class="content">
                    <h2>Password Reset Request</h2>
                    <p>We received a request to reset your password. Please use the following OTP code:</p>
                    <div class="reset-code">' . $reset_otp . '</div>
                    <p>This code will expire in ' . $this->otp_expiry . ' minutes.</p>
                    <p>If you didn\'t request a password reset, please ignore this email or contact support if you have concerns.</p>
                </div>
                <div class="footer">
                    <p>&copy; ' . date('Y') . ' Aftermarket Toolkit. All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>';
    }
}
?>
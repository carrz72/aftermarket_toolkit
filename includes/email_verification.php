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
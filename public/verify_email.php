?php
<?php
/**
 * Email Verification Handler
 * 
 * Manages email verification tokens, sending verification emails,
 * and verifying tokens.
 */
class EmailVerification {
    private $conn;
    
    /**
     * Constructor
     * 
     * @param mysqli $conn Database connection
     */
    public function __construct($conn) {
        $this->conn = $conn;
    }
    
    /**
     * Generate a verification token for a user
     * 
     * @param int $userId The user ID
     * @return string The generated token
     */
    public function generateToken($userId) {
        // Generate a random token
        $token = bin2hex(random_bytes(32));
        
        // Set expiration time (24 hours from now)
        $expires = date('Y-m-d H:i:s', time() + 86400);
        
        // Delete any existing tokens for this user
        $stmt = $this->conn->prepare("DELETE FROM email_verification_tokens WHERE user_id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        
        // Insert the new token
        $stmt = $this->conn->prepare("INSERT INTO email_verification_tokens (user_id, token, expires_at) VALUES (?, ?, ?)");
        $stmt->bind_param("iss", $userId, $token, $expires);
        $stmt->execute();
        
        return $token;
    }
    
    /**
     * Verify a token and mark user's email as verified
     * 
     * @param string $token The verification token
     * @return array Result with success status and message
     */
    public function verifyToken($token) {
        // Sanitize input
        $token = $this->conn->real_escape_string($token);
        
        // Check if token exists and is valid
        $stmt = $this->conn->prepare("SELECT * FROM email_verification_tokens WHERE token = ? AND expires_at > NOW()");
        $stmt->bind_param("s", $token);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            return [
                'success' => false,
                'message' => 'Invalid or expired verification token. Please request a new verification email.'
            ];
        }
        
        // Get the user ID
        $tokenData = $result->fetch_assoc();
        $userId = $tokenData['user_id'];
        
        // Update user's email verification status
        $updateStmt = $this->conn->prepare("UPDATE users SET email_verified = 1 WHERE id = ?");
        $updateStmt->bind_param("i", $userId);
        $success = $updateStmt->execute();
        
        if (!$success) {
            return [
                'success' => false,
                'message' => 'Failed to verify email. Please try again or contact support.'
            ];
        }
        
        // Delete the used token
        $deleteStmt = $this->conn->prepare("DELETE FROM email_verification_tokens WHERE token = ?");
        $deleteStmt->bind_param("s", $token);
        $deleteStmt->execute();
        
        return [
            'success' => true,
            'message' => 'Your email has been successfully verified!'
        ];
    }
    
    /**
     * Send verification email to the user
     * 
     * @param int $userId The user ID
     * @param string $email User's email address
     * @param string $username User's username
     * @return bool Whether the email was sent successfully
     */
    public function sendVerificationEmail($userId, $email, $username) {
        // Generate a verification token
        $token = $this->generateToken($userId);
        
        // Create the verification link
        $verificationLink = "http://" . $_SERVER['HTTP_HOST'] . "/aftermarket_toolkit/public/verify_email.php?token=" . $token;
        
        // Email subject
        $subject = "Verify Your Email - Aftermarket Toolbox";
        
        // Email body
        $message = "
        <html>
        <head>
            <title>Email Verification</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background-color: #189dc5; color: white; padding: 10px 20px; text-align: center; }
                .content { padding: 20px; background-color: #f9f9f9; }
                .button { display: inline-block; background-color: #189dc5; color: white; padding: 10px 20px; 
                          text-decoration: none; border-radius: 5px; margin: 20px 0; }
                .footer { font-size: 12px; color: #777; text-align: center; margin-top: 20px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>Email Verification</h1>
                </div>
                <div class='content'>
                    <p>Hello $username,</p>
                    <p>Thank you for registering with Aftermarket Toolbox! To complete your registration, please verify your email address by clicking the button below:</p>
                    <p style='text-align: center;'>
                        <a href='$verificationLink' class='button'>Verify Email Address</a>
                    </p>
                    <p>If the button doesn't work, you can copy and paste the following link into your browser:</p>
                    <p>$verificationLink</p>
                    <p>This link will expire in 24 hours.</p>
                    <p>If you didn't create an account, you can safely ignore this email.</p>
                </div>
                <div class='footer'>
                    <p>© " . date('Y') . " Aftermarket Toolbox. All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>
        ";
        
        // Email headers
        $headers = "MIME-Version: 1.0" . "\r\n";
        $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
        $headers .= "From: Aftermarket Toolbox <noreply@aftermarkettoolbox.com>" . "\r\n";
        
        // Send the email
        return mail($email, $subject, $message, $headers);
    }
    
    /**
     * Check if user's email is verified
     * 
     * @param int $userId The user ID
     * @return bool Whether the email is verified
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
}
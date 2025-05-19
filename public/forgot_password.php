<?php
// Forgot password page
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/email_verification.php';

// Initialize the email verification handler
$emailVerification = new EmailVerification($conn);

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Step 1: Request OTP
    if (isset($_POST['request_otp']) && isset($_POST['email'])) {
        $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
        
        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $result = $emailVerification->sendPasswordResetOTP($email);
            
            if ($result['success']) {
                $_SESSION['reset_email'] = $email;
                $_SESSION['success_message'] = $result['message'];
                $_SESSION['show_otp_form'] = true;
            } else {
                $_SESSION['error_message'] = $result['message'];
            }
        } else {
            $_SESSION['error_message'] = 'Please enter a valid email address';
        }
    }
    // Step 2: Verify OTP
    else if (isset($_POST['verify_otp']) && isset($_POST['otp']) && isset($_SESSION['reset_email'])) {
        $otp = trim($_POST['otp']);
        $email = $_SESSION['reset_email'];
        
        if (empty($otp)) {
            $_SESSION['error_message'] = 'Please enter the OTP';
            $_SESSION['show_otp_form'] = true;
        } else {
            $result = $emailVerification->verifyPasswordResetOTP($email, $otp);
              if ($result['success']) {
                $_SESSION['reset_token'] = $result['token'];
                $_SESSION['success_message'] = $result['message'];
                $_SESSION['show_password_form'] = true;
                unset($_SESSION['show_otp_form']);
            } else {
                $_SESSION['error_message'] = $result['message'];
                $_SESSION['show_otp_form'] = true;
            }
        }
    }
    // Step 3: Reset Password
    else if (isset($_POST['reset_password']) && isset($_POST['password']) && isset($_POST['confirm_password']) && isset($_SESSION['reset_token'])) {
        $password = $_POST['password'];
        $confirmPassword = $_POST['confirm_password'];
        $token = $_SESSION['reset_token'];
        
        if (strlen($password) < 8) {
            $_SESSION['error_message'] = 'Password must be at least 8 characters long';
            $_SESSION['show_password_form'] = true;
        } else if ($password !== $confirmPassword) {
            $_SESSION['error_message'] = 'Passwords do not match';
            $_SESSION['show_password_form'] = true;
        } else {
            $result = $emailVerification->resetPassword($token, $password);
            
            if ($result['success']) {
                // Clear all session variables related to password reset
                unset($_SESSION['reset_email']);
                unset($_SESSION['reset_token']);
                unset($_SESSION['show_otp_form']);
                unset($_SESSION['show_password_form']);
                
                $_SESSION['success_message'] = $result['message'];
                header('Location: login.php');
                exit();
            } else {
                $_SESSION['error_message'] = $result['message'];
                $_SESSION['show_password_form'] = true;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - Aftermarket Toolbox</title>
    <link rel="stylesheet" href="./assets/css/index.css">
    <style>
        .reset-container {
            max-width: 500px;
            margin: 50px auto;
            padding: 30px;
            background-color: #fff;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
        }
        
        .reset-title {
            text-align: center;
            margin-bottom: 30px;
            color: #189dc5;
        }
        
        .reset-form {
            margin-bottom: 20px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
            color: #333;
        }
        
        .form-group input {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 16px;
        }
        
        .form-group input:focus {
            border-color: #189dc5;
            outline: none;
            box-shadow: 0 0 5px rgba(24, 157, 197, 0.2);
        }
        
        .reset-btn {
            display: block;
            width: 100%;
            padding: 12px;
            background-color: #189dc5;
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        
        .reset-btn:hover {
            background-color: #157a9e;
        }
        
        .back-to-login {
            text-align: center;
            margin-top: 20px;
        }
        
        .back-to-login a {
            color: #189dc5;
            text-decoration: none;
        }
        
        .back-to-login a:hover {
            text-decoration: underline;
        }
        
        .alert {
            padding: 15px;
            margin-bottom: 20px;
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
        
        .otp-input {
            letter-spacing: 10px;
            font-size: 24px;
            text-align: center;
        }
        
        .step-indicator {
            display: flex;
            justify-content: center;
            margin-bottom: 30px;
        }
        
        .step {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background-color: #ddd;
            color: #666;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 10px;
            font-weight: bold;
        }
        
        .step.active {
            background-color: #189dc5;
            color: white;
        }
        
        .step-label {
            text-align: center;
            margin-top: 5px;
            font-size: 12px;
            color: #666;
        }
    </style>
</head>
<body>
   
    
    <div class="reset-container">
        <h2 class="reset-title">Password Recovery</h2>
        
        <!-- Step indicator -->
        <div class="step-indicator">
            <div>
                <div class="step <?php echo !isset($_SESSION['show_otp_form']) && !isset($_SESSION['show_password_form']) ? 'active' : ''; ?>">1</div>
                <div class="step-label">Request OTP</div>
            </div>
            <div>
                <div class="step <?php echo isset($_SESSION['show_otp_form']) ? 'active' : ''; ?>">2</div>
                <div class="step-label">Verify OTP</div>
            </div>
            <div>
                <div class="step <?php echo isset($_SESSION['show_password_form']) ? 'active' : ''; ?>">3</div>
                <div class="step-label">New Password</div>
            </div>
        </div>
        
        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger">
                <?= htmlspecialchars($_SESSION['error_message']) ?>
                <?php unset($_SESSION['error_message']); ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success">
                <?= htmlspecialchars($_SESSION['success_message']) ?>
                <?php unset($_SESSION['success_message']); ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['show_password_form'])): ?>
            <!-- Step 3: New Password Form -->
            <form method="POST" action="" class="reset-form">
                <div class="form-group">
                    <label for="password">New Password</label>
                    <input type="password" id="password" name="password" required minlength="8">
                </div>
                <div class="form-group">
                    <label for="confirm_password">Confirm Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" required minlength="8">
                </div>
                <button type="submit" name="reset_password" class="reset-btn">Reset Password</button>
            </form>
        <?php elseif (isset($_SESSION['show_otp_form'])): ?>
            <!-- Step 2: OTP Verification Form -->
            <form method="POST" action="" class="reset-form">
                <div class="form-group">
                    <label for="otp">Enter the OTP sent to your email</label>
                    <input type="text" id="otp" name="otp" class="otp-input" required maxlength="6" pattern="[0-9]{6}" autocomplete="off">
                </div>
                <button type="submit" name="verify_otp" class="reset-btn">Verify OTP</button>
            </form>
        <?php else: ?>
            <!-- Step 1: Email Form -->
            <form method="POST" action="" class="reset-form">
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" required>
                </div>
                <button type="submit" name="request_otp" class="reset-btn">Send OTP</button>
            </form>
        <?php endif; ?>
        
        <div class="back-to-login">
            <a href="login.php">Back to Login</a>
        </div>
    </div>
</body>
</html>
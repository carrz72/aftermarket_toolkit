<?php
session_start();
require_once __DIR__ . '/../config/db.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get and trim the submitted username and password
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if(empty($username) || empty($password)){
        $error = "Username and password are required.";
    } else {
        // Prepare statement to prevent SQL injection
        $stmt = $conn->prepare("SELECT id, username, password FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

        // Check if user exists
        if ($result && $result->num_rows === 1) {
            $user = $result->fetch_assoc();
            // Verify password using password_verify()
            if (password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                
                header("Location: ../index.php");
                exit;
            } else {
                $error = "Invalid username or password.";
            }
        } else {
            $error = "Invalid username or password.";
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login - Aftermarket Toolbox</title>
  <link rel="stylesheet" href="../public/assets/css/login.css">
</head>
<body>
  <div class="login-container">
    <?php if (isset($error)): ?>
      <p class="error"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>
    <h2>Login</h2>
    <form method="post" action="">
      <div class="inputs"> 
        <label for="username">User name</label>
        <input type="text" id="username" name="username" required>
      
        <label for="password">Password</label>
        <input type="password" id="password" name="password" required>
      </div>
      <button type="submit">Log In</button>
      <p>Don't have an account? <a href="register.php">Register here</a></p>
      <p>Forgot password? <a href="forgot_password.php">Forgot password</a></p>
      <button type="button" onclick="window.location.href='../index.php'">Back</button>
    </form>
  </div>
</body>
</html>
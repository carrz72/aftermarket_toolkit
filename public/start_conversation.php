<?php
session_start();
require_once __DIR__ . '/../config/db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}



$userId = $_SESSION['user_id'];
$error = '';
$success = '';

// Handle the form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['start_conversation'])) {
    $username = trim($_POST['username']);
    $message = trim($_POST['message']);
    
    if (empty($username) || empty($message)) {
        $error = "Both username and message are required";
    } else {
        // Find the user by username
        $stmt = $conn->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
        $stmt->bind_param("si", $username, $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            $error = "User not found";
        } else {
            $receiver = $result->fetch_assoc();
            $receiverId = $receiver['id'];
            
            // Insert the message
            $msgStmt = $conn->prepare("INSERT INTO messages (sender_id, receiver_id, message, is_read, sent_at) VALUES (?, ?, ?, 0, NOW())");
            $msgStmt->bind_param("iis", $userId, $receiverId, $message);
            
            if ($msgStmt->execute()) {
                $success = "Message sent successfully!";
                header("Location: chat.php?chat=" . $receiverId);
                exit();
            } else {
                $error = "Failed to send message: " . $conn->error;
            }
        }
    }
}

// Get all users except current user for the dropdown
$usersQuery = "SELECT id, username, profile_picture FROM users WHERE id != ? ORDER BY username";
$usersStmt = $conn->prepare($usersQuery);
$usersStmt->bind_param("i", $userId);
$usersStmt->execute();
$usersResult = $usersStmt->get_result();

$users = [];
while ($user = $usersResult->fetch_assoc()) {
    $users[] = $user;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Start Conversation - Aftermarket Toolbox</title>
    <link rel="stylesheet" href="./assets/css/chat.css">
    <style>
        body {
            background-color: #6b6b6b;
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
        }
        
        .back-button {
            display: flex;
            align-items: center;
            color: white;
            text-decoration: none;
            margin: 20px;
            width: fit-content;
        }
        
        .back-button:hover {
            text-decoration: underline;
        }
        
        .back-button .icon {
            margin-right: 8px;
        }
        
        .new-conversation-container {
            max-width: 600px;
            margin: 50px auto;
            background-color: white;
            border-radius: 12px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.2);
            padding: 30px;
        }
        
        .new-conversation-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .new-conversation-header h2 {
            color: #333;
            margin: 0;
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
        
        .form-control {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 16px;
            box-sizing: border-box;
        }
        
        .form-control:focus {
            border-color: #189dc5;
            outline: none;
            box-shadow: 0 0 0 2px rgba(24, 157, 197, 0.2);
        }
        
        .message-textarea {
            min-height: 120px;
            resize: vertical;
        }
        
        .btn-primary {
            background-color: #189dc5;
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: 8px;
            font-size: 16px;
            cursor: pointer;
            width: 100%;
            transition: background-color 0.3s, transform 0.3s;
        }
        
        .btn-primary:hover {
            background-color: #157a9e;
            transform: translateY(-2px);
        }
        
        .btn-primary:active {
            transform: translateY(0);
        }
        
        .alert {
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            position: relative;
        }
        
        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .user-search-container {
            position: relative;
        }
        
        .user-suggestions {
            position: absolute;
            top: 100%;
            left: 0;
            width: 100%;
            background-color: white;
            border: 1px solid #ddd;
            border-radius: 0 0 8px 8px;
            max-height: 200px;
            overflow-y: auto;
            z-index: 10;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            display: none;
        }
        
        .user-suggestions.active {
            display: block;
        }
        
        .user-suggestion {
            padding: 12px 15px;
            cursor: pointer;
            display: flex;
            align-items: center;
            border-bottom: 1px solid #eee;
            transition: background-color 0.2s;
        }
        
        .user-suggestion:last-child {
            border-bottom: none;
        }
        
        .user-suggestion:hover {
            background-color: #f0f2f5;
        }
        
        .user-avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            margin-right: 10px;
            object-fit: cover;
        }
        
        .icon {
            display: inline-block;
            width: 16px;
            height: 16px;
            stroke-width: 0;
            stroke: currentColor;
            fill: currentColor;
            vertical-align: middle;
        }
        
        @media (max-width: 768px) {
            .new-conversation-container {
                margin: 20px;
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <a href="chat.php" class="back-button">
        <svg class="icon" viewBox="0 0 24 24">
            <path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z"/>
        </svg>
        Back to Chats
    </a>
    
    <div class="new-conversation-container">
        <div class="new-conversation-header">
            <h2>Start New Conversation</h2>
        </div>
        
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <?php if (!empty($success)): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <div class="form-group user-search-container">
                <label for="username">Select User:</label>
                <input type="text" id="username" name="username" class="form-control" 
                       placeholder="Type username to search" autocomplete="off">
                <div class="user-suggestions" id="userSuggestions"></div>
            </div>
            
            <div class="form-group">
                <label for="message">Message:</label>
                <textarea id="message" name="message" class="form-control message-textarea" 
                          placeholder="Type your first message..."></textarea>
            </div>
            
            <button type="submit" name="start_conversation" class="btn-primary">
                Send Message
            </button>
        </form>
    </div>
    
    <script>
        const userInput = document.getElementById('username');
        const suggestionsContainer = document.getElementById('userSuggestions');
        const users = <?= json_encode($users) ?>;
        
        userInput.addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            
            // Clear and hide suggestions if search term is too short
            if (searchTerm.length < 1) {
                suggestionsContainer.classList.remove('active');
                suggestionsContainer.innerHTML = '';
                return;
            }
            
            // Filter users whose usernames match the search term
            const matches = users.filter(user => {
                return user.username.toLowerCase().includes(searchTerm);
            });
            
            // Show matching users or a no results message
            if (matches.length > 0) {
                suggestionsContainer.classList.add('active');
                suggestionsContainer.innerHTML = matches.map(user => {
                    const profileImg = user.profile_picture ? 
                        `./assets/images/${user.profile_picture}` : 
                        './assets/images/default-profile.jpg';
                    
                    return `<div class="user-suggestion" data-username="${user.username}">
                        <img src="${profileImg}" alt="${user.username}" class="user-avatar">
                        <span>${user.username}</span>
                    </div>`;
                }).join('');
                
                // Add click handlers to suggestions
                document.querySelectorAll('.user-suggestion').forEach(suggestion => {
                    suggestion.addEventListener('click', function() {
                        userInput.value = this.dataset.username;
                        suggestionsContainer.classList.remove('active');
                    });
                });
            } else {
                suggestionsContainer.innerHTML = '<div class="user-suggestion">No users found</div>';
                suggestionsContainer.classList.add('active');
            }
        });
        
        // Hide suggestions when clicking outside
        document.addEventListener('click', function(e) {
            if (e.target !== userInput && !suggestionsContainer.contains(e.target)) {
                suggestionsContainer.classList.remove('active');
            }
        });
    </script>
</body>
</html>
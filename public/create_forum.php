<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Post a Forum - Aftermarket Toolbox</title>
    <link rel="stylesheet" href="./assets/css/create_forum.css">
</head>
<body>
<div class="forum-container">
    <h2>Post a New Thread</h2>    <div id="error-messages" style="color: red;"></div>
    <form id="create-thread-form" method="POST">
    <label for="category">Category:</label><br>
        <select id="category" name="category" required>
            <option value="">Select a Category</option>
            <option value="announcements">Announcements</option>
            <option value="questions">Questions</option>
            <option value="general">General</option>
            <option value="feedback">Feedback</option>
            <!-- Add more categories as needed -->
        </select><br><br>

        <label for="title">Title:</label><br>
        <input type="text" id="title" name="title" required><br><br>
        <label for="content">Content:</label><br>
        <textarea id="content" name="content" rows="8" required></textarea><br><br>

        <button type="submit">Post Thread</button>
    </form>
    <button type="button" onclick="window.location.href='forum.php';">Back to Forum</button>
</div>

<script>
document.getElementById('create-thread-form').addEventListener('submit', function (e) {
    e.preventDefault();

    const title = document.getElementById('title').value.trim();
    const category = document.getElementById('category').value.trim();
    const content = document.getElementById('content').value.trim();
    const userId = <?= json_encode($_SESSION['user_id']) ?>;

    if (!title || !category || !content) {
        document.getElementById('error-messages').innerText = 'Title, category, and content are required.';
        return;
    }    fetch('../api/forum_threads/create_thread.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `title=${encodeURIComponent(title)}&category=${encodeURIComponent(category)}&body=${encodeURIComponent(content)}`
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                window.location.href = 'forum.php?thread=' + data.thread_id;
            } else {
                document.getElementById('error-messages').innerText = data.message || 'Failed to post the thread.';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            document.getElementById('error-messages').innerText = 'An error occurred. Please try again.';
        });
});
</script>
</body>
</html>
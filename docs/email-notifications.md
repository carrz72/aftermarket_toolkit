# Email Notification Implementation in Aftermarket Toolkit

This document outlines how to implement email notifications for forum responses and chat messages.

## Overview

Email notifications are now available for:
- Forum responses - when someone replies to your thread
- Chat messages - when you receive a new message

Notifications are only sent if the user has enabled email notifications in their profile settings.

## Files Created/Modified

1. `forum_response_handler.php` - Handles forum responses with email notifications
2. `chat_message_handler.php` - Handles chat messages with email notifications

## How to Integrate Email Notifications

### 1. Run the Setup Script

First, run the setup script to ensure your database has the email_notifications column:
```
http://localhost/aftermarket_toolkit/includes/ensure_email_notifications_column.php
```

### 2. Update the Forum Response Form

Modify the form action in forum.php to use the new handler:
```php
<form id="form-<?= $thread_id ?>" class="response-form" method="POST" action="../api/forum_threads/forum_response_handler.php">
```

### 3. Update the Chat Message System

If you're using JavaScript to send chat messages, update the AJAX URL to:
```javascript
url: "../api/chat/chat_message_handler.php"
```

### 4. Test the Implementation

- Try responding to a forum thread and verify the thread owner receives an email
- Try sending a chat message and verify the recipient receives an email
- Test the email notification preference by toggling it in the profile settings

## Technical Details

The implementation follows these steps:

1. When a forum response or chat message is created, we check if the recipient has enabled email notifications
2. If enabled, we create a notification in the database and send an email
3. The email contains formatting similar to the site's design
4. The email includes a link back to the relevant content

The email notification system relies on:
- PHPMailer for sending emails
- User preferences stored in the `email_notifications` column
- The `sendNotificationEmail()` function in `notification_email.php`

## Troubleshooting

If email notifications aren't working:

1. Check if PHPMailer is properly configured in `mailer.php`
2. Verify the user has a valid email address and has enabled notifications
3. Check the PHP error logs for any error messages
4. Make sure the database has the `email_notifications` column in the users table
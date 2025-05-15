<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/image_helper.php';
require_once __DIR__ . '/../includes/listing_preview_helper.php'; // Add listing preview helper

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$userId = $_SESSION['user_id'];

// Check if the user was redirected from a listing page
$listingId = isset($_GET['listing_id']) ? (int)$_GET['listing_id'] : null;
$listingTitle = isset($_GET['listing_title']) ? urldecode($_GET['listing_title']) : null;
$listingImage = isset($_GET['listing_image']) ? $_GET['listing_image'] : null;

// Convert URL-encoded paths to proper file paths
if ($listingImage) {
    $listingImage = urldecode($listingImage);
}

// Debug the listing parameters - remove in production
error_log("Debug - Listing ID: $listingId, Title: $listingTitle, Image: $listingImage");

$listingsQuery = "
    SELECT id, title, price, image 
    FROM listings 
    WHERE user_id = ? 
    ORDER BY created_at DESC 
    LIMIT 10
";
$listingsStmt = $conn->prepare($listingsQuery);
$listingsStmt->bind_param("i", $userId);
$listingsStmt->execute();
$listingsResult = $listingsStmt->get_result();

$userListings = [];
while ($listing = $listingsResult->fetch_assoc()) {
    $userListings[] = $listing;
}

// Get the current view (all or listings)
$view = 'all'; // Removed view selection, default to showing all messages

// Modify contactsQuery to show all conversations (removed filtering for listing-related messages)
$contactsQuery = "
    SELECT DISTINCT 
        u.id, 
        u.username AS name,
        u.profile_picture,
        (
            SELECT m.message 
            FROM messages m 
            WHERE (
                (m.sender_id = u.id AND m.receiver_id = ?) OR 
                (m.sender_id = ? AND m.receiver_id = u.id)
            )
            ORDER BY m.sent_at DESC 
            LIMIT 1
        ) AS last_message,
        (
            SELECT m.sent_at 
            FROM messages m 
            WHERE (
                (m.sender_id = u.id AND m.receiver_id = ?) OR 
                (m.sender_id = ? AND m.receiver_id = u.id)
            )
            ORDER BY m.sent_at DESC 
            LIMIT 1
        ) AS last_message_time,
        (
            SELECT COUNT(*) 
            FROM messages 
            WHERE sender_id = u.id AND receiver_id = ? AND is_read = 0
        ) AS unread_count
    FROM users u
    JOIN messages m ON (u.id = m.sender_id OR u.id = m.receiver_id)
    WHERE (m.sender_id = ? OR m.receiver_id = ?) AND u.id != ? 
    GROUP BY u.id
    ORDER BY last_message_time DESC
";

$contactsStmt = $conn->prepare($contactsQuery);
$contactsStmt->bind_param("iiiiiiii", $userId, $userId, $userId, $userId, $userId, $userId, $userId, $userId);
$contactsStmt->execute();
$contactsResult = $contactsStmt->get_result();

$contacts = [];
while ($contact = $contactsResult->fetch_assoc()) {
    // Format timestamp to human-readable format
    $timestamp = strtotime($contact['last_message_time']);
    $now = time();
    $diff = $now - $timestamp;
    
    if ($diff < 86400) {  // Less than 24 hours
        $timeFormatted = date('g:i A', $timestamp);
    } else if ($diff < 172800) {  // Less than 48 hours
        $timeFormatted = 'Yesterday';
    } else if ($diff < 604800) {  // Less than a week
        $timeFormatted = date('D', $timestamp);
    } else {
        $timeFormatted = date('M j', $timestamp);
    }
    
    $contacts[] = [
        'id' => $contact['id'],
        'name' => $contact['name'],
        'profile_picture' => $contact['profile_picture'],
        'last_message' => $contact['last_message'],
        'time' => $timeFormatted,
        'unread' => (int)$contact['unread_count']
    ];
}

// If no contacts found, don't use placeholders but show a proper empty state
if (empty($contacts)) {
    // Don't set dummy contacts, leave the array empty
    $contacts = [];
    
    // No active chat possible
    $activeChatId = 0;
    $activeContact = null;
} else {
    // Get active chat ID from URL or default to first contact
    $activeChatId = isset($_GET['chat']) ? (int)$_GET['chat'] : ($contacts[0]['id'] ?? 0);

    // Find the active contact
    $activeContact = null;
    foreach ($contacts as $contact) {
        if ($contact['id'] === $activeChatId) {
            $activeContact = $contact;
            break;
        }
    }
}

// Get active chat ID from URL or default to first contact
$activeChatId = isset($_GET['chat']) ? (int)$_GET['chat'] : ($contacts[0]['id'] ?? 0);

// If we have a chat ID from the URL but no existing conversation with that user,
// create a virtual contact entry. This happens when clicking "Message Seller" from a listing
if ($activeChatId > 0) {
    $found = false;
    foreach ($contacts as $contact) {
        if ($contact['id'] === $activeChatId) {
            $found = true;
            break;
        }
    }
    
    // Only create virtual contact if no existing conversation with this user
    if (!$found) {
        // Get the seller information
        $sellerQuery = "SELECT id, username, profile_picture FROM users WHERE id = ?";
        $sellerStmt = $conn->prepare($sellerQuery);
        $sellerStmt->bind_param("i", $activeChatId);
        $sellerStmt->execute();
        $sellerResult = $sellerStmt->get_result();
        
        if ($sellerRow = $sellerResult->fetch_assoc()) {
            $contacts[] = [
                'id' => $sellerRow['id'],
                'name' => $sellerRow['username'],
                'profile_picture' => $sellerRow['profile_picture'],
                'last_message' => '',
                'time' => 'Now',
                'unread' => 0
            ];
        }
    }
}

// Find the active contact
$activeContact = null;
foreach ($contacts as $contact) {
    if ($contact['id'] === $activeChatId) {
        $activeContact = $contact;
        break;
    }
}

// Fetch messages for the active chat
$messages = [];
if ($activeContact) {
    // Mark messages as read when user opens the conversation
    $markReadQuery = "
        UPDATE messages 
        SET is_read = 1 
        WHERE sender_id = ? AND receiver_id = ? AND is_read = 0
    ";
    $markReadStmt = $conn->prepare($markReadQuery);
    $markReadStmt->bind_param("ii", $activeChatId, $userId);
    $markReadStmt->execute();
    
    // Get all messages between these two users
    $messagesQuery = "
        SELECT m.*, m.listing_id 
        FROM messages m
        WHERE 
            (m.sender_id = ? AND m.receiver_id = ?) OR
            (m.sender_id = ? AND m.receiver_id = ?)
        ORDER BY m.sent_at ASC
    ";
    $messagesStmt = $conn->prepare($messagesQuery);
    $messagesStmt->bind_param("iiii", $userId, $activeChatId, $activeChatId, $userId);
    $messagesStmt->execute();
    $messagesResult = $messagesStmt->get_result();
    
    while ($message = $messagesResult->fetch_assoc()) {
        $timestamp = strtotime($message['sent_at']);
        $timeFormatted = date('g:i A', $timestamp);
        
        $messages[] = [
            'id' => $message['id'],
            'sender_id' => $message['sender_id'],
            'message' => $message['message'],
            'listing_id' => $message['listing_id'],
            'time' => $timeFormatted,
            'is_read' => $message['is_read']
        ];
    }
    
    // If no messages found, provide some placeholder data for UI testing
    if (empty($messages)) {
        $messages = [

        ];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chat - Aftermarket Toolbox</title>
    <link rel="stylesheet" href="./assets/css/chat.css">
    <style>
        /* Inline SVG icon styles */
        .icon {
            display: inline-block;
            width: 16px;
            height: 16px;
            stroke-width: 0;
            stroke: currentColor;
            fill: currentColor;
            vertical-align: middle;
        }
        
        .icon-lg {
            width: 24px;
            height: 24px;
        }
        
        .icon-xl {
            width: 48px;
            height: 48px;
        }
        
        /* Listing preview styles */
        .listing-preview-container {
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            margin-bottom: 10px;
            background-color: #f8f9fa;
            position: relative;
        }
        
        .listing-preview {
            display: flex;
            padding: 10px;
        }
        
        .listing-preview-image {
            width: 60px;
            height: 60px;
            margin-right: 10px;
            flex-shrink: 0;
        }
        
        .listing-preview-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 4px;
        }
        
        .listing-preview-info {
            flex-grow: 1;
            position: relative;
        }
        
        .listing-preview-title {
            font-weight: bold;
            font-size: 14px;
            margin-bottom: 5px;
            color: #333;
        }
        
        .listing-preview-view {
            font-size: 12px;
            color: #189dc5;
            text-decoration: none;
        }
        
        .listing-preview-view:hover {
            text-decoration: underline;
        }
        
        .remove-listing-btn {
            position: absolute;
            top: 0;
            right: 0;
            background: none;
            border: none;
            cursor: pointer;
            color: #999;
            padding: 2px;
        }
        
        .remove-listing-btn:hover {
            color: #555;
        }
        
        /* Message listing preview */
        .message-listing-preview {
            background-color: #f0f2f5;
            padding: 8px;
            border-radius: 8px;
            margin-top: 5px;
            border-left: 3px solid #189dc5;
        }
    </style>
</head>
<body>
    <a href="../index.php" class="back-button">
        <svg class="icon" viewBox="0 0 24 24">
            <path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z"/>
        </svg>
        Back to Home
    </a>
    
    <div class="chat-container">
        <div class="contacts-sidebar">
            <div class="contacts-header">
                <div class="conversations-title">
                    <h2>Conversations</h2>
                </div>
                <button class="new-chat-btn" onclick="location.href='start_conversation.php';">
                    <svg class="icon" viewBox="0 0 24 24">
                        <path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/>
                    </svg>
                </button>
            </div>
            <div class="search-container">
                <input type="text" placeholder="Search contacts..." class="search-input" id="contact-search">
                <svg class="icon search-icon" viewBox="0 0 24 24">
                    <path d="M15.5 14h-.79l-.28-.27C15.41 12.59 16 11.11 16 9.5 16 5.91 13.09 3 9.5 3S3 5.91 3 9.5 5.91 16 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/>
                </svg>
            </div>
            <div class="contacts-list">
    <?php if (!empty($contacts)): ?>
        <?php foreach ($contacts as $contact): ?>
            <a href="?chat=<?= $contact['id'] ?>" class="contact-item <?= $contact['id'] === $activeChatId ? 'active' : '' ?>">
                <div class="contact-avatar">
                    <?php if (!empty($contact['profile_picture'])): ?>
                        <img src="<?= htmlspecialchars($contact['profile_picture']) ?>" alt="<?= htmlspecialchars($contact['name']) ?>">
                    <?php else: ?>
                        <img src="./assets/images/default-profile.jpg" alt="<?= htmlspecialchars($contact['name']) ?>">
                    <?php endif; ?>
                    <?php if ($contact['unread'] > 0): ?>
                        <span class="unread-badge"><?= $contact['unread'] ?></span>
                    <?php endif; ?>
                </div>
                <div class="contact-info">
                    <div class="contact-name"><?= htmlspecialchars($contact['name']) ?></div>
                    <div class="contact-last-message"><?= htmlspecialchars($contact['last_message']) ?></div>
                </div>
                <div class="contact-time"><?= htmlspecialchars($contact['time']) ?></div>
            </a>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="empty-contacts">
            <div class="empty-contacts-icon">
                <svg class="icon icon-lg" viewBox="0 0 24 24">
                    <path d="M20 2H4c-1.1 0-1.99.9-1.99 2L2 22l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm-2 12H6v-2h12v2zm0-3H6V9h12v2zm0-3H6V6h12v2z"/>
                </svg>
            </div>
            <p class="empty-contacts-text">No conversations yet</p>
            <a href="start_conversation.php" class="empty-contacts-button">Start a new conversation</a>
        </div>
    <?php endif; ?>
</div>
        </div>

        <div class="chat-main">
            <?php if ($activeContact): ?>
                <div class="chat-header">
                    <button class="mobile-contacts-toggle">
                        <svg class="icon" viewBox="0 0 24 24">
                            <path d="M3 18h18v-2H3v2zm0-5h18v-2H3v2zm0-7v2h18V6H3z"/>
                        </svg>
                    </button>
                    <div class="chat-header-avatar">
                        <?php if (!empty($activeContact['profile_picture'])): ?>
                            <img src="<?= htmlspecialchars($activeContact['profile_picture']) ?>" alt="<?= htmlspecialchars($activeContact['name']) ?>">
                        <?php else: ?>
                            <img src="./assets/images/default-profile.jpg" alt="<?= htmlspecialchars($activeContact['name']) ?>">
                        <?php endif; ?>
                    </div>
                    <div class="chat-header-info">
                        <div class="chat-header-name"><?= htmlspecialchars($activeContact['name']) ?></div>
                        <div class="chat-header-status">Online</div>
                    </div>
                    <div class="chat-header-actions">
                        <button class="icon-button">
                            <svg class="icon" viewBox="0 0 24 24">
                                <path d="M12 8c1.1 0 2-.9 2-2s-.9-2-2-2-2 .9-2 2 .9 2 2 2zm0 2c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2zm0 6c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2z"/>
                            </svg>
                        </button>
                    </div>
                </div>

                <div class="chat-body">
                    <div class="messages-container">
    <?php foreach ($messages as $message): 
        // Extract listing information using the listing_id field or from the message text
        $listingId = null;
        $listingTitle = '';
        
        // Check if message has listing_id in database field
        if (!empty($message['listing_id'])) {
            $listingId = $message['listing_id'];
            
            // Fetch listing title from database
            $listingQuery = "SELECT title FROM listings WHERE id = ?";
            $listingStmt = $conn->prepare($listingQuery);
            $listingStmt->bind_param('i', $listingId);
            $listingStmt->execute();
            $listingResult = $listingStmt->get_result();
            if ($listingRow = $listingResult->fetch_assoc()) {
                $listingTitle = $listingRow['title'];
            }
        } 
        // If not in database field, extract from message text as fallback
        else if (preg_match('/\[LISTING_ID:(\d+)\]/', $message['message'], $listingMatches)) {
            $listingId = $listingMatches[1];
            
            // Extract listing title if available
            if (preg_match('/\[LISTING_TITLE:([^\]]+)\]/', $message['message'], $titleMatches)) {
                $listingTitle = $titleMatches[1];
            } else {
                // Try to get title from database as fallback
                $listingQuery = "SELECT title FROM listings WHERE id = ?";
                $listingStmt = $conn->prepare($listingQuery);
                $listingStmt->bind_param('i', $listingId);
                $listingStmt->execute();
                $listingResult = $listingStmt->get_result();
                if ($listingRow = $listingResult->fetch_assoc()) {
                    $listingTitle = $listingRow['title'];
                }
            }
        }
        
        // Clean up message text by removing listing markers
        $displayMessage = preg_replace('/\[LISTING_ID:\d+\]\[LISTING_TITLE:[^\]]+\]/', '', $message['message']);
    ?>
        <div class="message-box <?= $message['sender_id'] === $userId ? 'right' : 'left' ?>">
            <div class="message-content">
                <p><?= htmlspecialchars($displayMessage) ?></p>
            </div>
            
            <?php if ($listingId && $listingTitle): ?>
            <div class="message-listing-preview">
                <div class="listing-preview-title"><?= htmlspecialchars($listingTitle) ?></div>
                <a href="../api/listings/listing.php?id=<?= $listingId ?>" class="listing-preview-view">View listing</a>
            </div>
            <?php endif; ?>
            
            <div class="message-time">
                <?= htmlspecialchars($message['time']) ?>
                <?php if ($message['sender_id'] === $userId): ?>
                    <svg class="icon message-status" viewBox="0 0 24 24" style="width: 12px; height: 12px;">
                        <?php if ($message['is_read']): ?>
                            <path d="M18 7l-1.41-1.41-6.34 6.34 1.41 1.41L18 7zm4.24-1.41L11.66 16.17 7.48 12l-1.41 1.41L11.66 19l12-12-1.42-1.41zM.41 13.41L6 19l1.41-1.41L1.83 12 .41 13.41z"/>
                        <?php else: ?>
                            <path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41L9 16.17z"/>
                        <?php endif; ?>
                    </svg>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>
</div>

                    <div class="message-input">
                        <?php if (isset($_GET['listing_id']) && isset($_GET['listing_title'])): ?>
                        <div class="listing-preview-container">
                            <div class="listing-preview">
                                <?php if (isset($_GET['listing_image'])): ?>
                                <div class="listing-preview-image">
                                    <img src="<?= htmlspecialchars(urldecode($_GET['listing_image'])) ?>" 
                                         alt="<?= htmlspecialchars(urldecode($_GET['listing_title'])) ?>">
                                </div>
                                <?php endif; ?>
                                <div class="listing-preview-info">
                                    <div class="listing-preview-title"><?= htmlspecialchars(urldecode($_GET['listing_title'])) ?></div>
                                    <a href="../api/listings/listing.php?id=<?= $_GET['listing_id'] ?>" class="listing-preview-view">View listing</a>
                                    <button type="button" class="remove-listing-btn" onclick="removeListingPreview()">
                                        <svg class="icon" viewBox="0 0 24 24" width="16" height="16">
                                            <path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/>
                                        </svg>
                                    </button>
                                </div>
                            </div>
                            <input type="hidden" id="attached-listing-id" value="<?= $_GET['listing_id'] ?>">
                            <input type="hidden" id="attached-listing-title" value="<?= htmlspecialchars(urldecode($_GET['listing_title'])) ?>">
                        </div>
                        <?php endif; ?>
                        <textarea placeholder="Type your message here" class="message-send"><?php 
                            if (isset($_GET['listing_id']) && isset($_GET['listing_title'])) {
                                $title = htmlspecialchars(urldecode($_GET['listing_title']));
                                echo "Hi, I'm interested in your listing \"$title\". Is it still available?";
                            }
                        ?></textarea>
                        <button type="submit" class="button-send">
                            <svg class="icon" viewBox="0 0 24 24">
                                <path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/>
                            </svg>
                        </button>
                    </div>
                </div>
            <?php else: ?>
                <div class="no-chat-selected">
                    <div class="no-chat-icon">
                        <svg class="icon icon-xl" viewBox="0 0 24 24">
                            <path d="M20 2H4c-1.1 0-1.99.9-1.99 2L2 22l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zM6 9h12v2H6V9zm8 5H6v-2h8v2zm4-6H6V6h12v2z"/>
                        </svg>
                    </div>
                    <h2>No conversation selected</h2>
                    <p>Choose a conversation from the sidebar or start a new one</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

<script>
    // Contact search functionality
    document.getElementById('contact-search').addEventListener('input', function() {
        const searchTerm = this.value.toLowerCase();
        document.querySelectorAll('.contact-item').forEach(contact => {
            const name = contact.querySelector('.contact-name').textContent.toLowerCase();
            const message = contact.querySelector('.contact-last-message').textContent.toLowerCase();
            
            if (name.includes(searchTerm) || message.includes(searchTerm)) {
                contact.style.display = 'flex';
            } else {
                contact.style.display = 'none';
            }
        });
    });
    
    // Mobile sidebar toggle
    document.querySelector('.mobile-contacts-toggle')?.addEventListener('click', function() {
        document.querySelector('.chat-container').classList.toggle('show-contacts');
    });
    
    // Auto-scroll to bottom of messages on load
    window.onload = function() {
        const messagesContainer = document.querySelector('.messages-container');
        if (messagesContainer) {
            messagesContainer.scrollTop = messagesContainer.scrollHeight;
        }
    };

    // Function to remove listing preview
    function removeListingPreview() {
        const container = document.querySelector('.listing-preview-container');
        if (container) {
            container.remove();
            
            // Also clear any pre-filled message text
            const messageInput = document.querySelector('.message-send');
            if (messageInput && messageInput.value.includes('interested in your listing')) {
                messageInput.value = '';
            }
        }
    }

    // Handle message sending
    document.querySelector('.button-send')?.addEventListener('click', function(e) {
        e.preventDefault();
        sendMessage();
    });

    document.querySelector('.message-send')?.addEventListener('keydown', function(e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            sendMessage();
        }
    });

    function sendMessage() {
        const messageInput = document.querySelector('.message-send');
        let message = messageInput.value.trim();
        
        if (message) {
            const contactId = <?= json_encode($activeChatId) ?>;
            const messagesContainer = document.querySelector('.messages-container');
            const now = new Date();
            const timeString = now.getHours() + ':' + (now.getMinutes() < 10 ? '0' : '') + now.getMinutes();
            
            // Check if message contains a listing reference
            const listingContainer = document.querySelector('.listing-preview-container');
            const listingRef = document.querySelector('#attached-listing-id')?.value;
            const listingTitle = document.querySelector('#attached-listing-title')?.value;
            
            let messageHTML = `
                <div class="message-content">
                    <p>${message}</p>
                </div>
            `;
            
            // Prepare message with listing tags if we have a listing reference
            let messageText = message;
            if (listingRef && listingTitle) {
                // Add listing tags at the end of the message
                messageText = `${message} [LISTING_ID:${listingRef}][LISTING_TITLE:${listingTitle}]`;
                messageHTML += `
                    <div class="message-listing-preview">
                        <div class="listing-preview-title">${listingTitle}</div>
                        <a href="../api/listings/listing.php?id=${listingRef}" class="listing-preview-view">View listing</a>
                    </div>
                `;
            }
            
            messageHTML += `
                <div class="message-time">
                    ${timeString}
                    <svg class="icon message-status" viewBox="0 0 24 24" style="width: 12px; height: 12px;">
                        <path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41L9 16.17z"/>
                    </svg>
                </div>
            `;
            
            // Add message to UI
            const newMessage = document.createElement('div');
            newMessage.className = 'message-box right';
            newMessage.innerHTML = messageHTML;
            
            messagesContainer.appendChild(newMessage);
            messageInput.value = '';
            
            // Remove the listing preview after sending
            if (listingRef) {
                const previewContainer = document.querySelector('.listing-preview-container');
                if (previewContainer) {
                    previewContainer.remove();
                }
            }
            
            // Scroll to bottom
            messagesContainer.scrollTop = messagesContainer.scrollHeight;
              // Send message to server via AJAX
            const formData = new FormData();
            formData.append('message', messageText);
            formData.append('receiver_id', contactId);
            formData.append('from_chat_page', '1');  // Flag to prevent duplicate notifications
            
            fetch('send_message.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update message status after confirmation from server
                    setTimeout(() => {
                        const statusIcon = newMessage.querySelector('.message-status');
                        statusIcon.innerHTML = `
                            <path d="M18 7l-1.41-1.41-6.34 6.34 1.41 1.41L18 7zm4.24-1.41L11.66 16.17 7.48 12l-1.41 1.41L11.66 19l12-12-1.42-1.41zM.41 13.41L6 19l1.41-1.41L1.83 12 .41 13.41z"/>
                        `;
                    }, 1000);
                } else {
                    alert('Failed to send message: ' + data.error);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Failed to send message. Please try again.');
            });
        }
    }
</script>

</body>
</html>
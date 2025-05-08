<?php
session_start();
require_once __DIR__ . '/../config/db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$userId = $_SESSION['user_id'];

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
$view = isset($_GET['view']) ? $_GET['view'] : 'all';

// Modify your contactsQuery based on view
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
";

// Filter for listing-related messages
if ($view === 'listings') {
    $contactsQuery .= " AND m.message LIKE '%[LISTING_ID:%' ";
}

$contactsQuery .= "
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
        SELECT * FROM messages
        WHERE 
            (sender_id = ? AND receiver_id = ?) OR
            (sender_id = ? AND receiver_id = ?)
        ORDER BY sent_at ASC
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
            'time' => $timeFormatted,
            'is_read' => $message['is_read']
        ];
    }
    
    // If no messages found, provide some placeholder data for UI testing
    if (empty($messages)) {
        $messages = [
            ['id' => 1, 'sender_id' => $activeChatId, 'message' => 'Hello, How are you?', 'time' => '10:30 AM', 'is_read' => 1],
            ['id' => 2, 'sender_id' => $userId, 'message' => "I'm good, thanks for asking! How about you?", 'time' => '10:31 AM', 'is_read' => 1],
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
    <div class="conversations-title-container">
        <div class="conversations-dropdown">
            <h2 id="conversationsDropdownBtn">
                <?php 
                if (isset($_GET['view']) && $_GET['view'] === 'listings') {
                    echo 'Listing Conversations';
                } else {
                    echo 'Conversations';
                }
                ?>
                <svg class="icon dropdown-arrow" viewBox="0 0 24 24">
                    <path d="M7 10l5 5 5-5z"/>
                </svg>
            </h2>
            <div class="conversations-dropdown-content" id="conversationsDropdown">
                <a href="?view=all<?= isset($_GET['chat']) ? '&chat=' . htmlspecialchars($_GET['chat']) : '' ?>" class="dropdown-item<?= (!isset($_GET['view']) || $_GET['view'] === 'all') ? ' active' : '' ?>">
                    Conversations
                </a>
                <a href="?view=listings<?= isset($_GET['chat']) ? '&chat=' . htmlspecialchars($_GET['chat']) : '' ?>" class="dropdown-item<?= (isset($_GET['view']) && $_GET['view'] === 'listings') ? ' active' : '' ?>">
                    Listing Conversations
                </a>
            </div>
        </div>
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
        // Check if message contains listing information
        $hasListing = preg_match('/\[LISTING_ID:(\d+)\]/', $message['message'], $listingMatches);
        $listingId = $hasListing ? $listingMatches[1] : null;
        
        // Extract listing title if available
        $listingTitle = '';
        if ($hasListing && preg_match('/\[LISTING_TITLE:([^\]]+)\]/', $message['message'], $titleMatches)) {
            $listingTitle = $titleMatches[1];
        }
        
        // Clean up message text by removing listing markers
        $displayMessage = preg_replace('/\[LISTING_ID:\d+\]\[LISTING_TITLE:[^\]]+\]/', '', $message['message']);
    ?>
        <div class="message-box <?= $message['sender_id'] === $userId ? 'right' : 'left' ?>">
            <div class="message-content">
                <p><?= htmlspecialchars($displayMessage) ?></p>
            </div>
            
            <?php if ($hasListing): ?>
            <div class="message-listing-preview">
                <div class="listing-preview-title"><?= htmlspecialchars($listingTitle) ?></div>
                <a href="marketplace_item.php?id=<?= $listingId ?>" class="listing-preview-view">View listing</a>
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
                        <button class="attachment-button">
                            <svg class="icon" viewBox="0 0 24 24">
                                <path d="M16.5 6v11.5c0 2.21-1.79 4-4 4s-4-1.79-4-4V5c0-1.38 1.12-2.5 2.5-2.5s2.5 1.12 2.5 2.5v10.5c0 .55-.45 1-1 1s-1-.45-1-1V6H10v9.5c0 1.38 1.12 2.5 2.5 2.5s2.5-1.12 2.5-2.5V5c0-2.21-1.79-4-4-4S7 2.79 7 5v12.5c0 3.04 2.46 5.5 5.5 5.5s5.5-2.46 5.5-5.5V6h-1.5z"/>
                            </svg>
                        </button>
                        <textarea placeholder="Type your message here" class="message-send"></textarea>
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
            const listingRef = document.querySelector('#attached-listing-id')?.value;
            const listingTitle = document.querySelector('#attached-listing-title')?.value;
            
            let messageHTML = `
                <div class="message-content">
                    <p>${message}</p>
                </div>
            `;
            
            // If we have an attached listing, add it to the message
            if (listingRef && listingTitle) {
                message += ` [LISTING_ID:${listingRef}][LISTING_TITLE:${listingTitle}]`;
                messageHTML += `
                    <div class="message-listing-preview">
                        <div class="listing-preview-title">${listingTitle}</div>
                        <a href="marketplace_item.php?id=${listingRef}" class="listing-preview-view">View listing</a>
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
            
            // Scroll to bottom
            messagesContainer.scrollTop = messagesContainer.scrollHeight;
            
            // Send message to server via AJAX
            const formData = new FormData();
            formData.append('message', message);
            formData.append('receiver_id', contactId);
            
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

    // Conversations dropdown functionality
    document.addEventListener('DOMContentLoaded', function() {
        const dropdownBtn = document.getElementById('conversationsDropdownBtn');
        const dropdownContainer = document.querySelector('.conversations-dropdown');
        
        if (dropdownBtn && dropdownContainer) {
            // Force reset to non-active state on load
            dropdownContainer.classList.remove('active');
            
            // Toggle dropdown on button click
            dropdownBtn.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                dropdownContainer.classList.toggle('active');
            });
            
            // Close dropdown when clicking outside
            document.addEventListener('click', function(e) {
                if (!dropdownContainer.contains(e.target) && e.target !== dropdownBtn) {
                    dropdownContainer.classList.remove('active');
                }
            });
            
            // Preserve the chat parameter when switching views
            const chatParam = new URLSearchParams(window.location.search).get('chat');
            if (chatParam) {
                document.querySelectorAll('.conversations-dropdown-content a').forEach(link => {
                    if (!link.href.includes('chat=')) {
                        if (link.href.includes('?')) {
                            link.href += '&chat=' + chatParam;
                        } else {
                            link.href += '?chat=' + chatParam;
                        }
                    }
                });
            }
        }
    });
</script>

</body>
</html>
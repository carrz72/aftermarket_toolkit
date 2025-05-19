/**
 * Chat Message Handling Scripts
 * For Aftermarket Toolkit
 */

document.addEventListener('DOMContentLoaded', function() {
    const messageForm = document.getElementById('message-form');
    const chatMessages = document.getElementById('chat-messages');
    
    if (messageForm) {
        // Handle message submission
        messageForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const messageInput = document.getElementById('message-input');
            const recipientId = document.getElementById('recipient_id').value;
            const message = messageInput.value.trim();
            
            if (message.length === 0) {
                return;
            }
              // Send message using our enhanced handler that includes improved email notifications
            fetch('../api/chat/enhanced_chat_message_handler.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `recipient_id=${recipientId}&message=${encodeURIComponent(message)}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Add message to the chat immediately
                    const currentTime = new Date().toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
                    
                    const messageElement = document.createElement('div');
                    messageElement.className = 'message-item outgoing';
                    messageElement.innerHTML = `
                        <div class="message-content">
                            <p>${message}</p>
                            <span class="message-time">${currentTime}</span>
                        </div>
                    `;
                    
                    chatMessages.appendChild(messageElement);
                    chatMessages.scrollTop = chatMessages.scrollHeight;
                    
                    // Clear the input
                    messageInput.value = '';
                } else {
                    alert(data.message || 'Error sending message. Please try again.');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Something went wrong. Please try again.');
            });
        });
    }
    
    // Mark messages as read when viewed
    if (chatMessages) {
        const unreadMessages = document.querySelectorAll('.message-item.incoming:not(.read)');
        
        if (unreadMessages.length > 0) {
            unreadMessages.forEach(message => {
                const messageId = message.dataset.id;
                
                if (messageId) {                    // Mark message as read
                    fetch('../api/chat/enhanced_chat_message_handler.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `action=mark_read&message_id=${messageId}`
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            message.classList.add('read');
                        }
                    })
                    .catch(error => {
                        console.error('Error marking message as read:', error);
                    });
                }
            });
        }
    }
    
    // Auto refresh chat every 10 seconds if active
    let chatRefreshInterval;
    
    function startChatRefresh() {
        if (chatMessages && document.getElementById('active-chat')) {
            const recipientId = document.getElementById('recipient_id').value;
            
            chatRefreshInterval = setInterval(() => {
                // Only refresh if the user hasn't scrolled up to read history
                if (chatMessages.scrollHeight - chatMessages.scrollTop <= chatMessages.clientHeight + 100) {
                    refreshChatMessages(recipientId);
                }
            }, 10000);
        }
    }
    
    function stopChatRefresh() {
        clearInterval(chatRefreshInterval);
    }
    
    function refreshChatMessages(recipientId) {
        const lastMessageId = chatMessages.querySelector('.message-item:last-child')?.dataset.id || 0;
        
        fetch(`../api/chat/get_new_messages.php?recipient_id=${recipientId}&last_id=${lastMessageId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success && data.messages.length > 0) {
                    const wasAtBottom = chatMessages.scrollHeight - chatMessages.scrollTop <= chatMessages.clientHeight + 10;
                    
                    data.messages.forEach(message => {
                        const messageClass = message.sender_id == currentUserId ? 'outgoing' : 'incoming';
                        const messageTime = new Date(message.created_at).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
                        
                        const messageElement = document.createElement('div');
                        messageElement.className = `message-item ${messageClass}`;
                        messageElement.dataset.id = message.id;
                        messageElement.innerHTML = `
                            <div class="message-content">
                                <p>${message.message}</p>
                                <span class="message-time">${messageTime}</span>
                            </div>
                        `;
                        
                        chatMessages.appendChild(messageElement);
                        
                        // If it's an incoming message, mark it as read
                        if (messageClass === 'incoming') {
                            fetch('../api/chat/chat_message_handler.php', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/x-www-form-urlencoded',
                                },
                                body: `action=mark_read&message_id=${message.id}`
                            }).catch(error => console.error('Error marking message as read:', error));
                        }
                    });
                    
                    // Scroll to bottom if user was already at bottom
                    if (wasAtBottom) {
                        chatMessages.scrollTop = chatMessages.scrollHeight;
                    }
                }
            })
            .catch(error => console.error('Error refreshing messages:', error));
    }
    
    // Start auto-refresh when page loads
    startChatRefresh();
    
    // Handle page visibility changes
    document.addEventListener('visibilitychange', function() {
        if (document.visibilityState === 'visible') {
            startChatRefresh();
        } else {
            stopChatRefresh();
        }
    });
});
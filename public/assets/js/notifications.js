/**
 * Aftermarket Toolkit Notification System
 * JavaScript functionality for handling notifications
 */

document.addEventListener('DOMContentLoaded', function() {
    // Initialize notification dropdown behavior
    if (document.getElementById('notificationsBtn')) {
        initNotificationSystem();
        // Initial fetch
        fetchNotifications();
        // Poll for notifications every 60 seconds
        setInterval(fetchNotifications, 60000);
    }
});

/**
 * Initialize notification system
 */
function initNotificationSystem() {
    const notificationsBtn = document.getElementById('notificationsBtn');
    const notificationsDropdown = document.getElementById('notificationsDropdown');
    const markAllReadBtn = document.getElementById('markAllReadBtn');
    
    // Toggle dropdown when clicking on the notification button
    if (notificationsBtn) {
        notificationsBtn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            notificationsDropdown.classList.toggle('show');
            
            // Fetch fresh notifications when opening dropdown
            if (notificationsDropdown.classList.contains('show')) {
                fetchNotifications();
            }
        });
    }
    
    // Close dropdown when clicking outside
    document.addEventListener('click', function(e) {
        if (notificationsDropdown && 
            !notificationsBtn.contains(e.target) && 
            !notificationsDropdown.contains(e.target)) {
            notificationsDropdown.classList.remove('show');
        }
    });
    
    // Mark all notifications as read
    if (markAllReadBtn) {
        markAllReadBtn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            fetch('/aftermarket_toolkit/public/api/notifications.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=mark_all_read'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update UI
                    document.querySelectorAll('.notification-item.unread').forEach(item => {
                        item.classList.remove('unread');
                    });
                    document.querySelectorAll('.notification-mark-read').forEach(btn => {
                        btn.remove();
                    });
                    updateNotificationBadge(0);
                }
            })
            .catch(error => console.error('Error marking all as read:', error));
        });
    }
    
    // Handle notification item clicks
    document.addEventListener('click', function(e) {
        // Mark individual notification as read
        const markReadBtn = e.target.closest('.notification-mark-read');
        if (markReadBtn) {
            e.preventDefault();
            e.stopPropagation();
            
            const notificationItem = markReadBtn.closest('.notification-item');
            const notificationId = notificationItem.dataset.id;
            
            fetch('/aftermarket_toolkit/public/api/notifications.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=mark_read&notification_id=${notificationId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    notificationItem.classList.remove('unread');
                    markReadBtn.remove();
                    updateNotificationBadge(data.counts.total);
                }
            })
            .catch(error => console.error('Error marking as read:', error));
        }
        
        // Handle clicking on notification item
        const notificationItem = e.target.closest('.notification-item:not(.show-all)');
        if (notificationItem && !e.target.closest('.notification-mark-read')) {
            const notificationId = notificationItem.dataset.id;
            const notificationType = notificationItem.dataset.type;
            const relatedId = notificationItem.dataset.relatedId;
            
            // If unread, mark as read before navigating
            if (notificationItem.classList.contains('unread')) {
                e.preventDefault();
                
                fetch('/aftermarket_toolkit/public/api/notifications.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=mark_read&notification_id=${notificationId}`
                })
                .then(() => {
                    // Navigate to the appropriate link
                    window.location.href = getNotificationLink(notificationType, relatedId);
                })
                .catch(() => {
                    // Still navigate even if there was an error
                    window.location.href = getNotificationLink(notificationType, relatedId);
                });
            }
        }
    });
    
    // Initial notification fetch
    fetchNotifications();
}

/**
 * Fetch notifications via AJAX
 */
function fetchNotifications() {
    const baseUrl = window.location.pathname.includes('/public/') ? '..' : '/aftermarket_toolkit';
    
    fetch(`${baseUrl}/public/api/notifications.php`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                updateNotificationBadge(data.counts.total || 0);
                updateNotificationDropdown(data.notifications || []);
            }
        })
        .catch(error => {
            console.error('Error fetching notifications:', error);
        });
}

/**
 * Update the notification badge count
 * 
 * @param {number} count The notification count
 */
function updateNotificationBadge(count) {
    const badge = document.getElementById('notification-badge');
    
    if (!badge) return;
    
    if (count > 0) {
        badge.style.display = 'inline-flex';
        badge.textContent = count;
    } else {
        badge.style.display = 'none';
    }
}

/**
 * Update the notification dropdown with fresh notification data
 * 
 * @param {Array} notifications Array of notification objects
 */
function updateNotificationDropdown(notifications) {
    const list = document.querySelector('.notifications-list');
    
    if (!list) return;
    
    // If no notifications, show a message
    if (!notifications || notifications.length === 0) {
        list.innerHTML = '<div class="no-notifications">No new notifications</div>';
        return;
    }
    
    let html = '';
    const maxToShow = 5;
    
    // Build notification items HTML
    for (let i = 0; i < Math.min(notifications.length, maxToShow); i++) {
        const notification = notifications[i];
        const isUnread = !notification.is_read;
        const unreadClass = isUnread ? 'unread' : '';
        
        html += `<div class="notification-item ${unreadClass}" data-id="${notification.id}" data-type="${notification.type}" data-related-id="${notification.related_id || ''}">`;
        
        // Notification icon based on type
        let iconClass = 'fa-bell';
        switch (notification.type) {
            case 'friend_request': iconClass = 'fa-user-plus'; break;
            case 'message': iconClass = 'fa-envelope'; break;
            case 'forum_response': iconClass = 'fa-comments'; break;
            case 'listing_comment': iconClass = 'fa-tag'; break;
        }
        
        html += `<div class="notification-icon"><i class="fas ${iconClass}"></i></div>`;
        html += '<div class="notification-content">';
        html += `<div class="notification-text">${notification.content}</div>`;
        html += `<div class="notification-time">${formatTimeAgo(notification.created_at)}</div>`;
        html += '</div>';
        
        if (isUnread) {
            html += '<div class="notification-mark-read"><i class="fas fa-check"></i></div>';
        }
        
        html += `<a href="${getNotificationLink(notification.type, notification.related_id)}" class="notification-link"></a>`;
        html += '</div>';
    }
    
    // If there are more notifications than we're showing, add a "view all" link
    if (notifications.length > maxToShow) {
        const baseUrl = window.location.pathname.includes('/public/') ? '.' : './public';
        html += '<div class="notification-item show-all">';
        html += `<a href="${baseUrl}/notifications.php">View all notifications</a>`;
        html += '</div>';
    }
    
    list.innerHTML = html;
}

/**
 * Get the appropriate link for a notification
 * 
 * @param {string} type The notification type
 * @param {string} relatedId Optional ID of the related content
 * @return {string} URL to navigate to
 */
function getNotificationLink(type, relatedId) {
    const baseUrl = window.location.pathname.includes('/public/') ? '.' : './public';
    
    switch(type) {
        case 'friend_request':
            return `${baseUrl}/friends.php`;
        case 'message':
            // Use the sender_id as the chat parameter
            return `${baseUrl}/chat.php?chat=${relatedId}`;
        case 'forum_response':
            return relatedId ? `${baseUrl}/forum.php?thread=${relatedId}` : `${baseUrl}/forum.php`;
        case 'listing_comment':
            return relatedId ? `${baseUrl}/marketplace.php?listing=${relatedId}` : `${baseUrl}/marketplace.php`;
        default:
            return `${baseUrl}/notifications.php`;
    }
}

/**
 * Format timestamp as "time ago" text
 * 
 * @param {string} timestamp ISO timestamp or MySQL datetime
 * @return {string} Formatted time ago text
 */
function formatTimeAgo(timestamp) {
    const date = new Date(timestamp);
    const now = new Date();
    const seconds = Math.floor((now - date) / 1000);
    
    if (seconds < 60) {
        return 'just now';
    }
    
    const minutes = Math.floor(seconds / 60);
    if (minutes < 60) {
        return minutes + ' minute' + (minutes !== 1 ? 's' : '') + ' ago';
    }
    
    const hours = Math.floor(minutes / 60);
    if (hours < 24) {
        return hours + ' hour' + (hours !== 1 ? 's' : '') + ' ago';
    }
    
    const days = Math.floor(hours / 24);
    if (days < 30) {
        return days + ' day' + (days !== 1 ? 's' : '') + ' ago';
    }
    
    const months = Math.floor(days / 30);
    if (months < 12) {
        return months + ' month' + (months !== 1 ? 's' : '') + ' ago';
    }
    
    return Math.floor(months / 12) + ' year' + (Math.floor(months / 12) !== 1 ? 's' : '') + ' ago';
}
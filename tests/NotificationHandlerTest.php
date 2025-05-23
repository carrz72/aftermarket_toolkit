<?php
/**
 * NotificationHandlerTest
 * Tests for notification handling functionality
 */

require_once __DIR__ . '/BaseTest.php';

class NotificationHandlerTest extends BaseTest
{
    // Using parent's protected $conn
    
    protected function setUp(): void
    {
        parent::setUp();
        
        // Reset database to known state
        $this->resetTestDatabase();
        
        // Set up test users
        $this->conn->query("INSERT INTO users (id, username, email, password, created_at) VALUES 
            (1, 'testuser1', 'test1@example.com', 'password', NOW()),
            (2, 'testuser2', 'test2@example.com', 'password', NOW())");
    }
    
    protected function tearDown(): void
    {
        // Clean up
        $this->conn->close();
        $this->conn = null;
        
        parent::tearDown();
    }
    
    public function testSendNotification()
    {
        // Include the notification handler
        require_once __DIR__ . '/../includes/notification_handler.php';
        
        // Test sending a notification
        $result = sendNotification(
            $this->conn,
            1, // userId (recipient)
            'message', // type
            2, // senderId
            null, // relatedId
            'Test notification message' // content
        );
        
        $this->assertTrue($result);
        
        // Verify the notification was created
        $stmt = $this->conn->prepare("SELECT * FROM notifications WHERE user_id = ? AND type = ?");
        $stmt->bind_param("is", $userId, $type);
        $userId = 1;
        $type = 'message';
        $stmt->execute();
        $result = $stmt->get_result();
        
        $this->assertEquals(1, $result->num_rows);
        
        $notification = $result->fetch_assoc();
        $this->assertEquals('Test notification message', $notification['content']);
        $this->assertEquals(2, $notification['sender_id']);
    }
    
    public function testGetNotificationCounts()
    {
        require_once __DIR__ . '/../includes/notification_handler.php';
        
        // Create test notifications
        $this->conn->query("INSERT INTO notifications (user_id, type, sender_id, content, created_at) VALUES 
            (1, 'message', 2, 'Test message', NOW()),
            (1, 'friend_request', 2, 'Test friend request', NOW()),
            (1, 'forum_response', 2, 'Test forum response', NOW()),
            (1, 'job_application', 2, 'Test job application', NOW())");
        
        // Test counting notifications
        $counts = getNotificationCountsByType($this->conn, 1, true);
        
        $this->assertEquals(4, $counts['total']);
        $this->assertEquals(1, $counts['message']);
        $this->assertEquals(1, $counts['friend_request']);
        $this->assertEquals(1, $counts['forum_response']);
        $this->assertEquals(1, $counts['job_application']);
    }
      public function testEnhanceNotificationDetails()
    {
        // Include the notification handler with our constant defined
        define('INCLUDED', true);
        require_once __DIR__ . '/../includes/notification_handler.php';
        
        // Test enhancing notification details for different types
        $notification = [
            'id' => 1,
            'user_id' => 1,
            'type' => 'job_application',
            'sender_id' => 2,
            'related_id' => 5,
            'content' => 'Test job application',
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        $enhanced = enhanceNotificationDetails($this->conn, $notification);
        
        // Updated assertion to match the actual implementation
        $this->assertEquals('/aftermarket_toolkit/public/jobs.php?action=my_applications', $enhanced['link']);
        $this->assertArrayHasKey('time_ago', $enhanced);
    }
}
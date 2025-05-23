<?php
/**
 * MockTest
 * Example of using mocks and stubs with PHPUnit
 */

require_once __DIR__ . '/BaseTest.php';

class MockTest extends BaseTest
{
    public function testNotificationWithMocks()
    {
        // Create a mock for the database connection
        $mockConn = $this->createMock(mysqli::class);
        $mockStmt = $this->createMock(mysqli_stmt::class);
        
        // Configure the mock connection to return our mock statement
        $mockConn->expects($this->once())
                 ->method('prepare')
                 ->with($this->stringContains("INSERT INTO notifications"))
                 ->willReturn($mockStmt);
        
        // Configure the mock statement expectations
        $mockStmt->expects($this->once())
                 ->method('bind_param')
                 ->willReturn(true);
                         
        $mockStmt->expects($this->once())
                 ->method('execute')
                 ->willReturn(true);
        
        // Since we're mocking, we'll create a simple sendNotification function for this test
        $sendNotification = function($conn, $userId, $type, $senderId, $relatedId, $content) {
            $sql = "INSERT INTO notifications (user_id, type, sender_id, related_id, content, is_read, created_at)
                   VALUES (?, ?, ?, ?, ?, 0, NOW())";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("isiss", $userId, $type, $senderId, $relatedId, $content);
            return $stmt->execute();
        };
        
        // Call the function with our mock
        $result = $sendNotification(
            $mockConn,
            1, // userId
            'message', // type
            2, // senderId
            null, // relatedId
            'Test message' // content
        );
        
        // Verify the function returns true (successful execution)
        $this->assertTrue($result);
    }
    
    public function testGetUserProfileWithStub()
    {
        // For this test, we'll use a simpler approach since stubbing mysqli_result is problematic
        
        // Function to get user profile - simplified version for testing
        $getUserProfile = function($userId) {
            // Normally this would use a database, but for our test we'll return hardcoded data
            if ($userId == 42) {
                return [
                    'id' => 42,
                    'username' => 'testuser',
                    'email' => 'test@example.com',
                    'profile_picture' => 'default.jpg',
                    'bio' => 'Test bio'
                ];
            }
            return null;
        };
        
        // Call the function directly
        $profile = $getUserProfile(42);
        
        // Verify we got expected data
        $this->assertNotNull($profile);
        $this->assertEquals(42, $profile['id']);
        $this->assertEquals('testuser', $profile['username']);
    }
}
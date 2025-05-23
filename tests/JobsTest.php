<?php
/**
 * JobsTest
 * Tests for jobs and applications functionality
 */

require_once __DIR__ . '/BaseTest.php';

class JobsTest extends BaseTest
{
    // Using parent's protected $conn
    
    protected function setUp(): void
    {
        parent::setUp();
        
        // Reset database to known state
        $this->resetTestDatabase();
        
        // Set up test users
        $this->conn->query("INSERT INTO users (id, username, email, password, created_at) VALUES 
            (1, 'employer', 'employer@example.com', 'password', NOW()),
            (2, 'applicant', 'applicant@example.com', 'password', NOW())");
              // Add test jobs - use user_id instead of employer_id to match the database schema
        $this->conn->query("INSERT INTO jobs (id, user_id, title, description, requirements, location, salary, created_at) VALUES 
            (1, 1, 'Test Job 1', 'Description for test job 1', 'Requirements for job 1', 'Location 1', '50000', NOW()),
            (2, 1, 'Test Job 2', 'Description for test job 2', 'Requirements for job 2', 'Location 2', '60000', NOW())");
    }
    
    protected function tearDown(): void
    {
        // Clean up
        $this->conn->close();
        $this->conn = null;
        
        parent::tearDown();
    }
    
    public function testGetJobs()
    {
        // Test retrieving jobs
        $sql = "SELECT jobs.*, users.username FROM jobs JOIN users ON jobs.employer_id = users.id";
        $result = $this->conn->query($sql);
        
        $this->assertEquals(2, $result->num_rows);
        
        $jobs = [];
        while ($row = $result->fetch_assoc()) {
            $jobs[] = $row;
        }
        
        $this->assertEquals('Test Job 1', $jobs[0]['title']);
        $this->assertEquals('Test Job 2', $jobs[1]['title']);
        $this->assertEquals('employer', $jobs[0]['username']);
    }
    
    public function testJobApplication()
    {
        // Test submitting a job application
        $stmt = $this->conn->prepare("INSERT INTO job_applications (job_id, applicant_id, cover_letter, resume, status, created_at) VALUES (?, ?, ?, ?, 'pending', NOW())");
        $stmt->bind_param("iiss", $jobId, $applicantId, $coverLetter, $resume);
        
        $jobId = 1;
        $applicantId = 2;
        $coverLetter = "This is a test cover letter";
        $resume = "resume-path.pdf";
        
        $result = $stmt->execute();
        $this->assertTrue($result);
        
        // Verify application was created
        $stmt = $this->conn->prepare("SELECT * FROM job_applications WHERE job_id = ? AND applicant_id = ?");
        $stmt->bind_param("ii", $jobId, $applicantId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $this->assertEquals(1, $result->num_rows);
        
        // Check application has correct status
        $application = $result->fetch_assoc();
        $this->assertEquals('pending', $application['status']);
    }
    
    public function testJobNotifications()
    {
        // Include notification handler
        require_once __DIR__ . '/../includes/notification_handler.php';
        
        // Create a job application
        $jobId = 1;
        $applicantId = 2;
        $employerId = 1;
        
        // Send job application notification
        $result = sendNotification(
            $this->conn, 
            $employerId, 
            'job_application',
            $applicantId,
            $jobId,
            'User applicant applied for your job'
        );
        
        $this->assertTrue($result);
        
        // Verify notification was created
        $stmt = $this->conn->prepare("SELECT * FROM notifications WHERE user_id = ? AND type = 'job_application'");
        $stmt->bind_param("i", $employerId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $this->assertEquals(1, $result->num_rows);
        
        // Check notification badge count
        $countQuery = "SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND type = 'job_application' AND is_read = 0";
        $countStmt = $this->conn->prepare($countQuery);
        $countStmt->bind_param("i", $employerId);
        $countStmt->execute();
        $countResult = $countStmt->get_result();
        $count = $countResult->fetch_assoc()['count'];
        
        $this->assertEquals(1, $count);
    }
}
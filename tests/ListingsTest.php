<?php
/**
 * ListingsTest
 * Tests for marketplace listings functionality
 */

require_once __DIR__ . '/BaseTest.php';

class ListingsTest extends BaseTest
{
    // Using parent's protected $conn
    
    protected function setUp(): void
    {
        parent::setUp();
        
        // Reset database to known state
        $this->resetTestDatabase();
        
        // Set up test users and categories
        $this->conn->query("INSERT INTO users (id, username, email, password, created_at) VALUES 
            (1, 'seller', 'seller@example.com', 'password', NOW()),
            (2, 'buyer', 'buyer@example.com', 'password', NOW())");
              // Add test listings - escape 'condition' as it's a reserved keyword
        $this->conn->query("INSERT INTO listings (id, user_id, title, description, price, category, `condition`, created_at) VALUES 
            (1, 1, 'Test Listing 1', 'Description for test listing 1', 100.00, 'Parts', 'New', NOW()),
            (2, 1, 'Test Listing 2', 'Description for test listing 2', 200.00, 'Accessories', 'Used', NOW())");
    }
    
    protected function tearDown(): void
    {
        // Clean up
        $this->conn->close();
        $this->conn = null;
        
        parent::tearDown();
    }
    
    public function testGetListings()
    {
        // Mock the listings retrieval functionality
        $sql = "
          SELECT listings.*, users.username, users.profile_picture 
          FROM listings 
          JOIN users ON listings.user_id = users.id 
          WHERE (listings.title LIKE ? OR listings.description LIKE ?)
        ";
        $params = ["%test%", "%test%"];
        $types = "ss";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        
        // Check that we found our test listings
        $this->assertEquals(2, $result->num_rows);
        
        $listings = [];
        while ($row = $result->fetch_assoc()) {
            $listings[] = $row;
        }
        
        $this->assertEquals('Test Listing 1', $listings[0]['title']);
        $this->assertEquals('Test Listing 2', $listings[1]['title']);
        $this->assertEquals(100.00, $listings[0]['price']);
        $this->assertEquals('seller', $listings[0]['username']);
    }
    
    public function testFilterListingsByCategory()
    {
        // Test category filtering
        $sql = "
          SELECT listings.*, users.username
          FROM listings 
          JOIN users ON listings.user_id = users.id 
          WHERE listings.category = ?
        ";
        $params = ['Parts'];
        $types = "s";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        
        // We should only get one listing in the 'Parts' category
        $this->assertEquals(1, $result->num_rows);
        
        $listing = $result->fetch_assoc();
        $this->assertEquals('Test Listing 1', $listing['title']);
        $this->assertEquals('Parts', $listing['category']);
    }
    
    public function testSaveListingForUser()
    {
        // First create saved_listings table entry
        $stmt = $this->conn->prepare("INSERT INTO saved_listings (user_id, listing_id, saved_at) VALUES (?, ?, NOW())");
        $stmt->bind_param("ii", $userId, $listingId);
        $userId = 2;
        $listingId = 1;
        $result = $stmt->execute();
        
        $this->assertTrue($result);
        
        // Now verify it was saved
        $stmt = $this->conn->prepare("SELECT * FROM saved_listings WHERE user_id = ? AND listing_id = ?");
        $stmt->bind_param("ii", $userId, $listingId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $this->assertEquals(1, $result->num_rows);
        
        $saved = $result->fetch_assoc();
        $this->assertEquals(2, $saved['user_id']);
        $this->assertEquals(1, $saved['listing_id']);
    }
}
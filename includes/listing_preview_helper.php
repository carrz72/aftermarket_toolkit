<?php
/**
 * Helper functions for handling listing previews in messages
 */

/**
 * Gets a properly formatted image URL for display
 * 
 * @param string $imageUrl The image URL that might be URL-encoded or have incorrect format
 * @return string The properly formatted image URL
 */
function getFormattedImageUrl($imageUrl) {
    if (empty($imageUrl)) {
        return './assets/images/default-image.jpg';
    }
    
    // If the image path is URL-encoded, decode it
    $imageUrl = urldecode($imageUrl);
    
    // If it's a local path referring to uploads
    if (strpos($imageUrl, 'uploads') !== false) {
        // Ensure it has the correct path structure
        if (strpos($imageUrl, '/') !== 0) {
            $imageUrl = '/' . $imageUrl;
        }
    }
    
    return $imageUrl;
}

/**
 * Gets full listing details for preview
 * 
 * @param mysqli $conn Database connection
 * @param int $listingId Listing ID
 * @return array|null Listing data or null if not found
 */
function getFullListingDetails($conn, $listingId) {
    if (!$listingId) {
        return null;
    }
    
    $query = "SELECT id, title, description, price, image FROM listings WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $listingId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        return null;
    }
    
    $listing = $result->fetch_assoc();
    
    // Format the image URL if it exists
    if (!empty($listing['image'])) {
        $listing['image'] = getFormattedImageUrl($listing['image']);
    }
    
    return $listing;
}
?>
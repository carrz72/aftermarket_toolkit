<?php
session_start();
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/image_helper.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    $_SESSION['error'] = "You must be logged in to delete a listing.";
    header('Location: ../../public/login.php');
    exit();
}

// Check if listing ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = "Invalid listing ID.";
    header('Location: ./view_listings.php');
    exit();
}

$userId = $_SESSION['user_id'];
$listingId = (int)$_GET['id'];

// Begin transaction
$conn->begin_transaction();

try {
    // Verify the listing exists and belongs to this user
    $checkQuery = "SELECT id, image FROM listings WHERE id = ? AND user_id = ?";
    $checkStmt = $conn->prepare($checkQuery);
    $checkStmt->bind_param("ii", $listingId, $userId);
    $checkStmt->execute();
    $result = $checkStmt->get_result();

    if ($result->num_rows === 0) {
        $_SESSION['error'] = "You do not have permission to delete this listing.";
        header('Location: ./view_listings.php');
        $conn->rollback();
        exit();
    }

    // Get the listing data to delete image files
    $listing = $result->fetch_assoc();
    
    // Get all additional images for this listing
    $imagesQuery = "SELECT image_path FROM listing_images WHERE listing_id = ?";
    $imagesStmt = $conn->prepare($imagesQuery);
    $imagesStmt->bind_param("i", $listingId);
    $imagesStmt->execute();
    $imagesResult = $imagesStmt->get_result();
    
    // Delete additional images from filesystem
    while ($image = $imagesResult->fetch_assoc()) {
        $imagePath = $image['image_path'];
        if (strpos($imagePath, '/') === 0) {
            $fileToDelete = '../../' . substr($imagePath, 1);
            if (file_exists($fileToDelete)) {
                unlink($fileToDelete);
            }
        }
    }
    
    // Delete main image from filesystem
    $mainImage = $listing['image'];
    if (!empty($mainImage)) {
        $fileToDelete = getImageFilePath($mainImage);
        if (file_exists($fileToDelete)) {
            unlink($fileToDelete);
        }
    }

    // Delete additional images from database first (due to foreign key constraints)
    $deleteImagesQuery = "DELETE FROM listing_images WHERE listing_id = ?";
    $deleteImagesStmt = $conn->prepare($deleteImagesQuery);
    $deleteImagesStmt->bind_param("i", $listingId);
    $deleteImagesStmt->execute();
    
    // Then delete the listing
    $deleteQuery = "DELETE FROM listings WHERE id = ? AND user_id = ?";
    $deleteStmt = $conn->prepare($deleteQuery);
    $deleteStmt->bind_param("ii", $listingId, $userId);
    $deleteStmt->execute();
    
    // Commit the transaction
    $conn->commit();
    
    $_SESSION['success'] = "Listing has been successfully deleted.";
} catch (Exception $e) {
    // Roll back transaction on error
    $conn->rollback();
    $_SESSION['error'] = "Error deleting listing: " . $e->getMessage();
}

// Redirect back to listings page
header('Location: ./view_listings.php');
exit();
?>

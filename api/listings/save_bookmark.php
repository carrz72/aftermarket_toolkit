<?php
require_once __DIR__ . '/../../config/db.php';
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit;
}

// Get the request data
$data = json_decode(file_get_contents('php://input'), true);
$listingId = isset($data['listing_id']) ? (int)$data['listing_id'] : 0;
$userId = $_SESSION['user_id'];

if (!$listingId) {
    echo json_encode(['success' => false, 'message' => 'Invalid listing ID']);
    exit;
}

// Check if already bookmarked
$checkQuery = "SELECT id FROM saved_listings WHERE user_id = ? AND listing_id = ?";
$checkStmt = $conn->prepare($checkQuery);
$checkStmt->bind_param("ii", $userId, $listingId);
$checkStmt->execute();
$result = $checkStmt->get_result();

if ($result->num_rows > 0) {
    // Already saved, so unsave it
    $deleteQuery = "DELETE FROM saved_listings WHERE user_id = ? AND listing_id = ?";
    $deleteStmt = $conn->prepare($deleteQuery);
    $deleteStmt->bind_param("ii", $userId, $listingId);
    
    if ($deleteStmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Listing removed from saved items']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error removing bookmark']);
    }
} else {
    // Not saved yet, so save it
    $insertQuery = "INSERT INTO saved_listings (user_id, listing_id, saved_at) VALUES (?, ?, NOW())";
    $insertStmt = $conn->prepare($insertQuery);
    $insertStmt->bind_param("ii", $userId, $listingId);
    
    if ($insertStmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Listing saved successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error saving bookmark']);
    }
}
?>
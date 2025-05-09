<?php
require_once __DIR__ . '/../../config/db.php';
session_start();

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit;
}

// Check if listing_id is provided
if (!isset($_POST['listing_id'])) {
    echo json_encode(['success' => false, 'message' => 'No listing ID provided']);
    exit;
}

$userId = $_SESSION['user_id'];
$listingId = (int)$_POST['listing_id'];
$action = $_POST['action'] ?? 'add';

// Check if the bookmark exists
$checkStmt = $conn->prepare("SELECT id FROM saved_listings WHERE user_id = ? AND listing_id = ?");
$checkStmt->bind_param('ii', $userId, $listingId);
$checkStmt->execute();
$result = $checkStmt->get_result();
$exists = $result->num_rows > 0;

if ($action === 'add') {
    // Don't add again if it already exists
    if ($exists) {
        echo json_encode(['success' => true, 'message' => 'Already bookmarked', 'action' => 'none']);
        exit;
    }
    
    // Add bookmark
    $stmt = $conn->prepare("INSERT INTO saved_listings (user_id, listing_id, saved_at) VALUES (?, ?, NOW())");
    $stmt->bind_param('ii', $userId, $listingId);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Item saved successfully', 'action' => 'added']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
    }
} else {
    // Remove bookmark
    if (!$exists) {
        echo json_encode(['success' => true, 'message' => 'Not bookmarked', 'action' => 'none']);
        exit;
    }
    
    $stmt = $conn->prepare("DELETE FROM saved_listings WHERE user_id = ? AND listing_id = ?");
    $stmt->bind_param('ii', $userId, $listingId);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Item removed from bookmarks', 'action' => 'removed']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
    }
}
?>
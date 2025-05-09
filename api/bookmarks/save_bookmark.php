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

// Check if already saved
$checkStmt = $conn->prepare("SELECT id FROM saved_listings WHERE user_id = ? AND listing_id = ?");
$checkStmt->bind_param('ii', $userId, $listingId);
$checkStmt->execute();
$result = $checkStmt->get_result();

if ($result->num_rows > 0) {
    echo json_encode(['success' => true, 'message' => 'Item already saved', 'status' => 'saved']);
    exit;
}

// Save the bookmark
$stmt = $conn->prepare("INSERT INTO saved_listings (user_id, listing_id, saved_at) VALUES (?, ?, NOW())");
$stmt->bind_param('ii', $userId, $listingId);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Item saved successfully', 'status' => 'saved']);
} else {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
}
?>
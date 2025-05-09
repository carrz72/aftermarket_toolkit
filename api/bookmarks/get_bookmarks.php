<?php
require_once __DIR__ . '/../../config/db.php';
session_start();

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit;
}

$userId = $_SESSION['user_id'];

// Get all saved bookmarks for this user
$stmt = $conn->prepare("SELECT listing_id FROM saved_listings WHERE user_id = ?");
$stmt->bind_param('i', $userId);
$stmt->execute();
$result = $stmt->get_result();

$bookmarks = [];
while ($row = $result->fetch_assoc()) {
    $bookmarks[] = (int)$row['listing_id'];
}

echo json_encode(['success' => true, 'bookmarks' => $bookmarks]);
?>
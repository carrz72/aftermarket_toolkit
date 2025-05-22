<?php
// File: job_posting.php
// Handles job posting creation and management
session_start();
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/notification_handler.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'You must be logged in to post jobs']);
    exit();
}

$user_id = $_SESSION['user_id'];

// Handle job creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create') {
    // Validate inputs
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $requirements = trim($_POST['requirements'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $compensation = trim($_POST['compensation'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $expires_at = $_POST['expires_at'] ?? date('Y-m-d', strtotime('+30 days'));
    
    // Validate required fields
    if (empty($title) || empty($description) || empty($location) || empty($compensation) || empty($category)) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'All fields are required']);
        exit();
    }
    
    // Insert job into database
    $sql = "INSERT INTO jobs (user_id, title, description, requirements, location, compensation, category, status, created_at, updated_at, expires_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, 'open', NOW(), NOW(), ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("issssss", $user_id, $title, $description, $requirements, $location, $compensation, $category, $expires_at);
    
    if ($stmt->execute()) {
        $job_id = $stmt->insert_id;
        
        // Return success response
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true, 
            'message' => 'Job posted successfully',
            'job_id' => $job_id
        ]);
    } else {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Error posting job: ' . $conn->error]);
    }
    exit();
}

// Handle job update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update') {
    $job_id = filter_input(INPUT_POST, 'job_id', FILTER_SANITIZE_NUMBER_INT);
    
    // Check if job exists and belongs to the user
    $checkStmt = $conn->prepare("SELECT id FROM jobs WHERE id = ? AND user_id = ?");
    $checkStmt->bind_param("ii", $job_id, $user_id);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    
    if ($result->num_rows === 0) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Job not found or you do not have permission to edit it']);
        exit();
    }
    
    // Get and validate input fields
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $requirements = trim($_POST['requirements'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $compensation = trim($_POST['compensation'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $status = $_POST['status'] ?? 'open';
    $expires_at = $_POST['expires_at'] ?? null;
    
    // Validate required fields
    if (empty($title) || empty($description) || empty($location) || empty($compensation) || empty($category)) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'All fields are required']);
        exit();
    }
    
    // Update job in database
    $sql = "UPDATE jobs SET title = ?, description = ?, requirements = ?, location = ?, 
            compensation = ?, category = ?, status = ?, updated_at = NOW()";
            
    $params = [$title, $description, $requirements, $location, $compensation, $category, $status];
    $types = "sssssss";
    
    if ($expires_at) {
        $sql .= ", expires_at = ?";
        $params[] = $expires_at;
        $types .= "s";
    }
    
    $sql .= " WHERE id = ? AND user_id = ?";
    $params[] = $job_id;
    $params[] = $user_id;
    $types .= "ii";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    
    if ($stmt->execute()) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true, 
            'message' => 'Job updated successfully'
        ]);
    } else {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Error updating job: ' . $conn->error]);
    }
    exit();
}

// Handle job deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    $job_id = filter_input(INPUT_POST, 'job_id', FILTER_SANITIZE_NUMBER_INT);
    
    // Check if job exists and belongs to the user
    $checkStmt = $conn->prepare("SELECT id FROM jobs WHERE id = ? AND user_id = ?");
    $checkStmt->bind_param("ii", $job_id, $user_id);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    
    if ($result->num_rows === 0) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Job not found or you do not have permission to delete it']);
        exit();
    }
    
    // Delete job
    $stmt = $conn->prepare("DELETE FROM jobs WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $job_id, $user_id);
    
    if ($stmt->execute()) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true, 
            'message' => 'Job deleted successfully'
        ]);
    } else {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Error deleting job: ' . $conn->error]);
    }
    exit();
}

// Handle changing job status
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'change_status') {
    $job_id = filter_input(INPUT_POST, 'job_id', FILTER_SANITIZE_NUMBER_INT);
    $status = filter_input(INPUT_POST, 'status', FILTER_SANITIZE_STRING);
    
    // Validate status
    $valid_statuses = ['open', 'in_progress', 'completed', 'cancelled'];
    if (!in_array($status, $valid_statuses)) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Invalid status']);
        exit();
    }
    
    // Check if job exists and belongs to the user
    $checkStmt = $conn->prepare("SELECT id FROM jobs WHERE id = ? AND user_id = ?");
    $checkStmt->bind_param("ii", $job_id, $user_id);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    
    if ($result->num_rows === 0) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Job not found or you do not have permission to update it']);
        exit();
    }
    
    // Update job status
    $stmt = $conn->prepare("UPDATE jobs SET status = ?, updated_at = NOW() WHERE id = ? AND user_id = ?");
    $stmt->bind_param("sii", $status, $job_id, $user_id);
    
    if ($stmt->execute()) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true, 
            'message' => 'Job status updated successfully'
        ]);
    } else {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Error updating job status: ' . $conn->error]);
    }
    exit();
}

// If no valid action is specified, return error
header('Content-Type: application/json');
echo json_encode(['success' => false, 'message' => 'Invalid request']);
exit();
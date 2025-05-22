<?php
// File: job_application.php
// Handles job applications and bids
session_start();
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/notification_handler.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'You must be logged in to apply for jobs']);
    exit();
}

$user_id = $_SESSION['user_id'];

// Handle job application creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'apply') {
    // Validate inputs
    $job_id = filter_input(INPUT_POST, 'job_id', FILTER_SANITIZE_NUMBER_INT);
    $cover_letter = trim($_POST['cover_letter'] ?? '');
    $bid_amount = filter_input(INPUT_POST, 'bid_amount', FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
    
    // Validate required fields
    if (!$job_id || empty($cover_letter)) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Job ID and cover letter are required']);
        exit();
    }
    
    // Check if job exists and is open
    $jobStmt = $conn->prepare("SELECT user_id, title FROM jobs WHERE id = ? AND status = 'open' AND expires_at > NOW()");
    $jobStmt->bind_param("i", $job_id);
    $jobStmt->execute();
    $jobResult = $jobStmt->get_result();
    
    if ($jobResult->num_rows === 0) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Job not found or not accepting applications']);
        exit();
    }
    
    $jobData = $jobResult->fetch_assoc();
    $job_owner_id = $jobData['user_id'];
    $job_title = $jobData['title'];
    
    // Check if user is not applying to their own job
    if ($job_owner_id == $user_id) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'You cannot apply to your own job']);
        exit();
    }
    
    // Check if user has already applied
    $checkStmt = $conn->prepare("SELECT id FROM job_applications WHERE job_id = ? AND user_id = ?");
    $checkStmt->bind_param("ii", $job_id, $user_id);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    
    if ($checkResult->num_rows > 0) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'You have already applied for this job']);
        exit();
    }
    
    // Insert application into database
    $sql = "INSERT INTO job_applications (job_id, user_id, cover_letter, bid_amount, status, created_at, updated_at) 
            VALUES (?, ?, ?, ?, 'pending', NOW(), NOW())";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iisd", $job_id, $user_id, $cover_letter, $bid_amount);
    
    if ($stmt->execute()) {
        $application_id = $stmt->insert_id;
        
        // Get applicant username
        $userStmt = $conn->prepare("SELECT username FROM users WHERE id = ?");
        $userStmt->bind_param("i", $user_id);
        $userStmt->execute();
        $userResult = $userStmt->get_result();
        $userData = $userResult->fetch_assoc();
        $applicant_username = $userData['username'];
        
        // Create notification for job owner
        $notification_content = "{$applicant_username} applied for your job: {$job_title}";
        
        // Send notification using sendNotification function
        if (function_exists('sendNotification')) {
            sendNotification(
                $conn,
                $job_owner_id,
                'job_application',
                $user_id,
                $application_id,
                $notification_content
            );
        }
        
        // Return success response
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true, 
            'message' => 'Application submitted successfully',
            'application_id' => $application_id
        ]);
    } else {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Error submitting application: ' . $conn->error]);
    }
    exit();
}

// Handle application status update (accept, reject, withdraw)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && in_array($_POST['action'], ['accept', 'reject', 'withdraw'])) {
    $application_id = filter_input(INPUT_POST, 'application_id', FILTER_SANITIZE_NUMBER_INT);
    $action = $_POST['action'];
    
    if (!$application_id) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Application ID is required']);
        exit();
    }
    
    // Check if application exists
    $appStmt = $conn->prepare("
        SELECT ja.id, ja.job_id, ja.user_id, ja.status, j.user_id AS job_owner_id, j.title 
        FROM job_applications ja
        JOIN jobs j ON ja.job_id = j.id
        WHERE ja.id = ?
    ");
    $appStmt->bind_param("i", $application_id);
    $appStmt->execute();
    $appResult = $appStmt->get_result();
    
    if ($appResult->num_rows === 0) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Application not found']);
        exit();
    }
    
    $appData = $appResult->fetch_assoc();
    $applicant_id = $appData['user_id'];
    $job_owner_id = $appData['job_owner_id'];
    $job_id = $appData['job_id'];
    $job_title = $appData['title'];
    $current_status = $appData['status'];
    
    // Check permissions and validate the action
    if ($action === 'withdraw') {
        // Only the applicant can withdraw
        if ($user_id != $applicant_id) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'You can only withdraw your own applications']);
            exit();
        }
        
        // Can only withdraw if status is pending
        if ($current_status !== 'pending') {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Can only withdraw pending applications']);
            exit();
        }
        
        $new_status = 'withdrawn';
        $notification_recipient = $job_owner_id;
    } else {
        // Only the job owner can accept or reject
        if ($user_id != $job_owner_id) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Only the job owner can accept or reject applications']);
            exit();
        }
        
        // Can only accept/reject if status is pending
        if ($current_status !== 'pending') {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Can only update pending applications']);
            exit();
        }
        
        $new_status = ($action === 'accept') ? 'accepted' : 'rejected';
        $notification_recipient = $applicant_id;
        
        // If accepting, update job status to in_progress
        if ($action === 'accept') {
            $jobUpdateStmt = $conn->prepare("UPDATE jobs SET status = 'in_progress', updated_at = NOW() WHERE id = ?");
            $jobUpdateStmt->bind_param("i", $job_id);
            $jobUpdateStmt->execute();
        }
    }
    
    // Update application status
    $updateStmt = $conn->prepare("UPDATE job_applications SET status = ?, updated_at = NOW() WHERE id = ?");
    $updateStmt->bind_param("si", $new_status, $application_id);
    
    if ($updateStmt->execute()) {
        // Get username for notification
        $userStmt = $conn->prepare("SELECT username FROM users WHERE id = ?");
        $userStmt->bind_param("i", $user_id);
        $userStmt->execute();
        $userResult = $userStmt->get_result();
        $userData = $userResult->fetch_assoc();
        $username = $userData['username'];
        
        // Create notification content based on action
        if ($action === 'withdraw') {
            $notification_content = "{$username} has withdrawn their application for: {$job_title}";
        } else if ($action === 'accept') {
            $notification_content = "Your application for '{$job_title}' has been accepted!";
        } else {
            $notification_content = "Your application for '{$job_title}' has been rejected";
        }
        
        // Send notification
        if (function_exists('sendNotification')) {
            sendNotification(
                $conn,
                $notification_recipient,
                'job_application_update',
                $user_id,
                $job_id,
                $notification_content
            );
        }
        
        // Return success response
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true, 
            'message' => 'Application ' . $new_status . ' successfully'
        ]);
    } else {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Error updating application: ' . $conn->error]);
    }
    exit();
}

// If no valid action is specified, return error
header('Content-Type: application/json');
echo json_encode(['success' => false, 'message' => 'Invalid request']);
exit();
<?php
// File: withdraw_application.php
// Handles withdrawing job applications

session_start();
require_once __DIR__ . '/../../config/db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    $_SESSION['error'] = "You must be logged in to withdraw applications.";
    header("Location: ../../public/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Handle application withdrawal
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['application_id'])) {
    $application_id = filter_input(INPUT_POST, 'application_id', FILTER_VALIDATE_INT);
    
    if (!$application_id) {
        $_SESSION['error'] = "Invalid application ID.";
        header("Location: ../../public/jobs.php?my=applications");
        exit();
    }
    
    // Verify that this user owns the application
    $appCheckStmt = $conn->prepare("SELECT job_id, status FROM job_applications WHERE id = ? AND user_id = ?");
    $appCheckStmt->bind_param("ii", $application_id, $user_id);
    $appCheckStmt->execute();
    $appResult = $appCheckStmt->get_result();
    
    if ($appResult->num_rows === 0) {
        $_SESSION['error'] = "Application not found or you don't have permission to withdraw it.";
        header("Location: ../../public/jobs.php?my=applications");
        exit();
    }
    
    $application = $appResult->fetch_assoc();
    $job_id = $application['job_id'];
    
    // Check if application can be withdrawn (only pending applications can be withdrawn)
    if ($application['status'] !== 'pending') {
        $_SESSION['error'] = "Only pending applications can be withdrawn.";
        header("Location: ../../public/jobs.php?job_id=" . $job_id);
        exit();
    }
    
    // Update application status to withdrawn
    $updateStmt = $conn->prepare("UPDATE job_applications SET status = 'withdrawn', updated_at = NOW() WHERE id = ?");
    $updateStmt->bind_param("i", $application_id);
    
    if ($updateStmt->execute()) {
        // Get job details for notification
        $jobStmt = $conn->prepare("SELECT j.user_id, j.title FROM jobs j WHERE j.id = ?");
        $jobStmt->bind_param("i", $job_id);
        $jobStmt->execute();
        $job = $jobStmt->get_result()->fetch_assoc();
        
        // Notify job poster that application was withdrawn
        $notification_content = "An application for your job '{$job['title']}' has been withdrawn.";
        
        // Add notification entry
        $notifyStmt = $conn->prepare("
            INSERT INTO notifications (user_id, type, content, from_user_id, related_id, created_at) 
            VALUES (?, 'application_withdrawn', ?, ?, ?, NOW())
        ");
        $notifyStmt->bind_param("isii", $job['user_id'], $notification_content, $user_id, $job_id);
        $notifyStmt->execute();
        
        $_SESSION['success'] = "Your application has been withdrawn successfully.";
    } else {
        $_SESSION['error'] = "Error withdrawing application: " . $conn->error;
    }
    
    // Redirect to job details page if coming from there
    if (isset($_SERVER['HTTP_REFERER']) && strpos($_SERVER['HTTP_REFERER'], 'job_id=') !== false) {
        header("Location: " . $_SERVER['HTTP_REFERER']);
    } else {
        header("Location: ../../public/jobs.php?my=applications");
    }
    exit();
}

// If not a POST request, redirect to applications page
header("Location: ../../public/jobs.php?my=applications");
exit();
?>
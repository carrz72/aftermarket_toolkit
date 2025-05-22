<?php
// File: delete_job.php
// Handles deleting job postings

session_start();
require_once __DIR__ . '/../../config/db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    $_SESSION['error'] = "You must be logged in to delete jobs.";
    header("Location: ../../public/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Handle job deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['job_id'])) {
    $job_id = filter_input(INPUT_POST, 'job_id', FILTER_VALIDATE_INT);
    
    if (!$job_id) {
        $_SESSION['error'] = "Invalid job ID.";
        header("Location: ../../public/jobs.php?my=posted");
        exit();
    }
    
    // Verify that this user owns the job
    $jobCheckStmt = $conn->prepare("SELECT user_id, title FROM jobs WHERE id = ?");
    $jobCheckStmt->bind_param("i", $job_id);
    $jobCheckStmt->execute();
    $jobResult = $jobCheckStmt->get_result();
    
    if ($jobResult->num_rows === 0) {
        $_SESSION['error'] = "Job not found.";
        header("Location: ../../public/jobs.php?my=posted");
        exit();
    }
    
    $job = $jobResult->fetch_assoc();
    if ($job['user_id'] != $user_id) {
        $_SESSION['error'] = "You don't have permission to delete this job.";
        header("Location: ../../public/jobs.php?my=posted");
        exit();
    }
    
    // Update job status to cancelled instead of deleting
    $updateStmt = $conn->prepare("UPDATE jobs SET status = 'cancelled', updated_at = NOW() WHERE id = ?");
    $updateStmt->bind_param("i", $job_id);
    
    if ($updateStmt->execute()) {
        // Get applicants for this job to notify them
        $appStmt = $conn->prepare("SELECT user_id FROM job_applications WHERE job_id = ? AND status = 'pending'");
        $appStmt->bind_param("i", $job_id);
        $appStmt->execute();
        $appResult = $appStmt->get_result();
        
        // Update all pending applications to rejected
        $updateAppsStmt = $conn->prepare("UPDATE job_applications SET status = 'rejected', updated_at = NOW() WHERE job_id = ? AND status = 'pending'");
        $updateAppsStmt->bind_param("i", $job_id);
        $updateAppsStmt->execute();
        
        // Notify all applicants that the job was cancelled
        $notification_content = "A job you applied for ('{$job['title']}') has been cancelled.";
        
        while ($app = $appResult->fetch_assoc()) {
            $applicant_id = $app['user_id'];
            
            // Add notification entry
            $notifyStmt = $conn->prepare("
                INSERT INTO notifications (user_id, type, content, from_user_id, related_id, created_at) 
                VALUES (?, 'job_cancelled', ?, ?, ?, NOW())
            ");
            $notifyStmt->bind_param("isii", $applicant_id, $notification_content, $user_id, $job_id);
            $notifyStmt->execute();
        }
        
        $_SESSION['success'] = "Job cancelled successfully.";
    } else {
        $_SESSION['error'] = "Error cancelling job: " . $conn->error;
    }
    
    header("Location: ../../public/jobs.php?my=posted");
    exit();
}

// If not a POST request, redirect to my jobs page
header("Location: ../../public/jobs.php?my=posted");
exit();
?>
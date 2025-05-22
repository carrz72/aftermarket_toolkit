<?php
// File: apply_job.php
// Handles job applications and bid submissions

session_start();
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/notification_handler.php';
require_once __DIR__ . '/../../includes/notification_email.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    $_SESSION['error'] = "You must be logged in to apply for jobs.";
    header("Location: ../../public/login.php");
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize and validate input
    $user_id = $_SESSION['user_id'];
    $job_id = filter_input(INPUT_POST, 'job_id', FILTER_VALIDATE_INT);
    $cover_letter = trim(filter_input(INPUT_POST, 'cover_letter', FILTER_SANITIZE_SPECIAL_CHARS));
    $bid_amount = filter_input(INPUT_POST, 'bid_amount', FILTER_VALIDATE_FLOAT);
    
    // Validate required fields
    $errors = [];
    if (!$job_id) $errors[] = "Invalid job selected";
    if (empty($cover_letter)) $errors[] = "Cover letter is required";
    
    // Check if user has already applied to this job
    if (empty($errors)) {
        $checkStmt = $conn->prepare("SELECT id FROM job_applications WHERE job_id = ? AND user_id = ?");
        $checkStmt->bind_param("ii", $job_id, $user_id);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();
        
        if ($checkResult->num_rows > 0) {
            $errors[] = "You have already applied to this job";
        }
    }
    
    // Check if the job exists and is still open
    if (empty($errors)) {
        $jobStmt = $conn->prepare("SELECT user_id, title, status FROM jobs WHERE id = ?");
        $jobStmt->bind_param("i", $job_id);
        $jobStmt->execute();
        $jobResult = $jobStmt->get_result();
        
        if ($jobResult->num_rows === 0) {
            $errors[] = "Job not found";
        } else {
            $job = $jobResult->fetch_assoc();
            
            // Check if the job is still open
            if ($job['status'] !== 'open') {
                $errors[] = "This job is no longer accepting applications";
            }
            
            // Check if user is trying to apply to their own job
            if ($job['user_id'] == $user_id) {
                $errors[] = "You cannot apply to your own job posting";
            }
        }
    }
    
    if (empty($errors)) {
        // Set default values
        $created_at = date('Y-m-d H:i:s');
        
        // Insert the application into the database
        $stmt = $conn->prepare("
            INSERT INTO job_applications (job_id, user_id, cover_letter, bid_amount, status, created_at) 
            VALUES (?, ?, ?, ?, 'pending', ?)
        ");
        
        $stmt->bind_param("issds", $job_id, $user_id, $cover_letter, $bid_amount, $created_at);
        
        if ($stmt->execute()) {
            $application_id = $conn->insert_id;
            $_SESSION['success'] = "Your application has been submitted successfully!";
            
            // Notify the job poster about the new application
            $job_owner_id = $job['user_id'];
            $notification_content = "New application received for your job: " . $job['title'];
            
            // Use the sendNotification function if available
            if (function_exists('sendNotification')) {
                sendNotification(
                    $conn,
                    $job_owner_id,
                    'job_application',
                    $user_id,
                    $application_id,
                    $notification_content
                );
            } else {
                // Fallback to direct notification creation
                $notifyStmt = $conn->prepare("
                    INSERT INTO notifications (user_id, type, content, related_id, created_at) 
                    VALUES (?, 'job_application', ?, ?, NOW())
                ");
                $notifyStmt->bind_param("isi", $job_owner_id, $notification_content, $application_id);
                $notifyStmt->execute();
            }
            
            // Send email notification to job owner
            if (function_exists('sendNotificationEmail')) {
                sendNotificationEmail($job_owner_id, 'job_application', $notification_content, $conn);
            }
            
            header("Location: ../../public/jobs.php?job_id=" . $job_id);
            exit();
        } else {
            $_SESSION['error'] = "Error submitting application: " . $conn->error;
        }
    } else {
        $_SESSION['error'] = implode("<br>", $errors);
    }
    
    // If we get here, there were errors, redirect back to the job
    header("Location: ../../public/jobs.php?job_id=" . $job_id);
    exit();
}

// If not a POST request, redirect to the job board
header("Location: ../../public/jobs.php");
exit();
?>
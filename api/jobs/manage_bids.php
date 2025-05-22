<?php
// File: manage_bids.php
// Handles accepting, rejecting, and managing job applications/bids

session_start();
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/notification_handler.php';
require_once __DIR__ . '/../../includes/notification_email.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    $_SESSION['error'] = "You must be logged in to manage job applications.";
    header("Location: ../../public/login.php");
    exit();
}

// Handle actions for job applications
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $application_id = filter_input(INPUT_POST, 'application_id', FILTER_VALIDATE_INT);
    $job_id = filter_input(INPUT_POST, 'job_id', FILTER_VALIDATE_INT);
    $user_id = $_SESSION['user_id'];
    
    if (!$application_id || !$job_id) {
        $_SESSION['error'] = "Invalid request. Missing application or job ID.";
        header("Location: ../../public/jobs.php");
        exit();
    }
    
    // Verify that this user owns the job
    $jobCheckStmt = $conn->prepare("SELECT user_id, title, status FROM jobs WHERE id = ?");
    $jobCheckStmt->bind_param("i", $job_id);
    $jobCheckStmt->execute();
    $jobResult = $jobCheckStmt->get_result();
    
    if ($jobResult->num_rows === 0) {
        $_SESSION['error'] = "Job not found.";
        header("Location: ../../public/jobs.php");
        exit();
    }
    
    $job = $jobResult->fetch_assoc();
    if ($job['user_id'] != $user_id) {
        $_SESSION['error'] = "You don't have permission to manage applications for this job.";
        header("Location: ../../public/jobs.php");
        exit();
    }
    
    // Get application details
    $appStmt = $conn->prepare("SELECT user_id, status FROM job_applications WHERE id = ? AND job_id = ?");
    $appStmt->bind_param("ii", $application_id, $job_id);
    $appStmt->execute();
    $appResult = $appStmt->get_result();
    
    if ($appResult->num_rows === 0) {
        $_SESSION['error'] = "Application not found.";
        header("Location: ../../public/jobs.php?job_id=" . $job_id);
        exit();
    }
    
    $application = $appResult->fetch_assoc();
    $applicant_id = $application['user_id'];
    
    // Process based on action
    switch ($action) {
        case 'accept':
            // Update application status
            $updateAppStmt = $conn->prepare("UPDATE job_applications SET status = 'accepted', updated_at = NOW() WHERE id = ?");
            $updateAppStmt->bind_param("i", $application_id);
            
            if ($updateAppStmt->execute()) {
                // Update job status to in_progress
                $updateJobStmt = $conn->prepare("UPDATE jobs SET status = 'in_progress', updated_at = NOW() WHERE id = ?");
                $updateJobStmt->bind_param("i", $job_id);
                $updateJobStmt->execute();
                
                // Reject all other applications for this job
                $rejectOthersStmt = $conn->prepare("
                    UPDATE job_applications 
                    SET status = 'rejected', updated_at = NOW() 
                    WHERE job_id = ? AND id != ? AND status = 'pending'
                ");
                $rejectOthersStmt->bind_param("ii", $job_id, $application_id);
                $rejectOthersStmt->execute();
                
                // Notify the applicant that their bid was accepted
                $notification_content = "Your application for '{$job['title']}' has been accepted!";
                
                if (function_exists('sendNotification')) {
                    sendNotification(
                        $conn,
                        $applicant_id,
                        'application_accepted',
                        $user_id,
                        $job_id,
                        $notification_content
                    );
                } else {
                    // Fallback to direct notification creation
                    $notifyStmt = $conn->prepare("
                        INSERT INTO notifications (user_id, type, content, related_id, created_at) 
                        VALUES (?, 'application_accepted', ?, ?, NOW())
                    ");
                    $notifyStmt->bind_param("isi", $applicant_id, $notification_content, $job_id);
                    $notifyStmt->execute();
                }
                
                // Send email notification
                if (function_exists('sendNotificationEmail')) {
                    sendNotificationEmail($applicant_id, 'application_accepted', $notification_content, $conn);
                }
                
                // Also notify rejected applicants
                $getRejectedStmt = $conn->prepare("
                    SELECT user_id FROM job_applications 
                    WHERE job_id = ? AND id != ? AND status = 'rejected'
                ");
                $getRejectedStmt->bind_param("ii", $job_id, $application_id);
                $getRejectedStmt->execute();
                $rejectedResult = $getRejectedStmt->get_result();
                
                while ($rejected = $rejectedResult->fetch_assoc()) {
                    $rejected_user_id = $rejected['user_id'];
                    $rejected_notification = "Your application for '{$job['title']}' was not selected.";
                    
                    if (function_exists('sendNotification')) {
                        sendNotification(
                            $conn,
                            $rejected_user_id,
                            'application_rejected',
                            $user_id,
                            $job_id,
                            $rejected_notification
                        );
                    }
                    
                    // Optional: send email to rejected applicants
                    if (function_exists('sendNotificationEmail')) {
                        sendNotificationEmail($rejected_user_id, 'application_rejected', $rejected_notification, $conn);
                    }
                }
                
                $_SESSION['success'] = "Application accepted successfully. The job status has been updated to 'In Progress'.";
            } else {
                $_SESSION['error'] = "Error accepting application: " . $conn->error;
            }
            break;
            
        case 'reject':
            // Update application status
            $updateStmt = $conn->prepare("UPDATE job_applications SET status = 'rejected', updated_at = NOW() WHERE id = ?");
            $updateStmt->bind_param("i", $application_id);
            
            if ($updateStmt->execute()) {
                // Notify the applicant that their application was rejected
                $notification_content = "Your application for '{$job['title']}' was not selected.";
                
                if (function_exists('sendNotification')) {
                    sendNotification(
                        $conn,
                        $applicant_id,
                        'application_rejected',
                        $user_id,
                        $job_id,
                        $notification_content
                    );
                } else {
                    // Fallback to direct notification creation
                    $notifyStmt = $conn->prepare("
                        INSERT INTO notifications (user_id, type, content, related_id, created_at) 
                        VALUES (?, 'application_rejected', ?, ?, NOW())
                    ");
                    $notifyStmt->bind_param("isi", $applicant_id, $notification_content, $job_id);
                    $notifyStmt->execute();
                }
                
                // Send email notification
                if (function_exists('sendNotificationEmail')) {
                    sendNotificationEmail($applicant_id, 'application_rejected', $notification_content, $conn);
                }
                
                $_SESSION['success'] = "Application rejected successfully.";
            } else {
                $_SESSION['error'] = "Error rejecting application: " . $conn->error;
            }
            break;
            
        case 'complete':
            // Make sure the job is in progress
            if ($job['status'] !== 'in_progress') {
                $_SESSION['error'] = "This job is not currently in progress.";
                header("Location: ../../public/jobs.php?job_id=" . $job_id);
                exit();
            }
            
            // Update job status
            $updateJobStmt = $conn->prepare("UPDATE jobs SET status = 'completed', updated_at = NOW() WHERE id = ?");
            $updateJobStmt->bind_param("i", $job_id);
            
            if ($updateJobStmt->execute()) {
                // Notify the accepted applicant
                $notification_content = "The job '{$job['title']}' has been marked as completed.";
                
                if (function_exists('sendNotification')) {
                    sendNotification(
                        $conn,
                        $applicant_id,
                        'job_completed',
                        $user_id,
                        $job_id,
                        $notification_content
                    );
                } else {
                    // Fallback to direct notification creation
                    $notifyStmt = $conn->prepare("
                        INSERT INTO notifications (user_id, type, content, related_id, created_at) 
                        VALUES (?, 'job_completed', ?, ?, NOW())
                    ");
                    $notifyStmt->bind_param("isi", $applicant_id, $notification_content, $job_id);
                    $notifyStmt->execute();
                }
                
                // Send email notification
                if (function_exists('sendNotificationEmail')) {
                    sendNotificationEmail($applicant_id, 'job_completed', $notification_content, $conn);
                }
                
                $_SESSION['success'] = "Job marked as completed successfully.";
            } else {
                $_SESSION['error'] = "Error completing job: " . $conn->error;
            }
            break;
            
        default:
            $_SESSION['error'] = "Invalid action specified.";
    }
    
    // Redirect back to the job page
    header("Location: ../../public/jobs.php?job_id=" . $job_id);
    exit();
}

// If not a valid request, redirect to jobs page
header("Location: ../../public/jobs.php");
exit();
?>
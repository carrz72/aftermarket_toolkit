<?php
// File: post_job.php
// Handles the creation of a new job posting

session_start();
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/notification_handler.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    $_SESSION['error'] = "You must be logged in to post a job.";
    header("Location: ../../public/login.php");
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize and validate input
    $user_id = $_SESSION['user_id'];
    $title = trim(filter_input(INPUT_POST, 'title', FILTER_SANITIZE_SPECIAL_CHARS));
    $description = trim(filter_input(INPUT_POST, 'description', FILTER_SANITIZE_SPECIAL_CHARS));
    $requirements = trim(filter_input(INPUT_POST, 'requirements', FILTER_SANITIZE_SPECIAL_CHARS));
    $location = trim(filter_input(INPUT_POST, 'location', FILTER_SANITIZE_SPECIAL_CHARS));
    $compensation = trim(filter_input(INPUT_POST, 'compensation', FILTER_SANITIZE_SPECIAL_CHARS));
    $category = trim(filter_input(INPUT_POST, 'category', FILTER_SANITIZE_SPECIAL_CHARS));
    $expires_days = filter_input(INPUT_POST, 'expires_days', FILTER_VALIDATE_INT);
    
    // Validate required fields
    $errors = [];
    if (empty($title)) $errors[] = "Job title is required";
    if (empty($description)) $errors[] = "Job description is required";
    if (empty($requirements)) $errors[] = "Job requirements are required";
    if (empty($location)) $errors[] = "Job location is required";
    if (empty($compensation)) $errors[] = "Compensation information is required";
    if (empty($category)) $errors[] = "Job category is required";
    if (!$expires_days || $expires_days < 1) $errors[] = "Please provide a valid expiration period";
    
    if (empty($errors)) {
        // Set default values for dates
        $created_at = date('Y-m-d H:i:s');
        $expires_at = date('Y-m-d H:i:s', strtotime("+{$expires_days} days"));
        
        // Insert the job into the database
        $stmt = $conn->prepare("
            INSERT INTO jobs (user_id, title, description, requirements, location, compensation, category, status, created_at, expires_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, 'open', ?, ?)
        ");
        
        $stmt->bind_param("issssssss", 
            $user_id, $title, $description, $requirements, $location, $compensation, $category, $created_at, $expires_at
        );
        
        if ($stmt->execute()) {
            $job_id = $conn->insert_id;
            $_SESSION['success'] = "Your job has been posted successfully!";
            
            // Notify relevant tradespeople about the new job
            // Get users with matching skills
            $skillStmt = $conn->prepare("
                SELECT DISTINCT user_id FROM tradesperson_skills 
                WHERE skill_name LIKE ? OR skill_name LIKE ?
            ");
            
            $categoryLike = "%$category%";
            $skillStmt->bind_param("ss", $categoryLike, $categoryLike);
            $skillStmt->execute();
            $skillResult = $skillStmt->get_result();
            
            while ($skillRow = $skillResult->fetch_assoc()) {
                $tradesperson_id = $skillRow['user_id'];
                
                // Don't notify the job poster
                if ($tradesperson_id != $user_id) {
                    $notification_content = "New job posted: " . $title;
                    
                    // Use the sendNotification function if available
                    if (function_exists('sendNotification')) {
                        sendNotification(
                            $conn,
                            $tradesperson_id,
                            'new_job',
                            $user_id,
                            $job_id,
                            $notification_content
                        );
                    } else {
                        // Fallback to direct notification creation
                        $notifyStmt = $conn->prepare("
                            INSERT INTO notifications (user_id, type, content, related_id, created_at) 
                            VALUES (?, 'new_job', ?, ?, NOW())
                        ");
                        $notifyStmt->bind_param("isi", $tradesperson_id, $notification_content, $job_id);
                        $notifyStmt->execute();
                    }
                }
            }
            
            header("Location: ../../public/jobs.php?job_id=" . $job_id);
            exit();
        } else {
            $_SESSION['error'] = "Error posting job: " . $conn->error;
        }
    } else {
        $_SESSION['error'] = implode("<br>", $errors);
    }
    
    // If we get here, there were errors, redirect back to the form
    header("Location: ../../public/jobs.php?action=post");
    exit();
}

// If not a POST request, redirect to the job board
header("Location: ../../public/jobs.php");
exit();
?>
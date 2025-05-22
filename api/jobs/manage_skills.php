<?php
// File: manage_skills.php
// Handles adding, updating, and removing tradesperson skills

session_start();
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/image_helper.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    $_SESSION['error'] = "You must be logged in to manage your skills.";
    header("Location: ../../public/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Handle skill form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'add_skill':
            // Sanitize and validate input
            $skill_name = trim(filter_input(INPUT_POST, 'skill_name', FILTER_SANITIZE_SPECIAL_CHARS));
            $experience_level = trim(filter_input(INPUT_POST, 'experience_level', FILTER_SANITIZE_SPECIAL_CHARS));
            
            // Validate required fields
            $errors = [];
            if (empty($skill_name)) $errors[] = "Skill name is required";
            if (empty($experience_level)) $errors[] = "Experience level is required";
            
            // Process certification file if uploaded
            $certification_file = null;
            if (isset($_FILES['certification_file']) && $_FILES['certification_file']['error'] == 0) {
                $allowed = ['jpg', 'jpeg', 'png', 'pdf', 'doc', 'docx'];
                $filename = $_FILES['certification_file']['name'];
                $filetype = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                
                if (!in_array($filetype, $allowed)) {
                    $errors[] = "Invalid certification file type. Allowed types: jpg, jpeg, png, pdf, doc, docx";
                } else {
                    $new_filename = uniqid('cert_') . '.' . $filetype;
                    $upload_path = '../../uploads/certifications/';
                    
                    // Create directory if it doesn't exist
                    if (!file_exists($upload_path)) {
                        mkdir($upload_path, 0777, true);
                    }
                    
                    if (move_uploaded_file($_FILES['certification_file']['tmp_name'], $upload_path . $new_filename)) {
                        $certification_file = 'uploads/certifications/' . $new_filename;
                    } else {
                        $errors[] = "Failed to upload certification file";
                    }
                }
            }
            
            if (empty($errors)) {
                // Check if skill already exists for this user
                $checkStmt = $conn->prepare("SELECT id FROM tradesperson_skills WHERE user_id = ? AND skill_name = ?");
                $checkStmt->bind_param("is", $user_id, $skill_name);
                $checkStmt->execute();
                
                if ($checkStmt->get_result()->num_rows > 0) {
                    $_SESSION['error'] = "You already have this skill listed on your profile.";
                } else {
                    // Insert the new skill
                    $created_at = date('Y-m-d H:i:s');
                    $stmt = $conn->prepare("
                        INSERT INTO tradesperson_skills (user_id, skill_name, experience_level, certification_file, created_at) 
                        VALUES (?, ?, ?, ?, ?)
                    ");
                    
                    $stmt->bind_param("issss", $user_id, $skill_name, $experience_level, $certification_file, $created_at);
                    
                    if ($stmt->execute()) {
                        $_SESSION['success'] = "Skill added successfully!";
                    } else {
                        $_SESSION['error'] = "Error adding skill: " . $conn->error;
                    }
                }
            } else {
                $_SESSION['error'] = implode("<br>", $errors);
            }
            break;
            
        case 'delete_skill':
            $skill_id = filter_input(INPUT_POST, 'skill_id', FILTER_VALIDATE_INT);
            
            if (!$skill_id) {
                $_SESSION['error'] = "Invalid skill selected";
            } else {
                // Check if this skill belongs to the user
                $checkStmt = $conn->prepare("SELECT certification_file FROM tradesperson_skills WHERE id = ? AND user_id = ?");
                $checkStmt->bind_param("ii", $skill_id, $user_id);
                $checkStmt->execute();
                $result = $checkStmt->get_result();
                
                if ($result->num_rows === 0) {
                    $_SESSION['error'] = "Skill not found or you don't have permission to delete it";
                } else {
                    $skill = $result->fetch_assoc();
                    
                    // Delete the skill
                    $deleteStmt = $conn->prepare("DELETE FROM tradesperson_skills WHERE id = ? AND user_id = ?");
                    $deleteStmt->bind_param("ii", $skill_id, $user_id);
                    
                    if ($deleteStmt->execute()) {
                        // Delete certification file if exists
                        if (!empty($skill['certification_file'])) {
                            $file_path = '../../' . $skill['certification_file'];
                            if (file_exists($file_path)) {
                                unlink($file_path);
                            }
                        }
                        
                        $_SESSION['success'] = "Skill deleted successfully!";
                    } else {
                        $_SESSION['error'] = "Error deleting skill: " . $conn->error;
                    }
                }
            }
            break;
            
        default:
            $_SESSION['error'] = "Invalid action specified";
    }
    
    // Redirect back to the skills page
    header("Location: ../../public/tradesperson_profile.php");
    exit();
}

// If not a POST request, redirect back to profile
header("Location: ../../public/tradesperson_profile.php");
exit();
?>
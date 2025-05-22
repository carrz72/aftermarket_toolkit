<?php
// File: skill_verification.php
// Handles tradesperson skill management and verification
session_start();
require_once __DIR__ . '/../../config/db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'You must be logged in to manage skills']);
    exit();
}

$user_id = $_SESSION['user_id'];

// Handle adding a new skill
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_skill') {
    // Validate inputs
    $skill_name = trim($_POST['skill_name'] ?? '');
    $experience_level = trim($_POST['experience_level'] ?? '');
    
    // Validate required fields
    if (empty($skill_name) || empty($experience_level)) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Skill name and experience level are required']);
        exit();
    }
    
    // Check for valid experience level
    $valid_levels = ['beginner', 'intermediate', 'expert', 'master'];
    if (!in_array(strtolower($experience_level), $valid_levels)) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Invalid experience level']);
        exit();
    }
    
    // Check if skill already exists for this user
    $checkStmt = $conn->prepare("SELECT id FROM tradesperson_skills WHERE user_id = ? AND skill_name = ?");
    $checkStmt->bind_param("is", $user_id, $skill_name);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    
    if ($checkResult->num_rows > 0) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'You have already added this skill']);
        exit();
    }
    
    // Process certification file if uploaded
    $certification_file = null;
    if (isset($_FILES['certification_file']) && $_FILES['certification_file']['error'] === UPLOAD_ERR_OK) {
        $allowed_types = ['image/jpeg', 'image/png', 'application/pdf'];
        $file_type = $_FILES['certification_file']['type'];
        
        if (!in_array($file_type, $allowed_types)) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Invalid file type. Only JPG, PNG and PDF files are allowed']);
            exit();
        }
        
        // Generate unique filename
        $file_ext = pathinfo($_FILES['certification_file']['name'], PATHINFO_EXTENSION);
        $filename = 'cert_' . $user_id . '_' . time() . '.' . $file_ext;
        $upload_dir = __DIR__ . '/../../uploads/certifications/';
        
        // Create directory if it doesn't exist
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        $filepath = $upload_dir . $filename;
        
        // Move uploaded file
        if (move_uploaded_file($_FILES['certification_file']['tmp_name'], $filepath)) {
            $certification_file = 'uploads/certifications/' . $filename;
        } else {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Error uploading certification file']);
            exit();
        }
    }
    
    // Insert skill into database
    $sql = "INSERT INTO tradesperson_skills (user_id, skill_name, experience_level, certification_file, is_verified, created_at) 
            VALUES (?, ?, ?, ?, 0, NOW())";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("isss", $user_id, $skill_name, $experience_level, $certification_file);
    
    if ($stmt->execute()) {
        $skill_id = $stmt->insert_id;
        
        // Return success response
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true, 
            'message' => 'Skill added successfully',
            'skill_id' => $skill_id,
            'certification_file' => $certification_file
        ]);
    } else {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Error adding skill: ' . $conn->error]);
    }
    exit();
}

// Handle updating a skill
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_skill') {
    $skill_id = filter_input(INPUT_POST, 'skill_id', FILTER_SANITIZE_NUMBER_INT);
    $experience_level = trim($_POST['experience_level'] ?? '');
    
    // Validate inputs
    if (!$skill_id || empty($experience_level)) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Skill ID and experience level are required']);
        exit();
    }
    
    // Check for valid experience level
    $valid_levels = ['beginner', 'intermediate', 'expert', 'master'];
    if (!in_array(strtolower($experience_level), $valid_levels)) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Invalid experience level']);
        exit();
    }
    
    // Check if skill exists and belongs to the user
    $checkStmt = $conn->prepare("SELECT id FROM tradesperson_skills WHERE id = ? AND user_id = ?");
    $checkStmt->bind_param("ii", $skill_id, $user_id);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    
    if ($checkResult->num_rows === 0) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Skill not found or you do not have permission to update it']);
        exit();
    }
    
    // Process new certification file if uploaded
    $certification_file_sql = "";
    $certification_file_param = "";
    if (isset($_FILES['certification_file']) && $_FILES['certification_file']['error'] === UPLOAD_ERR_OK) {
        $allowed_types = ['image/jpeg', 'image/png', 'application/pdf'];
        $file_type = $_FILES['certification_file']['type'];
        
        if (!in_array($file_type, $allowed_types)) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Invalid file type. Only JPG, PNG and PDF files are allowed']);
            exit();
        }
        
        // Generate unique filename
        $file_ext = pathinfo($_FILES['certification_file']['name'], PATHINFO_EXTENSION);
        $filename = 'cert_' . $user_id . '_' . time() . '.' . $file_ext;
        $upload_dir = __DIR__ . '/../../uploads/certifications/';
        
        // Create directory if it doesn't exist
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        $filepath = $upload_dir . $filename;
        
        // Move uploaded file
        if (move_uploaded_file($_FILES['certification_file']['tmp_name'], $filepath)) {
            $certification_file = 'uploads/certifications/' . $filename;
            $certification_file_sql = ", certification_file = ?";
            $certification_file_param = $certification_file;
        } else {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Error uploading certification file']);
            exit();
        }
    }
    
    // Update skill in database
    $sql = "UPDATE tradesperson_skills SET experience_level = ?, is_verified = 0";
    
    if (!empty($certification_file_sql)) {
        $sql .= $certification_file_sql;
    }
    
    $sql .= " WHERE id = ? AND user_id = ?";
    
    $stmt = $conn->prepare($sql);
    
    if (!empty($certification_file_param)) {
        $stmt->bind_param("ssii", $experience_level, $certification_file_param, $skill_id, $user_id);
    } else {
        $stmt->bind_param("sii", $experience_level, $skill_id, $user_id);
    }
    
    if ($stmt->execute()) {
        // Return success response
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true, 
            'message' => 'Skill updated successfully'
        ]);
    } else {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Error updating skill: ' . $conn->error]);
    }
    exit();
}

// Handle deleting a skill
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_skill') {
    $skill_id = filter_input(INPUT_POST, 'skill_id', FILTER_SANITIZE_NUMBER_INT);
    
    // Validate input
    if (!$skill_id) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Skill ID is required']);
        exit();
    }
    
    // Check if skill exists and belongs to the user
    $checkStmt = $conn->prepare("SELECT id, certification_file FROM tradesperson_skills WHERE id = ? AND user_id = ?");
    $checkStmt->bind_param("ii", $skill_id, $user_id);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    
    if ($checkResult->num_rows === 0) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Skill not found or you do not have permission to delete it']);
        exit();
    }
    
    // Get certification file path before deleting
    $skillData = $checkResult->fetch_assoc();
    $certification_file = $skillData['certification_file'];
    
    // Delete skill from database
    $stmt = $conn->prepare("DELETE FROM tradesperson_skills WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $skill_id, $user_id);
    
    if ($stmt->execute()) {
        // Delete certification file if it exists
        if (!empty($certification_file)) {
            $filepath = __DIR__ . '/../../' . $certification_file;
            if (file_exists($filepath)) {
                unlink($filepath);
            }
        }
        
        // Return success response
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true, 
            'message' => 'Skill deleted successfully'
        ]);
    } else {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Error deleting skill: ' . $conn->error]);
    }
    exit();
}

// Get skills for a user
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['user_id'])) {
    $target_user_id = filter_input(INPUT_GET, 'user_id', FILTER_SANITIZE_NUMBER_INT);
    
    // If no user_id specified, use the logged-in user
    if (!$target_user_id) {
        $target_user_id = $user_id;
    }
    
    // Query to get skills
    $stmt = $conn->prepare("
        SELECT id, skill_name, experience_level, certification_file, is_verified, created_at 
        FROM tradesperson_skills 
        WHERE user_id = ? 
        ORDER BY created_at DESC
    ");
    $stmt->bind_param("i", $target_user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $skills = [];
    while ($row = $result->fetch_assoc()) {
        // Format data
        $row['is_verified'] = (bool)$row['is_verified'];
        $row['has_certification'] = !empty($row['certification_file']);
        $skills[] = $row;
    }
    
    // Return skills data
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'skills' => $skills
    ]);
    exit();
}

// If no valid action is specified, return error
header('Content-Type: application/json');
echo json_encode(['success' => false, 'message' => 'Invalid request']);
exit();
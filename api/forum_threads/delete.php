<?php
// Required headers
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: DELETE");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Include database and object files
include_once '../config/database.php';
include_once '../objects/forum_thread.php';

// Get database connection
$database = new Database();
$db = $database->getConnection();

// Prepare forum_thread object
$forum_thread = new ForumThread($db);

// Get thread ID from request
$data = json_decode(file_get_contents("php://input"));

// Check if ID is set
if (!empty($data->id)) {
    // Set thread ID to delete
    $forum_thread->id = $data->id;

    // Delete the thread
    if ($forum_thread->delete()) {
        // Set response code - 200 OK
        http_response_code(200);
        
        // Tell the user
        echo json_encode(array("message" => "Thread was deleted."));
    } else {
        // Set response code - 503 Service Unavailable
        http_response_code(503);
        
        // Tell the user
        echo json_encode(array("message" => "Unable to delete thread."));
    }
} else {
    // Set response code - 400 Bad Request
    http_response_code(400);
    
    // Tell the user
    echo json_encode(array("message" => "Unable to delete thread. No ID provided."));
}
?>
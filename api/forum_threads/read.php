<?php
// Headers for REST API
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

// Include database and object files
include_once '../config/database.php';
include_once '../objects/forum_thread.php';

// Instantiate database and forum_thread objects
$database = new Database();
$db = $database->getConnection();

$forum_thread = new ForumThread($db);

// Query threads
$stmt = $forum_thread->read();
$num = $stmt->rowCount();

// Check if any threads found
if($num > 0) {
    // Threads array
    $threads_arr = array();
    $threads_arr["records"] = array();
    
    // Retrieve table contents
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        extract($row);
        
        $thread_item = array(
            "id" => $id,
            "title" => $title,
            "content" => html_entity_decode($content),
            "author_id" => $author_id,
            "created_at" => $created_at,
            "updated_at" => $updated_at,
            "category_id" => $category_id,
            "status" => $status
        );
        
        array_push($threads_arr["records"], $thread_item);
    }
    
    // Set response code - 200 OK
    http_response_code(200);
    
    // Show threads data in JSON format
    echo json_encode($threads_arr);
} else {
    // Set response code - 404 Not found
    http_response_code(404);
    
    // Tell the user no threads found
    echo json_encode(array("message" => "No threads found."));
}
?>
<?php
header("Content-Type: application/json");
require_once '../../config/db.php'; // your DB connection file

$data = json_decode(file_get_contents("php://input"));

$id = $data->id;
$title = $data->title;
$body = $data->body;

$sql = "UPDATE forum_threads 
        SET title = '$title', body = '$body' 
        WHERE id = '$id'";

if (mysqli_query($conn, $sql)) {
    echo json_encode(["message" => "Forum thread updated successfully"]);
} else {
    echo json_encode(["error" => "Error updating forum thread"]);
}
?>

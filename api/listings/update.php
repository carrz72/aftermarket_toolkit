<?php
header("Content-Type: application/json");
require_once '../../config/db.php'; // your DB connection file

// Get data from the body of the request (via PUT)
$data = json_decode(file_get_contents("php://input"));

// Extracting data from request
$id = $data->id; // The listing ID to update
$title = $data->title;
$description = $data->description;
$price = $data->price;
$condition = $data->condition;
$image = $data->image;
$category = $data->category;

// SQL to update listing
$sql = "UPDATE listings 
        SET title = '$title', description = '$description', price = '$price', 
            condition = '$condition', image = '$image', category = '$category' 
        WHERE id = '$id'";

if (mysqli_query($conn, $sql)) {
    echo json_encode(["message" => "Listing updated successfully"]);
} else {
    echo json_encode(["error" => "Error updating listing"]);
}
?>

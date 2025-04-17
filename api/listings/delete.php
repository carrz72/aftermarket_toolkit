<?php
header("Content-Type: application/json");
require_once '../../config/db.php'; // your DB connection file

// Get data from the body of the request (via DELETE)
$data = json_decode(file_get_contents("php://input"));
$id = $data->id; // The listing ID to delete

// SQL to delete listing
$sql = "DELETE FROM listings WHERE id = '$id'";

if (mysqli_query($conn, $sql)) {
    echo json_encode(["message" => "Listing deleted successfully"]);
} else {
    echo json_encode(["error" => "Error deleting listing"]);
}
?>

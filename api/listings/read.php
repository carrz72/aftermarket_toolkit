<?php
header("Content-Type: application/json");
require_once '../../config/db.php'; // your db connection

$sql = "SELECT * FROM listings ORDER BY created_at DESC";
$result = mysqli_query($conn, $sql);

$listings = [];

while ($row = mysqli_fetch_assoc($result)) {
    $listings[] = $row;
}

echo json_encode($listings);
?>
<?php
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Access-Control-Allow-Headers,Content-Type,Access-Control-Allow-Methods,Authorization,X-Requested-With');

require_once '../../config/db.php';

$data = json_decode(file_get_contents("php://input"));

if (!isset($data->user_id) || !isset($data->title) || !isset($data->content)) {
    echo json_encode(['message' => 'Missing Required Parameters']);
    exit();
}

$userId = $data->user_id;
$title = $data->title;
$content = $data->content;

$query = "INSERT INTO forum_threads (user_id, title, content, created_at) VALUES (?, ?, ?, NOW())";
$stmt = $conn->prepare($query);
$stmt->bind_param('iss', $userId, $title, $content);

if ($stmt->execute()) {
    echo json_encode(['message' => 'Thread Created', 'thread_id' => $stmt->insert_id]);
} else {
    echo json_encode(['message' => 'Thread Creation Failed']);
}
?>
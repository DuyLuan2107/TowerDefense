<?php
require_once __DIR__ . '/../db/connect.php';

$q = trim($_GET['q'] ?? '');
if ($q === '') {
    echo json_encode([]);
    exit;
}

$sql = "SELECT id, title 
        FROM posts 
        WHERE title LIKE ? 
        ORDER BY created_at DESC 
        LIMIT 7";

$like = "%$q%";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $like);
$stmt->execute();
$res = $stmt->get_result();

$data = [];
while ($row = $res->fetch_assoc()) {
    $data[] = $row;
}

header('Content-Type: application/json');
echo json_encode($data);
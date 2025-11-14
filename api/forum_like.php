<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../db/connect.php';

if (!isset($_SESSION['user'])) {
    echo json_encode(['error' => 'not_logged_in']);
    exit;
}

$post_id = (int)($_POST['post_id'] ?? 0);
$user_id = (int)$_SESSION['user']['id'];

if ($post_id <= 0) {
    echo json_encode(['error' => 'invalid_post']);
    exit;
}

// kiểm tra đã like chưa
$stmt = $conn->prepare("SELECT id FROM post_likes WHERE post_id = ? AND user_id = ?");
$stmt->bind_param("ii", $post_id, $user_id);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows > 0) {
    // unlike
    $del = $conn->prepare("DELETE FROM post_likes WHERE post_id = ? AND user_id = ?");
    $del->bind_param("ii", $post_id, $user_id);
    $del->execute();
    $status = "unliked";
} else {
    // like
    $ins = $conn->prepare("INSERT INTO post_likes (post_id, user_id) VALUES (?, ?)");
    $ins->bind_param("ii", $post_id, $user_id);
    $ins->execute();
    $status = "liked";
}

$count = $conn->query("SELECT COUNT(*) AS total FROM post_likes WHERE post_id = $post_id")
               ->fetch_assoc()['total'];

echo json_encode([
    'status' => $status,
    'likes' => $count
]);
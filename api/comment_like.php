<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../db/connect.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user'])) {
    echo json_encode(['error' => 'not_logged_in']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'invalid_method']);
    exit;
}

$comment_id = (int)($_POST['comment_id'] ?? 0);
$user_id = (int)$_SESSION['user']['id'];

if ($comment_id <= 0) {
    echo json_encode(['error' => 'invalid_comment']);
    exit;
}

// Kiểm tra xem đã like chưa
$check = $conn->prepare("SELECT id FROM comment_likes WHERE comment_id = ? AND user_id = ?");
$check->bind_param("ii", $comment_id, $user_id);
$check->execute();
$result = $check->get_result();

if ($result->num_rows > 0) {
    // Đã like -> Unlike
    $stmt = $conn->prepare("DELETE FROM comment_likes WHERE comment_id = ? AND user_id = ?");
    $stmt->bind_param("ii", $comment_id, $user_id);
    $stmt->execute();
    $status = "unliked";
} else {
    // Chưa like -> Like
    $stmt = $conn->prepare("INSERT INTO comment_likes (comment_id, user_id) VALUES (?, ?)");
    $stmt->bind_param("ii", $comment_id, $user_id);
    $stmt->execute();
    $status = "liked";
}

// Đếm tổng số like
$countStmt = $conn->prepare("SELECT COUNT(*) as total FROM comment_likes WHERE comment_id = ?");
$countStmt->bind_param("i", $comment_id);
$countStmt->execute();
$countResult = $countStmt->get_result();
$totalLikes = $countResult->fetch_assoc()['total'];

echo json_encode([
    'status' => $status,
    'likes' => $totalLikes
]);

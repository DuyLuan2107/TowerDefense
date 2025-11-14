<?php
// comment_delete.php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once 'db/connect.php';

$comment_id = (int)($_GET['id'] ?? 0);
$post_id    = (int)($_GET['post'] ?? 0);

if (!$comment_id || !$post_id || !isset($_SESSION['user'])) {
    header("Location: forum_view.php?id=" . $post_id);
    exit;
}

// chỉ cho xoá nếu là chủ comment
$uid = (int)$_SESSION['user']['id'];

$stmt = $conn->prepare("DELETE FROM comments WHERE id = ? AND user_id = ?");
$stmt->bind_param("ii", $comment_id, $uid);
$stmt->execute();

header("Location: forum_view.php?id=" . $post_id);
exit;

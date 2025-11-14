<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once 'db/connect.php';

$cid = (int)($_GET['id'] ?? 0);
$post_id = (int)($_GET['post'] ?? 0);

if ($cid <= 0 || $post_id <= 0) die("Không hợp lệ.");

// Lấy comment
$stmt = $conn->prepare("SELECT * FROM comments WHERE id = ?");
$stmt->bind_param("i", $cid);
$stmt->execute();
$c = $stmt->get_result()->fetch_assoc();

if (!$c) die("Không tìm thấy bình luận.");

// Kiểm tra quyền
if (!isset($_SESSION['user']) || $_SESSION['user']['id'] != $c['user_id']) {
    die("Bạn không có quyền xoá bình luận này.");
}

// Xoá
$conn->query("DELETE FROM comments WHERE id = $cid");

header("Location: forum_view.php?id=".$post_id);
exit;
?>
<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once 'db/connect.php';

if (!isset($_SESSION['user'])) {
    header("Location: auth.php");
    exit;
}

$post_id = (int)($_GET['id'] ?? 0);
if ($post_id <= 0) {
    header("Location: forum_list.php");
    exit;
}

$uid = (int)$_SESSION['user']['id'];

// --- KIỂM TRA QUYỀN ADMIN ---
$isAdmin = isset($_SESSION['user']['role']) && $_SESSION['user']['role'] === 'admin';

// Kiểm tra bài viết tồn tại và lấy người sở hữu
$stmt = $conn->prepare("SELECT user_id FROM posts WHERE id = ?");
$stmt->bind_param("i", $post_id);
$stmt->execute();
$res = $stmt->get_result()->fetch_assoc();

if (!$res) {
    header("Location: forum_list.php");
    exit;
}

// Logic phân quyền:
// Nếu KHÔNG phải chủ bài VÀ KHÔNG phải admin -> Không có quyền
if ($res['user_id'] != $uid && !$isAdmin) {
    header("Location: forum_view.php?id=" . $post_id);
    exit;
}

// Xoá bài
// Quan trọng: Bỏ "AND user_id = ?" để Admin có thể xoá bài của người khác
$stmtDel = $conn->prepare("DELETE FROM posts WHERE id = ?");
$stmtDel->bind_param("i", $post_id);
$stmtDel->execute();

header("Location: forum_list.php");
exit;
?>
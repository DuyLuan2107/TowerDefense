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

// chỉ cho xoá nếu là chủ bài
$uid = (int)$_SESSION['user']['id'];

// kiểm tra chủ bài
$stmt = $conn->prepare("SELECT user_id FROM posts WHERE id = ?");
$stmt->bind_param("i", $post_id);
$stmt->execute();
$res = $stmt->get_result()->fetch_assoc();

if (!$res) {
    header("Location: forum_list.php");
    exit;
}

if ($res['user_id'] != $uid) {
    // không phải chủ bài -> đá về list
    header("Location: forum_view.php?id=" . $post_id);
    exit;
}

// xoá bài (comments bị xoá theo nhờ FOREIGN KEY ON DELETE CASCADE)
$stmtDel = $conn->prepare("DELETE FROM posts WHERE id = ? AND user_id = ?");
$stmtDel->bind_param("ii", $post_id, $uid);
$stmtDel->execute();

header("Location: forum_list.php");
exit;

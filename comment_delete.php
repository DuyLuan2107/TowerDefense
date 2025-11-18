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

// --- KIỂM TRA QUYỀN ---
// Logic: Phải đăng nhập VÀ (là tác giả bài viết HOẶC là admin)
$isLoggedIn = isset($_SESSION['user']);
$isAuthor = $isLoggedIn && ($_SESSION['user']['id'] == $c['user_id']);
$isAdmin = $isLoggedIn && (isset($_SESSION['user']['role']) && $_SESSION['user']['role'] === 'admin');

if (!$isLoggedIn) {
    die("Bạn cần đăng nhập.");
}

if (!$isAuthor && !$isAdmin) {
    die("Bạn không có quyền xoá bình luận này.");
}
// ----------------------

// Xoá
// Sử dụng Prepared Statement để an toàn hơn (dù $cid đã ép kiểu int)
$stmtDel = $conn->prepare("DELETE FROM comments WHERE id = ?");
$stmtDel->bind_param("i", $cid);
$stmtDel->execute();

header("Location: forum_view.php?id=".$post_id);
exit;
?>
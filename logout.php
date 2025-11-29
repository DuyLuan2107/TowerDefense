<?php
session_start();
require "db/connect.php"; // Kết nối CSDL

// 1. Xử lý xóa Token "Ghi nhớ đăng nhập"
if (isset($_COOKIE['remember_token'])) {
    $token = $_COOKIE['remember_token'];

    // A. Xóa token khỏi Database (Để token này không còn dùng được nữa)
    if (isset($conn)) {
        $stmt = $conn->prepare("DELETE FROM login_tokens WHERE token = ?");
        $stmt->bind_param("s", $token);
        $stmt->execute();
    }

    // B. Xóa Cookie ở trình duyệt (Đặt thời gian về quá khứ)
    // Lưu ý: Path '/' phải giống hệt lúc bạn setcookie
    setcookie('remember_token', '', time() - 3600, '/');
    unset($_COOKIE['remember_token']);
}

// 2. Hủy Session hiện tại
session_unset();
session_destroy();

// 3. Chuyển hướng về trang đăng nhập
header("Location: login.php");
exit;
?>
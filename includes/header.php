<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once "db/connect.php";

/* ===== Cập nhật last_activity cho user đang đăng nhập ===== */
if (isset($_SESSION['user'])) {
    $uid = (int)$_SESSION['user']['id'];
    $conn->query("UPDATE users SET last_activity = NOW() WHERE id = $uid");
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Game Thủ Thành PHP</title>
    <link rel="stylesheet" href="assets/style.css">
    <style>
        /* Header user info */
        .user-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .avatar-img {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #fff;
        }
        .username {
            font-weight: bold;
            color: #fff;
        }
    </style>
</head>
<body>

<!-- Navbar -->
<?php include "includes/navbar.php"; ?>
<div class="container"></div>
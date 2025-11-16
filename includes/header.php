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
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;700&display=swap" rel="stylesheet">
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    
    </head>
<body style="font-family: 'Montserrat', sans-serif;">

<?php include "includes/navbar.php"; ?>
<?php
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../db/connect.php'; // dùng file connect.php của bạn

function require_admin() {
    global $conn; // 

    if (!isset($_SESSION['user']) || empty($_SESSION['user']['id'])) {
        header('Location: /auth.php');
        exit;
    }

    $user_id = intval($_SESSION['user']['id']);
    $stmt = $conn->prepare("SELECT role, is_locked FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->bind_result($role, $is_locked);
    if (!$stmt->fetch() || $is_locked) {
        http_response_code(403);
        echo "Forbidden.";
        exit;
    }
    $stmt->close();

    if ($role !== 'admin') {
        http_response_code(403);
        echo "Bạn không có quyền truy cập.";
        exit;
    }
}

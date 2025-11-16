<?php
// includes/admin_log.php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../db/connect.php';

function admin_log($admin_id, $action, $target_table = null, $target_id = null) {
    global $conn;
    $ip = $_SERVER['REMOTE_ADDR'] ?? null;
    $stmt = $conn->prepare("INSERT INTO admin_logs (admin_id, action, target_table, target_id, ip) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("issis", $admin_id, $action, $target_table, $target_id, $ip);
    $stmt->execute();
    $stmt->close();
}

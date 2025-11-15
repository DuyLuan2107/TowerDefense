<?php
session_start();
require_once "db/connect.php";

if (isset($_SESSION['user'])) {
    $uid = (int)$_SESSION['user']['id'];

    // đánh dấu đã thoát
    $conn->query("UPDATE users SET last_activity = NOW() - INTERVAL 60 SECOND WHERE id = $uid");
}

session_unset();
session_destroy();

header("Location: index.php");
exit;

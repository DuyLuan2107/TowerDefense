<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once 'db/connect.php';


if (!isset($_SESSION['user'])) {
    echo json_encode(['error' => 'not_logged_in']);
    exit;
}

$cid = (int)($_POST['id'] ?? 0);
$content = trim($_POST['content'] ?? '');

if ($cid <= 0 || $content === '') {
    echo json_encode(['error' => 'invalid_data']);
    exit;
}

$stmt = $conn->prepare("SELECT user_id FROM comments WHERE id=?");
$stmt->bind_param("i", $cid);
$stmt->execute();
$info = $stmt->get_result()->fetch_assoc();

if (!$info || $info['user_id'] != $_SESSION['user']['id']) {
    echo json_encode(['error' => 'no_permission']);
    exit;
}

$upd = $conn->prepare("UPDATE comments SET content=? WHERE id=?");
$upd->bind_param("si", $content, $cid);
$upd->execute();

echo json_encode(['success' => true, 'content' => htmlspecialchars($content)]);

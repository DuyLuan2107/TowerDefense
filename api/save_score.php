<?php
// api/save_score.php
header('Content-Type: application/json');
session_start();

require_once __DIR__ . '/../db/connect.php'; // $conn

// Kiểm tra đăng nhập
if (!isset($_SESSION['user'])) {
  echo json_encode(['ok' => false, 'message' => 'Bạn cần đăng nhập để lưu điểm.']);
  exit;
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

$score = isset($data['score']) ? (int)$data['score'] : 0;
$enemies_killed = isset($data['enemies_killed']) ? (int)$data['enemies_killed'] : 0;
$gold_left = isset($data['gold_left']) ? (int)$data['gold_left'] : 0;
$duration_seconds = isset($data['duration_seconds']) ? (int)$data['duration_seconds'] : 0;
$user_id = (int)$_SESSION['user']['id'];

if ($score < 0) $score = 0;

$stmt = $conn->prepare("INSERT INTO scores(user_id, score, enemies_killed, gold_left, duration_seconds) 
                        VALUES (?, ?, ?, ?, ?)");
$stmt->bind_param("iiiii", $user_id, $score, $enemies_killed, $gold_left, $duration_seconds);

if ($stmt->execute()) {
  echo json_encode(['ok' => true]);
} else {
  echo json_encode(['ok' => false, 'message' => 'Không lưu được điểm.']);
}

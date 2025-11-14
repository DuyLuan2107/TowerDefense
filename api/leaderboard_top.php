<?php
// api/leaderboard_top.php
header('Content-Type: application/json');
session_start();
require_once __DIR__ . '/../db/connect.php';

// Lấy top 10: best_score per user
$sqlTop = "
  SELECT u.name, t.best_score
  FROM (
    SELECT user_id, MAX(score) AS best_score
    FROM scores
    GROUP BY user_id
  ) t
  JOIN users u ON u.id = t.user_id
  ORDER BY t.best_score DESC
  LIMIT 10
";
$top = [];
$res = $conn->query($sqlTop);
if ($res) while ($row = $res->fetch_assoc()) $top[] = $row;

// Tính rank của user hiện tại (nếu có)
$userRank = null;
$userBest = null;
if (isset($_SESSION['user'])) {
  $uid = (int)$_SESSION['user']['id'];
  // best của user
  $resBest = $conn->query("SELECT MAX(score) AS best FROM scores WHERE user_id=".$uid);
  $userBest = ($resBest && ($r=$resBest->fetch_assoc())) ? (int)$r['best'] : null;

  if ($userBest !== null) {
    // rank = 1 + số người có best_score > userBest
    $sqlRank = "
      SELECT COUNT(*) AS higher 
      FROM (
        SELECT user_id, MAX(score) AS best_score
        FROM scores
        GROUP BY user_id
      ) t
      WHERE t.best_score > $userBest
    ";
    $r2 = $conn->query($sqlRank)->fetch_assoc();
    $userRank = 1 + (int)($r2['higher'] ?? 0);
  }
}

echo json_encode([
  'ok' => true,
  'top' => $top,              // [{name, best_score}, ...]
  'userBest' => $userBest,    // null nếu chưa có điểm
  'userRank' => $userRank     // null nếu chưa có điểm/không đăng nhập
]);

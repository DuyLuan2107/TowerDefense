<?php
require_once 'db/connect.php';
include 'includes/header.php';

/**
 * BXH theo điểm cao nhất của MỖI NGƯỜI.
 * Lấy MAX(score) per user, join ra tên.
 * Có phân trang đơn giản (?page=1,2,...)
 */
$perPage = 10;
$page = max(1, intval($_GET['page'] ?? 1));
$offset = ($page - 1) * $perPage;

$sqlCount = "SELECT COUNT(*) AS total_users FROM (SELECT user_id FROM scores GROUP BY user_id) t";
$totalUsers = $conn->query($sqlCount)->fetch_assoc()['total_users'] ?? 0;

$sql = "
  SELECT u.name, MAX(s.score) AS best_score
  FROM scores s
  JOIN users u ON u.id = s.user_id
  GROUP BY s.user_id
  ORDER BY best_score DESC
  LIMIT ? OFFSET ?
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $perPage, $offset);
$stmt->execute();
$result = $stmt->get_result();

echo '<div class="profile-container" style="max-width:700px">';
echo '<h2>🏆 Bảng Xếp Hạng (điểm cao nhất)</h2>';
echo '<p class="muted">Bạn không cần đăng nhập để xem BXH, nhưng phải đăng nhập mới lưu điểm và có tên trong bảng.</p>';

echo '<table style="width:100%; border-collapse:collapse">';
echo '<tr style="background:#f1f1f1"><th style="text-align:left;padding:8px">#</th><th style="text-align:left;padding:8px">Người chơi</th><th style="text-align:right;padding:8px">Điểm cao nhất</th></tr>';

$rankStart = $offset + 1;
while ($row = $result->fetch_assoc()) {
  echo '<tr>';
  echo '<td style="padding:8px">' . ($rankStart++) . '</td>';
  echo '<td style="padding:8px">' . htmlspecialchars($row['name']) . '</td>';
  echo '<td style="padding:8px; text-align:right">' . (int)$row['best_score'] . '</td>';
  echo '</tr>';
}
echo '</table>';

// Phân trang
$totalPages = max(1, ceil($totalUsers / $perPage));
if ($totalPages > 1) {
  echo '<div style="margin-top:12px">';
  for ($p = 1; $p <= $totalPages; $p++) {
    $cur = ($p == $page) ? 'font-weight:bold' : '';
    echo '<a style="margin-right:8px; '.$cur.'" href="?page='.$p.'">'.$p.'</a>';
  }
  echo '</div>';
}

echo '</div>';

include 'includes/footer.php';

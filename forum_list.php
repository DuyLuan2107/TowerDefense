<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once 'db/connect.php';
include 'includes/header.php';

// Tìm kiếm
$q = trim($_GET['q'] ?? '');
$perPage = 5;
$page = max(1, intval($_GET['page'] ?? 1));
$offset = ($page - 1) * $perPage;

// đếm tổng bài
$sqlCount = "SELECT COUNT(*) AS total FROM posts";
$params = [];
$where = "";

if ($q !== '') {
    $where = " WHERE title LIKE ? OR content LIKE ?";
    $sqlCount .= $where;
    $like = "%$q%";
    $params = [$like, $like];
}
// chuẩn bị count
$stmtCount = $conn->prepare($sqlCount);
if ($where !== '') $stmtCount->bind_param("ss", ...$params);
$stmtCount->execute();
$total = $stmtCount->get_result()->fetch_assoc()['total'] ?? 0;
$totalPages = max(1, ceil($total / $perPage));

// lấy danh sách bài
$sql = "
  SELECT p.*, u.name AS author
  FROM posts p
  JOIN users u ON u.id = p.user_id
  $where
  ORDER BY p.created_at DESC
  LIMIT ? OFFSET ?
";

if ($where === '') {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $perPage, $offset);
} else {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssii", $params[0], $params[1], $perPage, $offset);
}
$stmt->execute();
$result = $stmt->get_result();
?>

<div class="profile-container" style="max-width:900px; text-align:left">
  <h2>💬 Cộng Đồng Game</h2>

  <form method="get" style="margin-bottom:15px; display:flex; gap:8px;">
    <input type="text" name="q" placeholder="Tìm bài viết..." 
           value="<?= htmlspecialchars($q) ?>" 
           style="flex:1;padding:8px;border-radius:8px;border:1px solid #ccc;">
    <button class="btn-send" style="padding:8px 16px">Tìm</button>
  </form>

  <div style="margin-bottom:15px;">
    <?php if (isset($_SESSION['user'])): ?>
      <a href="forum_create_post.php" class="btn-send">✍️ Đăng bài mới</a>
    <?php else: ?>
      <span class="muted">Bạn cần <a href="auth.php">đăng nhập</a> để đăng bài.</span>
    <?php endif; ?>
  </div>

  <?php if ($total == 0): ?>
    <p class="muted">Chưa có bài nào.</p>
  <?php else: ?>
    <?php while ($row = $result->fetch_assoc()): ?>
      <div style="padding:15px;border-bottom:1px solid #eee;">
        <h3 style="margin:0 0 5px;">
          <a href="forum_view.php?id=<?= $row['id'] ?>">
            <?= htmlspecialchars($row['title']) ?>
          </a>
        </h3>
        <div class="muted" style="font-size:0.9em;">
          By <?= htmlspecialchars($row['author']) ?> • 
          <?= $row['created_at'] ?>
        </div>
        <p style="margin-top:8px;">
          <?= nl2br(htmlspecialchars(mb_substr($row['content'], 0, 160))) ?>...
        </p>
      </div>
    <?php endwhile; ?>

    <!-- Phân trang -->
    <?php if ($totalPages > 1): ?>
      <div style="margin-top:15px;">
        <?php for ($p = 1; $p <= $totalPages; $p++): ?>
          <?php
            $link = '?page='.$p.($q !== '' ? '&q='.urlencode($q) : '');
            $style = $p == $page ? 'font-weight:bold;' : '';
          ?>
          <a href="<?= $link ?>" style="margin-right:8px;<?= $style ?>"><?= $p ?></a>
        <?php endfor; ?>
      </div>
    <?php endif; ?>
  <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>

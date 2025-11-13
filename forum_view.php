<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once 'db/connect.php';
include 'includes/header.php';

$post_id = (int)($_GET['id'] ?? 0);
if ($post_id <= 0) {
  echo "<div class='profile-container'><p>Bài viết không tồn tại.</p></div>";
  include 'includes/footer.php'; exit;
}

// Lấy bài viết
$sql = "SELECT p.*, u.name AS author FROM posts p 
        JOIN users u ON u.id = p.user_id WHERE p.id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $post_id);
$stmt->execute();
$post = $stmt->get_result()->fetch_assoc();

if (!$post) {
  echo "<div class='profile-container'><p>Bài viết không tồn tại.</p></div>";
  include 'includes/footer.php'; exit;
}

// Thêm bình luận
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['comment'])) {
  if (!isset($_SESSION['user'])) {
    echo "<script>alert('Bạn cần đăng nhập để bình luận.');</script>";
  } else {
    $content = trim($_POST['content'] ?? '');
    if ($content !== '') {
      $uid = (int)$_SESSION['user']['id'];
      $stmtC = $conn->prepare("INSERT INTO comments (post_id, user_id, content) VALUES (?,?,?)");
      $stmtC->bind_param("iis", $post_id, $uid, $content);
      $stmtC->execute();
      header("Location: forum_view.php?id=".$post_id);
      exit;
    }
  }
}

// Lấy bình luận
$sqlC = "SELECT c.*, u.name AS author 
         FROM comments c JOIN users u ON u.id = c.user_id 
         WHERE c.post_id = ? ORDER BY c.created_at ASC";
$stmtC2 = $conn->prepare($sqlC);
$stmtC2->bind_param("i", $post_id);
$stmtC2->execute();
$comments = $stmtC2->get_result();
?>

<div class="profile-container" style="max-width:800px; text-align:left">
  <a href="forum_list.php">&larr; Quay lại Cộng Đồng Game</a>
  <h2 style="margin-top:10px;"><?= htmlspecialchars($post['title']) ?></h2>
  <div class="muted" style="font-size:0.9em;">
    By <?= htmlspecialchars($post['author']) ?> • <?= $post['created_at'] ?>
  </div>
  <p style="margin-top:15px; white-space:pre-line;">
    <?= htmlspecialchars($post['content']) ?>
  </p>

  <!-- (Tuỳ chọn) nút sửa / xoá nếu là chủ bài -->
  <?php if (isset($_SESSION['user']) && $_SESSION['user']['id'] == $post['user_id']): ?>
    <div style="margin-bottom:15px;">
      <a href="forum_edit_post.php?id=<?= $post_id ?>" class="btn">Sửa bài</a>
      <a href="forum_delete_post.php?id=<?= $post_id ?>" class="btn" 
         onclick="return confirm('Xoá bài này?');">Xoá</a>
    </div>
  <?php endif; ?>

  <hr>
  <h3>Bình luận</h3>

  <?php while ($c = $comments->fetch_assoc()): ?>
    <div style="margin-bottom:10px; padding:8px; border-radius:8px; background:#f7f7f7;">
      <strong><?= htmlspecialchars($c['author']) ?></strong>
      <span class="muted" style="font-size:0.85em;"> • <?= $c['created_at'] ?></span>
      <p style="margin:5px 0; white-space:pre-line;"><?= htmlspecialchars($c['content']) ?></p>

      <?php if (isset($_SESSION['user']) && $_SESSION['user']['id'] == $post['user_id']): ?>
        <div style="margin-bottom:15px;">
            <a href="forum_edit_post.php?id=<?= $post_id ?>" class="btn">Sửa bài</a>
            <a href="forum_delete_post.php?id=<?= $post_id ?>" class="btn"
            onclick="return confirm('Xoá bài này?');">Xoá</a>
        </div>
    <?php endif; ?>

    </div>
  <?php endwhile; ?>

  <?php if (isset($_SESSION['user'])): ?>
    <form method="post" style="margin-top:15px;">
      <textarea name="content" rows="3" style="width:100%;padding:8px;border-radius:8px;border:1px solid #ccc;"
                placeholder="Viết bình luận..."></textarea>
      <br><br>
      <button class="btn-send" type="submit" name="comment">Gửi bình luận</button>
    </form>
  <?php else: ?>
    <p class="muted">Bạn cần <a href="auth.php">đăng nhập</a> để bình luận.</p>
  <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>

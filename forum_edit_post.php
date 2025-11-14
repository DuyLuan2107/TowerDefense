<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once 'db/connect.php';
include 'includes/header.php';

if (!isset($_SESSION['user'])) {
    echo '<div class="profile-container"><div class="profile-message">
          Vui lòng <a class="btn-login" href="auth.php">đăng nhập</a> để sửa bài viết.
          </div></div>';
    include 'includes/footer.php';
    exit;
}

$post_id = (int)($_GET['id'] ?? 0);
if ($post_id <= 0) {
    echo "<div class='profile-container'><p>Bài viết không tồn tại.</p></div>";
    include 'includes/footer.php';
    exit;
}

// Lấy bài viết + kiểm tra quyền sở hữu
$sql = "SELECT * FROM posts WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $post_id);
$stmt->execute();
$post = $stmt->get_result()->fetch_assoc();

if (!$post) {
    echo "<div class='profile-container'><p>Bài viết không tồn tại.</p></div>";
    include 'includes/footer.php'; exit;
}

if ($post['user_id'] != $_SESSION['user']['id']) {
    echo "<div class='profile-container'><p>Bạn không có quyền sửa bài này.</p></div>";
    include 'includes/footer.php'; exit;
}

// Xử lý submit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title   = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');

    if ($title !== '') {
        $stmtUpdate = $conn->prepare("UPDATE posts SET title = ?, content = ? WHERE id = ?");
        $stmtUpdate->bind_param("ssi", $title, $content, $post_id);
        $stmtUpdate->execute();

        header("Location: forum_view.php?id=" . $post_id);
        exit;
    } else {
        $error = "Tiêu đề không được để trống.";
    }
}
?>
<div class="profile-container" style="max-width:700px">
  <h2>✏️ Sửa bài viết</h2>

  <?php if (!empty($error)): ?>
    <div class="auth-message error"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <form method="post">
    <input style="width:100%;padding:10px;margin-bottom:10px"
           name="title"
           value="<?= htmlspecialchars($post['title']) ?>"
           placeholder="Tiêu đề bài viết">
    <textarea style="width:100%;padding:10px;height:160px"
              name="content"
              placeholder="Nội dung bài viết..."><?= htmlspecialchars($post['content']) ?></textarea>
    <br><br>
    <button class="btn-send" type="submit">Lưu thay đổi</button>
  </form>
</div>
<?php include 'includes/footer.php'; ?>

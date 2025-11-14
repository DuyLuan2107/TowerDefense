<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once 'db/connect.php';
include 'includes/header.php';

if (!isset($_SESSION['user'])) {
  echo '<div class="profile-container"><div class="profile-message">
        Vui lòng <a class="btn-login" href="auth.php">đăng nhập</a> để đăng bài.
        </div></div>';
  include 'includes/footer.php';
  exit;
}

$user_id = (int)$_SESSION['user']['id'];
$prefillScore = (int)($_GET['score'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $title = trim($_POST['title'] ?? '');
  $content = trim($_POST['content'] ?? '');
  if ($title !== '') {
    $stmt = $conn->prepare("INSERT INTO posts (user_id, title, content) VALUES (?,?,?)");
    $stmt->bind_param("iss", $user_id, $title, $content);
    $stmt->execute();
    header('Location: forum_list.php');
    exit;
  }
}
?>
<div class="profile-container" style="max-width:700px">
  <h2>✍️ Đăng bài khoe điểm</h2>
  <form method="post">
    <input style="width:100%;padding:10px;margin-bottom:10px" 
           name="title" value="<?= htmlspecialchars($prefillScore ? "Mình vừa đạt {$prefillScore} điểm ở Tower Defense!" : "") ?>"
           placeholder="Tiêu đề bài viết">
    <textarea style="width:100%;padding:10px;height:140px" name="content"
              placeholder="Chia sẻ thêm chiến thuật, cảm nhận, ảnh chụp màn hình (dán link)..."></textarea>
    <br><br>
    <button class="btn-send" type="submit">Đăng bài</button>
  </form>
</div>
<?php include 'includes/footer.php'; ?>

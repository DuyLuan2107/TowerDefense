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

$cid = (int)($_GET['id'] ?? 0);
$post_id = (int)($_GET['post'] ?? 0);

if ($cid <= 0 || $post_id <= 0) die("Dữ liệu không hợp lệ.");

// Lấy comment
$stmt = $conn->prepare("SELECT * FROM comments WHERE id = ?");
$stmt->bind_param("i", $cid);
$stmt->execute();
$c = $stmt->get_result()->fetch_assoc();

if (!$c) die("Không tìm thấy bình luận.");

// Kiểm tra quyền
if (!isset($_SESSION['user']) || $_SESSION['user']['id'] != $c['user_id']) {
    die("Bạn không có quyền sửa bình luận này.");
}

// Submit sửa
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $content = trim($_POST['content']);

    if ($content !== "") {
        $upd = $conn->prepare("UPDATE comments SET content = ? WHERE id = ?");
        $upd->bind_param("si", $content, $cid);
        $upd->execute();
    }
    header("Location: forum_view.php?id=".$post_id);
    exit;
}
?>

<div class="profile-container" style="max-width:700px;">

    <h2>Sửa bình luận</h2>

    <form method="post">
        <textarea name="content" rows="4"
          style="width:100%;padding:8px;border-radius:8px;border:1px solid #ccc;">
<?= htmlspecialchars($c['content']) ?>
        </textarea>

        <br><br>
        <button class="btn-send">Lưu</button>
        <br><br>
        <a href="javascript:history.back()">Hủy</a>
    </form>

</div>

<?php include 'includes/footer.php'; ?>
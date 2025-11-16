<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once 'db/connect.php';
include 'includes/header.php';

if (!isset($_SESSION['user'])) {
    echo '<div class="cmt-edit-container"><div class="cmt-edit-msg">
          Vui lòng <a class="cmt-edit-login" href="auth.php">đăng nhập</a> để sửa bình luận.
          </div></div>';
    include 'includes/footer.php';
    exit;
}

$cid = (int)($_GET['id'] ?? 0);
$post_id = (int)($_GET['post'] ?? 0);

if ($cid <= 0 || $post_id <= 0) die("Dữ liệu không hợp lệ.");

$stmt = $conn->prepare("SELECT * FROM comments WHERE id = ?");
$stmt->bind_param("i", $cid);
$stmt->execute();
$c = $stmt->get_result()->fetch_assoc();

if (!$c) die("Không tìm thấy bình luận.");

if ($_SESSION['user']['id'] != $c['user_id']) {
    die("Bạn không có quyền sửa bình luận này.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $content = trim($_POST['content']);
    $hasNew = !empty($_FILES['new_image']['name']);

    // Update nội dung
    $upd = $conn->prepare("UPDATE comments SET content=? WHERE id=?");
    $upd->bind_param("si", $content, $cid);
    $upd->execute();

    // Thay ảnh nếu có ảnh mới
    if ($hasNew) {
        $tmp = $_FILES['new_image']['tmp_name'];
        $ext = strtolower(pathinfo($_FILES['new_image']['name'], PATHINFO_EXTENSION));
        $mime = mime_content_type($tmp);

        if (strpos($mime, "image/") === 0) {
            $dir = "uploads/comment_images/";
            if (!is_dir($dir)) mkdir($dir, 0777, true);

            $newFile = $dir . time() . "_" . rand(1000,9999) . "." . $ext;
            move_uploaded_file($tmp, $newFile);

            // Xóa ảnh cũ
            if ($oldImage) {
                if (file_exists($oldImage['image_path'])) unlink($oldImage['image_path']);
                $conn->query("DELETE FROM comment_images WHERE comment_id=$cid");
            }

            // Lưu ảnh mới
            $stmtImg = $conn->prepare("INSERT INTO comment_images (comment_id, image_path) VALUES (?,?)");
            $stmtImg->bind_param("is", $cid, $newFile);
            $stmtImg->execute();
        }
    }

    header("Location: forum_view.php?id=".$post_id);
    exit;
}
?>

<div style="width:700px; margin:15px auto 15px auto;">
    <a href="javascript:history.back()" 
       style="display:inline-block; font-size:3em; text-decoration:none; color:#1877f2;">
       ←
    </a>
</div>

<div class="cmt-edit-container">

    <h2 class="cmt-edit-title">✏️ Sửa bình luận</h2>

    <form method="post" class="cmt-edit-form">

        <textarea name="content" class="cmt-edit-textarea" rows="4"><?= htmlspecialchars($c['content']) ?></textarea>

        <div class="cmt-edit-actions">
            <button class="cmt-edit-save">💾 Lưu thay đổi</button>
            <a href="javascript:history.back()" class="cmt-edit-cancel">Hủy</a>
        </div>

    </form>

</div>

<?php include 'includes/footer.php'; ?>

<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once 'db/connect.php';
include 'includes/header.php';

if (!isset($_SESSION['user'])) {
    echo "Vui lòng đăng nhập.";
    include 'includes/footer.php';
    exit;
}

$cid = (int)($_GET['id'] ?? 0);
$post_id = (int)($_GET['post'] ?? 0);

if ($cid <= 0 || $post_id <= 0) die("Dữ liệu không hợp lệ.");

$stmt = $conn->prepare("SELECT * FROM comments WHERE id=?");
$stmt->bind_param("i", $cid);
$stmt->execute();
$c = $stmt->get_result()->fetch_assoc();

if (!$c) die("Không tìm thấy bình luận.");

if ($_SESSION['user']['id'] != $c['user_id']) die("Bạn không có quyền sửa bình luận.");

// Lấy ảnh cũ
$oldImgQ = $conn->query("SELECT * FROM comment_images WHERE comment_id=$cid");
$oldImage = $oldImgQ->fetch_assoc();


// ================== SUBMIT ==================
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

<div class="profile-container" style="max-width:700px;">

    <h2>Sửa bình luận</h2>

    <form method="post" enctype="multipart/form-data">

        <textarea name="content" rows="4"
          style="width:100%;padding:8px;border-radius:8px;border:1px solid #ccc;">
<?= htmlspecialchars($c['content']) ?></textarea>

        <!-- CHỈ HIỂN THỊ ẢNH & NÚT KHI CÓ ẢNH CŨ -->
        <?php if ($oldImage): ?>

            <h3>Ảnh của bình luận</h3>

            <div id="imgBox" style="margin-bottom:10px;">
                <img id="previewImage"
                     src="<?= $oldImage['image_path'] ?>"
                     style="max-width:220px;border-radius:8px;margin-bottom:10px;display:block;">
            </div>

            <button type="button" id="btnReplace"
                style="padding:6px 10px;background:#007bff;color:white;border:none;border-radius:6px;cursor:pointer;">
                Thay ảnh
            </button>

            <input type="file" name="new_image" id="newImageInput"
                   accept="image/*" style="display:none;">

        <?php endif; ?>

        <br><br>
        <button class="btn-send">Lưu</button>
        <br><br>
        <a href="forum_view.php?id=<?= $post_id ?>">Hủy</a>

    </form>

</div>

<script>
// Chỉ có nếu có ảnh cũ
const replaceBtn = document.getElementById("btnReplace");
if (replaceBtn) {
    const newInput = document.getElementById("newImageInput");
    replaceBtn.onclick = () => newInput.click();

    newInput.addEventListener("change", function () {
        const file = this.files[0];
        if (!file) return;

        const url = URL.createObjectURL(file);
        document.getElementById("previewImage").src = url;
    });
}
</script>

<?php include 'includes/footer.php'; ?>
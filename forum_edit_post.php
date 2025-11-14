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

// Lấy bài viết
$stmt = $conn->prepare("SELECT * FROM posts WHERE id = ?");
$stmt->bind_param("i", $post_id);
$stmt->execute();
$post = $stmt->get_result()->fetch_assoc();

if (!$post) {
    echo "<div class='profile-container'><p>Bài viết không tồn tại.</p></div>";
    include 'includes/footer.php';
    exit;
}

// Kiểm tra quyền sửa
if ($post['user_id'] != $_SESSION['user']['id']) {
    echo "<div class='profile-container'><p>Bạn không có quyền sửa bài này.</p></div>";
    include 'includes/footer.php';
    exit;
}

// Lấy file cũ
$oldFiles = $conn->query("SELECT * FROM post_files WHERE post_id = $post_id");

// =========================
// XÓA FILE CŨ
// =========================
if (isset($_POST['delete_file'])) {
    $fid = (int)$_POST['delete_file'];

    $q = $conn->query("SELECT file_path FROM post_files WHERE id = $fid AND post_id = $post_id");
    if ($f = $q->fetch_assoc()) {
        if (file_exists($f['file_path'])) unlink($f['file_path']);
    }

    $conn->query("DELETE FROM post_files WHERE id = $fid");

    header("Location: forum_edit_post.php?id=".$post_id);
    exit;
}

// =========================
// LƯU BÀI VIẾT + FILE MỚI
// =========================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['delete_file'])) {

    $title = trim($_POST['title']);
    $content = trim($_POST['content']);

    if ($title === "") {
        $error = "Tiêu đề không được để trống.";
    } else {
        // update bài
        $upd = $conn->prepare("UPDATE posts SET title=?, content=? WHERE id=?");
        $upd->bind_param("ssi", $title, $content, $post_id);
        $upd->execute();

        // upload file mới
        if (!empty($_FILES['files']['name'][0])) {

            $uploadDir = "uploads/";
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

            foreach ($_FILES['files']['name'] as $i => $name) {
                $tmp = $_FILES['files']['tmp_name'][$i];
                $err = $_FILES['files']['error'][$i];

                if ($err !== UPLOAD_ERR_OK) continue;
                if (!file_exists($tmp)) continue;

                $mime = mime_content_type($tmp);
                $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));

                if (!(strpos($mime, "image/") === 0 || $mime === "video/mp4")) continue;

                $newName = time()."_".rand(1000,9999).".".$ext;
                $path = $uploadDir.$newName;

                move_uploaded_file($tmp, $path);

                $type = strpos($mime,"video")===0 ? "video" : "image";

                $stmtF = $conn->prepare("INSERT INTO post_files (post_id,file_path,file_type) VALUES (?,?,?)");
                $stmtF->bind_param("iss",$post_id,$path,$type);
                $stmtF->execute();
            }
        }

        header("Location: forum_view.php?id=".$post_id);
        exit;
    }
}
?>

<div class="profile-container" style="max-width:700px">
  <h2>✏️ Sửa bài viết</h2>

  <?php if (!empty($error)): ?>
    <div class="auth-message error"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <form method="post" enctype="multipart/form-data">

    <input style="width:100%;padding:10px;margin-bottom:10px"
           name="title"
           value="<?= htmlspecialchars($post['title']) ?>"
           placeholder="Tiêu đề bài viết">

    <textarea style="width:100%;padding:10px;height:140px"
              name="content"
              placeholder="Nội dung bài viết..."><?= htmlspecialchars($post['content']) ?></textarea>

    <label>Ảnh / Video (có thể chọn nhiều):</label>
    <input type="file" id="fileInput" name="files[]" multiple
        accept="image/*,video/mp4"
        style="width: 100px; margin:10px 0;">

    <div id="previewArea" style="margin-top:10px; display:flex; flex-wrap:wrap; gap:10px;">
        <!-- preview file cũ -->
        <?php foreach ($oldFiles as $f): ?>
        <div class="preview-item old-file" data-old-id="<?= $f['id'] ?>"
            style="
            width:120px;height:120px;position:relative;
            overflow:hidden;border-radius:12px;background:#f0f0f0;
            display:flex;justify-content:center;align-items:center;">
            
            <?php if ($f['file_type']==='image'): ?>
            <img src="<?= $f['file_path'] ?>" style="width:100%;height:100%;object-fit:cover;">
            <?php else: ?>
            <video src="<?= $f['file_path'] ?>" muted autoplay loop
                style="width:100%;height:100%;object-fit:cover;"></video>
            <?php endif; ?>

            <button type="submit" name="delete_file" value="<?= $f['id'] ?>"
                style="
                position:absolute;top:4px;right:4px;
                width:22px;height:22px;
                background:rgba(0,0,0,0.6);color:white;border:none;
                border-radius:50%;cursor:pointer;font-size:14px;">
                ×
            </button>
        </div>
        <?php endforeach; ?>
    </div>

    <br><br>
    <button class="btn-send" type="submit">Lưu thay đổi</button>
    <br><br>
    <a href="javascript:history.back()">Hủy</a>
  </form>
</div>

<script>
const fileInput = document.getElementById('fileInput');
const preview = document.getElementById('previewArea');
let filesBuffer = [];
const MAX = 40 * 1024 * 1024;

fileInput.addEventListener("change", () => {
    for (let f of fileInput.files) {
        if (f.size > MAX) { alert("File quá lớn!"); continue; }
        filesBuffer.push(f);
    }
    fileInput.value = "";
    renderNewFiles();
});

// Render file mới vào preview
function renderNewFiles() {
    document.querySelectorAll('.preview-new').forEach(e => e.remove());

    filesBuffer.forEach((file, index) => {
        const wrap = document.createElement("div");
        wrap.className = "preview-new";
        wrap.style = `
            width:120px;height:120px;position:relative;
            overflow:hidden;border-radius:12px;
            background:#f0f0f0;margin:10px 10px 0 0;
            display:flex;justify-content:center;align-items:center;
        `;

        const url = URL.createObjectURL(file);

        if (file.type.startsWith("image/")) {
            let img = document.createElement("img");
            img.src = url;
            img.style = "width:100%;height:100%;object-fit:cover;";
            wrap.appendChild(img);
        } else {
            let v = document.createElement("video");
            v.src = url; v.muted = true; v.autoplay = true; v.loop = true;
            v.style = "width:100%;height:100%;object-fit:cover;";
            wrap.appendChild(v);
        }

        const btn = document.createElement("button");
        btn.innerHTML = "×";
        btn.style = `
            position:absolute;top:4px;right:4px;
            width:22px;height:22px;
            background:rgba(0,0,0,0.6);color:white;border:none;
            border-radius:50%;cursor:pointer;
        `;
        btn.onclick = () => {
            filesBuffer.splice(index, 1);
            renderNewFiles();
        };

        wrap.appendChild(btn);
        preview.appendChild(wrap);
    });
}

document.querySelector("form").addEventListener("submit", () => {
    const dt = new DataTransfer();
    filesBuffer.forEach(f => dt.items.add(f));
    fileInput.files = dt.files;
});
</script>

<?php include 'includes/footer.php'; ?>

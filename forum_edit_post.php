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
$stmt = $conn->prepare("SELECT * FROM posts WHERE id=?");
$stmt->bind_param("i", $post_id);
$stmt->execute();
$post = $stmt->get_result()->fetch_assoc();

// Kiểm tra bài
if (!$post) {
    echo "<div class='profile-container'><p>Bài viết không tồn tại.</p></div>";
    include 'includes/footer.php';
    exit;
}

// Kiểm tra quyền
if ($post['user_id'] != $_SESSION['user']['id']) {
    echo "<div class='profile-container'><p>Bạn không có quyền sửa bài này.</p></div>";
    include 'includes/footer.php';
    exit;
}

// Lấy file cũ
$oldFiles = $conn->query("SELECT * FROM post_files WHERE post_id=$post_id");

// ==================== XÓA FILE ====================
if (isset($_POST['delete_file'])) {
    $fid = (int)$_POST['delete_file'];
    $q = $conn->query("SELECT file_path FROM post_files WHERE id=$fid AND post_id=$post_id");
    if ($f = $q->fetch_assoc()) {
        if (file_exists($f['file_path'])) unlink($f['file_path']);
    }
    $conn->query("DELETE FROM post_files WHERE id=$fid");
    header("Location: forum_edit_post.php?id=".$post_id);
    exit;
}

// ==================== LƯU ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['delete_file'])) {

    // Xóa file cũ đánh dấu
    if (!empty($_POST['delete_list'])) {
        $list = json_decode($_POST['delete_list'], true);
        foreach ($list as $fid) {
            $fid = (int)$fid;
            $q = $conn->query("SELECT file_path FROM post_files WHERE id=$fid AND post_id=$post_id");
            if ($f = $q->fetch_assoc()) {
                if (file_exists($f['file_path'])) unlink($f['file_path']);
            }
            $conn->query("DELETE FROM post_files WHERE id=$fid");
        }
    }

    // Update bài
    $title = trim($_POST['title']);
    $content = trim($_POST['content']);

    if ($title === "") {
        $error = "Tiêu đề không được để trống.";
    } else {
        $upd = $conn->prepare("UPDATE posts SET title=?, content=? WHERE id=?");
        $upd->bind_param("ssi", $title, $content, $post_id);
        $upd->execute();

        // Upload file mới
        if (!empty($_FILES['files']['name'][0])) {
            $uploadDir = "uploads/posts/";
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

            foreach ($_FILES['files']['name'] as $i => $name) {
                $tmp = $_FILES['files']['tmp_name'][$i];
                $err = $_FILES['files']['error'][$i];
                if ($err !== UPLOAD_ERR_OK || !file_exists($tmp)) continue;

                $mime = mime_content_type($tmp);
                $ext  = strtolower(pathinfo($name, PATHINFO_EXTENSION));

                if (!(strpos($mime,"image/")===0 || $mime==="video/mp4")) continue;

                $newName = time()."_".rand(1000,9999).".".$ext;
                $path = $uploadDir.$newName;
                move_uploaded_file($tmp, $path);

                $type = strpos($mime,"video")===0 ? "video" : "image";

                $stmtF = $conn->prepare("INSERT INTO post_files (post_id,file_path,file_type) VALUES (?,?,?)");
                $stmtF->bind_param("iss", $post_id, $path, $type);
                $stmtF->execute();
            }
        }

        header("Location: forum_view.php?id=".$post_id);
        exit;
    }
}

?>



<div style="width:700px; margin:0px auto 15px auto;">
    <a href="javascript:history.back()" 
       style="display:inline-block; font-size:3em; text-decoration:none; color:#1877f2;">
       ←
    </a>
</div>

<div class="edit-post-container">

    <h2>✏️ Sửa bài viết</h2>

    <div class="edit-post">

        <?php if (!empty($error)): ?>
            <div class="edit-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="delete_list" id="deleteList">

            <!-- TITLE -->
            <textarea class="edit-title"
                      name="title"
                      placeholder="Tiêu đề bài viết..."><?= htmlspecialchars($post['title']) ?></textarea>

            <!-- CONTENT -->
            <textarea class="edit-textarea"
                      name="content"
                      placeholder="Nội dung bài viết..."><?= htmlspecialchars($post['content']) ?></textarea>

            <label class="edit-label">Ảnh / Video (có thể chọn nhiều):</label>
            <input type="file" id="fileInput" name="files[]" multiple accept="image/*,video/mp4">

            <div id="previewArea" class="edit-preview">
                <?php foreach ($oldFiles as $f): ?>
                    <div class="edit-preview-item old-file" data-old-id="<?= $f['id'] ?>">

                        <?php if ($f['file_type']==='image'): ?>
                            <img src="<?= $f['file_path'] ?>">
                        <?php else: ?>
                            <video src="<?= $f['file_path'] ?>" muted autoplay loop></video>
                        <?php endif; ?>

                        <button type="button" class="edit-del-old" data-id="<?= $f['id'] ?>">×</button>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="edit-actions">
                <button class="edit-btn" type="submit">💾 Lưu thay đổi</button>
                <a class="edit-cancel" href="javascript:history.back()">Hủy</a>
            </div>
        </form>

    </div>
</div>


<script>
/* --- JS giữ nguyên logic, chỉ đổi class --- */
const fileInput  = document.getElementById('fileInput');
const preview    = document.getElementById('previewArea');
let filesBuffer  = [];
let deleteList   = [];
const MAX = 40*1024*1024;

fileInput.addEventListener("change", () => {
    for (let f of fileInput.files) {
        if (f.size > MAX) { alert("File quá lớn!"); continue; }
        filesBuffer.push(f);
    }
    fileInput.value = "";
    renderNewFiles();
});

// preview file mới
function renderNewFiles() {
    document.querySelectorAll('.preview-new').forEach(e => e.remove());
    filesBuffer.forEach((file,index)=>{
        const wrap = document.createElement("div");
        wrap.className = "edit-preview-item preview-new";

        const url = URL.createObjectURL(file);
        if (file.type.startsWith("image/")) {
            wrap.innerHTML = `<img src="${url}">`;
        } else {
            wrap.innerHTML = `<video src="${url}" muted autoplay loop></video>`;
        }

        const btn = document.createElement("button");
        btn.className = "edit-del-old";
        btn.innerText = "×";
        btn.onclick = () => { filesBuffer.splice(index,1); renderNewFiles(); };
        wrap.appendChild(btn);

        preview.appendChild(wrap);
    });
}

document.querySelector("form").addEventListener("submit", ()=>{
    const dt = new DataTransfer();
    filesBuffer.forEach(f => dt.items.add(f));
    fileInput.files = dt.files;
});

document.querySelectorAll(".edit-del-old").forEach(btn => {
    btn.onclick = function () {
        deleteList.push(this.dataset.id);
        document.getElementById("deleteList").value = JSON.stringify(deleteList);
        this.parentElement.remove();
    };
});
</script>

<?php include 'includes/footer.php'; ?>

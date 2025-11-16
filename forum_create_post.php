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

    // Lưu bài
    $stmt = $conn->prepare("INSERT INTO posts (user_id, title, content) VALUES (?,?,?)");
    $stmt->bind_param("iss", $user_id, $title, $content);
    $stmt->execute();
    $post_id = $stmt->insert_id;

    // Upload file
    if (!empty($_FILES['files']['name'][0])) {

        $uploadDir = "uploads/";
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

        foreach ($_FILES['files']['name'] as $i => $name) {

            $tmp = $_FILES['files']['tmp_name'][$i];
            $error = $_FILES['files']['error'][$i];
            if ($error !== UPLOAD_ERR_OK || !file_exists($tmp)) continue;

            $mime = mime_content_type($tmp);
            $ext  = strtolower(pathinfo($name, PATHINFO_EXTENSION));

            if (!(strpos($mime, 'image/') === 0 || $mime === 'video/mp4')) continue;

            $newName = time() . "_" . rand(1000,9999) . "." . $ext;
            $target = $uploadDir . $newName;

            move_uploaded_file($tmp, $target);

            $type = strpos($mime, "video") === 0 ? "video" : "image";

            $stmtF = $conn->prepare("INSERT INTO post_files (post_id, file_path, file_type) VALUES (?,?,?)");
            $stmtF->bind_param("iss", $post_id, $target, $type);
            $stmtF->execute();
        }
    }

    header('Location: forum_list.php');
    exit;
  }
}
?>

<!-- =========================== -->
<!--     GIAO DIỆN CREATE POST    -->
<!-- =========================== -->

<div style="width:700px; margin:0px auto 15px auto;">
    <a href="javascript:history.back()" 
       style="display:inline-block; font-size:3em; text-decoration:none; color:#1877f2;">
       ←
    </a>
</div>
<div class="post-create">

    <h2>✍️ Đăng bài</h2>

    <form method="post" enctype="multipart/form-data">

        <!-- TITLE -->
        <textarea class="pc-title"
                  name="title"
                  placeholder="Tiêu đề bài viết..."><?= htmlspecialchars(
                     $prefillScore ? "Mình vừa đạt {$prefillScore} điểm ở Tower Defense!" : ""
                  ) ?></textarea>

        <!-- CONTENT -->
        <textarea class="pc-content"
                  name="content"
                  placeholder="Chia sẻ thêm chiến thuật, cảm nhận..."></textarea>

        <!-- UPLOAD -->
        <label class="pc-label">Ảnh / Video (có thể chọn nhiều):</label>
        <input type="file" name="files[]" multiple accept="image/*,video/mp4" class="pc-file">

        <!-- PREVIEW -->
        <div id="previewArea" class="pc-preview"></div>

        <div class="pc-actions">
            <button class="pc-btn" type="submit">Đăng bài</button>
        </div>

    </form>

</div>

<script>
// ===========================
//       JS PREVIEW FILE
// ===========================
const fileInput = document.querySelector('input[name="files[]"]');
const preview = document.getElementById('previewArea');

let filesBuffer = [];
const MAX_SIZE = 40 * 1024 * 1024; // 40MB

fileInput.addEventListener("change", function () {
    for (let file of this.files) {

        if (file.size > MAX_SIZE) {
            alert(`File "${file.name}" lớn hơn 40MB và sẽ không tải lên.`);
            continue;
        }

        filesBuffer.push(file);
    }

    this.value = ""; 
    renderPreview();
});

function renderPreview() {
    preview.innerHTML = "";

    filesBuffer.forEach((file, index) => {
        const box = document.createElement("div");
        box.className = "pc-preview-item";

        const url = URL.createObjectURL(file);

        if (file.type.startsWith("image/")) {
            box.innerHTML = `<img src="${url}">`;
        } else {
            box.innerHTML = `<video src="${url}" muted autoplay loop></video>`;
        }

        const del = document.createElement("button");
        del.innerText = "×";
        del.onclick = () => {
            filesBuffer.splice(index, 1);
            renderPreview();
        };

        box.appendChild(del);
        preview.appendChild(box);
    });
}

document.querySelector("form").addEventListener("submit", function() {
    const dt = new DataTransfer();
    filesBuffer.forEach(f => dt.items.add(f));
    fileInput.files = dt.files;
});
</script>

<?php include 'includes/footer.php'; ?>

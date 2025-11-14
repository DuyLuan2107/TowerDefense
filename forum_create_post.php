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

    // 1. Lưu bài viết
    $stmt = $conn->prepare("INSERT INTO posts (user_id, title, content) VALUES (?,?,?)");
    $stmt->bind_param("iss", $user_id, $title, $content);
    $stmt->execute();
    $post_id = $stmt->insert_id;

    // 2. Xử lý file đính kèm
    if (!empty($_FILES['files']['name'][0])) {
        $uploadDir = "uploads/";

        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        foreach ($_FILES['files']['name'] as $i => $name) {

            $tmp = $_FILES['files']['tmp_name'][$i];
            $error = $_FILES['files']['error'][$i];

            if ($error !== UPLOAD_ERR_OK) continue;
            if (!file_exists($tmp)) continue;

            // kiểm tra file MIME tối thiểu để chặn file độc
            $mime = mime_content_type($tmp);
            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));

            if (!(strpos($mime, 'image/') === 0 || $mime === 'video/mp4')) {
                continue;
            }

            // tạo tên file mới
            $newName = time() . "_" . rand(1000,9999) . "." . $ext;
            $target = $uploadDir . $newName;

            move_uploaded_file($tmp, $target);

            // xác định loại file
            $type = strpos($mime, "video") === 0 ? "video" : "image";

            // lưu DB
            $stmtF = $conn->prepare("
                INSERT INTO post_files (post_id, file_path, file_type)
                VALUES (?,?,?)
            ");
            $stmtF->bind_param("iss", $post_id, $target, $type);
            $stmtF->execute();
        }
    }

    header('Location: forum_list.php');
    exit;
  }
}

?>
<div class="profile-container" style="max-width:700px">
  <h2>✍️ Đăng bài khoe điểm</h2>

  <form method="post" enctype="multipart/form-data">

    <input style="width:100%;padding:10px;margin-bottom:10px" 
           name="title"
           placeholder="Tiêu đề bài viết"
           value="<?= htmlspecialchars($prefillScore ? "Mình vừa đạt {$prefillScore} điểm ở Tower Defense!" : "") ?>">

    <textarea style="width:100%;padding:10px;height:140px" 
              name="content"
              placeholder="Chia sẻ thêm chiến thuật, cảm nhận..."></textarea>

    <label>Ảnh / Video (có thể chọn nhiều):</label>
    <input type="file" name="files[]" multiple
           accept="image/*,video/mp4"
           style="width: 100px; margin:10px 0;">
    <div id="previewArea" style="margin-top:10px;"></div>

    <br><br>
    <button class="btn-send" type="submit">Đăng bài</button>
  </form>
</div>

<script>
const fileInput = document.querySelector('input[name="files[]"]');
const preview = document.getElementById('previewArea');
const MAX_SIZE = 40 * 1024 * 1024; // 40MB

let filesBuffer = [];

fileInput.addEventListener("change", function() {
    for (let file of this.files) {
        if (file.size > MAX_SIZE) {
            alert(`File "${file.name}" lớn hơn 40MB và sẽ không được tải lên.`);
            continue;
        }

        filesBuffer.push(file);
    }

    this.value = ""; // reset để lần chọn sau không mất preview
    renderPreview();
});

// Hiển thị lại toàn bộ file đang có trong buffer
function renderPreview() {
    preview.innerHTML = "";

    filesBuffer.forEach((file, index) => {
        const wrap = document.createElement("div");
        wrap.style = `
            width: 120px;
            height: 120px;
            margin: 10px;
            position: relative;
            display: inline-flex;
            justify-content: center;
            align-items: center;
            overflow: hidden;
            border-radius: 12px;
            background: #f0f0f0;
        `;

        const url = URL.createObjectURL(file);

        // IMAGE
        if (file.type.startsWith("image/")) {
            const img = document.createElement("img");
            img.src = url;
            img.style = `
                width: 100%;
                height: 100%;
                object-fit: cover;
            `;
            wrap.appendChild(img);
        }

        // VIDEO
        else if (file.type === "video/mp4") {
            const video = document.createElement("video");
            video.src = url;
            video.muted = true;
            video.playsInline = true;
            video.autoplay = true;
            video.loop = true;
            video.style = `
                width: 100%;
                height: 100%;
                object-fit: cover;
            `;
            wrap.appendChild(video);
        }

        // nút xoá
        const btn = document.createElement("button");
        btn.innerText = "×";
        btn.style = `
            position:absolute;
            top:4px; right:4px;
            background:rgba(0,0,0,0.6);
            color:white;
            border:none;
            border-radius:50%;
            width:22px;
            height:22px;
            font-size:14px;
            cursor:pointer;
            line-height:20px;
            text-align:center;
        `;
        btn.onclick = () => {
            filesBuffer.splice(index, 1);
            renderPreview();
        };

        wrap.appendChild(btn);
        preview.appendChild(wrap);
    });
}


// Khi submit, đưa filesBuffer vào form
document.querySelector("form").addEventListener("submit", function(e) {
    const dt = new DataTransfer();

    filesBuffer.forEach(file => dt.items.add(file));

    fileInput.files = dt.files;
});
</script>


<?php include 'includes/footer.php'; ?>
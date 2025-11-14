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

$resLike = $conn->query("SELECT COUNT(*) AS total FROM post_likes WHERE post_id = $post_id");
$totalLikes = $resLike->fetch_assoc()['total'] ?? 0;

$userLiked = false;
if (isset($_SESSION['user'])) {
    $uid = $_SESSION['user']['id'];
    $chk = $conn->query("SELECT id FROM post_likes WHERE post_id = $post_id AND user_id = $uid");
    $userLiked = $chk->num_rows > 0;
}


// Thêm bình luận
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['comment'])) {
  if (!isset($_SESSION['user'])) {
    echo "<script>alert('Bạn cần đăng nhập để bình luận.');</script>";
  } else {
    $content = trim($_POST['content'] ?? '');
    $imageSelected = isset($_FILES['comment_image']) && $_FILES['comment_image']['error'] === UPLOAD_ERR_OK;

    if ($content === '' && !$imageSelected) {
        echo "<script>alert('Bình luận phải có nội dung hoặc ảnh.');</script>";
    } else {
        // Lưu bình luận (cho phép content rỗng)
        $uid = (int)$_SESSION['user']['id'];
        $stmtC = $conn->prepare("INSERT INTO comments (post_id, user_id, content) VALUES (?,?,?)");
        $stmtC->bind_param("iis", $post_id, $uid, $content);
        $stmtC->execute();
        $comment_id = $stmtC->insert_id;

        // Upload ảnh nếu có
        if ($imageSelected) {
            $tmp = $_FILES['comment_image']['tmp_name'];
            $mime = mime_content_type($tmp);

            if (strpos($mime, "image/") === 0) {
                $ext = strtolower(pathinfo($_FILES['comment_image']['name'], PATHINFO_EXTENSION));

                $dir = "uploads/comment_images/";
                if (!is_dir($dir)) mkdir($dir, 0777, true);

                $newName = time() . "_" . rand(1000,9999) . "." . $ext;
                $path = $dir . $newName;

                move_uploaded_file($tmp, $path);

                // lưu DB
                $stmtImg = $conn->prepare("INSERT INTO comment_images (comment_id, image_path) VALUES (?,?)");
                $stmtImg->bind_param("is", $comment_id, $path);
                $stmtImg->execute();
            }
        }

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
  <a href="forum_list.php">&larr; Quay lại</a>
  <h2 style="margin-top:10px; margin-bottom:5px;"><?= htmlspecialchars($post['title']) ?></h2>
  <div class="muted" style="font-size:0.9em;">
    By <?= htmlspecialchars($post['author']) ?> • <?= $post['created_at'] ?>
  </div>
  <p style="margin-top:15px;">
    <?= htmlspecialchars($post['content']) ?>
  </p>
  <?php
  // Lấy file đính kèm của post
  $files = $conn->query("SELECT * FROM post_files WHERE post_id = $post_id");
  ?>
  
  <?php while ($f = $files->fetch_assoc()): ?>
      <?php if ($f['file_type'] === 'image'): ?>
          <img src="<?= $f['file_path'] ?>"
              style="max-width:100%; margin:15px 0; border-radius:8px;">
      <?php else: ?>
          <video controls
                style="max-width:100%; margin:15px 0; border-radius:8px;">
              <source src="<?= $f['file_path'] ?>" type="video/mp4">
          </video>
      <?php endif; ?>
  <?php endwhile; ?>
  
  <div style="
    margin:15px 0;
    display:flex;
    align-items:center;
    gap:12px;
    flex-wrap:wrap;
  ">

      <!-- LIKE -->
      <button id="likeBtn"
          style="padding:6px 12px;border-radius:6px;border:1px solid #ccc;cursor:pointer;display:flex;align-items:center;gap:6px;">
          <?= $userLiked ? "❤️" : "🤍" ?> 
          <span id="likeCount"><?= $totalLikes ?></span>
      </button>

      <!-- CHIA SẺ -->
      <button id="shareBtn"
          style="padding:6px 12px;border-radius:6px;border:1px solid #ccc;cursor:pointer;">
          Chia sẻ
      </button>

      <!-- SỬA / XOÁ BÀI -->
      <?php if (isset($_SESSION['user']) && $_SESSION['user']['id'] == $post['user_id']): ?>
          <a href="forum_edit_post.php?id=<?= $post_id ?>"
            style="padding:6px 12px;border-radius:6px;border:1px solid #ccc;cursor:pointer;text-decoration:none;font-size:0.9em;color:black;">
              Sửa bài
          </a>

          <a href="forum_delete_post.php?id=<?= $post_id ?>"
            onclick="return confirm('Xoá bài này?');"
            style="padding:6px 12px;border-radius:6px;border:1px solid #ccc;cursor:pointer;text-decoration:none;font-size:0.9em;color:black;">
              Xoá bài
          </a>
      <?php endif; ?>
  </div>

  <!-- THÔNG BÁO SAO CHÉP -->
  <div id="shareStatus" 
      style="display:none; padding:10px; background:#e0ffe0; color:#006600; border:1px solid #66cc66;
              border-radius:6px; margin-bottom:10px; font-size:0.9em;">
      Đã sao chép đường dẫn bài viết!
  </div>


  <hr>
  <h3>Bình luận</h3>

  <?php while ($c = $comments->fetch_assoc()): ?>
    <div style="margin-bottom:10px; padding:8px; border-radius:8px; background:#f7f7f7;">
        <strong><?= htmlspecialchars($c['author']) ?></strong>
        <span class="muted" style="font-size:0.85em;"> • <?= $c['created_at'] ?></span>

        <p style="margin:5px 0;">
          <?= htmlspecialchars($c['content']) ?>
        </p>

        <?php
        $cid = $c['id'];
        $img = $conn->query("SELECT image_path FROM comment_images WHERE comment_id = $cid")->fetch_assoc();
        ?>

        <?php if (!empty($img['image_path'])): ?>
            <img src="<?= $img['image_path'] ?>" 
                style="max-width:100%; margin:10px 0; border-radius:8px;">
        <?php endif; ?>


        <!-- Nút sửa/xóa comment -->
        <?php if (isset($_SESSION['user']) && $_SESSION['user']['id'] == $c['user_id']): ?>
            <div style="margin-top:8px;">
                <a href="comment_edit.php?id=<?= $c['id'] ?>&post=<?= $post_id ?>"
                  style="font-size:0.8em; color:#0077cc; cursor:pointer; margin-right:10px;">
                  Sửa
                </a>

                <a href="comment_delete.php?id=<?= $c['id'] ?>&post=<?= $post_id ?>"
                  onclick="return confirm('Xoá bình luận này?');"
                  style="font-size:0.8em; color:#d9534f; cursor:pointer;">
                  Xoá
                </a>

            </div>
        <?php endif; ?>
    </div>
  <?php endwhile; ?>
  
  <!-- Viết bình luận -->
  <?php if (isset($_SESSION['user'])): ?>
    <form method="post" enctype="multipart/form-data" style="margin-top:15px;">
      <textarea name="content" rows="3"
                style="width:100%;padding:8px;border-radius:8px;border:1px solid #ccc;"
                placeholder="Viết bình luận..."></textarea>

      <input type="file" name="comment_image" accept="image/*" style="margin:10px 0;">

      <br>
      <button class="btn-send" type="submit" name="comment">Gửi bình luận</button>
    </form>

  <?php else: ?>
    <p class="muted">Bạn cần <a href="auth.php">đăng nhập</a> để bình luận.</p>
  <?php endif; ?>
</div>

<script>
// Like button giữ nguyên logic fetch
document.getElementById("likeBtn").onclick = function() {
    fetch("api/forum_like.php", {
        method: "POST",
        headers: {"Content-Type": "application/x-www-form-urlencoded"},
        body: "post_id=<?= $post_id ?>"
    })
    .then(r => r.json())
    .then(d => {
        if (d.error === "not_logged_in") {
            alert("Bạn cần đăng nhập để like.");
            return;
        }

        document.getElementById("likeCount").innerText = d.likes;
        likeBtn.innerHTML = (d.status === "liked" ? "❤️" : "🤍") + 
                            " <span id='likeCount'>" + d.likes + "</span>";
    });
};

// Share button
document.getElementById("shareBtn").onclick = function() {
    navigator.clipboard.writeText(window.location.href).then(() => {
        const box = document.getElementById("shareStatus");
        box.style.display = "block";

        setTimeout(() => {
            box.style.display = "none";
        }, 2000);
    });
};
</script>


<?php include 'includes/footer.php'; ?>
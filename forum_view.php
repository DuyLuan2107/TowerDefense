<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once 'db/connect.php';

function formatDateVN($datetime) {
    $date = new DateTime($datetime);
    $monthNames = [
        1 => 'Tháng 1', 2 => 'Tháng 2', 3 => 'Tháng 3', 4 => 'Tháng 4',
        5 => 'Tháng 5', 6 => 'Tháng 6', 7 => 'Tháng 7', 8 => 'Tháng 8',
        9 => 'Tháng 9', 10 => 'Tháng 10', 11 => 'Tháng 11', 12 => 'Tháng 12'
    ];
    return $date->format('d') . ' ' . $monthNames[(int)$date->format('m')] . ' ' . $date->format('Y');
}

// --- KIỂM TRA QUYỀN ADMIN ---
$isAdmin = false;
if (isset($_SESSION['user']) && isset($_SESSION['user']['role']) && $_SESSION['user']['role'] === 'admin') {
    $isAdmin = true;
}
// -----------------------------

$post_id = (int)($_GET['id'] ?? 0);
if ($post_id <= 0) {
  echo "<div class='fb-post-container'><p>Bài viết không tồn tại.</p></div>";
  include 'includes/footer.php'; exit;
}

// Lấy bài viết
$sql = "SELECT p.*, u.name AS author, u.avatar AS author_avatar FROM posts p 
        JOIN users u ON u.id = p.user_id WHERE p.id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $post_id);
$stmt->execute();
$post = $stmt->get_result()->fetch_assoc();

if (!$post) {
  echo "<div class='fb-post-container'><p>Bài viết không tồn tại.</p></div>";
  include 'includes/footer.php'; exit;
}

// Likes
$resLike = $conn->query("SELECT COUNT(*) AS total FROM post_likes WHERE post_id = $post_id");
$totalLikes = $resLike->fetch_assoc()['total'] ?? 0;

$userLiked = false;
if (isset($_SESSION['user'])) {
    $uid = $_SESSION['user']['id'];
    $chk = $conn->query("SELECT id FROM post_likes WHERE post_id = $post_id AND user_id = $uid");
    $userLiked = $chk->num_rows > 0;
}

// Bình luận - Xử lý POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['comment'])) {
  if (!isset($_SESSION['user'])) {
    echo "<script>alert('Bạn cần đăng nhập để bình luận.');</script>";
  } else {
    $content = trim($_POST['content'] ?? '');
    $imageSelected = isset($_FILES['comment_image']) && $_FILES['comment_image']['error'] === UPLOAD_ERR_OK;

    if ($content === '' && !$imageSelected) {
        echo "<script>alert('Bình luận phải có nội dung hoặc ảnh.');</script>";
    } else {
        $uid = (int)$_SESSION['user']['id'];
        $stmtC = $conn->prepare("INSERT INTO comments (post_id, user_id, content) VALUES (?,?,?)");
        $stmtC->bind_param("iis", $post_id, $uid, $content);
        $stmtC->execute();
        $comment_id = $stmtC->insert_id;

        if ($imageSelected) {
            $tmp = $_FILES['comment_image']['tmp_name'];
            $mime = mime_content_type($tmp);

            if (strpos($mime, "image/") === 0) {
                $ext = strtolower(pathinfo($_FILES['comment_image']['name'], PATHINFO_EXTENSION));
                $dir = "uploads/comments/";
                if (!is_dir($dir)) mkdir($dir, 0777, true);
                $newName = time() . "_" . rand(1000,9999) . "." . $ext;
                $path = $dir . $newName;
                move_uploaded_file($tmp, $path);
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
include 'includes/header.php';
// Lấy danh sách bình luận
$sqlC = "SELECT c.*, u.name AS author, u.avatar AS author_avatar 
          FROM comments c JOIN users u ON u.id = c.user_id 
          WHERE c.post_id = ? ORDER BY c.created_at ASC";
$stmtC2 = $conn->prepare($sqlC);
$stmtC2->bind_param("i", $post_id);
$stmtC2->execute();
$comments = $stmtC2->get_result();
?>

        <div class="fb-post-container">
            <div style="margin-bottom:10px;">
            <a href="javascript:history.back()" 
              style="display:inline-block; font-size:3em; text-decoration:none; color:#1877f2;">
              ←
            </a>
        </div>
    <div class="fb-post">
        <div class="fb-post-header">
            <a href="user_profile.php?id=<?= $post['user_id'] ?>">
                <img class="avatar" src="<?= htmlspecialchars($post['author_avatar'] ?? 'uploads/avatar/default.png') ?>" alt="Avatar">
            </a>

            <div class="info">
                <div class="author">
                    <a href="user_profile.php?id=<?= $post['user_id'] ?>" 
                    style="color:black; font-weight:bold; text-decoration:none;">
                    <?= htmlspecialchars($post['author']) ?>
                    </a>
                </div>

                <div class="time"><?= formatDateVN($post['created_at']) ?></div>
                <?php if (!empty($post['topic'])): ?>
                    <div class="topic-badge">Chủ đề: <?= htmlspecialchars($post['topic']) ?></div>
                <?php endif; ?>
            </div>
        </div>

        <div class="post-title"><?= htmlspecialchars($post['title']) ?></div>

        <div class="fb-post-media">
            <?php
            $files = $conn->query("SELECT * FROM post_files WHERE post_id = $post_id");
            while ($f = $files->fetch_assoc()):
                if ($f['file_type'] === 'image'): ?>
                    <img src="<?= $f['file_path'] ?>" alt="Post image">
                <?php else: ?>
                    <video controls>
                        <source src="<?= $f['file_path'] ?>" type="video/mp4">
                    </video>
            <?php endif; endwhile; ?>
        </div>

        <?php if (trim($post['content']) !== ''): ?>
            <div class="fb-post-content">
                <?= nl2br(htmlspecialchars($post['content'])) ?>
            </div>
        <?php endif; ?>

        <div class="fb-post-actions">
            <button id="likeBtn"><?= $userLiked ? "❤️" : "🤍" ?> <span id="likeCount"><?= $totalLikes ?></span></button>
            <button id="shareBtn">Chia sẻ</button>
            
            <?php if (isset($_SESSION['user'])): ?>
                <?php 
                    $isAuthor = $_SESSION['user']['id'] == $post['user_id'];
                ?>
                
                <!-- Chỉ tác giả mới được Sửa -->
                <?php if ($isAuthor): ?>
                    <a href="forum_edit_post.php?id=<?= $post_id ?>">Sửa</a>
                <?php endif; ?>

                <!-- Tác giả HOẶC Admin được Xoá -->
                <?php if ($isAuthor || $isAdmin): ?>
                    <a href="forum_delete_post.php?id=<?= $post_id ?>" onclick="return confirm('Xoá bài này?');" style="<?= $isAdmin && !$isAuthor ? 'color: red;' : '' ?>">Xoá</a>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <div id="shareStatus" style="display:none; padding:10px; background:#e0ffe0; color:#006600; border:1px solid #66cc66; border-radius:6px; margin-bottom:10px; font-size:0.9em;">
            Đã sao chép đường dẫn bài viết!
        </div>

        <!-- Comments -->
        <div class="fb-comments">
            <?php while ($c = $comments->fetch_assoc()): ?>
                <div class="fb-comment">
                    <a href="user_profile.php?id=<?= $c['user_id'] ?>">
                        <img class="avatar" src="<?= $c['author_avatar'] ?: 'uploads/avatar/default.png' ?>" alt="Avatar">
                    </a>

                    <div class="content">
                        <strong>
                            <a href="user_profile.php?id=<?= $c['user_id'] ?>"
                            style="color:black; text-decoration:none;">
                            <?= htmlspecialchars($c['author']) ?>
                            </a>
                        </strong>

                        <span style="font-size:0.8em; color:#65676b;"> • <?= formatDateVN($c['created_at']) ?></span>
                        <p><?= htmlspecialchars($c['content']) ?></p>
                        <?php
                        $cid = $c['id'];
                        $img = $conn->query("SELECT image_path FROM comment_images WHERE comment_id = $cid")->fetch_assoc();
                        if (!empty($img['image_path'])): ?>
                            <img src="<?= $img['image_path'] ?>" style="max-width:100%; margin-top:5px; border-radius:8px;">
                        <?php endif; ?>

                        <?php if (isset($_SESSION['user'])): ?>
                            <?php 
                                $isCommentAuthor = $_SESSION['user']['id'] == $c['user_id'];
                            ?>
                            <div style="margin-top:5px;">
                                <!-- Chỉ chủ bình luận mới được sửa -->
                                <?php if ($isCommentAuthor): ?>
                                    <a href="comment_edit.php?id=<?= $c['id'] ?>&post=<?= $post_id ?>" style="font-size:0.8em; color:#1877f2; margin-right:10px;">Sửa</a>
                                <?php endif; ?>
                                
                                <!-- Chủ bình luận HOẶC Admin được xoá -->
                                <?php if ($isCommentAuthor || $isAdmin): ?>
                                    <a href="comment_delete.php?id=<?= $c['id'] ?>&post=<?= $post_id ?>" onclick="return confirm('Xoá bình luận này?');" style="font-size:0.8em; color:#d9534f;">Xoá</a>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endwhile; ?>

            <?php if (isset($_SESSION['user'])): ?>
    <form class="fb-comment-form" method="post" enctype="multipart/form-data">
        <img class="avatar" src="<?= htmlspecialchars($_SESSION['user']['avatar'] ?? 'uploads/avatar/default.png') ?>" alt="Avatar">
        <div class="input-container">
            <textarea name="content" rows="2" placeholder="Viết bình luận..."></textarea>
            <div class="controls">
                <input type="file" name="comment_image" accept="image/*">
                <button type="submit" name="comment">Gửi</button>
            </div>
        </div>
    </form>
<?php else: ?>
    <p style="color:#65676b;">Bạn cần <a href="auth.php">đăng nhập</a> để bình luận.</p>
<?php endif; ?>

        </div>
    </div>
    </div>
</div>

<script>
// Like button
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
        likeBtn.innerHTML = (d.status === "liked" ? "❤️" : "🤍") + " <span id='likeCount'>" + d.likes + "</span>";
    });
};

// Share button
document.getElementById("shareBtn").onclick = function() {
    navigator.clipboard.writeText(window.location.href).then(() => {
        const box = document.getElementById("shareStatus");
        box.style.display = "block";
        setTimeout(() => { box.style.display = "none"; }, 2000);
    });
};
</script>

<?php include 'includes/footer.php'; ?>
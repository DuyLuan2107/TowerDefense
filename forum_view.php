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
    return $date->format('d') . ' ' . $monthNames[(int)$date->format('m')] . ' ' . $date->format('Y') . ' lúc ' . $date->format('H:i');
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
    $parent_comment_id = isset($_POST['parent_comment_id']) && $_POST['parent_comment_id'] !== '' ? (int)$_POST['parent_comment_id'] : NULL;

    if ($content === '' && !$imageSelected) {
        echo "<script>alert('Bình luận phải có nội dung hoặc ảnh.');</script>";
    } else {
        $uid = (int)$_SESSION['user']['id'];
        $is_reply = $parent_comment_id !== NULL ? 1 : 0;
        $stmtC = $conn->prepare("INSERT INTO comments (post_id, user_id, content, parent_comment_id, is_reply) VALUES (?,?,?,?,?)");
        $stmtC->bind_param("iisii", $post_id, $uid, $content, $parent_comment_id, $is_reply);
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

        // Giữ lại cách sắp xếp
        $sort_param = isset($_GET['sort']) ? '&sort=' . $_GET['sort'] : '';
        $order_param = isset($_GET['order']) ? '&order=' . $_GET['order'] : '';
        header("Location: forum_view.php?id=".$post_id.$sort_param.$order_param . "#comment-" . $comment_id);
        exit;
    }
  }
}

// Lấy thông tin bình luận để trả lời (AJAX)
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'get_comment') {
    if (!isset($_GET['cid'])) {
        echo json_encode(['error' => 'Comment ID not provided']);
        exit;
    }
    
    $cid = (int)$_GET['cid'];
    $sql = "SELECT c.id, c.content, u.name FROM comments c 
            JOIN users u ON u.id = c.user_id 
            WHERE c.id = ? AND c.post_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $cid, $post_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($comment = $result->fetch_assoc()) {
        echo json_encode($comment);
    } else {
        echo json_encode(['error' => 'Comment not found']);
    }
    exit;
}
include 'includes/header.php';

// Lấy tham số sắp xếp
$sort_by = $_GET['sort'] ?? 'time'; // 'time' hoặc 'likes'
$order = $_GET['order'] ?? 'asc'; // 'asc' hoặc 'desc'

// Xây dựng câu query sắp xếp
if ($sort_by === 'likes') {
    $sqlC = "SELECT c.*, u.name AS author, u.avatar AS author_avatar,
            (SELECT COUNT(*) FROM comment_likes WHERE comment_id = c.id) as like_count
            FROM comments c JOIN users u ON u.id = c.user_id 
            WHERE c.post_id = ? 
            ORDER BY like_count " . ($order === 'desc' ? 'DESC' : 'ASC') . ", c.created_at ASC";
} else {
    $sqlC = "SELECT c.*, u.name AS author, u.avatar AS author_avatar 
            FROM comments c JOIN users u ON u.id = c.user_id 
            WHERE c.post_id = ? 
            ORDER BY c.created_at " . ($order === 'desc' ? 'DESC' : 'ASC');
}

$stmtC2 = $conn->prepare($sqlC);
$stmtC2->bind_param("i", $post_id);
$stmtC2->execute();
$comments = $stmtC2->get_result();

// Hàm đếm số like của bình luận
function getCommentLikes($conn, $comment_id) {
    $sql = "SELECT COUNT(*) as total FROM comment_likes WHERE comment_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $comment_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc()['total'];
}

// Hàm kiểm tra user đã like bình luận chưa
function hasUserLikedComment($conn, $comment_id, $user_id) {
    $sql = "SELECT id FROM comment_likes WHERE comment_id = ? AND user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $comment_id, $user_id);
    $stmt->execute();
    return $stmt->get_result()->num_rows > 0;
}

// Hàm lấy thông tin bình luận cha
function getParentComment($conn, $parent_id) {
    $sql = "SELECT c.id, c.content, u.name FROM comments c 
            JOIN users u ON u.id = c.user_id 
            WHERE c.id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $parent_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}
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
            <!-- Menu sắp xếp dropdown -->
            <div style="margin-bottom:15px; padding:10px; background:#f0f2f5; border-radius:8px; display:flex; gap:15px; align-items:center; font-size:0.9em;">
                <strong>Sắp xếp theo:</strong>
                <select id="sort-by" onchange="updateSort()" style="padding:8px 12px; border-radius:5px; border:1px solid #ddd; background:#fff; cursor:pointer; font-size:0.9em;">
                    <option value="time" <?= $sort_by === 'time' ? 'selected' : '' ?>>Thời gian</option>
                    <option value="likes" <?= $sort_by === 'likes' ? 'selected' : '' ?>>Số tim</option>
                </select>
                <select id="order" onchange="updateSort()" style="padding:8px 12px; border-radius:5px; border:1px solid #ddd; background:#fff; cursor:pointer; font-size:0.9em;">
                    <option value="asc" <?= $order === 'asc' ? 'selected' : '' ?>>Tăng dần</option>
                    <option value="desc" <?= $order === 'desc' ? 'selected' : '' ?>>Giảm dần</option>
                </select>
            </div>
            <script>
            function updateSort() {
                const sortBy = document.getElementById('sort-by').value;
                const order = document.getElementById('order').value;
                window.location.href = 'forum_view.php?id=<?= $post_id ?>&sort=' + sortBy + '&order=' + order;
            }
            </script>
            <?php while ($c = $comments->fetch_assoc()):
                 $cid = $c['id'];  ?>
                <div class="fb-comment" id="comment-<?= $c['id'] ?>">
                    <a href="user_profile.php?id=<?= $c['user_id'] ?>">
                        <img class="avatar" src="<?= htmlspecialchars($c['author_avatar'] ?? 'uploads/avatar/default.png') ?>" alt="Avatar">
                    </a>

                    <div class="content">
                        <strong>
                            <a href="user_profile.php?id=<?= $c['user_id'] ?>"
                            style="color:black; text-decoration:none;">
                            <?= htmlspecialchars($c['author']) ?>
                            </a>
                        </strong>

                        <span style="font-size:0.8em; color:#65676b;"> • <?= formatDateVN($c['created_at']) ?></span>
                        
                        <!-- Hiển thị bình luận gốc nếu là trả lời -->
                        <?php if ($c['is_reply']): 
                            if ($c['parent_comment_id']) {
                                $parent = getParentComment($conn, $c['parent_comment_id']);
                            } else {
                                $parent = null;
                            }
                        ?>
                            <?php if ($parent): ?>
                                <div style="background:#f0f2f5; border-left:4px solid #1877f2; padding:8px 12px; margin:8px 0; border-radius:4px; font-size:0.85em;">
                                    <strong style="color:#1877f2;">                                       
                                        <a href="#comment-<?= (int)$parent['id'] ?>"
                                        style="color:#1877f2; text-decoration:underline;"
                                        class="jump-to-parent">
                                        Trả lời: <?= htmlspecialchars($parent['name']) ?>
                                        </a>
                                    </strong>

                                    <p style="margin:5px 0 0 0; color:#555;">
                                        <?= htmlspecialchars(substr($parent['content'], 0, 100)) ?>
                                        <?= strlen($parent['content']) > 100 ? '...' : '' ?>
                                    </p>

                                    <?php
                                        // kiểm tra ảnh của bình luận gốc
                                        $parent_img = $conn->query("SELECT image_path FROM comment_images WHERE comment_id = " . (int)$parent['id'])->fetch_assoc();
                                        if (!empty($parent_img['image_path'])):
                                    ?>
                                        <div style="margin:5px 0 0 0; color:#999; font-style:italic;">
                                            Tệp đính kèm
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php else: ?>
                                <div style="background:#f5f5f5; border-left:4px solid #999; padding:8px 12px; margin:8px 0; border-radius:4px; font-size:0.85em;">
                                    <strong style="color:#999;">Bình luận gốc đã bị xóa</strong>
                                    <p style="margin:5px 0 0 0; color:#999; font-style:italic;">Nội dung không còn tồn tại</p>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                        
                        <p class="cmt-content" id="cmt_content_<?= $cid ?>"><?= htmlspecialchars($c['content']) ?></p>

                            <div class="cmt-edit-inline" id="edit_box_<?= $cid ?>" style="display:none; margin-top:5px;width:98%;">
                                <textarea id="edit_text_<?= $cid ?>" rows="3" style="width:100%; padding:6px;resize: none"><?= htmlspecialchars($c['content']) ?></textarea>

                                <div style="margin-top:8px; display:flex; gap:8px; align-items:center;">
                                    <button type="button" class="edit-emoji-btn" data-cid="<?= $cid ?>" title="Chèn emoji" style="background:none; border:1px solid #e6e6e6; padding:6px 8px; border-radius:8px; cursor:pointer; font-size:18px;">😃</button>
                                    <button onclick="saveComment(<?= $cid ?>)" style="margin-left:auto; padding:6px 10px; border-radius:6px; border:none; background:#28a745; color:#fff; cursor:pointer;">💾 Lưu</button>
                                    <button onclick="cancelEdit(<?= $cid ?>)" style="padding:6px 10px; border-radius:6px; border:1px solid #ccc; background:#fff; cursor:pointer;">Hủy</button>
                                </div>
                            </div>


                        <?php
                        $cid = $c['id'];
                        $img = $conn->query("SELECT image_path FROM comment_images WHERE comment_id = $cid")->fetch_assoc();
                        if (!empty($img['image_path'])): ?>
                            <img src="<?= $img['image_path'] ?>" style="max-width:100%; margin-top:5px; border-radius:8px;">
                        <?php endif; ?>

                        <?php if (isset($_SESSION['user'])): ?>
                            <?php 
                                $isCommentAuthor = $_SESSION['user']['id'] == $c['user_id'];
                                $commentLikes = getCommentLikes($conn, $cid);
                                $userLikedComment = hasUserLikedComment($conn, $cid, $_SESSION['user']['id']);
                            ?>
                            <div style="margin-top:5px;">
                                <!-- Nút Like bình luận -->
                                <a href="javascript:void(0)" onclick="likeComment(<?= $cid ?>)" 
                                id="like-btn-<?= $cid ?>" 
                                style="font-size:0.8em; color:#1877f2; margin-right:10px; text-decoration:none;">
                                <?= $userLikedComment ? '❤️' : '🤍' ?> <span id="like-count-<?= $cid ?>"><?= $commentLikes ?></span>
                                </a>
                                
                                <!-- Nút Trả lời -->
                                <a href="javascript:void(0)" onclick="replyComment(<?= $cid ?>, '<?= htmlspecialchars(addslashes($c['author'])) ?>')" 
                                style="font-size:0.8em; color:#1877f2; margin-right:10px;">
                                Trả lời
                                </a>

                                <!-- Chỉ chủ bình luận mới được sửa -->
                                <?php if ($isCommentAuthor): ?>
                                    <a href="javascript:void(0)" onclick="editComment(<?= $cid ?>)" 
                                    style="font-size:0.8em; color:#1877f2; margin-right:10px;">
                                    Sửa
                                    </a>

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
<form class="fb-comment-form" method="post" enctype="multipart/form-data" style="display:flex; gap:10px; align-items:flex-start;">
    <img class="avatar" src="<?= htmlspecialchars($_SESSION['user']['avatar'] ?? 'uploads/avatar/default.png') ?>" alt="Avatar" style="width:40px; height:40px; border-radius:50%;">

    <div style="flex:1; position:relative;">
        <div id="reply-quote" style="display:none; background:#f0f2f5; border-left:4px solid #1877f2; padding:8px 12px; margin-bottom:8px; border-radius:4px; font-size:0.9em;">
            <div style="display:flex; justify-content:space-between; align-items:center;">
                <strong style="color:#1877f2;">Trả lời: <span id="reply-author"></span></strong>
                <a href="javascript:void(0)" onclick="cancelReply()" style="color:#65676b; cursor:pointer; font-size:1.2em;">✕</a>
            </div>
            <div style="margin-top:5px; color:#555; font-style:italic;" id="reply-content"></div>
        </div>

        <textarea id="mainCommentInput" name="content" rows="2" placeholder="Viết bình luận..." onkeydown="handleCommentKeypress(event)"
                  style="width:100%; padding:10px; border:1px solid #dcdfe6; border-radius:8px; resize:vertical; min-height:56px;"></textarea>


                <!-- preview container (ẩn mặc định) -->
                <div id="commentImagePreviewWrap" style="display:none; margin-top:8px; align-items:center; gap:8px;">
                    <img id="commentImagePreview" src="" alt="Preview" style="max-width:120px; max-height:90px; border-radius:8px; border:1px solid #e6e6e6; object-fit:cover;">
                    <button type="button" id="removeCommentImageBtn" style="background:#fff; border:1px solid #d9534f; color:#d9534f; padding:6px 8px; border-radius:8px; cursor:pointer;">Xóa</button>
                </div>
                  <!-- controls ở phải: đặt position relative ở cha để emojiPicker căn theo -->
        <div class="controls" style="display:flex; justify-content:space-between; align-items:flex-start; margin-top:8px; position:relative;">
            <div style="flex:1;"></div>

            <div style="display:flex; flex-direction:column; gap:8px; align-items:flex-end;">
                <!-- emoji picker container (ẩn mặc định) -->
                <div id="emojiPicker" class="emoji-picker-container" style="display:none; position:absolute; z-index:1001;"></div>

                <!-- nút và input file (theo hàng dọc) -->
                <div style="display:flex; gap:8px; align-items:center;">
                    <button type="button" id="emojiToggleBtn" class="emoji-toggle-btn" title="Chèn biểu tượng cảm xúc" aria-label="Emoji"
                            style="background:none; border:1px solid #e6e6e6; padding:6px 8px; border-radius:8px; cursor:pointer; font-size:18px;">😃</button>

                    <!-- IMAGE INPUT + PREVIEW -->
                    <label id="fileLabel" title="Đính kèm ảnh" style="cursor:pointer; border:1px solid #e6e6e6; padding:6px 8px; border-radius:8px; display:flex; align-items:center; gap:8px;">
                        🖼️
                        <input id="commentImageInput" type="file" name="comment_image" accept="image/*" style="display:none;">
                    </label>

                    

                    <button type="submit" name="comment" id="sendCommentBtn" class="send-btn" style="padding:8px 14px; border-radius:18px; border:none; background:#1877f2; color:#fff; cursor:pointer;">
                        Gửi
                    </button>
                </div>
            </div>
        </div>
        <input type="hidden" id="parent-comment-id" name="parent_comment_id" value="">
    </div>
</form>
<?php else: ?>
    <p style="color:#65676b;">Bạn cần <a href="auth.php">đăng nhập</a> để bình luận.</p>
<?php endif; ?>


        </div>
    </div>
    </div>
</div>

<style>
/* emoji picker */
.emoji-picker-container {
    background:#fff;
    border:1px solid #ddd;
    box-shadow:0 6px 18px rgba(0,0,0,0.08);
    border-radius:8px;
    width:350px;
    padding:2px;
}
.emoji-grid {
    display:grid;
    grid-template-columns:repeat(8, 1fr);
    gap:6px;
    max-height:220px;
    overflow:auto;
}
.emoji-item {
    cursor:pointer;
    font-size:18px;
    text-align:center;
    padding:6px;
    border-radius:6px;
}
.emoji-item:hover { background:#f0f2f5; }
.fb-comment.highlight {
    outline: 2px solid -webkit-focus-ring-color;
    outline-offset: 2px;
}

</style>

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
<script>
// Bật chế độ sửa
function editComment(id) {
    document.getElementById("cmt_content_" + id).style.display = "none";
    document.getElementById("edit_box_" + id).style.display = "block";
}

// Hủy sửa
function cancelEdit(id) {
    document.getElementById("edit_box_" + id).style.display = "none";
    document.getElementById("cmt_content_" + id).style.display = "block";
}

// Lưu comment qua AJAX
function saveComment(id) {
    const newContent = document.getElementById("edit_text_" + id).value.trim();
    if (newContent === "") {
        alert("Nội dung không được để trống.");
        return;
    }

    fetch("comment_edit.php", {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: "id=" + id + "&content=" + encodeURIComponent(newContent)
    })
    .then(r => r.json())
    .then(d => {
        if (d.error) {
            alert("Lỗi: " + d.error);
            return;
        }

        document.getElementById("cmt_content_" + id).innerHTML = d.content;
        cancelEdit(id);
    });
}

// Trả lời bình luận
function replyComment(commentId, authorName) {
    // Lấy nội dung bình luận qua AJAX
    fetch("forum_view.php?action=get_comment&cid=" + commentId + "&id=<?= $post_id ?>")
    .then(r => r.json())
    .then(d => {
        if (d.error) {
            alert("Lỗi: " + d.error);
            return;
        }
        
        // Hiển thị khung quote
        document.getElementById("reply-quote").style.display = "block";
        document.getElementById("reply-author").textContent = authorName;
        document.getElementById("reply-content").textContent = d.content.substring(0, 100) + (d.content.length > 100 ? "..." : "");
        document.getElementById("parent-comment-id").value = commentId;
        
        // Focus vào textarea
        const textarea = document.querySelector('.fb-comment-form textarea');
        textarea.focus();
        textarea.scrollIntoView({ behavior: "smooth" });
    });
}

// Hủy trả lời
function cancelReply() {
    document.getElementById("reply-quote").style.display = "none";
    document.getElementById("reply-author").textContent = "";
    document.getElementById("reply-content").textContent = "";
    document.getElementById("parent-comment-id").value = "";
}

// Xử lý phím tắt Shift+Enter để gửi bình luận
function handleCommentKeypress(event) {
    if (event.key === 'Enter' && event.shiftKey) {
        event.preventDefault();
        // Tìm form chứa textarea này
        const form = event.target.closest('form');
        if (form) {
            // Kích hoạt nút submit
            const submitBtn = form.querySelector('button[type="submit"]');
            if (submitBtn) {
                submitBtn.click();
            }
        }
        return false;
    }
}

// Like bình luận
function likeComment(commentId) {
    fetch("api/comment_like.php", {
        method: "POST",
        headers: {"Content-Type": "application/x-www-form-urlencoded"},
        body: "comment_id=" + commentId
    })
    .then(r => r.json())
    .then(d => {
        if (d.error === "not_logged_in") {
            alert("Bạn cần đăng nhập để like.");
            return;
        }
        
        const btn = document.getElementById("like-btn-" + commentId);
        const countSpan = document.getElementById("like-count-" + commentId);
        
        if (d.status === "liked") {
            btn.innerHTML = '❤️ <span id="like-count-' + commentId + '">' + d.likes + '</span>';
        } else {
            btn.innerHTML = '🤍 <span id="like-count-' + commentId + '">' + d.likes + '</span>';
        }
    });
}

// Highlight comment từ fragment URL
(function() {
    function applyHighlightFromHash() {
        var hash = location.hash;
        if (!hash) return;
        var m = hash.match(/^#comment-(\d+)$/);
        if (!m) return;
        var targetId = 'comment-' + m[1];

        var tries = 0;
        var maxTries = 60;
        var interval = setInterval(function() {
            var el = document.getElementById(targetId);
            tries++;
            if (el || tries >= maxTries) {
                clearInterval(interval);
                if (!el) return;

                // Cuộn đến giữa màn hình
                el.scrollIntoView({ behavior: 'smooth', block: 'center' });

                // Thêm class highlight
                el.classList.add('highlight');

                // Xóa fragment khỏi URL ngay sau khi đã cuộn (để reload sẽ không có #)
                try {
                    var newUrl = window.location.pathname + window.location.search;
                    history.replaceState(null, '', newUrl);
                } catch (e) {
                    // nếu browser không hỗ trợ, bỏ qua
                }

                // Bỏ highlight sau 4s
                setTimeout(function(){ el.classList.remove('highlight'); }, 4000);

                // focus hỗ trợ bàn phím/ARIA
                try { el.setAttribute('tabindex','-1'); el.focus({ preventScroll:true }); } catch(e){}
            }
        }, 100);
    }

    if (document.readyState === 'complete' || document.readyState === 'interactive') {
        applyHighlightFromHash();
    } else {
        document.addEventListener('DOMContentLoaded', applyHighlightFromHash);
    }

    window.addEventListener('hashchange', applyHighlightFromHash);
})();

document.addEventListener('DOMContentLoaded', function() {
    const commonEmojis = ["😀","😁","😂","🤣","😎","😍","🤔","😡","😭","👍","🔥","💯","🤯","🤝","🎮","😉","🙂","🙃","😅","🙏","👏","🤩","😴","🤖","🎉"];

    const picker = document.getElementById('emojiPicker');
    const btn = document.getElementById('emojiToggleBtn');
    const ta = document.getElementById('mainCommentInput');

    if (!picker || !btn || !ta) return;

    // ensure picker is direct child of body to avoid parent overflow issues
    if (picker.parentElement !== document.body) {
        document.body.appendChild(picker);
    }

    // style safety
    picker.style.position = 'fixed';
    picker.style.zIndex = 99999;
    picker.style.display = 'none';

    // render emoji grid
    function renderEmojiGrid() {
        picker.innerHTML = '';
        const grid = document.createElement('div');
        grid.className = 'emoji-grid';
        commonEmojis.forEach(e => {
            const d = document.createElement('div');
            d.className = 'emoji-item';
            d.textContent = e;
            d.addEventListener('click', (ev) => {
                ev.stopPropagation();
                insertEmojiAtCursor(e);
                hideEmojiPicker();
                ta.focus();
            });
            grid.appendChild(d);
        });
        picker.appendChild(grid);
    }

    function insertEmojiAtCursor(emoji) {
        const start = ta.selectionStart ?? ta.value.length;
        const end = ta.selectionEnd ?? ta.value.length;
        const before = ta.value.slice(0, start);
        const after = ta.value.slice(end);
        ta.value = before + emoji + after;
        const pos = start + emoji.length;
        ta.selectionStart = ta.selectionEnd = pos;
        ta.dispatchEvent(new Event('input'));
    }

    function showEmojiPicker() {
        renderEmojiGrid();
        // đo kích thước picker sau khi render (display:block tạm)
        picker.style.display = 'block';
        picker.style.left = '0px'; picker.style.top = '0px';
        const pickerRect = picker.getBoundingClientRect();
        const btnRect = btn.getBoundingClientRect();

        // right-align trên nút
        let left = btnRect.right - pickerRect.width;
        if (left < 8) left = Math.max(8, btnRect.left); // tránh trôi sang trái quá
        // muốn hiển thị trên nút
        let top = btnRect.top - pickerRect.height - 8;
        // nếu không đủ chỗ trên viewport, show phía dưới nút
        if (top < 8) top = btnRect.bottom + 8;

        // đặt tọa độ fixed (viewport) — không cần scroll offset vì fixed
        picker.style.left = Math.round(left) + 'px';
        picker.style.top = Math.round(top) + 'px';
        picker.style.display = 'block';
    }

    function hideEmojiPicker() {
        picker.style.display = 'none';
    }

    function toggleEmojiPicker(e) {
        e.stopPropagation();
        if (picker.style.display === 'block') hideEmojiPicker();
        else showEmojiPicker();
    }

    btn.addEventListener('click', toggleEmojiPicker);

    // đóng khi click ngoài (body)
    document.addEventListener('click', function(ev) {
        if (!picker.contains(ev.target) && !btn.contains(ev.target)) hideEmojiPicker();
    });

    // tránh đóng khi click trong picker
    picker.addEventListener('click', function(ev){ ev.stopPropagation(); });

    // init once
    renderEmojiGrid();
});
// Image preview + remove
document.addEventListener('DOMContentLoaded', function() {
    const fileInput = document.getElementById('commentImageInput');
    const previewWrap = document.getElementById('commentImagePreviewWrap');
    const previewImg = document.getElementById('commentImagePreview');
    const removeBtn = document.getElementById('removeCommentImageBtn');
    const fileLabel = document.getElementById('fileLabel');

    let currentObjectUrl = null;

    // khi click label => mở file dialog
    fileLabel.addEventListener('click', function(e) {
        // allow clicking label itself to open input
        fileInput.click();
    });

    // file changed
    fileInput.addEventListener('change', function(e) {
        const f = fileInput.files && fileInput.files[0];
        if (!f) {
            hidePreview();
            return;
        }
        // chỉ chấp nhận ảnh
        if (!f.type.startsWith('image/')) {
            alert('Vui lòng chọn file hình ảnh.');
            fileInput.value = '';
            hidePreview();
            return;
        }

        // giải phóng object url cũ
        if (currentObjectUrl) {
            URL.revokeObjectURL(currentObjectUrl);
            currentObjectUrl = null;
        }

        currentObjectUrl = URL.createObjectURL(f);
        previewImg.src = currentObjectUrl;
        previewWrap.style.display = 'flex';
    });

    // xóa ảnh đã chọn
    removeBtn.addEventListener('click', function() {
        fileInput.value = ''; // clear file input
        if (currentObjectUrl) {
            URL.revokeObjectURL(currentObjectUrl);
            currentObjectUrl = null;
        }
        hidePreview();
    });

    function hidePreview() {
        previewImg.src = '';
        previewWrap.style.display = 'none';
    }

    // khi submit form, nếu muốn có validate kích thước / loại có thể thêm ở đây
    // ví dụ: fileInput.files[0].size ...
});

// ---------- Hỗ trợ emoji chung (danh sách nhỏ) ----------
const simpleEmojiList = ["😀","😁","😂","🤣","😎","😍","🤔","😡","😭","👍","🔥","💯","🤯","🎉"];

// Mở hộp sửa
function editComment(id) {
    // ẩn nội dung hiển thị, hiện khung edit
    document.getElementById("cmt_content_" + id).style.display = "none";
    const editBox = document.getElementById("edit_box_" + id);
    editBox.style.display = "block";

    // emoji button behavior
    const emojiBtn = editBox.querySelector('.edit-emoji-btn');
    if (emojiBtn && !emojiBtn._hasListener) {
        emojiBtn.addEventListener('click', function(e){
            e.stopPropagation();
            const cid = this.dataset.cid;
            openSmallEmojiPickerForEdit(cid, this);
        });
        emojiBtn._hasListener = true;
    }
}

// Hủy sửa (giữ nguyên nội dung hiển thị)
function cancelEdit(id) {
    const editBox = document.getElementById("edit_box_" + id);
    if (editBox) {
        editBox.style.display = "none";
        editBox._removeImage = false;
    }
    const contentEl = document.getElementById("cmt_content_" + id);
    if (contentEl) contentEl.style.display = "block";
}

// save via AJAX (FormData) - sẽ gửi file nếu có
function saveComment(id) {
    const textarea = document.getElementById('edit_text_' + id);
    if (!textarea) return alert('Không tìm thấy vùng nhập.');
    const content = textarea.value.trim();
    const editBox = document.getElementById('edit_box_' + id);
    const fileInput = document.getElementById('editImageInput_' + id);

    if (content === '' && (!fileInput || !fileInput.files || fileInput.files.length === 0) && !editBox._removeImage) {
        return alert('Nội dung không được để trống.');
    }

    const fd = new FormData();
    fd.append('id', id);
    fd.append('content', content);
    // nếu user đã chọn file
    if (fileInput && fileInput.files && fileInput.files[0]) {
        fd.append('comment_image', fileInput.files[0]);
    }
    // nếu user đã bấm xóa ảnh cũ
    if (editBox._removeImage) {
        fd.append('remove_image', '1');
    }

    fetch('comment_edit.php', {
        method: 'POST',
        body: fd
    })
    .then(r => r.json())
    .then(d => {
        if (d.error) return alert('Lỗi: ' + (d.error || 'Không xác định'));
        // Cập nhật nội dung hiển thị
        const contentEl = document.getElementById('cmt_content_' + id);
        if (contentEl) {
            // server trả về d.content (đã escape/format nếu cần)
            contentEl.innerHTML = d.content_html ?? d.content ?? content;
            contentEl.style.display = 'block';
        }

        // cập nhật/hiển thị ảnh trong comment nếu server trả về image_path
        if (d.image_path) {
            // nếu đã có img thì thay src, ngược lại chèn img mới
            const commentEl = document.getElementById('comment-' + id);
            if (commentEl) {
                let imgEl = commentEl.querySelector('.comment-img-auto');
                if (!imgEl) {
                    imgEl = document.createElement('img');
                    imgEl.className = 'comment-img-auto';
                    imgEl.style.maxWidth = '100%';
                    imgEl.style.marginTop = '5px';
                    imgEl.style.borderRadius = '8px';
                    commentEl.querySelector('.content').appendChild(imgEl);
                }
                imgEl.src = d.image_path;
            }
        } else if (d.removed_image) {
            // server xác nhận ảnh đã bị xóa -> remove img element nếu có
            const commentEl = document.getElementById('comment-' + id);
            if (commentEl) {
                const imgEl = commentEl.querySelector('.comment-img-auto');
                if (imgEl) imgEl.remove();
            }
        }

        // đóng edit box
        editBox.style.display = 'none';
        // revoke objectURL nếu có
        if (fileInput && fileInput._objectUrl) {
            URL.revokeObjectURL(fileInput._objectUrl);
            fileInput._objectUrl = null;
        }
        editBox._removeImage = false;
    })
    .catch(err => {
        console.error(err);
        alert('Lỗi khi lưu bình luận.');
    });
}

// ---------- Emoji picker nhỏ cho edit (đơn giản, dùng 1 popup tạm) ----------
function openSmallEmojiPickerForEdit(commentId, btnEl) {
    // tạo hộp emoji tạm nếu chưa có
    let popup = document.getElementById('emojiPopupForEdit');
    if (!popup) {
        popup = document.createElement('div');
        popup.id = 'emojiPopupForEdit';
        popup.style.position = 'fixed';
        popup.style.background = '#fff';
        popup.style.border = '1px solid #ddd';
        popup.style.boxShadow = '0 6px 18px rgba(0,0,0,0.08)';
        popup.style.borderRadius = '8px';
        popup.style.padding = '8px';
        popup.style.zIndex = 999999;
        document.body.appendChild(popup);
        // close on outside click
        document.addEventListener('click', function(ev){
            if (!popup.contains(ev.target) && ev.target !== btnEl) popup.style.display = 'none';
        });
    }

    // fill
    popup.innerHTML = '';
    const grid = document.createElement('div');
    grid.style.display = 'grid';
    grid.style.gridTemplateColumns = 'repeat(8, 1fr)';
    grid.style.gap = '6px';
    simpleEmojiList.forEach(e => {
        const d = document.createElement('div');
        d.textContent = e;
        d.style.cursor = 'pointer';
        d.style.fontSize = '18px';
        d.style.textAlign = 'center';
        d.style.padding = '6px';
        d.style.borderRadius = '6px';
        d.addEventListener('click', function(ev){
            ev.stopPropagation();
            insertEmojiToEdit(commentId, e);
            popup.style.display = 'none';
        });
        grid.appendChild(d);
    });
    popup.appendChild(grid);

    // position above the button if possible
    const rect = btnEl.getBoundingClientRect();
    const popupRectEstimateWidth = 260;
    let left = rect.right - popupRectEstimateWidth;
    if (left < 8) left = rect.left;
    let top = rect.top - 8 - 220;
    if (top < 8) top = rect.bottom + 8;
    popup.style.left = Math.round(left) + 'px';
    popup.style.top = Math.round(top) + 'px';
    popup.style.display = 'block';
}

function insertEmojiToEdit(cid, emoji) {
    const ta = document.getElementById('edit_text_' + cid);
    if (!ta) return;
    const start = ta.selectionStart ?? ta.value.length;
    const end = ta.selectionEnd ?? ta.value.length;
    const before = ta.value.slice(0, start);
    const after = ta.value.slice(end);
    ta.value = before + emoji + after;
    const pos = start + emoji.length;
    ta.selectionStart = ta.selectionEnd = pos;
    ta.focus();
}
</script>

<?php include 'includes/footer.php'; ?>
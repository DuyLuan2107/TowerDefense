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
                                    <strong style="color:#1877f2;">Trả lời: <?= htmlspecialchars($parent['name']) ?></strong>
                                    <p style="margin:5px 0 0 0; color:#555;"><?= htmlspecialchars(substr($parent['content'], 0, 100)) ?><?= strlen($parent['content']) > 100 ? '...' : '' ?></p>
                                </div>
                            <?php else: ?>
                                <div style="background:#f5f5f5; border-left:4px solid #999; padding:8px 12px; margin:8px 0; border-radius:4px; font-size:0.85em;">
                                    <strong style="color:#999;">Trả lời: Bình luận gốc đã bị xóa</strong>
                                    <p style="margin:5px 0 0 0; color:#999; font-style:italic;">Nội dung không còn tồn tại</p>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                        
                        <p class="cmt-content" id="cmt_content_<?= $cid ?>"><?= htmlspecialchars($c['content']) ?></p>

                            <div class="cmt-edit-inline" id="edit_box_<?= $cid ?>" style="display:none; margin-top:10px;">
                                <div class="editor-box">
                                    <div class="editor-toolbar">
                                        <button type="button" class="tool-btn" data-cmd="bold" data-target="editEditor_<?= $cid ?>"><b>B</b></button>
                                        <button type="button" class="tool-btn" data-cmd="italic" data-target="editEditor_<?= $cid ?>"><i>I</i></button>
                                        <button type="button" class="tool-btn" data-cmd="underline" data-target="editEditor_<?= $cid ?>"><u>U</u></button>
                                        <button type="button" class="tool-btn emojiBtn" data-target="editEditor_<?= $cid ?>">😊</button>
                                    </div>

                                    <!-- Ô contenteditable -->
                                    <div id="editEditor_<?= $cid ?>" 
                                        class="editor-area edit-editor" 
                                        contenteditable="true"><?= htmlspecialchars($c['content']) ?></div>
                                </div>

                                <div style="margin-top:5px;">
                                    <button onclick="saveComment(<?= $cid ?>)" class="send-btn">💾 Lưu</button>
                                    <button onclick="cancelEdit(<?= $cid ?>)" class="send-btn" style="background:#777;">Hủy</button>
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
    <form class="fb-comment-form" method="post" enctype="multipart/form-data">
    <img class="avatar" 
         src="<?= htmlspecialchars($_SESSION['user']['avatar'] ?? 'uploads/avatar/default.png') ?>" 
         alt="Avatar">

    <div class="comment-editor-container">

        <!-- Quote -->
        <div id="reply-quote" class="reply-quote">
            <div class="reply-header">
                <strong>Trả lời: <span id="reply-author"></span></strong>
                <span class="reply-close" onclick="cancelReply()">✕</span>
            </div>
            <div class="reply-text" id="reply-content"></div>
        </div>

        <!-- COMMENT EDITOR (Giống editor bài viết) -->
        <div class="editor-box">
            <div class="editor-toolbar">
                <button type="button" class="tool-btn" data-cmd="bold"><b>B</b></button>
                <button type="button" class="tool-btn" data-cmd="italic"><i>I</i></button>
                <button type="button" class="tool-btn" data-cmd="underline"><u>U</u></button>

                <!-- emoji -->
                <button type="button" class="tool-btn emojiBtn" data-target="commentEditor">😊</button>
            </div>

            <div id="commentEditor" class="editor-area" contenteditable="true"
                 placeholder="Viết bình luận..."></div>
        </div>

        <!-- Hidden để submit text -->
        <input type="hidden" name="content" id="comment-content-hidden">

        <!-- Chọn file + gửi -->
        <div class="controls">
    <input type="file" name="comment_image" accept="image/*">
    <button type="submit" name="comment">Gửi</button>
</div>


        <input type="hidden" id="parent-comment-id" name="parent_comment_id">
    </div>
</form>
<style>
    /* ---------- COMMENT EDITOR GIỐNG CREATE POST ---------- */
.fb-comment-form .controls {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.fb-comment-form .controls button {
    padding: 6px 15px;
    border-radius: 18px;
    border: none;
    background-color: #1877f2;
    color: #fff;
    cursor: pointer;
    font-weight: 500;
    transition: background 0.2s;
}

.fb-comment-form .controls button:hover {
    background-color: #145dbf;
}

.fb-comment-form {
    display: flex;
    gap: 10px;
    margin-top: 15px;
}

.fb-comment-form .avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
}

.comment-editor-container {
    flex: 1;
}

/* Reply quote */
.reply-quote {
    display: none;
    background: #f0f2f5;
    border-left: 4px solid #1877f2;
    padding: 8px 12px;
    margin-bottom: 10px;
    border-radius: 6px;
    font-size: 0.9em;
}
.reply-header {
    display: flex;
    justify-content: space-between;
}
.reply-close {
    cursor: pointer;
    font-size: 18px;
}

/* Editor box (giống create-post) */
.editor-box {
    border: 1px solid #cbd5e1;
    background: #ffffff;
    border-radius: 10px;
    margin-bottom: 10px;
    overflow: hidden;
}

.editor-toolbar {
    background: #f8fafc;
    border-bottom: 1px solid #e2e8f0;
    padding: 8px;
    display: flex;
    gap: 8px;
}

.tool-btn {
    background: none;
    border: none;
    cursor: pointer;
    padding: 6px 10px;
    border-radius: 6px;
}

.editor-area {
    min-height: 80px;
    outline: none;
    padding: 12px 14px;
    line-height: 1.4;
    font-size: 15px;
}

/* Placeholder contenteditable */
.editor-area:empty:before {
    content: attr(placeholder);
    color: #94a3b8;
    pointer-events: none;
}

/* Controls */
.comment-controls {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.file-btn {
    font-size: 20px;
    cursor: pointer;
}
.file-btn input {
    display: none;
}

.send-btn {
    background: #1877f2;
    color: #fff;
    border: none;
    padding: 7px 14px;
    border-radius: 8px;
    cursor: pointer;
}
.send-btn:hover {
    background: #145ecc;
}

/* Emoji panel */
#emojiPanel {
    position: fixed;
    background: #fff;
    border: 1px solid #cbd5e1;
    padding: 8px;
    border-radius: 8px;
    display: none;
    flex-wrap: wrap;
    gap: 6px;
    width: 220px;
    z-index: 9999;
}
#emojiPanel span {
    font-size: 20px;
    padding: 6px;
    cursor: pointer;
    border-radius: 6px;
}
#emojiPanel span:hover {
    background: #e2e8f0;
}
.edit-editor {
    min-height: 70px;
    padding: 10px;
    font-size: 15px;
    border: none;
    outline: none;
}


</style>
<!-- Emoji panel dùng chung -->
<div id="emojiPanel"></div>

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
<script>
// Bật chế độ sửa
function editComment(id) {
    const ed = document.getElementById("editEditor_" + id);
editors["editEditor_" + id] = ed;

// Lưu caret mặc định (cuối nội dung)
setTimeout(() => saveCaretFor(ed), 50);
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
    const newContent = document.getElementById("editEditor_" + id).innerHTML.trim();

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

        // cập nhật vào giao diện
        document.getElementById("cmt_content_" + id).innerHTML = d.content;

        // tắt khung sửa
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


// map editors by id
const editors = {
    commentEditor: document.getElementById("commentEditor"),
    titleEditor: document.getElementById("titleEditor"),      // nếu có
    contentEditor: document.getElementById("contentEditor")   // nếu có
};

// saved range per editor id (store cloned ranges)
const savedRanges = {};

// helper: save current caret range for an editor (cloneRange)
function saveCaretFor(editor) {
    try {
        const sel = window.getSelection();
        if (!sel || sel.rangeCount === 0) return;
        const range = sel.getRangeAt(0).cloneRange();
        savedRanges[editor.id] = range;
    } catch (e) {
        // ignore
    }
}

// helper: restore saved caret for editor (use cloneRange to avoid mutations)
function restoreCaretFor(editor) {
    try {
        const range = savedRanges[editor.id];
        if (!range) {
            // If no saved range, move caret to end
            editor.focus();
            const r = document.createRange();
            r.selectNodeContents(editor);
            r.collapse(false);
            const sel = window.getSelection();
            sel.removeAllRanges();
            sel.addRange(r);
            return;
        }
        editor.focus();
        const sel = window.getSelection();
        sel.removeAllRanges();
        sel.addRange(range.cloneRange());
    } catch (e) {
        // ignore
    }
}

// Attach events to maintain "active editor" and save caret frequently
Object.values(editors).forEach(ed => {
    if (!ed) return;
    // Save on user actions inside editor
    ed.addEventListener('keyup', () => saveCaretFor(ed));
    ed.addEventListener('mouseup', () => saveCaretFor(ed));
    ed.addEventListener('focus', () => saveCaretFor(ed));
    ed.addEventListener('click', () => saveCaretFor(ed));
    // Also save when user presses keys that move caret
    ed.addEventListener('keydown', (ev) => {
        // small delay for some browsers
        setTimeout(() => saveCaretFor(ed), 0);
    });
});

// Prevent toolbar buttons stealing focus: listen mousedown on toolbar buttons and save caret **before** click
document.querySelectorAll('.tool-btn, .emojiBtn').forEach(btn => {
    btn.addEventListener('mousedown', (ev) => {
        // find focused editor (where selection currently is)
        const sel = window.getSelection();
        if (sel && sel.rangeCount) {
            // try to detect editor containing caret
            const container = sel.getRangeAt(0).commonAncestorContainer;
            const editorEl = container.nodeType === 1 ? container.closest('.editor-area') : container.parentElement.closest('.editor-area');
            if (editorEl) saveCaretFor(editorEl);
        }
        // prevent the button from taking focus (optional)
        ev.preventDefault();
    });
});

// FORMAT buttons (B/I/U) using execCommand after restore
document.querySelectorAll('.tool-btn[data-cmd]').forEach(btn => {
    btn.addEventListener('click', (ev) => {
        // determine which editor we saved for (prefer active by savedRanges)
        // find editor which has savedRange recently
        let targetEditor = null;
        // If the button has data-target, prefer it (some toolbar buttons do)
        if (btn.dataset && btn.dataset.target) {
            targetEditor = document.getElementById(btn.dataset.target);
        } else {
            // fallback: choose any editor that has saved range
            for (let id in savedRanges) {
                if (savedRanges[id]) { targetEditor = document.getElementById(id); break; }
            }
        }
        if (!targetEditor) return;
        restoreCaretFor(targetEditor);
        document.execCommand(btn.dataset.cmd, false, null);
        // after executing, update saved range
        saveCaretFor(targetEditor);
    });
});

/* ============================
   EMOJI PANEL (insert at caret)
   ============================ */

const emojiPanel = document.getElementById("emojiPanel");
const emojis = ["😀","😁","😂","🤣","😎","😍","🤔","😡","😭","👍","🔥","💯","🤯","🤝","🎮"];

// render emojis
emojiPanel.innerHTML = "";
emojis.forEach(e => {
    const s = document.createElement('span');
    s.textContent = e;
    s.setAttribute('role','button');
    s.style.cursor = 'pointer';
    s.addEventListener('click', (ev) => {
        ev.stopPropagation();
        // try find the target editor by checking last savedRange keys
        // if emoji button had data-target, use that editor
        const targetId = emojiPanel.dataset.targetId;
        const targetEditor = targetId ? document.getElementById(targetId) : (document.getElementById('commentEditor') || null);
        if (targetEditor) {
            restoreCaretFor(targetEditor);
            // insert text at caret using execCommand (safe)
            document.execCommand('insertText', false, e);
            // update saved range
            saveCaretFor(targetEditor);
        }
        emojiPanel.style.display = 'none';
    });
    emojiPanel.appendChild(s);
});

// When clicking emojiBtn: save caret (handled by mousedown above), position panel and mark target
document.querySelectorAll('.emojiBtn').forEach(btn => {
    btn.addEventListener('click', (ev) => {
        ev.stopPropagation();
        const targetId = btn.dataset.target;
        emojiPanel.dataset.targetId = targetId || 'commentEditor';

        // position near button but ensure inside viewport
        const rect = btn.getBoundingClientRect();
        const left = Math.max(8, rect.left);
        let top = rect.bottom + 6;
        // if not enough space below, show above
        if (top + 260 > (window.innerHeight || document.documentElement.clientHeight)) {
            top = rect.top - 6 - 260; // approximate height
        }
        emojiPanel.style.left = `${left}px`;
        emojiPanel.style.top = `${top}px`;
        emojiPanel.style.display = 'flex';
    });
});

// click outside hide panel
document.addEventListener('click', () => { emojiPanel.style.display = 'none'; });

// prevent panel click from closing
emojiPanel.addEventListener('click', e => e.stopPropagation());

/* ============================
   SUBMIT COMMENT -> send HTML/plain
   ============================ */

const commentForm = document.querySelector('.fb-comment-form');
if (commentForm) {
    commentForm.addEventListener('submit', (ev) => {
        // get HTML from editor, but you might want plain text:
        const html = (document.getElementById('commentEditor') || { innerHTML: '' }).innerHTML.trim();
        document.getElementById('comment-content-hidden').value = html;
        // allow submit to continue
    });
}


</script>

<?php include 'includes/footer.php'; ?>
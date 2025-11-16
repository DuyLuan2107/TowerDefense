<?php
include "includes/header.php";
include "db/connect.php";

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user'])) {
    echo '
    <div class="profile-container">
        <h2>👤 Thông Tin Cá Nhân</h2>
        <div class="profile-message">
            <p>Bạn cần đăng nhập để xem thông tin cá nhân.</p>
            <a href="auth.php" class="btn-login">🔑 Đăng Nhập Ngay</a>
        </div>
    </div>';
    include "includes/footer.php";
    exit;
}

// User đang đăng nhập
$user = $_SESSION['user'];
$user_id = $user['id'];

$avatar = !empty($user['avatar']) ? $user['avatar'] : 'uploads/default.png';

/* MỞ FORM KHI CÓ LỖI HOẶC THÀNH CÔNG */
$forceOpen = isset($_GET['open']);
if (!empty($_SESSION['update_error']) || !empty($_SESSION['update_success'])) {
    $forceOpen = true;
}
?>

<div class="profile-container">

    <h2>👤 Thông Tin Cá Nhân</h2>

    <!-- CARD THÔNG TIN -->
    <div class="profile-card">
        <img src="<?= htmlspecialchars($avatar) ?>" class="profile-avatar">

        <div class="profile-info">
            <h3><?= htmlspecialchars($user['name']) ?></h3>
            <p><strong>Email:</strong> <?= htmlspecialchars($user['email']) ?></p>
            <p class="status">Trạng thái:
                <span class="online">Đang hoạt động</span>
            </p>

            <a href="logout.php" class="btn-logout">🚪 Đăng Xuất</a>
        </div>
    </div>

    <hr>

    <button class="btn-update-toggle" onclick="toggleUpdateForm()">⚙️ Cập nhật tài khoản</button>

    <!-- FORM CẬP NHẬT -->
    <div id="updateForm" class="update-section" style="display:<?= $forceOpen ? 'block' : 'none' ?>;">

        <?php if (!empty($_SESSION['update_error'])): ?>
            <div class="alert error">
                <?= $_SESSION['update_error']; unset($_SESSION['update_error']); ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($_SESSION['update_success'])): ?>
            <div class="alert success">
                <?= $_SESSION['update_success']; unset($_SESSION['update_success']); ?>
            </div>
        <?php endif; ?>

        <h3>🔧 Thay đổi thông tin</h3>

        <!-- Avatar -->
        <form action="update_profile.php" method="POST" enctype="multipart/form-data" class="update-box">
            <h4>🖼 Thay đổi Avatar</h4>
            <input type="file" name="avatar" required>
            <button type="submit" name="change_avatar">Cập Nhật Avatar</button>
        </form>

        <!-- Đổi tên -->
        <form action="update_profile.php" method="POST" class="update-box">
            <h4>✏️ Đổi tên ingame</h4>
            <input type="text" name="new_name" placeholder="Tên mới" required>
            <button type="submit" name="change_name">Cập Nhật Tên</button>
        </form>

        <!-- Đổi mật khẩu -->
        <form action="update_profile.php" method="POST" class="update-box">
            <h4>🔑 Đổi mật khẩu</h4>
            <input type="password" name="old_password" placeholder="Mật khẩu cũ" required>
            <input type="password" name="new_password" placeholder="Mật khẩu mới" required>
            <button type="submit" name="change_password">Đổi Mật Khẩu</button>
        </form>

    </div>

    <hr>

    <!-- BÀI VIẾT CỦA USER -->
    <?php
    $perPage = 5;
    $page = max(1, (int)($_GET['page'] ?? 1));
    $offset = ($page - 1) * $perPage;

    $stmtCount = $conn->prepare("SELECT COUNT(*) FROM posts WHERE user_id = ?");
    $stmtCount->bind_param("i", $user_id);
    $stmtCount->execute();
    $total = $stmtCount->get_result()->fetch_row()[0] ?? 0;

    $totalPages = max(1, ceil($total / $perPage));

    $stmt = $conn->prepare("
        SELECT p.*,
        (SELECT COUNT(*) FROM post_likes pl WHERE pl.post_id = p.id) AS like_count,
        (SELECT COUNT(*) FROM comments c WHERE c.post_id = p.id) AS comment_count
        FROM posts p
        WHERE p.user_id = ?
        ORDER BY p.created_at DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->bind_param("iii", $user_id, $perPage, $offset);
    $stmt->execute();
    $posts = $stmt->get_result();
    ?>

    <h3>📝 Bài viết của bạn</h3>

    <?php if ($posts->num_rows == 0): ?>
        <p class="muted">Bạn chưa đăng bài viết nào.</p>
    <?php else: ?>

        <div class="user-post-list">
        <?php while ($p = $posts->fetch_assoc()): ?>
            <div class="user-post-card">
                <div class="upc-title">
                    <a href="forum_view.php?id=<?= $p['id'] ?>">
                        <?= htmlspecialchars($p['title']) ?>
                    </a>
                </div>

                <div class="upc-meta">
                    <?= $p['created_at'] ?> • ❤️ <?= $p['like_count'] ?> • 💬 <?= $p['comment_count'] ?>
                </div>

                <div class="upc-actions">
                    <a href="forum_view.php?id=<?= $p['id'] ?>" class="btn-small">Xem</a>
                    <a href="forum_edit_post.php?id=<?= $p['id'] ?>" class="btn-small">Sửa</a>
                    <a href="forum_delete_post.php?id=<?= $p['id'] ?>" class="btn-small delete"
                    onclick="return confirm('Xóa bài viết này?');">Xóa</a>
                </div>
            </div>
        <?php endwhile; ?>
        </div>

        <?php if ($totalPages > 1): ?>
        <div class="pagination">
            <a class="<?= $page<=1 ? 'disabled' : '' ?>" href="<?= $page>1 ? '?page='.($page-1) : '#' ?>">«</a>

            <?php for ($p=1; $p<=$totalPages; $p++): ?>
                <a class="<?= $p==$page ? 'active' : '' ?>" href="?page=<?= $p ?>"><?= $p ?></a>
            <?php endfor; ?>

            <a class="<?= $page>=$totalPages ? 'disabled' : '' ?>" href="<?= $page<$totalPages ? '?page='.($page+1) : '#' ?>">»</a>
        </div>
        <?php endif; ?>

    <?php endif; ?>

</div>

<script>
function toggleUpdateForm() {
    let f = document.getElementById("updateForm");
    f.style.display = f.style.display === "none" ? "block" : "none";
}
</script>

<!-- =================== CSS =================== -->
<style>
.profile-container { max-width:700px; margin:30px auto; padding:20px; }

.profile-card {
    display:flex; gap:20px; padding:20px; background:#f9f9f9;
    border-radius:12px; box-shadow:0 2px 8px rgba(0,0,0,0.1);
}

.profile-avatar {
    width:120px; height:120px; border-radius:50%;
    object-fit:cover; border:3px solid #ddd;
}

.status .online { color:green; font-weight:bold; }

.btn-logout {
    display:inline-block; margin-top:10px;
    background:#ff4d4f; padding:8px 15px; border-radius:6px;
    color:#fff; text-decoration:none;
}

.btn-update-toggle {
    width:100%; background:#007bff; color:#fff;
    padding:12px; border:none; border-radius:8px; cursor:pointer;
    margin:20px 0;
}

.update-section {
    background:#fff; padding:20px; border-radius:12px;
    box-shadow:0 2px 12px rgba(0,0,0,0.1);
}

.update-box {
    background:#f0f0f0; padding:15px; border-radius:10px; margin-bottom:15px;
}

.update-box button {
    background:#28a745; border:none; padding:10px 15px;
    border-radius:6px; color:#fff; cursor:pointer;
}

.alert {
    padding:10px; margin-bottom:15px; border-radius:6px;
}
.alert.error { background:#ffd6d6; color:#a30000; }
.alert.success { background:#d9ffe2; color:#006622; }

.user-post-card {
    padding:12px; background:#f9f9f9; border-radius:10px;
    margin-bottom:10px; box-shadow:0 1px 4px rgba(0,0,0,0.1);
}

.upc-title a {
    font-size:1.1em; font-weight:bold; color:#333; text-decoration:none;
}

.upc-title a:hover { color:#007bff; }

.upc-meta { font-size:0.9em; color:#666; margin-top:4px; }

.upc-actions {
    margin-top:8px; display:flex; justify-content:center;
    gap:10px;
}

.btn-small {
    padding:6px 10px; background:#007bff; color:white;
    border-radius:6px; text-decoration:none; font-size:0.85em;
}

.btn-small.delete { background:#d9534f; }

.pagination {
    display:flex; justify-content:center; margin-top:15px; gap:5px;
}

.pagination a {
    padding:6px 12px; border-radius:6px; background:#eee; text-decoration:none; color:#333;
}

.pagination a.active { background:#007bff; color:white; }
.pagination a.disabled { pointer-events:none; opacity:0.5; }
</style>

<?php include "includes/footer.php"; ?>

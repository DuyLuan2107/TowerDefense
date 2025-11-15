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

    <!-- NÚT HIỆN / ẨN -->
    <button class="btn-update-toggle" onclick="toggleUpdateForm()">⚙️ Cập nhật tài khoản</button>

    <!-- FORM CẬP NHẬT -->
    <div id="updateForm" class="update-section" style="display:none;">

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

    <!-- =========================== -->
    <!-- DANH SÁCH BÀI VIẾT CỦA USER -->
    <!-- =========================== -->

    <?php
    $stmt = $conn->prepare("
        SELECT p.*,
        (SELECT COUNT(*) FROM post_likes pl WHERE pl.post_id = p.id) AS like_count,
        (SELECT COUNT(*) FROM comments c WHERE c.post_id = p.id) AS comment_count
        FROM posts p
        WHERE p.user_id = ?
        ORDER BY p.created_at DESC
    ");
    $stmt->bind_param("i", $user_id);
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
                        <a href="forum_delete_post.php?id=<?= $p['id'] ?>"
                           onclick="return confirm('Xóa bài viết này?');"
                           class="btn-small delete">Xóa</a>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>

    <?php endif; ?>

</div>

<!-- JS -->
<script>
function toggleUpdateForm() {
    let f = document.getElementById("updateForm");
    f.style.display = f.style.display === "none" ? "block" : "none";
}
</script>

<!-- CSS giống user_profile -->
<style>
.profile-container { max-width:700px; margin:30px auto; padding:20px; }
.profile-card { display:flex; gap:20px; padding:20px; background:#f9f9f9;
    border-radius:12px; box-shadow:0 2px 8px rgba(0,0,0,0.1); }
.profile-avatar { width:120px; height:120px; border-radius:50%; object-fit:cover;
    border:3px solid #ddd; }
.profile-info h3 { margin:0; }
.status .online { color:green; font-weight:bold; }
.btn-logout { background:#ff4d4f; padding:8px 15px; border-radius:6px; color:#fff; text-decoration:none; }
.btn-update-toggle { width:100%; background:#007bff; color:#fff; padding:12px;
    border:none; border-radius:8px; cursor:pointer; margin:20px 0; }
.update-section { background:#fff; padding:20px; border-radius:12px;
    box-shadow:0 2px 12px rgba(0,0,0,0.1); }
.update-box { background:#f0f0f0; padding:15px; border-radius:10px; margin-bottom:15px; }
.update-box button { background:#28a745; border:none; padding:10px 15px;
    border-radius:6px; color:#fff; cursor:pointer; }

.user-post-card {
    padding:12px; background:#f9f9f9; border-radius:10px; margin-bottom:10px;
    box-shadow:0 1px 4px rgba(0,0,0,0.1);
}
.upc-title a { font-size:1.1em; font-weight:bold; color:#333; text-decoration:none; }
.upc-title a:hover { color:#007bff; }
.upc-meta { font-size:0.9em; color:#666; margin-top:4px; }
.upc-actions { display:flex; gap:8px; margin-top:8px; }
.btn-small { padding:6px 10px; background:#007bff; color:white;
    border-radius:6px; text-decoration:none; font-size:0.85em; }
.btn-small.delete { background:#d9534f; }

.upc-actions {
    margin-top: 8px;
    display: flex;
    justify-content: center;   /* Căn giữa */
    align-items: center;
    gap: 10px;                 /* Khoảng cách giữa các nút */
}

</style>

<?php include "includes/footer.php"; ?>

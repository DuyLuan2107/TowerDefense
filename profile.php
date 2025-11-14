<?php
include "includes/header.php";
include "db/connect.php";

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>

<div class="profile-container">
    <h2>👤 Thông Tin Cá Nhân</h2>

    <?php if (!isset($_SESSION['user'])): ?>
        <div class="profile-message">
            <p>Bạn cần đăng nhập để xem thông tin cá nhân.</p>
            <a href="auth.php" class="btn-login">🔑 Đăng Nhập Ngay</a>
        </div>
    <?php else: 
        $user = $_SESSION['user'];
        $avatar = !empty($user['avatar']) ? $user['avatar'] : 'uploads/default.png';
    ?>

        <!-- THÔNG TIN NGƯỜI DÙNG -->
        <div class="profile-card">
            <img src="<?= htmlspecialchars($avatar); ?>" class="profile-avatar">
            <div class="profile-info">
                <h3><?= htmlspecialchars($user['name']); ?></h3>
                <p><strong>Email:</strong> <?= htmlspecialchars($user['email']); ?></p>
                <p class="status">Trạng thái: <span class="online">Đang hoạt động</span></p>
                <a href="logout.php" class="btn-logout">🚪 Đăng Xuất</a>
            </div>
        </div>

        <hr>

        <!-- NÚT HIỆN / ẨN FORM CẬP NHẬT -->
        <button class="btn-update-toggle" onclick="toggleUpdateForm()">⚙️ Cập nhật tài khoản</button>

        <!-- FORM CẬP NHẬT - MẶC ĐỊNH ẨN -->
        <div id="updateForm" class="update-section" style="display: none;">

            <h3>🔧 Thay đổi thông tin</h3>

            <!-- 1. Cập nhật Avatar -->
            <form action="update_profile.php" method="POST" enctype="multipart/form-data" class="update-box">
                <h4>🖼 Thay đổi Avatar</h4>
                <input type="file" name="avatar" required>
                <button type="submit" name="change_avatar">Cập nhật Avatar</button>
            </form>

            <!-- 2. Đổi tên ingame -->
            <form action="update_profile.php" method="POST" class="update-box">
                <h4>✏️ Sửa tên ingame</h4>
                <input type="text" name="new_name" placeholder="Tên mới" required>
                <button type="submit" name="change_name">Cập nhật Tên</button>
            </form>

            <!-- 3. Đổi mật khẩu -->
            <form action="update_profile.php" method="POST" class="update-box">
                <h4>🔑 Đổi mật khẩu</h4>
                <input type="password" name="old_password" placeholder="Mật khẩu cũ" required>
                <input type="password" name="new_password" placeholder="Mật khẩu mới" required>
                <button type="submit" name="change_password">Đổi mật khẩu</button>
            </form>

        </div>

    <?php endif; ?>
</div>

<script>
function toggleUpdateForm() {
    let form = document.getElementById("updateForm");
    form.style.display = form.style.display === "none" ? "block" : "none";
}
</script>

<style>
.profile-container {
    max-width: 700px;
    margin: 30px auto;
    padding: 20px;
}

.profile-card {
    display: flex;
    gap: 20px;
    align-items: center;
    background: #f9f9f9;
    padding: 20px;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.profile-avatar {
    width: 120px;
    height: 120px;
    border-radius: 50%;
    object-fit: cover;
    border: 3px solid #ddd;
}

.btn-update-toggle {
    width: 100%;
    padding: 12px;
    margin: 20px 0;
    background: #007bff;
    border: none;
    color: white;
    border-radius: 8px;
    cursor: pointer;
}
.btn-update-toggle:hover { background: #0069d9; }

.update-section {
    background: #ffffff;
    padding: 20px;
    border-radius: 12px;
    box-shadow: 0 2px 12px rgba(0,0,0,0.1);
}

.update-box {
    background: #f0f0f0;
    padding: 15px;
    margin-bottom: 20px;
    border-radius: 10px;
}

.update-box input {
    width: 100%;
    padding: 10px;
    margin: 6px 0;
}

.update-box button {
    padding: 10px 15px;
    background: #28a745;
    border: none;
    color: white;
    border-radius: 6px;
}
.update-box button:hover { background: #218838; }

.status .online {
    color: green;
    font-weight: bold;
}
.btn-logout, .btn-login {
    display: inline-block;
    margin-top: 10px;
    padding: 8px 15px;
    background: #ff4d4f;
    color: #fff;
    text-decoration: none;
    border-radius: 6px;
}
</style>

<?php include "includes/footer.php"; ?>

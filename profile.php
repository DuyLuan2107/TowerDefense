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
        // Kiểm tra avatar, nếu chưa có → dùng ảnh mặc định
        $avatar = !empty($user['avatar']) ? $user['avatar'] : 'uploads/default.png';
    ?>
        <div class="profile-card">
            <img src="<?= htmlspecialchars($avatar); ?>" alt="Avatar người dùng" class="profile-avatar">
            <div class="profile-info">
                <h3><?= htmlspecialchars($user['name']); ?></h3>
                <p><strong>Email:</strong> <?= htmlspecialchars($user['email']); ?></p>
                <p class="status">Trạng thái: <span class="online">Đang hoạt động</span></p>
                <a href="logout.php" class="btn-logout">🚪 Đăng Xuất</a>
            </div>
        </div>
    <?php endif; ?>
</div>

<style>
.profile-container {
    max-width: 600px;
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

.profile-info h3 {
    margin: 0 0 10px 0;
    font-size: 24px;
}

.profile-info p {
    margin: 5px 0;
}

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
    transition: 0.3s;
}
.btn-logout:hover, .btn-login:hover {
    background: #e04444;
}
</style>

<?php include "includes/footer.php"; ?>

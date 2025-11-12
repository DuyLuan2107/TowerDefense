<?php
include "includes/header.php";
include "db/connect.php";
if (session_status() === PHP_SESSION_NONE) {
    session_start();
};
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
    ?>
        <div class="profile-card">
            <img src="assets/avatar.png" alt="Avatar người dùng" class="profile-avatar">
            <div class="profile-info">
                <h3><?= htmlspecialchars($user['name']); ?></h3>
                <p><strong>Email:</strong> <?= htmlspecialchars($user['email']); ?></p>
                <p class="status">Trạng thái: <span class="online">Đang hoạt động</span></p>
                <a href="logout.php" class="btn-logout">🚪 Đăng Xuất</a>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php include "includes/footer.php"; ?>
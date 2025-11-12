<?php
session_start(); // đảm bảo có session
?>
<nav class="navbar">
    <div class="nav-left">
        <a href="index.php">Home</a>
        <a href="game.php">Game</a>
        <a href="profile.php">Thông Tin Cá Nhân</a>
        <a href="contact.php">Liên Hệ</a>
    </div>

    <div class="nav-right">
        <?php if (isset($_SESSION['user'])): ?>
            <span class="user-name">👤 Xin chào, <strong><?php echo htmlspecialchars($_SESSION['user']['name']); ?></strong></span>
            <a href="logout.php" class="logout-btn">Đăng Xuất</a>
        <?php else: ?>
            <a href="auth.php">Đăng Nhập / Đăng Ký</a>
        <?php endif; ?>
    </div>
</nav>
<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<nav class="navbar">
    <div class="nav-left">
        <a href="index.php"><i class="fa-solid fa-house"></i> Home</a>
        <a href="game.php"><i class="fa-solid fa-gamepad"></i> Game</a>
        <a href="profile.php"><i class="fa-solid fa-user"></i> Thông Tin Cá Nhân</a>
        <a href="contact.php"><i class="fa-solid fa-envelope"></i> Liên Hệ</a>
        <a href="leaderboard.php"><i class="fa-solid fa-ranking-star"></i> Bảng Xếp Hạng</a>
        <a href="forum_list.php"><i class="fa-solid fa-comments"></i> Cộng Đồng Game</a>
        <?php if (isset($_SESSION['user']) && ($_SESSION['user']['role'] ?? '') === 'admin'): ?>
        <a href="admin/admin_panel.php"><i class="fa-solid fa-shield-halved"></i> Admin</a>
    <?php endif; ?>
    </div>

    <div class="nav-right">
        <?php if (isset($_SESSION['user'])): ?>
            <span class="user-name">
                <i class="fa-solid fa-circle-user"></i> 
                Xin chào, <strong><?php echo htmlspecialchars($_SESSION['user']['name']); ?></strong>
            </span>
            <a href="logout.php" class="logout-btn"><i class="fa-solid fa-right-from-bracket"></i> Đăng Xuất</a>
        <?php else: ?>
            <a href="auth.php" class="login-btn"><i class="fa-solid fa-right-to-bracket"></i> Đăng Nhập / Đăng Ký</a>
        <?php endif; ?>
    </div>
</nav>

<!-- Font Awesome -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

<style>
/* --- Navbar tổng thể --- */
.navbar {
    width: 100%;
    background: linear-gradient(90deg, #181818, #242424, #181818);
    padding: 10px 0px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    box-shadow: 0 4px 12px rgba(0,0,0,0.3);
    position: sticky;
    top: 0;
    z-index: 999;
    font-family: Arial, sans-serif;
}

/* --- Link chung --- */
.navbar a {
    color: #f0f0f0;
    margin: 0 12px;
    text-decoration: none;
    font-size: 16px;
    transition: 0.3s;
    padding: 8px 12px;
    border-radius: 6px;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

/* --- Hiệu ứng hover --- */
.navbar a:hover {
    background: rgba(255,255,255,0.1);
    transform: translateY(-2px);
}

/* --- Phần bên trái menu --- */
.nav-left {
    display: flex;
    align-items: center;
}

/* --- Phần bên phải --- */
.nav-right {
    display: flex;
    align-items: center;
}

.user-name {
    color: #fff;
    margin-right: 15px;
    font-size: 15px;
    display: flex;
    align-items: center;
    gap: 6px;
}

/* --- nút đăng xuất --- */
.logout-btn {
    background: #e63946;
    padding: 8px 14px;
    border-radius: 6px;
    font-weight: bold;
}
.logout-btn:hover {
    background: #d62828;
}

/* --- nút đăng nhập --- */
.login-btn {
    background: #1d9bf0;
    padding: 8px 14px;
    border-radius: 6px;
    font-weight: bold;
}
.login-btn:hover {
    background: #198fda;
}

/* --- Responsive cho điện thoại --- */
@media (max-width: 768px) {
    .navbar {
        flex-direction: column;
        gap: 10px;
        text-align: center;
    }

    .nav-left {
        flex-wrap: wrap;
        justify-content: center;
    }

    .nav-left a {
        margin: 6px;
    }
}
</style>

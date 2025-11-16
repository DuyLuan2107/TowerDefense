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
        <a href="admin/admin_panel.php" class="admin-link"><i class="fa-solid fa-shield-halved"></i> Admin Panel</a>
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

<style>
/* --- Navbar tổng thể --- */
.navbar {
    width: 100%;
    /* Nền tối, trong suốt (Glassmorphism) */
    background: rgba(10, 15, 30, 0.85);
    backdrop-filter: blur(10px);
    -webkit-backdrop-filter: blur(10px);
    
    padding: 12px 0px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    
    /* Đường viền Neon Cyan */
    border-bottom: 2px solid #00f7ff;
    box-shadow: 0 4px 15px rgba(0, 247, 255, 0.15);
    
    position: sticky;
    top: 0;
    z-index: 999;
    font-family: 'Montserrat', sans-serif; /* Font mới */
}

/* --- Link chung --- */
.navbar a {
    color: #ccc; /* Màu chữ xám nhạt */
    margin: 0 12px;
    text-decoration: none;
    font-size: 16px;
    font-weight: 500;
    transition: 0.3s ease;
    padding: 8px 12px;
    border-radius: 6px;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

/* --- Hiệu ứng hover --- */
.navbar a:hover {
    color: #00f7ff; /* Chuyển màu Neon Cyan */
    text-shadow: 0 0 8px rgba(0, 247, 255, 0.7);
    background: none; /* Bỏ nền xám cũ */
    transform: translateY(-2px);
}

/* --- Phần bên trái menu --- */
.nav-left {
    display: flex;
    align-items: center;
    padding-left: 20px;
}

/* Link Admin (nổi bật) */
.admin-link {
    color: #ffc107 !important; /* Màu vàng */
}
.admin-link:hover {
    color: #ffd54f !important;
    text-shadow: 0 0 8px rgba(255, 193, 7, 0.7) !important;
}


/* --- Phần bên phải --- */
.nav-right {
    display: flex;
    align-items: center;
    padding-right: 20px;
}

.user-name {
    color: #fff;
    margin-right: 15px;
    font-size: 15px;
    display: flex;
    align-items: center;
    gap: 6px;
}
.user-name strong {
    color: #00f7ff; /* Highlight tên user */
    font-weight: 700;
}

/* --- nút đăng xuất (Gradient Cam/Đỏ) --- */
.logout-btn {
    background: linear-gradient(90deg, #F09819 0%, #FF512F 100%);
    color: #fff !important;
    padding: 9px 15px;
    border-radius: 6px;
    font-weight: bold;
    text-shadow: none !important;
}
.logout-btn:hover {
    background: linear-gradient(90deg, #ffae40 0%, #ff7354 100%);
    color: #fff !imporant;
    box-shadow: 0 0 10px rgba(240, 152, 25, 0.5);
}

/* --- nút đăng nhập (Gradient Tím/Xanh) --- */
.login-btn {
    background: linear-gradient(90deg, #6a11cb 0%, #2575fc 100%);
    color: #fff !important;
    padding: 9px 15px;
    border-radius: 6px;
    font-weight: bold;
    text-shadow: none !important;
}
.login-btn:hover {
    background: linear-gradient(90deg, #8a34eb 0%, #4a90fc 100%);
    color: #fff !important;
    box-shadow: 0 0 10px rgba(37, 117, 252, 0.5);
}

/* --- Responsive cho điện thoại --- */
@media (max-width: 992px) { /* Tăng breakpoint lên 992px */
    .navbar {
        flex-direction: column;
        gap: 15px;
        padding-top: 15px;
        padding-bottom: 15px;
    }
    .nav-left, .nav-right {
        padding: 0;
        width: 100%;
        justify-content: center;
        flex-wrap: wrap; /* Cho phép xuống dòng */
    }
    .navbar a {
        margin: 5px;
    }
}

/* =====================================================
    CSS CHO FOOTER (Vì file footer.php chỉ có HTML)
=====================================================
*/
footer {
    width: 100%;
    /* Nền tối, đồng bộ với navbar */
    background: #0A0F1E; 
    
    /* Viền neon trên cùng */
    border-top: 1px solid #00f7ff;
    box-shadow: 0 -4px 15px rgba(0, 247, 255, 0.1);
    
    padding: 30px 0;
    text-align: center;
    margin-top: auto; /* Đẩy footer xuống dưới nếu trang ngắn */
}

footer p {
    margin: 0;
    color: #888; /* Màu chữ xám */
    font-size: 14px;
    font-family: 'Montserrat', sans-serif;
    font-weight: 500;
}

</style>
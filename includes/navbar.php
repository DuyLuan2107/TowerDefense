<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// [CODE MỚI] Lấy tên trang hiện tại
$currentPage = basename($_SERVER['PHP_SELF']);
$isGamePage = ($currentPage === 'game.php');
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
        
        <?php if (!$isGamePage): ?>
        <div id="volume-control" class="volume-btn">
            <i class="fa-solid fa-volume-xmark"></i>
        </div>
        <?php endif; ?>
    </div>
</nav>

<?php if (!$isGamePage): ?>
<audio id="bg-music" loop>
    <source src="assets/music/game-bgm.mp3" type="audio/mpeg">
</audio>
<audio id="hover-sound" muted>
    <source src="assets/sounds/pop-sound.mp3" type="audio/mpeg">
</audio>
<?php endif; ?>


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

/* --- [CSS MỚI] Nút Âm Thanh --- */
.volume-btn {
    color: #ccc;
    margin-left: 15px;
    font-size: 1.2rem;
    cursor: pointer;
    transition: all 0.3s ease;
    padding: 8px 12px;
    border-radius: 6px;
    user-select: none; 
}
.volume-btn:hover {
    color: #00f7ff;
    background: rgba(255,255,255,0.1);
    transform: translateY(-2px);
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
    /* Sửa CSS cho nút âm lượng trên responsive */
    .volume-btn {
        margin-left: 5px; 
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

/* =====================================================
    CSS CHUNG CHO BODY (ĐỂ CÓ NỀN TỐI)
=====================================================
*/
body {
    /* Nền tối chủ đạo của web game */
    background: #1a1a2e; 
    margin: 0;
    padding: 0;
}

/* =====================================================
    CSS CHO TRANG LIÊN HỆ (CONTACT.PHP)
=====================================================
*/

/* --- Container chính --- */
.contact-container {
    width: 100%;
    max-width: 650px;
    
    /* Căn giữa container */
    margin: 60px auto; 

    /* Nền tối, trong suốt (Glassmorphism) */
    background: rgba(10, 15, 30, 0.85);
    backdrop-filter: blur(10px);
    -webkit-backdrop-filter: blur(10px);

    padding: 30px 40px;
    border-radius: 10px;
    
    /* Viền Neon Cyan */
    border: 1px solid #00f7ff;
    box-shadow: 0 0 25px rgba(0, 247, 255, 0.3);
    
    text-align: center;
    color: #f0f0f0;
    box-sizing: border-box;
}

/* --- Tiêu đề và Mô tả --- */
.contact-container h2 {
    color: #00f7ff; /* Neon Cyan */
    font-size: 28px;
    font-weight: 700;
    margin-bottom: 15px;
    text-shadow: 0 0 8px rgba(0, 247, 255, 0.7);
}

.contact-container p {
    color: #ccc;
    font-size: 16px;
    margin-bottom: 30px;
}

/* --- Form --- */
.contact-form .form-group {
    margin-bottom: 20px;
    text-align: left;
}

.contact-form label {
    display: block;
    margin-bottom: 8px;
    color: #ccc;
    font-weight: 500;
    font-size: 14px;
}

/* --- Input và Textarea --- */
.contact-form input[type="text"],
.contact-form input[type="email"],
.contact-form textarea {
    width: 100%;
    padding: 14px 16px;
    border-radius: 5px;
    border: 1px solid #4a4a5e; /* Border tối */
    background: #0d1321; /* Nền input tối */
    color: #f0f0f0; /* Chữ sáng */
    box-sizing: border-box; 
    transition: all 0.2s ease-in-out;
    font-family: 'Montserrat', sans-serif; /* Dùng font mới */
}

.contact-form input:focus,
.contact-form textarea:focus {
    border-color: #00f7ff; /* Viền Neon khi focus */
    box-shadow: 0 0 10px rgba(0, 247, 255, 0.7);
    outline: none;
}

.contact-form textarea {
    min-height: 120px;
    resize: vertical; /* Cho phép thay đổi chiều cao */
}

/* --- Nút Gửi (Đồng bộ với nút Đăng nhập) --- */
.btn-send {
    width: 100%;
    padding: 14px;
    margin-top: 10px;
    border-radius: 6px;
    border: none;
    
    /* Gradient Tím/Xanh */
    background: linear-gradient(90deg, #6a11cb 0%, #2575fc 100%);
    color: #fff !important;
    font-weight: bold;
    font-size: 16px;
    cursor: pointer;
    transition: all 0.3s;
    font-family: 'Montserrat', sans-serif;
}

.btn-send:hover {
    transform: translateY(-2px);
    box-shadow: 0 0 15px rgba(37, 117, 252, 0.6);
}

/* --- Thông báo thành công --- */
.contact-success {
    margin-top: 20px;
    padding: 12px;
    border-radius: 5px;
    font-weight: 600;
    
    /* Nền xanh lá neon */
    background: rgba(20, 255, 120, 0.1);
    color: #14ff78;
    border: 1px solid #14ff78;
}
/* =====================================================
    CSS CHO TRANG CHỦ (INDEX.PHP)
=====================================================
*/

/* --- 1. Hero Section --- */
.hero-section {
    /* LƯU Ý: Thay 'path/to/your/game-bg.jpg' bằng link ảnh nền game của bạn.
      (Ví dụ: 'assets/images/background.jpg') 
    */
    background: linear-gradient(rgba(26, 26, 46, 0.8), rgba(26, 26, 46, 0.8)), 
                url('assets/images/game-bg.jpg') no-repeat center center/cover;
    height: 60vh; /* Chiều cao 60% màn hình */
    display: flex;
    justify-content: center;
    align-items: center;
    text-align: center;
    color: #fff;
    border-bottom: 2px solid #00f7ff; /* Khớp với viền navbar */
    padding: 0 20px;
}

.hero-content h1 {
    font-size: 3.5rem; /* Cỡ chữ lớn */
    color: #00f7ff;
    text-shadow: 0 0 15px rgba(0, 247, 255, 0.8);
    margin-bottom: 15px;
    font-weight: 700;
}

.hero-content p {
    font-size: 1.2rem;
    color: #ccc;
    max-width: 600px;
    margin: 0 auto 30px auto;
    line-height: 1.6;
}

.btn-play {
    /* Nút "Bắt đầu chơi" - Giống nút Đăng nhập */
    background: linear-gradient(90deg, #6a11cb 0%, #2575fc 100%);
    color: #fff;
    padding: 14px 28px;
    border-radius: 8px;
    font-weight: bold;
    font-size: 1.1rem;
    text-decoration: none;
    transition: all 0.3s;
    box-shadow: 0 0 15px rgba(37, 117, 252, 0.5);
}
.btn-play:hover {
    transform: translateY(-3px) scale(1.05);
    box-shadow: 0 0 25px rgba(37, 117, 252, 0.8);
}

/* --- 2. Features Section --- */
.features {
    padding: 60px 20px;
    background: #1a1a2e; /* Nền tối của body */
    text-align: center;
}

.features h2 {
    font-size: 2.5rem;
    color: #fff;
    margin-bottom: 50px;
    font-weight: 700;
}

.feature-list {
    display: flex;
    justify-content: center;
    gap: 30px;
    flex-wrap: wrap;
}

.feature {
    /* Thẻ Glassmorphism */
    background: rgba(10, 15, 30, 0.85);
    border: 1px solid #00f7ff;
    box-shadow: 0 0 15px rgba(0, 247, 255, 0.2);
    padding: 30px;
    border-radius: 10px;
    width: 320px;
    transition: transform 0.3s, box-shadow 0.3s;
}

.feature:hover {
    transform: translateY(-10px);
    box-shadow: 0 0 25px rgba(0, 247, 255, 0.5);
}

.feature h3 {
    color: #00f7ff;
    font-size: 1.5rem;
    margin-bottom: 10px;
}

.feature p {
    color: #ccc;
    font-size: 1rem;
    line-height: 1.5;
}

/* --- 3. How to Play Section --- */
.how-to-play {
    padding: 60px 20px;
    background: #162447; /* Nền xanh đậm hơn */
    text-align: center;
}

.how-to-play h2 {
    font-size: 2.5rem;
    color: #fff;
    margin-bottom: 50px;
    font-weight: 700;
}

.steps-container {
    display: flex;
    justify-content: center;
    gap: 30px;
    flex-wrap: wrap;
}

.step {
    width: 300px;
    text-align: center;
}

.step-icon {
    font-size: 3.5rem;
    color: #00f7ff;
    margin-bottom: 20px;
    /* Hiệu ứng thở (pulse) */
    animation: pulse 2s infinite ease-in-out;
}

.step h3 {
    color: #fff;
    font-size: 1.5rem;
    margin-bottom: 10px;
}

.step p {
    color: #ccc;
    font-size: 1rem;
}

/* Animation cho icon */
@keyframes pulse {
    0% { transform: scale(1); opacity: 0.8; }
    50% { transform: scale(1.1); opacity: 1; }
    100% { transform: scale(1); opacity: 0.8; }
}

/* --- 4. CTA Section (Kêu gọi Đăng ký) --- */
.cta-section {
    padding: 70px 20px;
    /* LƯU Ý: Thay 'path/to/your/cta-bg.jpg' bằng link ảnh nền khác 
    */
    background: linear-gradient(rgba(106, 17, 203, 0.85), rgba(37, 117, 252, 0.85)), 
                url('assets/images/cta-bg.jpg') no-repeat center center/cover;
    text-align: center;
    color: #fff;
}

.cta-content h2 {
    font-size: 2.5rem;
    margin-bottom: 15px;
    font-weight: 700;
}

.cta-content p {
    font-size: 1.2rem;
    max-width: 600px;
    margin: 0 auto 30px auto;
    color: #eee;
    line-height: 1.6;
}

.btn-cta-register {
    /* Nút "Đăng Ký" - Giống nút Đăng xuất (Đỏ/Cam) */
    background: linear-gradient(90deg, #F09819 0%, #FF512F 100%);
    color: #fff;
    padding: 14px 28px;
    border-radius: 8px;
    font-weight: bold;
    font-size: 1.1rem;
    text-decoration: none;
    transition: all 0.3s;
    box-shadow: 0 0 15px rgba(240, 152, 25, 0.5);
}

.btn-cta-register:hover {
    transform: translateY(-3px) scale(1.05);
    box-shadow: 0 0 25px rgba(240, 152, 25, 0.8);
}

/* --- 5. Community Section --- */
.community-section {
    padding: 60px 20px;
    background: #1a1a2e;
    text-align: center;
}

.community-section h2 {
    font-size: 2.5rem;
    color: #fff;
    margin-bottom: 15px;
    font-weight: 700;
}

.community-section p {
    font-size: 1.2rem;
    color: #ccc;
    margin-bottom: 30px;
}

.community-links {
    display: flex;
    justify-content: center;
    gap: 20px;
    flex-wrap: wrap;
}

.btn-community {
    /* Nút viền (Ghost button) */
    background: transparent;
    border: 2px solid #00f7ff;
    color: #00f7ff;
    padding: 12px 24px;
    border-radius: 8px;
    font-weight: bold;
    font-size: 1rem;
    text-decoration: none;
    transition: all 0.3s;
}

.btn-community:hover {
    background: #00f7ff;
    color: #1a1a2e; /* Đổi màu chữ thành màu nền */
    box-shadow: 0 0 15px rgba(0, 247, 255, 0.5);
}
/* =====================================================
    CSS CHO BẢNG XẾP HẠNG (LEADERBOARD.PHP)
=====================================================
*/

/* --- Wrapper (giống .contact-wrapper) --- */
.leaderboard-wrapper {
    display: flex;
    justify-content: center;
    padding: 60px 20px;
    /* Nền tối đã được body xử lý */
}

/* --- Container (giống .contact-container) --- */
.leaderboard-container {
    width: 100%;
    max-width: 700px;
    background: rgba(10, 15, 30, 0.85);
    backdrop-filter: blur(10px);
    -webkit-backdrop-filter: blur(10px);
    padding: 30px 40px;
    border-radius: 10px;
    border: 1px solid #00f7ff;
    box-shadow: 0 0 25px rgba(0, 247, 255, 0.3);
    color: #f0f0f0;
    box-sizing: border-box;
}

.leaderboard-container h2 {
    color: #00f7ff; /* Neon Cyan */
    font-size: 28px;
    font-weight: 700;
    margin-bottom: 15px;
    text-align: center;
    text-shadow: 0 0 8px rgba(0, 247, 255, 0.7);
}

.leaderboard-muted {
    color: #ccc;
    font-size: 16px;
    margin-bottom: 30px;
    text-align: center;
}

/* --- Header của Bảng --- */
.leaderboard-header {
    display: flex;
    justify-content: space-between;
    padding: 0 15px 10px 15px;
    border-bottom: 2px solid #4a4a5e;
    color: #888;
    font-size: 14px;
    font-weight: 600;
    text-transform: uppercase;
}
.header-rank {
    flex-basis: 80px; /* Độ rộng cột Hạng */
    text-align: left;
}
.header-name {
    flex-grow: 1; /* Cột Tên tự co dãn */
    text-align: left;
}
.header-score {
    flex-basis: 100px; /* Độ rộng cột Điểm */
    text-align: right;
}

/* --- Danh sách --- */
.leaderboard-list {
    margin-top: 15px;
}

.leaderboard-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 15px;
    border-radius: 8px;
    background: #0d1321; /* Nền input */
    margin-bottom: 10px;
    border: 1px solid transparent; /* Viền trong suốt */
    transition: transform 0.3s, background 0.3s;
}

.leaderboard-item:hover {
    transform: scale(1.02);
    background: #162447; /* Nền xanh đậm hơn */
}

.leaderboard-item-empty {
    padding: 30px;
    text-align: center;
    color: #888;
    font-style: italic;
}

/* --- Các cột --- */
.leaderboard-item .rank {
    flex-basis: 80px;
    font-size: 1.2rem;
    font-weight: 700;
    color: #00f7ff;
    text-align: left;
}

.leaderboard-item .name {
    flex-grow: 1;
    font-size: 1.1rem;
    font-weight: 500;
    color: #fff;
    text-align: left;
}

.leaderboard-item .score {
    flex-basis: 100px;
    font-size: 1.2rem;
    font-weight: 700;
    color: #00f7ff;
    text-align: right;
}

/* --- HIỆU ỨNG TOP 3 --- */

/* Hạng 1 (Vàng) */
.leaderboard-item.rank-1 {
    background: linear-gradient(90deg, rgba(255, 193, 7, 0.2), rgba(13, 19, 33, 0.85) 60%);
    border-color: #ffc107;
    box-shadow: 0 0 15px rgba(255, 193, 7, 0.3);
}
.rank-1-icon {
    color: #ffc107;
    text-shadow: 0 0 8px #ffc107;
}

/* Hạng 2 (Bạc) */
.leaderboard-item.rank-2 {
    background: linear-gradient(90deg, rgba(192, 192, 192, 0.2), rgba(13, 19, 33, 0.85) 60%);
    border-color: #c0c0c0;
}
.rank-2-icon {
    color: #c0c0c0;
    text-shadow: 0 0 8px #c0c0c0;
}

/* Hạng 3 (Đồng) */
.leaderboard-item.rank-3 {
    background: linear-gradient(90deg, rgba(205, 127, 50, 0.2), rgba(13, 19, 33, 0.85) 60%);
    border-color: #cd7f32;
}
.rank-3-icon {
    color: #cd7f32;
    text-shadow: 0 0 8px #cd7f32;
}


/* --- CSS PHÂN TRANG (Pagination) --- */
.pagination {
    display: flex;
    justify-content: center;
    margin-top: 30px;
    gap: 5px;
}
.pagination a {
    color: #ccc;
    text-decoration: none;
    padding: 8px 14px;
    border-radius: 5px;
    font-weight: 600;
    background: #0d1321;
    border: 1px solid #4a4a5e;
    transition: all 0.2s;
}
.pagination a:hover {
    background: #00f7ff;
    color: #1a1a2e;
    border-color: #00f7ff;
}
.pagination a.active {
    background: #00f7ff;
    color: #1a1a2e;
    border-color: #00f7ff;
    cursor: default;
}
.pagination a.disabled {
    opacity: 0.5;
    cursor: not-allowed;
}
.pagination a.disabled:hover {
    background: #0d1321;
    color: #ccc;
    border-color: #4a4a5e;
}

/* =====================================================
    CSS CHO ADMIN CONTACTS (admin_contacts.php)
=====================================================
*/

/* --- Wrapper chung --- */
.admin-wrapper {
    display: flex;
    justify-content: center;
    padding: 60px 20px;
    background: #1a1a2e; /* Nền tối */
}

/* --- Container (giống leaderboard) --- */
.admin-container {
    width: 100%;
    max-width: 900px;
    background: rgba(10, 15, 30, 0.85);
    backdrop-filter: blur(10px);
    -webkit-backdrop-filter: blur(10px);
    padding: 30px 40px;
    border-radius: 10px;
    border: 1px solid #00f7ff;
    box-shadow: 0 0 25px rgba(0, 247, 255, 0.3);
    color: #f0f0f0;
    box-sizing: border-box;
}

.admin-container h2 {
    color: #00f7ff;
    font-size: 28px;
    font-weight: 700;
    margin-bottom: 10px;
    text-align: left;
    text-shadow: 0 0 8px rgba(0, 247, 255, 0.7);
}
.admin-container p {
    color: #ccc;
    font-size: 1rem;
    margin-top: 0;
    margin-bottom: 30px;
    text-align: left;
}

/* --- Danh sách tin nhắn --- */
.contact-list {
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.contact-card {
    background: #0d1321;
    border: 1px solid #4a4a5e;
    border-radius: 8px;
    transition: all 0.3s;
}

/* Tin nhắn chưa đọc (UNREAD) */
.contact-card.unread {
    border-left: 4px solid #00f7ff; /* Viền neon bên trái */
    background: #162447; /* Nền sáng hơn */
}

.contact-card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 15px 20px;
    border-bottom: 1px solid #2a2a3e;
    flex-wrap: wrap;
    gap: 10px;
}
.contact-info {
    display: flex;
    flex-direction: column;
    gap: 2px;
}
.contact-name {
    font-size: 1.1rem;
    font-weight: 700;
    color: #fff;
}
.contact-email {
    font-size: 0.9rem;
    color: #00f7ff;
    font-style: italic;
}
.contact-date {
    font-size: 0.85rem;
    color: #888;
}

.contact-card-body {
    padding: 20px;
    color: #ccc;
    line-height: 1.6;
    white-space: pre-wrap; /* Giữ lại các dấu xuống dòng */
}

.contact-card-actions {
    display: flex;
    gap: 10px;
    padding: 0 20px 15px 20px;
    justify-content: flex-end;
}
.btn-action {
    background: #4a4a5e;
    color: #ccc;
    text-decoration: none;
    padding: 6px 12px;
    border-radius: 5px;
    font-size: 0.85rem;
    font-weight: 600;
    transition: all 0.2s;
}
.btn-action i {
    margin-right: 5px;
}
.btn-action.mark-read:hover {
    background: #2575fc;
    color: #fff;
}
.btn-action.delete:hover {
    background: #e63946;
    color: #fff;
}
.contact-card-empty {
    text-align: center;
    color: #888;
    padding: 30px;
}
</style>


<?php if (!$isGamePage): ?>
<script>
document.addEventListener('DOMContentLoaded', () => {

    const bgMusic = document.getElementById('bg-music');
    const hoverSound = document.getElementById('hover-sound');
    const volumeControl = document.getElementById('volume-control');
    if (!volumeControl) return;

    const volumeIcon = volumeControl.querySelector('i');

    // Lấy trạng thái đã lưu (true/false)
    let isMuted = localStorage.getItem('soundMuted') === 'true';

    // Áp dụng trạng thái ngay khi load trang
    if (isMuted) {
        bgMusic.muted = true;
        hoverSound.muted = true;
        volumeIcon.className = "fa-solid fa-volume-xmark";
    } else {
        bgMusic.muted = false;
        hoverSound.muted = false;
        bgMusic.play().catch(()=>{});
        volumeIcon.className = "fa-solid fa-volume-high";
    }

    // Bấm nút toggle
    volumeControl.addEventListener('click', () => {
        isMuted = !isMuted;

        if (isMuted) {
            bgMusic.muted = true;
            bgMusic.pause();
            hoverSound.muted = true;
            volumeIcon.className = "fa-solid fa-volume-xmark";
        } else {
            bgMusic.muted = false;
            hoverSound.muted = false;
            bgMusic.play().catch(()=>{});
            volumeIcon.className = "fa-solid fa-volume-high";
        }

        // LƯU trạng thái
        localStorage.setItem('soundMuted', isMuted);
    });

    // Âm pop khi hover
    const buttons = document.querySelectorAll(
        '.navbar a, .btn-play, .btn-cta-register, .btn-community, .btn-send, .form-box button, .pagination a'
    );
    
    buttons.forEach(button => {
        button.addEventListener('mouseenter', () => {
            if (!isMuted) {
                hoverSound.currentTime = 0;
                hoverSound.play().catch(()=>{});
            }
        });
    });

});
</script>
<?php endif; ?>
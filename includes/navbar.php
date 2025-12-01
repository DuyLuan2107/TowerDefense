<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// [CODE MỚI] Lấy tên trang hiện tại
$currentPage = basename($_SERVER['PHP_SELF']);
$isGamePage = ($currentPage === 'game.php');

// [CODE MỚI] Đếm số lời mời kết bạn (Notification Badge)
$pending_count = 0;
if (isset($_SESSION['user'])) {
    // Giả sử biến $conn đã được include từ file cha (header.php hoặc index.php)
    // Nếu chưa, dòng dưới đây đảm bảo không lỗi, nhưng tốt nhất file cha nên có require db/connect.php
    if (isset($conn)) {
        $my_uid = $_SESSION['user']['id'];
        $stmt_count = $conn->prepare("SELECT COUNT(*) FROM friends WHERE receiver_id = ? AND status = 'pending'");
        $stmt_count->bind_param("i", $my_uid);
        $stmt_count->execute();
        $stmt_count->bind_result($pending_count);
        $stmt_count->fetch();
        $stmt_count->close();
    }
}
?>

<nav class="navbar">
    <div class="nav-left">
        <a href="index.php"><i class="fa-solid fa-house"></i> Home</a>
        <a href="game.php"><i class="fa-solid fa-gamepad"></i> Game</a>
        
        <?php if (isset($_SESSION['user'])): ?>
            <a href="friends.php" style="position: relative;">
                <i class="fa-solid fa-user-group"></i> Bạn Bè
                <?php if ($pending_count > 0): ?>
                    <span class="nav-badge"><?= $pending_count ?></span>
                <?php endif; ?>
            </a>
            <a href="profile.php"><i class="fa-solid fa-user"></i> Hồ Sơ</a>
        <?php endif; ?>

        <a href="leaderboard.php"><i class="fa-solid fa-ranking-star"></i> Bảng Xếp Hạng</a>
        <a href="forum_list.php"><i class="fa-solid fa-comments"></i> Cộng Đồng</a>
        <a href="contact.php"><i class="fa-solid fa-envelope"></i> Liên Hệ</a>

        <?php if (isset($_SESSION['user']) && ($_SESSION['user']['role'] ?? '') === 'admin'): ?>
            <a href="admin/dashboard.php" class="admin-link"><i class="fa-solid fa-shield-halved"></i> Admin Panel</a>
        <?php endif; ?>
    </div>

    <div class="nav-right">
        <?php if (isset($_SESSION['user'])): ?>
            <span class="user-name">
                <img src="<?= isset($_SESSION['user']['avatar']) ? $_SESSION['user']['avatar'] : 'assets/images/default_avatar.png' ?>" class="nav-mini-avatar" alt="avt">
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
    background: rgba(10, 15, 30, 0.95); /* Tăng độ đậm nền một chút */
    backdrop-filter: blur(10px);
    -webkit-backdrop-filter: blur(10px);
    
    padding: 12px 20px; /* Thêm padding ngang */
    display: flex;
    justify-content: space-between;
    align-items: center;
    box-sizing: border-box; /* Quan trọng để không bị tràn */
    
    /* Đường viền Neon Cyan */
    border-bottom: 2px solid #00f7ff;
    box-shadow: 0 4px 15px rgba(0, 247, 255, 0.15);
    
    position: sticky;
    top: 0;
    z-index: 999;
    font-family: 'Montserrat', sans-serif; 
}

/* --- Link chung --- */
.navbar a {
    color: #ccc; 
    margin: 0 5px;
    text-decoration: none;
    font-size: 15px;
    font-weight: 600;
    transition: 0.3s ease;
    padding: 8px 10px;
    border-radius: 6px;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

/* --- Hiệu ứng hover --- */
.navbar a:hover {
    color: #00f7ff; 
    text-shadow: 0 0 8px rgba(0, 247, 255, 0.7);
    background: rgba(255,255,255,0.05); 
    transform: translateY(-2px);
}

/* --- [CSS MỚI] Badge thông báo số đỏ --- */
.nav-badge {
    background-color: #ef4444;
    color: white;
    font-size: 10px;
    font-weight: bold;
    padding: 2px 6px;
    border-radius: 10px;
    margin-left: 2px;
    animation: pulse-red 2s infinite;
}
@keyframes pulse-red {
    0% { transform: scale(1); box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.7); }
    70% { transform: scale(1.1); box-shadow: 0 0 0 5px rgba(239, 68, 68, 0); }
    100% { transform: scale(1); box-shadow: 0 0 0 0 rgba(239, 68, 68, 0); }
}

/* --- [CSS MỚI] Avatar nhỏ trên menu --- */
.nav-mini-avatar {
    width: 26px; height: 26px; 
    border-radius: 50%; 
    object-fit: cover;
    border: 1px solid #00f7ff;
    margin-right: 5px;
}

/* --- Phần bên trái menu --- */
.nav-left {
    display: flex;
    align-items: center;
    flex-wrap: wrap; /* Cho phép xuống dòng nếu màn hình nhỏ */
}

/* Link Admin (nổi bật) */
.admin-link {
    color: #ffc107 !important; 
}
.admin-link:hover {
    color: #ffd54f !important;
    text-shadow: 0 0 8px rgba(255, 193, 7, 0.7) !important;
}


/* --- Phần bên phải --- */
.nav-right {
    display: flex;
    align-items: center;
}

.user-name {
    color: #fff;
    margin-right: 15px;
    font-size: 14px;
    display: flex;
    align-items: center;
    gap: 4px;
}
.user-name strong {
    color: #00f7ff; 
    font-weight: 700;
}

/* --- nút đăng xuất (Gradient Cam/Đỏ) --- */
.logout-btn {
    background: linear-gradient(90deg, #F09819 0%, #FF512F 100%);
    color: #fff !important;
    padding: 8px 15px;
    border-radius: 20px;
    font-size: 14px;
    font-weight: bold;
    text-shadow: none !important;
}
.logout-btn:hover {
    background: linear-gradient(90deg, #ffae40 0%, #ff7354 100%);
    box-shadow: 0 0 10px rgba(240, 152, 25, 0.5);
}

/* --- nút đăng nhập (Gradient Tím/Xanh) --- */
.login-btn {
    background: linear-gradient(90deg, #6a11cb 0%, #2575fc 100%);
    color: #fff !important;
    padding: 8px 15px;
    border-radius: 20px;
    font-size: 14px;
    font-weight: bold;
    text-shadow: none !important;
}
.login-btn:hover {
    background: linear-gradient(90deg, #8a34eb 0%, #4a90fc 100%);
    box-shadow: 0 0 10px rgba(37, 117, 252, 0.5);
}

/* --- Nút Âm Thanh --- */
.volume-btn {
    color: #ccc;
    margin-left: 10px;
    font-size: 1.2rem;
    cursor: pointer;
    transition: all 0.3s ease;
    padding: 5px 10px;
    border-radius: 6px;
    user-select: none; 
}
.volume-btn:hover {
    color: #00f7ff;
    background: rgba(255,255,255,0.1);
}

/* --- Responsive cho điện thoại --- */
@media (max-width: 992px) { 
    .navbar {
        flex-direction: column;
        gap: 15px;
        padding: 15px;
        height: auto;
    }
    .nav-left, .nav-right {
        width: 100%;
        justify-content: center;
        flex-wrap: wrap; 
        gap: 5px;
    }
    .navbar a {
        font-size: 14px;
        margin: 2px;
        padding: 6px 8px;
    }
    .user-name {
        display: none; /* Ẩn tên user trên mobile để đỡ chật */
    }
}

/* =====================================================
    GIỮ NGUYÊN CSS CHO CÁC PHẦN KHÁC (Footer, Body, Contact...)
    (Để code không bị quá dài, phần dưới này giữ nguyên như code cũ của bạn)
   =====================================================
*/
footer {
    width: 100%;
    background: #0A0F1E; 
    border-top: 1px solid #00f7ff;
    box-shadow: 0 -4px 15px rgba(0, 247, 255, 0.1);
    padding: 30px 0;
    text-align: center;
    margin-top: auto; 
}
footer p {
    margin: 0; color: #888; font-size: 14px; font-weight: 500;
}

/* (Các CSS khác cho Contact, Leaderboard, Index... bạn giữ nguyên như cũ ở đây) */
/* ... */
</style>

<?php if (!$isGamePage): ?>
<script>
document.addEventListener('DOMContentLoaded', () => {

    const bgMusic = document.getElementById('bg-music');
    const hoverSound = document.getElementById('hover-sound');
    const volumeControl = document.getElementById('volume-control');
    if (!volumeControl) return;

    const volumeIcon = volumeControl.querySelector('i');

    // Lấy trạng thái đã lưu
    let isMuted = localStorage.getItem('soundMuted') === 'true';

    // Áp dụng trạng thái
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

    // Toggle
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
        localStorage.setItem('soundMuted', isMuted);
    });

    // Hover sound
    const buttons = document.querySelectorAll('a, button, .btn-play');
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
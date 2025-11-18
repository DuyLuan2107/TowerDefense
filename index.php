<?php include "includes/header.php"; ?>

<style>
    /* Import Font */
    @import url('https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700;800&display=swap');

    /* Cấu hình chung */
    body {
        background-color: #0a0e1a; /* Các phần dưới vẫn giữ nền tối để nội dung nổi bật */
        color: #e0e6f2;
        font-family: 'Montserrat', sans-serif;
        margin: 0; padding: 0;
    }

    /* --- HERO SECTION (ĐÃ CẬP NHẬT SÁNG HƠN) --- */
    .hero-section {
        position: relative;
        /* Đổi tên ảnh thành ảnh mới bạn vừa tải */
        background-image: url('assets/images/tower_defense_bg.png'); 
        background-size: cover;
        background-position: center center;
        background-repeat: no-repeat;
        min-height: 90vh; /* Full màn hình hơn */
        display: flex;
        align-items: center;
        justify-content: center;
        text-align: center;
        padding: 0 20px;
        margin-bottom: -50px; /* Đẩy phần dưới lên một chút */
        z-index: 1;
    }

    /* Lớp phủ: Giảm độ tối xuống RẤT NHIỀU để ảnh nền rõ ràng hơn */
    .hero-section::before {
        content: "";
        position: absolute;
        top: 0; left: 0; width: 100%; height: 100%;
        /* Lớp phủ mỏng hơn nhiều, chỉ hơi tối ở phía dưới để nối liền */
        background: linear-gradient(to bottom, rgba(0,0,0,0.05), rgba(0,0,0,0.3) 70%, #0a0e1a 100%);
        z-index: 1;
    }

    /* Nội dung chính */
    .hero-content {
        position: relative;
        z-index: 2;
        max-width: 900px;
        animation: fadeInUp 1s ease-out;
    }

    .hero-content h1 {
        font-size: 4.5em;
        color: #ffffff; /* Giữ màu trắng cho nổi bật */
        /* Bóng chữ đậm hơn để đọc được trên nền trời sáng */
        text-shadow: 0 4px 15px rgba(0, 0, 0, 0.9); 
        margin-bottom: 15px;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 3px;
    }

    .hero-content p {
        font-size: 1.5em;
        line-height: 1.6;
        margin-bottom: 40px;
        color: #f0f0f0; /* Giữ màu trắng xám */
        font-weight: 600;
        text-shadow: 0 2px 10px rgba(0,0,0,0.95); /* Bóng đen rất đậm cho chữ nhỏ */
    }

    /* Nút "Bắt đầu chơi" */
    .btn-play {
        display: inline-block;
        /* Đổi sang màu xanh ngọc/xanh lá tươi sáng cho phù hợp với nền ngày */
        background: linear-gradient(135deg, #32cd32 0%, #00b050 100%); 
        color: #fff;
        padding: 22px 55px;
        font-size: 1.6em;
        font-weight: 800;
        text-decoration: none;
        border-radius: 50px;
        box-shadow: 0 10px 30px rgba(50, 205, 50, 0.4);
        transition: transform 0.2s, box-shadow 0.2s;
        text-transform: uppercase;
        border: 2px solid rgba(255,255,255,0.4);
        text-shadow: 0 1px 2px rgba(0,0,0,0.3);
    }

    .btn-play:hover {
        transform: translateY(-5px) scale(1.02);
        box-shadow: 0 15px 40px rgba(50, 205, 50, 0.6);
        background: linear-gradient(135deg, #00cd32 0%, #00e050 100%);
    }

    /* --- CÁC SECTION KHÁC (Giữ nguyên màu tối để tương phản) --- */
    section {
        padding: 100px 20px;
        text-align: center;
        border-bottom: 1px solid rgba(255,255,255,0.05);
    }

    section h2 {
        font-size: 2.8em;
        color: #00e0ff;
        margin-bottom: 60px;
        text-transform: uppercase;
        text-shadow: 0 0 15px rgba(0, 224, 255, 0.3);
        font-weight: 700;
    }
    
    section h2::after {
        content: ''; display: block; width: 60%; height: 4px;
        background: #00e0ff; margin: 10px auto 0; border-radius: 2px;
    }

    /* --- FEATURES --- */
    .features { background-color: #0b0e14; }
    .feature-list {
        display: flex; flex-wrap: wrap; justify-content: center; gap: 30px;
    }
    .feature {
        background: linear-gradient(145deg, #151923, #0f1118);
        padding: 40px 30px;
        border-radius: 16px;
        border: 1px solid rgba(255,255,255,0.05);
        flex: 1 1 300px; max-width: 350px;
        transition: 0.3s;
    }
    .feature:hover {
        transform: translateY(-10px);
        border-color: #32cd32; /* Đổi màu hover sang xanh lá cây cho đồng bộ */
        box-shadow: 0 10px 30px rgba(50, 205, 50, 0.1);
    }
    .feature h3 { font-size: 1.5em; margin: 20px 0 10px; color: #fff; }

    /* --- HOW TO PLAY --- */
    .how-to-play { background-color: #0f1118; }
    .steps-container { display: flex; flex-wrap: wrap; justify-content: center; gap: 40px; }
    .step { flex: 1 1 250px; max-width: 300px; }
    .step-icon {
        font-size: 4em; color: #00e0ff; margin-bottom: 20px;
        text-shadow: 0 0 20px rgba(0, 224, 255, 0.4);
    }
    .step h3 { color: #fff; font-size: 1.4em; margin-bottom: 10px; }

    /* --- CTA --- */
    .cta-section {
        background: linear-gradient(45deg, #1a0b2e, #0b1a2e);
        padding: 120px 20px;
    }
    .btn-cta-register {
        display: inline-block;
        background: #00e0ff;
        color: #000;
        padding: 15px 40px;
        font-size: 1.2em;
        font-weight: 700;
        border-radius: 8px;
        text-decoration: none;
        box-shadow: 0 0 20px rgba(0, 224, 255, 0.4);
        transition: 0.3s;
    }
    .btn-cta-register:hover {
        background: #fff;
        transform: scale(1.05);
    }

    /* --- COMMUNITY --- */
    .community-links { display: flex; justify-content: center; gap: 20px; margin-top: 40px; }
    .btn-community {
        padding: 15px 30px;
        background: rgba(255,255,255,0.05);
        border: 1px solid rgba(255,255,255,0.2);
        color: #00e0ff;
        text-decoration: none;
        border-radius: 8px;
        font-weight: 600;
        transition: 0.3s;
        display: flex; align-items: center; gap: 10px;
    }
    .btn-community:hover {
        background: #00e0ff; color: #000; border-color: #00e0ff;
    }

    @keyframes fadeInUp {
        from { opacity: 0; transform: translateY(30px); }
        to { opacity: 1; transform: translateY(0); }
    }

    @media (max-width: 768px) {
        .hero-content h1 { font-size: 2.5em; }
        .hero-content p { font-size: 1.1em; }
        .btn-play { padding: 15px 30px; font-size: 1.2em; }
    }
</style>

<div class="hero-section">
    <div class="hero-content">
        <h1>🛡️ Game Thủ Thành</h1>
        <p>Bảo vệ vương quốc khỏi đám quái vật hắc ám!<br>
           Xây dựng chiến lược, nâng cấp pháo đài và trở thành huyền thoại.</p>
        <a href="game.php" class="btn-play">🎮 Bắt đầu chiến đấu</a>
    </div>
</div>

<section class="features">
    <h2>Điểm Nổi Bật</h2>
    <div class="feature-list">
        <div class="feature">
            <div style="font-size: 3em; margin-bottom: 15px;">🗺️</div>
            <h3>Bản đồ đa dạng</h3>
            <p>Khám phá từ Rừng Rậm, Sa Mạc Cát Cháy đến Vùng Đất Băng Giá với độ khó tăng dần.</p>
        </div>
        <div class="feature">
            <div style="font-size: 3em; margin-bottom: 15px;">🏰</div>
            <h3>Hệ thống Tháp</h3>
            <p>4 loại tháp cơ bản với 3 cấp độ nâng cấp. Tùy chỉnh chiến thuật phòng thủ của riêng bạn.</p>
        </div>
        <div class="feature">
            <div style="font-size: 3em; margin-bottom: 15px;">🔥</div>
            <h3>Hiệu ứng Mãn nhãn</h3>
            <p>Đồ họa phong cách Neon-Dark, âm thanh chiến đấu sống động và hiệu ứng phép thuật rực rỡ.</p>
        </div>
    </div>
</section>

<section class="how-to-play">
    <h2>Cách Chơi</h2>
    <div class="steps-container">
        <div class="step">
            <div class="step-icon"><i class="fa-solid fa-chess-rook"></i></div>
            <h3>1. Xây Tháp</h3>
            <p>Dùng vàng khởi điểm để đặt tháp tại các vị trí chiến lược dọc đường đi.</p>
        </div>
        <div class="step">
            <div class="step-icon"><i class="fa-solid fa-circle-chevron-up"></i></div>
            <h3>2. Nâng Cấp</h3>
            <p>Tiêu diệt quái để kiếm vàng. Dùng vàng nâng cấp sức mạnh và tầm bắn.</p>
        </div>
        <div class="step">
            <div class="step-icon"><i class="fa-solid fa-shield-virus"></i></div>
            <h3>3. Tử Thủ</h3>
            <p>Đừng để quá 10 con quái vật lọt qua cổng thành. Sống sót qua mọi đợt tấn công!</p>
        </div>
    </div>
</section>

<section class="cta-section">
    <div class="cta-content">
        <h2>Sẵn sàng tham chiến?</h2>
        <p>Đăng ký tài khoản miễn phí ngay hôm nay để lưu lại kỷ lục điểm số và tranh tài trên bảng xếp hạng toàn cầu.</p>
        <a href="auth.php" class="btn-cta-register">🚀 Đăng Ký Ngay</a>
    </div>
</section>

<section class="community-section">
    <h2>Cộng Đồng & Xếp Hạng</h2>
    <p>Bạn có đủ kỹ năng để đứng đầu Top 1 Server? Hãy xem ai đang thống trị!</p>
    <div class="community-links">
        <a href="leaderboard.php" class="btn-community"><i class="fa-solid fa-ranking-star"></i> Xem Bảng Xếp Hạng</a>
        <a href="forum_list.php" class="btn-community"><i class="fa-solid fa-comments"></i> Thảo Luận Chiến Thuật</a>
    </div>
</section>

<?php include "includes/footer.php"; ?>
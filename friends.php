<?php
session_start();
require "db/connect.php";
include "includes/header.php";

// Kiểm tra đăng nhập
if (!isset($_SESSION['user'])) {
    header("Location: auth.php");
    exit;
}
$my_id = $_SESSION['user']['id'];

// 1. Lấy danh sách BẠN BÈ (đã kết bạn)
// Lấy thông tin của đối phương (người không phải là mình)
$friends_sql = "
    SELECT u.id, u.name, u.avatar 
    FROM users u 
    JOIN friends f ON (u.id = f.sender_id OR u.id = f.receiver_id)
    WHERE (f.sender_id = $my_id OR f.receiver_id = $my_id) 
    AND f.status = 'accepted' AND u.id != $my_id
";
$friends = $conn->query($friends_sql)->fetch_all(MYSQLI_ASSOC);

// 2. Lấy danh sách LỜI MỜI (người khác gửi cho mình)
$requests_sql = "
    SELECT u.id, u.name, u.avatar 
    FROM users u 
    JOIN friends f ON u.id = f.sender_id
    WHERE f.receiver_id = $my_id AND f.status = 'pending'
";
$requests = $conn->query($requests_sql)->fetch_all(MYSQLI_ASSOC);
?>

<style>
    /* --- THEME WEB GAME (CYBERPUNK STYLE) --- */
    body {
        background-color: #0b0e14;
        color: #e2e8f0;
        font-family: 'Montserrat', sans-serif;
    }

    .friend-container {
        max-width: 900px;
        margin: 40px auto;
        padding: 0 20px;
    }

    /* SEARCH BOX GLOW */
    .search-wrapper {
        background: rgba(30, 41, 59, 0.6);
        padding: 8px;
        border-radius: 12px;
        border: 1px solid rgba(0, 247, 255, 0.2);
        box-shadow: 0 0 15px rgba(0, 247, 255, 0.1);
        display: flex;
        gap: 10px;
        margin-bottom: 40px;
        backdrop-filter: blur(10px);
    }
    .search-wrapper input {
        flex: 1;
        background: transparent;
        border: none;
        color: #fff;
        font-size: 16px;
        padding: 10px 15px;
        outline: none;
    }
    .search-wrapper button {
        background: linear-gradient(90deg, #00f7ff, #008cff);
        border: none;
        padding: 10px 30px;
        border-radius: 8px;
        color: #000;
        font-weight: 700;
        cursor: pointer;
        transition: 0.3s;
        box-shadow: 0 0 10px rgba(0, 247, 255, 0.4);
    }
    .search-wrapper button:hover {
        transform: scale(1.05);
        box-shadow: 0 0 20px rgba(0, 247, 255, 0.7);
    }

    /* TITLES */
    .section-title {
        font-size: 1.5rem;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 1px;
        color: #fff;
        margin: 40px 0 20px;
        display: flex;
        align-items: center;
        gap: 10px;
        text-shadow: 0 0 10px rgba(255, 255, 255, 0.3);
    }
    .section-title i { color: #00f7ff; }

    /* USER GRID */
    .user-list {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
        gap: 20px;
    }

    /* CARD DESIGN (GLASSMORPHISM) */
    .user-card {
        background: rgba(23, 28, 41, 0.8);
        border: 1px solid rgba(255, 255, 255, 0.08);
        border-radius: 12px;
        padding: 15px;
        display: flex;
        align-items: center;
        gap: 15px;
        transition: 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        position: relative;
        overflow: hidden;
    }
    
    .user-card:hover {
        transform: translateY(-5px);
        background: rgba(30, 41, 59, 0.9);
        border-color: #00f7ff;
        box-shadow: 0 10px 20px rgba(0, 0, 0, 0.5), 0 0 15px rgba(0, 247, 255, 0.2);
    }

    /* Avatar */
    .user-card img {
        width: 60px;
        height: 60px;
        border-radius: 12px;
        object-fit: cover;
        border: 2px solid #3f4b63;
        transition: 0.3s;
        cursor: pointer;
    }
    .user-card:hover img { border-color: #00f7ff; }

    .user-info { flex: 1; overflow: hidden; }
    .user-info h4 {
        margin: 0;
        font-size: 16px;
        font-weight: 700;
        color: #fff;
        white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    }
    .user-info a { color: inherit; text-decoration: none; transition: 0.2s; }
    .user-info a:hover { color: #00f7ff; }
    
    .user-info small {
        display: block;
        color: #94a3b8;
        font-size: 12px;
        margin-top: 3px;
    }

    /* ACTION BUTTONS */
    .btn-action {
        padding: 8px 12px; /* Thu gọn padding để vừa nhiều nút */
        border-radius: 6px;
        border: none;
        font-size: 12px;
        font-weight: 700;
        cursor: pointer;
        transition: 0.2s;
        text-transform: uppercase;
        display: inline-flex; align-items: center; justify-content: center; gap: 5px;
        text-decoration: none; /* Cho thẻ a */
    }

    /* Add Friend (Blue) */
    .btn-add { background: rgba(0, 140, 255, 0.2); color: #008cff; border: 1px solid #008cff; }
    .btn-add:hover { background: #008cff; color: #fff; box-shadow: 0 0 10px #008cff; }

    /* Accept (Green) */
    .btn-accept { background: rgba(16, 185, 129, 0.2); color: #10b981; border: 1px solid #10b981; }
    .btn-accept:hover { background: #10b981; color: #fff; box-shadow: 0 0 10px #10b981; }

    /* Remove (Red) */
    .btn-remove { background: rgba(239, 68, 68, 0.1); color: #ef4444; border: 1px solid #ef4444; }
    .btn-remove:hover { background: #ef4444; color: #fff; box-shadow: 0 0 10px #ef4444; }

    /* View Profile (White/Grey) */
    .btn-view { background: rgba(255, 255, 255, 0.1); color: #e2e8f0; border: 1px solid rgba(255, 255, 255, 0.2); }
    .btn-view:hover { background: #fff; color: #000; box-shadow: 0 0 10px rgba(255,255,255,0.5); }

    /* Disabled */
    .btn-disabled { background: transparent; border: 1px solid #475569; color: #64748b; cursor: default; }

    /* Empty State */
    .empty-state {
        text-align: center; padding: 40px; color: #64748b;
        border: 1px dashed #334155; border-radius: 12px; font-style: italic;
    }
</style>

<div class="friend-container">
    
    <div class="search-wrapper">
        <input type="text" id="searchInput" placeholder="Nhập tên ingame hoặc Email..." onkeypress="if(event.key==='Enter') searchUser()">
        <button onclick="searchUser()"><i class="fa-solid fa-search"></i> TÌM</button>
    </div>
    
    <div id="searchResults"></div>

    <?php if(count($requests) > 0): ?>
    <h3 class="section-title"><i class="fa-solid fa-envelope-open-text"></i> Lời mời kết bạn <span style="font-size:0.8em; color:#64748b; margin-left:10px">(<?= count($requests) ?>)</span></h3>
    <div class="user-list">
        <?php foreach($requests as $r): ?>
        <div class="user-card" id="card-<?= $r['id'] ?>">
            <a href="profile.php?id=<?= $r['id'] ?>">
                <img src="<?= htmlspecialchars($r['avatar']) ?>" alt="Avatar">
            </a>
            
            <div class="user-info">
                <h4><a href="profile.php?id=<?= $r['id'] ?>"><?= htmlspecialchars($r['name']) ?></a></h4>
                <small>Muốn kết bạn với bạn</small>
            </div>
            
            <div style="display:flex; gap:5px; flex-direction:column;">
                <button class="btn-action btn-accept" onclick="handleFriend('accept', <?= $r['id'] ?>)"><i class="fa-solid fa-check"></i></button>
                <button class="btn-action btn-remove" onclick="handleFriend('remove', <?= $r['id'] ?>)"><i class="fa-solid fa-xmark"></i></button>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <h3 class="section-title"><i class="fa-solid fa-users"></i> Danh sách bạn bè</h3>
    <div class="user-list">
        <?php if(count($friends) == 0): ?>
            <div class="empty-state">
                <i class="fa-solid fa-user-astronaut" style="font-size:3em; margin-bottom:15px; opacity:0.5;"></i>
                <p>Danh sách trống. Hãy tìm kiếm chiến hữu để cùng leo rank!</p>
            </div>
        <?php endif; ?>
        
        <?php foreach($friends as $f): ?>
        <div class="user-card" id="card-<?= $f['id'] ?>">
            <a href="profile.php?id=<?= $f['id'] ?>">
                <img src="<?= htmlspecialchars($f['avatar']) ?>" alt="Avatar">
            </a>
            
            <div class="user-info">
                <h4><a href="profile.php?id=<?= $f['id'] ?>"><?= htmlspecialchars($f['name']) ?></a></h4>
                <small class="status-online" style="color:#10b981;">● Online</small>
            </div>
            
            <div style="display:flex; gap:5px;">
                <a href="profile.php?id=<?= $f['id'] ?>" class="btn-action btn-view" title="Xem hồ sơ">
                    <i class="fa-solid fa-eye"></i>
                </a>
                <button class="btn-action btn-remove" title="Hủy kết bạn" onclick="if(confirm('Hủy kết bạn với <?= $f['name'] ?>?')) handleFriend('remove', <?= $f['id'] ?>)">
                    <i class="fa-solid fa-user-minus"></i>
                </button>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<script>
// 1. TÌM KIẾM NGƯỜI DÙNG
async function searchUser() {
    const keyword = document.getElementById('searchInput').value.trim();
    const resultDiv = document.getElementById('searchResults');
    
    if (!keyword) return;
    
    // Hiển thị loading
    resultDiv.innerHTML = '<p style="color:#94a3b8; text-align:center; padding:20px;"><i class="fa-solid fa-circle-notch fa-spin"></i> Đang quét dữ liệu...</p>';

    const formData = new FormData();
    formData.append('action', 'search');
    formData.append('keyword', keyword);

    try {
        // GỌI API (Đảm bảo đường dẫn đúng)
        const res = await fetch('api/api_friend.php', { method: 'POST', body: formData });
        
        if (!res.ok) throw new Error(`Lỗi HTTP: ${res.status}`);
        
        const json = await res.json();

        if (json.status === 'error') {
            resultDiv.innerHTML = `<p style="color:#ef4444; text-align:center;">${json.message}</p>`;
            return;
        }

        // Vẽ kết quả tìm kiếm
        let html = '<h3 class="section-title" style="color:#facc15"><i class="fa-solid fa-magnifying-glass"></i> Kết quả tìm kiếm</h3><div class="user-list">';
        
        if (json.data && json.data.length > 0) {
            json.data.forEach(u => {
                let btn = '';
                // Logic nút bấm dựa trên quan hệ
                if (u.rel_status === 'none') {
                    btn = `<button class="btn-action btn-add" onclick="handleFriend('add', ${u.id}, this)"><i class="fa-solid fa-user-plus"></i> Kết bạn</button>`;
                } else if (u.rel_status === 'sent') {
                    btn = `<button class="btn-action btn-disabled"><i class="fa-solid fa-paper-plane"></i> Đã gửi</button>`;
                } else if (u.rel_status === 'received') {
                    btn = `<button class="btn-action btn-accept" onclick="handleFriend('accept', ${u.id}, this)"><i class="fa-solid fa-check"></i> Đồng ý</button>`;
                } else {
                    btn = `<button class="btn-action btn-disabled" style="border-color:#10b981; color:#10b981"><i class="fa-solid fa-user-check"></i> Bạn bè</button>`;
                }

                const avatar = u.avatar ? u.avatar : 'assets/images/default_avatar.png';
                
                // Kết quả tìm kiếm cũng có nút Xem Profile
                html += `
                    <div class="user-card" style="border-color:#facc15">
                        <a href="profile.php?id=${u.id}">
                            <img src="${avatar}" alt="Avt">
                        </a>
                        <div class="user-info">
                            <h4><a href="profile.php?id=${u.id}">${u.name}</a></h4>
                            <small>${u.email}</small>
                        </div>
                        <div style="display:flex; gap:5px;">
                            <a href="profile.php?id=${u.id}" class="btn-action btn-view" title="Xem hồ sơ"><i class="fa-solid fa-eye"></i></a>
                            ${btn}
                        </div>
                    </div>`;
            });
        } else {
            html += '<div class="empty-state">Không tìm thấy người chơi nào.</div>';
        }
        html += '</div><hr style="border-color:rgba(255,255,255,0.1); margin:30px 0;">';
        resultDiv.innerHTML = html;

    } catch (error) {
        console.error(error);
        resultDiv.innerHTML = `<p style="color:#ef4444; text-align:center;">Lỗi kết nối server.</p>`;
    }
}

// 2. XỬ LÝ HÀNH ĐỘNG
async function handleFriend(action, targetId, btnElement = null) {
    if(btnElement) {
        btnElement.disabled = true;
        // Nếu là nút icon thì đổi icon xoay, nếu nút text thì đổi text
        if(btnElement.innerHTML.includes('<i')) {
             btnElement.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i>';
        } else {
             btnElement.textContent = "...";
        }
    }

    const formData = new FormData();
    formData.append('action', action);
    formData.append('target_id', targetId);

    try {
        const res = await fetch('api/api_friend.php', { method: 'POST', body: formData });
        const json = await res.json();

        if (json.status === 'success') {
            if (action === 'add' && btnElement) {
                btnElement.innerHTML = '<i class="fa-solid fa-paper-plane"></i> Đã gửi';
                btnElement.className = "btn-action btn-disabled";
            } 
            else if (action === 'accept' || action === 'remove') {
                const card = document.getElementById('card-' + targetId);
                if(card) {
                    // Hiệu ứng xóa card
                    card.style.transform = 'scale(0.9)';
                    card.style.opacity = '0';
                    setTimeout(() => { 
                        card.remove(); 
                        // Reload trang nếu là "Accept" để user chuyển xuống danh sách bạn bè
                        if(action === 'accept') location.reload(); 
                    }, 300);
                } else {
                    // Nếu đang ở màn hình tìm kiếm (không có ID card)
                    if(action === 'accept' && btnElement) {
                        btnElement.innerHTML = '<i class="fa-solid fa-user-check"></i> Bạn bè';
                        btnElement.className = "btn-action btn-disabled";
                        btnElement.style.color = "#10b981";
                        btnElement.style.borderColor = "#10b981";
                    }
                }
            }
        } else {
            alert(json.message);
            if(btnElement) {
                btnElement.disabled = false;
                // Reset lại nút nếu lỗi (cần làm kỹ hơn nếu muốn reset đúng icon)
                btnElement.innerHTML = '<i class="fa-solid fa-rotate-right"></i> Thử lại'; 
            }
        }
    } catch (error) {
        console.error(error);
        alert('Lỗi kết nối.');
        if(btnElement) btnElement.disabled = false;
    }
}
</script>

<?php include "includes/footer.php"; ?>
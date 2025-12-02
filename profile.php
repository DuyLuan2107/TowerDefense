<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once "db/connect.php";
include "includes/header.php";

date_default_timezone_set('Asia/Ho_Chi_Minh'); 

if (!isset($_SESSION['user'])) {
    header("Location: auth.php");
    exit;
}

$my_id = $_SESSION['user']['id'];
$view_id = $my_id; // Mặc định xem của mình
$is_own_profile = true;

// 1. XÁC ĐỊNH ĐANG XEM PROFILE AI
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $view_id = intval($_GET['id']);
    if ($view_id !== $my_id) {
        $is_own_profile = false;
    }
}

// 2. LẤY THÔNG TIN USER
$stmt = $conn->prepare("SELECT id, name, email, avatar, bio, last_activity, role, created_at FROM users WHERE id = ?");
$stmt->bind_param("i", $view_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if (!$user) {
    echo "<div class='profile-container' style='text-align:center; padding:50px;'><p class='alert alert-error'>Người dùng không tồn tại.</p> <a href='index.php'>Về trang chủ</a></div>";
    include "includes/footer.php"; exit;
}

// 3. KIỂM TRA QUAN HỆ BẠN BÈ
$friend_status = 'none'; // none, pending, sent, friend
if (!$is_own_profile) {
    $stmt_friend = $conn->prepare("SELECT * FROM friends WHERE (sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?)");
    $stmt_friend->bind_param("iiii", $my_id, $view_id, $view_id, $my_id);
    $stmt_friend->execute();
    $res_friend = $stmt_friend->get_result();
    
    if ($res_friend->num_rows > 0) {
        $rel = $res_friend->fetch_assoc();
        if ($rel['status'] === 'accepted') {
            $friend_status = 'friend';
        } elseif ($rel['sender_id'] == $my_id) {
            $friend_status = 'sent'; // Mình đã gửi
        } else {
            $friend_status = 'pending'; // Họ gửi cho mình
        }
    }
}

// Xử lý hiển thị
$is_online = false;
$last_activity_formatted = "Không rõ";
if (!empty($user['last_activity'])) {
    try {
        $last_activity_time = new DateTime($user['last_activity']);
        if ((new DateTime())->getTimestamp() - $last_activity_time->getTimestamp() < 300) $is_online = true;
        $last_activity_formatted = $last_activity_time->format('H:i - d/m/Y');
    } catch (Exception $e) {}
}
$join_date = !empty($user['created_at']) ? date('d/m/Y', strtotime($user['created_at'])) : "N/A";
$bio = !empty($user['bio']) ? $user['bio'] : 'Chưa có tiểu sử.';
$avatar = !empty($user['avatar']) && file_exists($user['avatar']) ? $user['avatar'] : 'uploads/avatar/default.png';

// --- THỐNG KÊ ---
$post_count = $conn->query("SELECT COUNT(id) FROM posts WHERE user_id = $view_id")->fetch_row()[0] ?? 0;
$comment_count = $conn->query("SELECT COUNT(id) FROM comments WHERE user_id = $view_id")->fetch_row()[0] ?? 0;
$high_score = $conn->query("SELECT MAX(score) FROM scores WHERE user_id = $view_id")->fetch_row()[0] ?? 0;

// --- DỮ LIỆU TABS ---
$posts_result = $conn->query("SELECT id, title, created_at FROM posts WHERE user_id = $view_id ORDER BY created_at DESC LIMIT 10");
$comments_result = $conn->query("SELECT c.content, c.created_at, p.id as pid, p.title as ptitle FROM comments c JOIN posts p ON c.post_id = p.id WHERE c.user_id = $view_id ORDER BY c.created_at DESC LIMIT 15");
$score_history_result = $conn->query("SELECT score, enemies_killed, gold_left, duration_seconds, created_at FROM scores WHERE user_id = $view_id ORDER BY score DESC LIMIT 15");
?>

<style>
    /* CSS CŨ GIỮ NGUYÊN */
    :root { --profile-bg: #ffffff; --page-bg: #f0f2f5; --text-primary: #1c1e21; --text-secondary: #65676b; --border-color: #ddd; --online-color: #31a24c; --offline-color: #8a8d91; --link-color: #0866ff; --shadow: 0 2px 8px rgba(0, 0, 0, 0.1); }
    .profile-container { max-width: 800px; margin: 2rem auto; padding: 1.5rem; background-color: var(--profile-bg); border-radius: 12px; box-shadow: var(--shadow); }
    .profile-title { text-align: center; font-size: 1.8rem; font-weight: 700; margin-bottom: 1.5rem; color: var(--text-primary); }
    .profile-header { display: flex; gap: 1.5rem; align-items: flex-start; padding-bottom: 1.5rem; border-bottom: 1px solid var(--border-color); }
    .profile-avatar { width: 120px; height: 120px; border-radius: 50%; object-fit: cover; border: 4px solid #fff; box-shadow: 0 0 10px rgba(0,0,0,0.15); flex-shrink: 0; }
    .profile-info { flex-grow: 1; }
    .profile-info h2 { margin: 0 0 0.25rem; font-size: 2rem; color: var(--text-primary); }
    .profile-info .email { font-size: 1rem; color: var(--text-secondary); margin-bottom: 0.5rem; }
    .status-badge { display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.25rem 0.75rem; border-radius: 99px; font-weight: 600; font-size: 0.9rem; }
    .status-badge.online { background-color: #e7f7ec; color: var(--online-color); }
    .status-badge.offline { background-color: #f0f0f0; color: var(--offline-color); }
    .last-activity { font-size: 0.9em; color: var(--text-secondary); margin-top: 0.5rem; }
    .profile-extra-info { display: flex; gap: 1.5rem; margin-top: 0.75rem; flex-wrap: wrap; }
    .info-item { font-size: 0.95rem; color: var(--text-secondary); }
    .info-item strong { color: var(--text-primary); font-weight: 600; margin-right: 6px; }
    .profile-stats { display: flex; justify-content: space-around; gap: 1rem; padding: 1.5rem 0; text-align: center; }
    .stat-item { background-color: #f9f9f9; padding: 1rem; border-radius: 10px; flex: 1; min-width: 120px; }
    .stat-item .stat-number { display: block; font-size: 2rem; font-weight: 700; color: var(--link-color); margin-bottom: 0.25rem; }
    .stat-item .stat-label { font-size: 0.9rem; color: var(--text-secondary); font-weight: 500; }
    .alert { padding: 1rem; border-radius: 8px; font-weight: 500; }
    .alert.alert-info { background-color: #eef5ff; color: #0a58d0; }
    .alert.alert-error { background-color: #fdeded; color: #d93025; }
    .profile-bio { font-size: 1rem; color: var(--text-primary); font-style: italic; margin: 0.75rem 0 0.5rem; padding-left: 1rem; border-left: 3px solid var(--border-color); }
    .profile-tabs-nav { display: flex; border-bottom: 1px solid var(--border-color); margin-top: 1.5rem; }
    .tab-button { padding: 0.75rem 1.5rem; cursor: pointer; background: none; border: none; font-size: 1rem; font-weight: 600; color: var(--text-secondary); border-bottom: 3px solid transparent; transition: color 0.2s, border-bottom 0.2s; }
    .tab-button:hover { color: var(--text-primary); }
    .tab-button.active { color: var(--link-color); border-bottom-color: var(--link-color); }
    .profile-tab-content { padding-top: 1.5rem; }
    .tab-pane { display: none; }
    .tab-pane.active { display: block; }
    .post-list { list-style: none; padding: 0; margin: 0; }
    .post-item { margin-bottom: 0.75rem; background-color: #f9f9f9; border-radius: 8px; transition: box-shadow 0.2s ease; }
    .post-item:hover { box-shadow: 0 2px 5px rgba(0,0,0,0.07); }
    .post-item a { display: block; padding: 1rem 1.25rem; text-decoration: none; color: var(--link-color); font-weight: 500; }
    .post-item .post-date { display: block; font-size: 0.85em; color: var(--text-secondary); margin-top: 0.25rem; padding: 0 1.25rem 1rem; }
    .comment-list { list-style: none; padding: 0; margin: 0; }
    .comment-item { margin-bottom: 1rem; padding: 1rem; background-color: #f9f9f9; border-radius: 8px; }
    .comment-item .comment-content { font-style: italic; color: var(--text-primary); margin-bottom: 0.5rem; }
    .comment-item .comment-meta { font-size: 0.9em; color: var(--text-secondary); }
    .comment-item .comment-meta a { color: var(--link-color); text-decoration: none; font-weight: 500; }
    .score-history-table { width: 100%; border-collapse: collapse; text-align: left; }
    .score-history-table th, .score-history-table td { padding: 0.75rem 1rem; border-bottom: 1px solid var(--border-color); }
    .score-history-table th { background-color: #f9f9f9; font-weight: 600; color: var(--text-secondary); font-size: 0.9rem; }
    .score-history-table td { color: var(--text-primary); }
    .score-history-table td.score-highlight { font-weight: 700; color: var(--link-color); }
    @media (max-width: 600px) { .profile-container { margin: 1rem; padding: 1rem; } .profile-header { flex-direction: column; text-align: center; } .profile-avatar { width: 100px; height: 100px; } .profile-info h2 { font-size: 1.75rem; } .profile-extra-info { justify-content: center; } .profile-stats { flex-direction: column; } .profile-bio { text-align: center; border-left: none; padding-left: 0; } .profile-tabs-nav { justify-content: space-around; } .tab-button { padding: 0.75rem 0.5rem; font-size: 0.9rem; } .score-history-table { font-size: 0.85rem; } .score-history-table th, .score-history-table td { padding: 0.5rem; } }
    .profile-status-row { display: flex; align-items: center; gap: 1rem; margin-top: 0.6rem; }
    .logout-btn-inline { padding: 0.35rem 0.8rem; background-color: #d93025; color: white; border-radius: 6px; text-decoration: none; font-size: 0.85rem; font-weight: 600; transition: 0.2s; white-space: nowrap; }
    .logout-btn-inline:hover { background-color: #b1241c; }
    .btn-update-toggle { background:#007bff; color:#fff; padding:10px 15px; border:none; border-radius:8px; cursor:pointer; margin:15px 0; font-size:1rem; }
    .update-section { background:#fff; padding:20px; border-radius:12px; box-shadow:0 2px 12px rgba(0,0,0,0.1); margin-top:10px; max-width: 500px; margin-left: auto; margin-right: auto; }
    .update-box { background:#f0f0f0; padding:15px; border-radius:10px; margin-bottom:15px; }
    .update-box button { background:#28a745; border:none; padding:10px 15px; border-radius:6px; color:#fff; cursor:pointer; }
    .btn-edit-bio { margin-top: 8px; background:#0866ff; color:white; border:none; padding:6px 12px; border-radius:6px; cursor:pointer; font-size:0.85rem; }
    .bio-edit-box { background:#f0f0f0; padding:12px; border-radius:8px; margin-top:10px; }
    .bio-textarea { width:100%; height:80px; padding:5px; border-radius:6px; border:1px solid #ccc; resize: none; }
    .btn-save-bio { margin-top:8px; background:#28a745; color:white; border:none; padding:8px 14px; border-radius:6px; cursor:pointer; }
    .btn-cancel-bio { margin-top:8px; background:#dc3545; color:white; border:none; padding:8px 14px; border-radius:6px; cursor:pointer; }
    .bio-edit-wrapper { margin-bottom: 10px; }
    .status-wrapper { margin-top: 10px; display: flex; align-items: center; gap: 10px; }
    .update-grid { display: flex; flex-direction: column; gap: 20px; }
    .update-card { background: #ffffff; border-radius: 14px; padding: 20px; box-shadow: 0 4px 12px rgba(0,0,0,0.08); transition: 0.2s; }
    .update-card:hover { transform: translateY(-3px); box-shadow: 0 6px 18px rgba(0,0,0,0.12); }
    .card-header { display: flex; align-items: center; gap: 10px; margin-bottom: 15px; }
    .card-icon { font-size: 1.6rem; }
    .card-title { font-size: 1.15rem; font-weight: 600; color: #333; }
    .input-text, .input-file { width: 94%; padding: 10px 12px; border-radius: 8px; border: 1px solid #ccc; margin-bottom: 12px; font-size: 1rem; }
    .btn-update-green { background: #28a745; border: none; padding: 10px 15px; border-radius: 8px; color: #fff; font-weight: 600; cursor: pointer; width: 100%; transition: 0.2s; }
    .btn-update-green:hover { background: #1f8b38; }
    .input-group { position: relative; }
    .toggle-password { position: absolute; right: 12px; top: 40%; transform: translateY(-50%); cursor: pointer; font-size: 1.1rem; user-select: none; }

    /* NÚT QUAY LẠI VÀ KẾT BẠN */
    .btn-back { display: inline-block; margin-bottom: 15px; color: #666; text-decoration: none; font-weight: 600; background: #f0f0f0; padding: 5px 12px; border-radius: 20px; font-size: 0.9rem; }
    .btn-back:hover { background: #e0e0e0; color: #000; }
    
    .btn-friend { padding: 6px 12px; border: none; border-radius: 6px; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 5px; font-size: 0.9rem; }
    .f-add { background: #0866ff; color: white; }
    .f-sent { background: #e4e6eb; color: #65676b; cursor: default; }
    .f-accept { background: #42b72a; color: white; }
    .f-friend { background: #e7f3ff; color: #1877f2; border: 1px solid #1877f2; }
    .f-cancel { background: #fff0f0; color: #d93025; border: 1px solid #d93025; margin-left: 5px; } /* Style cho nút Hủy */
    .f-cancel:hover { background: #ffe0e0; }
</style>

<div class="profile-container">
    <?php if (!$is_own_profile): ?>
        <?php if (isset($_GET['from']) && $_GET['from'] == 'leaderboard'): ?>
            <a href="leaderboard.php" class="btn-back">← Quay lại Bảng Xếp Hạng</a>
        <?php else: ?>
            <a href="friends.php" class="btn-back">← Quay lại Bạn Bè</a>
        <?php endif; ?>
    <?php endif; ?>

    <h2 class="profile-title">👤 <?= $is_own_profile ? 'Thông tin cá nhân' : 'Hồ sơ người chơi' ?></h2>
    
    <div class="profile-header">
        <img class="profile-avatar" src="<?= htmlspecialchars($avatar) ?>" alt="Avatar">
        
        <div class="profile-info">
            <h2><?= htmlspecialchars($user['name']) ?> 
                <?php if($user['role'] === 'admin') echo '<span style="font-size:0.6em; background:#ffc107; color:#000; padding:2px 5px; border-radius:4px; vertical-align:middle;">ADMIN</span>'; ?>
            </h2>
            <p class="email"><?= htmlspecialchars($user['email']) ?></p>

            <p class="profile-bio"><?= htmlspecialchars($bio) ?></p>

            <?php if ($is_own_profile): ?>
                <div class="bio-edit-wrapper">
                    <button class="btn-edit-bio" onclick="toggleBioForm()">✏️ Chỉnh sửa tiểu sử</button>
                    <div id="bioForm" class="bio-edit-box" style="display:none;">
                        <form action="update_profile.php" method="POST">
                            <textarea name="new_bio" class="bio-textarea"><?= htmlspecialchars($bio) ?></textarea>
                            <button type="submit" name="change_bio" class="btn-save-bio">Lưu tiểu sử</button>
                            <button type="button" class="btn-cancel-bio" onclick="toggleBioForm()">Hủy</button>
                        </form>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (!$is_own_profile): ?>
                <div style="margin: 10px 0;">
                    <?php if ($friend_status === 'none'): ?>
                        <button class="btn-friend f-add" onclick="handleProfileFriend('add', <?= $view_id ?>, this)">
                            <i class="fa-solid fa-user-plus"></i> Kết bạn
                        </button>
                    <?php elseif ($friend_status === 'sent'): ?>
                        <button class="btn-friend f-sent">
                            <i class="fa-solid fa-check"></i> Đã gửi lời mời
                        </button>
                        <button class="btn-friend f-cancel" onclick="handleProfileFriend('remove', <?= $view_id ?>, this)">
                            Hủy lời mời
                        </button>
                    <?php elseif ($friend_status === 'pending'): ?>
                        <button class="btn-friend f-accept" onclick="handleProfileFriend('accept', <?= $view_id ?>, this)">
                            <i class="fa-solid fa-user-check"></i> Chấp nhận
                        </button>
                    <?php elseif ($friend_status === 'friend'): ?>
                        <button class="btn-friend f-friend">
                            <i class="fa-solid fa-check"></i> Bạn bè
                        </button>
                        <button class="btn-friend f-cancel" onclick="if(confirm('Hủy kết bạn?')) handleProfileFriend('remove', <?= $view_id ?>, this)">
                            Hủy
                        </button>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <div class="status-wrapper">
                <?php if ($is_online): ?>
                    <span class="status-badge online">● Đang hoạt động</span>
                <?php else: ?>
                    <span class="status-badge offline">● Ngoại tuyến</span>
                <?php endif; ?>

                <?php if ($is_own_profile): ?>
                    <a href="logout.php" class="logout-btn-inline">Đăng xuất</a>
                <?php endif; ?>
            </div>

            <p class="last-activity">
                Hoạt động gần nhất: <?= $last_activity_formatted ?>
            </p>

            <div class="profile-extra-info">
                <span class="info-item"><strong>Tham gia:</strong> <?= $join_date ?></span>
            </div>
        </div>
    </div>

    <?php if ($is_own_profile): ?>
    <button class="btn-update-toggle" onclick="toggleUpdateForm()">⚙️ Cập nhật tài khoản</button>

    <div id="updateForm" class="update-section" style="display:none;">
        <?php if (!empty($_SESSION['update_error'])): ?>
            <div class="alert error"><?= $_SESSION['update_error']; unset($_SESSION['update_error']); ?></div>
        <?php endif; ?>
        <?php if (!empty($_SESSION['update_success'])): ?>
            <div class="alert success"><?= $_SESSION['update_success']; unset($_SESSION['update_success']); ?></div>
        <?php endif; ?>

        <h3 style="text-align:center; margin-bottom:20px; font-size:1.4rem;">🔧 Tùy chỉnh tài khoản</h3>
        <div class="update-grid">
            <div class="update-card">
                <div class="card-header"><span class="card-icon">🖼️</span><span class="card-title">Thay đổi Avatar</span></div>
                <form action="update_profile.php" method="POST" enctype="multipart/form-data">
                    <input type="file" name="avatar" class="input-file">
                    <button type="submit" name="change_avatar" class="btn-update-green">Cập nhật avatar</button>
                </form>
            </div>
            <div class="update-card">
                <div class="card-header"><span class="card-icon">✏️</span><span class="card-title">Đổi tên ingame</span></div>
                <form action="update_profile.php" method="POST">
                    <input type="text" name="new_name" class="input-text" placeholder="Nhập tên mới">
                    <button type="submit" name="change_name" class="btn-update-green">Cập nhật tên</button>
                </form>
            </div>
            <div class="update-card">
                <div class="card-header"><span class="card-icon">🔑</span><span class="card-title">Đổi mật khẩu</span></div>
                <form action="update_profile.php" method="POST">
                    <div class="input-group" style="margin-bottom: 12px;">
                        <input type="password" id="old-pass" name="old_password" class="input-text password-input" placeholder="Mật khẩu cũ">
                        <span class="toggle-password" onclick="showOldPass()">👁️</span>
                    </div>
                    <div class="input-group" style="margin-bottom: 12px;">
                        <input type="password" id="new-pass" name="new_password" class="input-text password-input" placeholder="Mật khẩu mới">
                        <span class="toggle-password" onclick="showNewPass()">👁️</span>
                    </div>
                    <button type="submit" name="change_password" class="btn-update-green">Đổi mật khẩu</button>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="profile-stats">
        <div class="stat-item"><span class="stat-number"><?= $post_count ?></span><span class="stat-label">Bài viết</span></div>
        <div class="stat-item"><span class="stat-number"><?= $comment_count ?></span><span class="stat-label">Bình luận</span></div>
        <div class="stat-item"><span class="stat-number"><?= number_format($high_score) ?></span><span class="stat-label">Điểm Cao Nhất</span></div>
    </div>

    <div class="profile-tabs">
        <nav class="profile-tabs-nav">
            <button class="tab-button active" data-target="#tab-posts">Bài viết</button>
            <button class="tab-button" data-target="#tab-comments">Bình luận</button>
            <button class="tab-button" data-target="#tab-scores">Lịch sử điểm</button>
        </nav>

        <div class="profile-tab-content">
            <div id="tab-posts" class="tab-pane active">
                <?php if ($posts_result->num_rows == 0): ?>
                    <p class="alert alert-info">Chưa có bài viết nào.</p>
                <?php else: ?>
                    <ul class="post-list">
                        <?php while ($p = $posts_result->fetch_assoc()): ?>
                            <li class="post-item">
                                <a href="forum_view.php?id=<?= $p['id'] ?>"><?= htmlspecialchars($p['title']) ?></a>
                                <span class="post-date">Đăng lúc: <?= date("H:i, d/m/Y", strtotime($p['created_at'])) ?></span>
                            </li>
                        <?php endwhile; ?>
                    </ul>
                <?php endif; ?>
            </div>

            <div id="tab-comments" class="tab-pane">
                <?php if ($comments_result->num_rows == 0): ?>
                    <p class="alert alert-info">Chưa có bình luận nào.</p>
                <?php else: ?>
                    <ul class="comment-list">
                        <?php while ($c = $comments_result->fetch_assoc()): ?>
                            <li class="comment-item">
                                <p class="comment-content">"<?= htmlspecialchars($c['content']) ?>"</p>
                                <span class="comment-meta">Bình luận trong bài viết <a href="forum_view.php?id=<?= $c['post_id'] ?>"><?= htmlspecialchars($c['post_title']) ?></a> • lúc <?= date("H:i, d/m/Y", strtotime($c['created_at'])) ?></span>
                            </li>
                        <?php endwhile; ?>
                    </ul>
                <?php endif; ?>
            </div>

            <div id="tab-scores" class="tab-pane">
                 <?php if ($score_history_result->num_rows == 0): ?>
                    <p class="alert alert-info">Chưa chơi ván nào.</p>
                <?php else: ?>
                    <table class="score-history-table">
                        <thead><tr><th>Điểm số</th><th>Quái (Diệt)</th><th>Vàng (Còn)</th><th>Thời gian (Giây)</th><th>Ngày chơi</th></tr></thead>
                        <tbody>
                            <?php while ($s = $score_history_result->fetch_assoc()): ?>
                            <tr>
                                <td class="score-highlight"><?= number_format($s['score']) ?></td>
                                <td><?= $s['enemies_killed'] ?></td>
                                <td><?= $s['gold_left'] ?></td>
                                <td><?= $s['duration_seconds'] ?>s</td>
                                <td><?= date("H:i, d/m/Y", strtotime($s['created_at'])) ?></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
    // JS: Tab Switch
    document.addEventListener('DOMContentLoaded', function() {
        const tabButtons = document.querySelectorAll('.tab-button');
        const tabPanes = document.querySelectorAll('.tab-pane');
        tabButtons.forEach(button => {
            button.addEventListener('click', () => {
                const targetPaneId = button.getAttribute('data-target');
                const targetPane = document.querySelector(targetPaneId);
                tabButtons.forEach(btn => btn.classList.remove('active'));
                button.classList.add('active');
                tabPanes.forEach(pane => pane.classList.remove('active'));
                if (targetPane) targetPane.classList.add('active');
            });
        });
    });

    // JS: Xử lý Kết bạn trên Profile
    async function handleProfileFriend(action, targetId, btn) {
        btn.disabled = true;
        const originalText = btn.innerHTML;
        btn.innerHTML = '...';
        
        const formData = new FormData();
        formData.append('action', action);
        formData.append('target_id', targetId);

        try {
            const res = await fetch('api/api_friend.php', { method: 'POST', body: formData });
            const json = await res.json();
            
            if(json.status === 'success') {
                location.reload(); // Reload để cập nhật trạng thái
            } else {
                alert(json.message);
                btn.disabled = false;
                btn.innerHTML = originalText;
            }
        } catch (e) {
            console.error(e);
            alert('Lỗi kết nối');
            btn.disabled = false;
            btn.innerHTML = originalText;
        }
    }

    // JS: Form Toggle
    function toggleUpdateForm() {
        let f = document.getElementById("updateForm");
        f.style.display = (f.style.display === "none" || f.style.display === "") ? "block" : "none";
        document.getElementById("bioForm").style.display = "none";
    }
    function toggleBioForm() {
        let f = document.getElementById("bioForm");
        f.style.display = (f.style.display === "none" || f.style.display === "") ? "block" : "none";
        document.getElementById("updateForm").style.display = "none";
    }
    function showOldPass() {
        let input = document.getElementById("old-pass");
        let icon = input.parentElement.querySelector(".toggle-password");
        if (input.type === "password") { input.type = "text"; icon.textContent = "🙈"; } else { input.type = "password"; icon.textContent = "👁️"; }
    }
    function showNewPass() {
        let input = document.getElementById("new-pass");
        let icon = input.parentElement.querySelector(".toggle-password");
        if (input.type === "password") { input.type = "text"; icon.textContent = "🙈"; } else { input.type = "password"; icon.textContent = "👁️"; }
    }
</script>

<?php 
if (($is_own_profile) && (!empty($_SESSION['update_error']) || !empty($_SESSION['update_success']))): ?>
<script>document.getElementById("updateForm").style.display = "block";</script>
<?php 
    unset($_SESSION['update_error']);
    unset($_SESSION['update_success']);
endif; ?>

<?php include "includes/footer.php"; ?>
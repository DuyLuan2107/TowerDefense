<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once "db/connect.php";
include "includes/header.php";

date_default_timezone_set('Asia/Ho_Chi_Minh'); 

$user_id = (int)($_GET['id'] ?? 0);
if ($user_id <= 0) {
    echo "<div class='profile-container'><p class='alert alert-error'>Không tìm thấy người dùng.</p></div>";
    include "includes/footer.php"; exit;
}

/* === THAY ĐỔI (1): Lấy thêm `bio`, `role`, `created_at` === */
$stmt = $conn->prepare("
    SELECT id, name, email, avatar, bio, last_activity, role, created_at 
    FROM users WHERE id = ?
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if (!$user) {
    echo "<div class='profile-container'><p class='alert alert-error'>Người dùng không tồn tại.</p></div>";
    include "includes/footer.php"; exit;
}

// Xử lý logic trạng thái
$is_online = false;
$last_activity_formatted = "Không rõ";
if (!empty($user['last_activity'])) {
    try {
        $last_activity_time = new DateTime($user['last_activity']);
        $now = new DateTime();
        $diff_seconds = $now->getTimestamp() - $last_activity_time->getTimestamp();
        if ($diff_seconds < 300) $is_online = true;
        $last_activity_formatted = $last_activity_time->format('H:i:s - d/m/Y');
    } catch (Exception $e) { $last_activity_formatted = $user['last_activity']; }
}

// Xử lý ngày tham gia
$join_date = "Không rõ";
if (!empty($user['created_at'])) {
    try {
        $join_date = (new DateTime($user['created_at']))->format('d/m/Y');
    } catch (Exception $e) {}
}


$bio = $user['bio'] ?? 'Người dùng này chưa có tiểu sử.';

// Đường dẫn avatar dự phòng
$avatar = $user['avatar'] ?? 'uploads/avatar/default.png';
if (empty($avatar) || !file_exists($avatar)) {
     $avatar = 'uploads/avatar/default.png';
}

/* === KHỐI THỐNG KÊ (giữ nguyên) === */
// 1. Đếm tổng số bài viết
$stmt_post_count = $conn->prepare("SELECT COUNT(id) AS total FROM posts WHERE user_id = ?");
$stmt_post_count->bind_param("i", $user_id);
$stmt_post_count->execute();
$post_count = $stmt_post_count->get_result()->fetch_assoc()['total'] ?? 0;

// 2. Đếm tổng số bình luận
$stmt_comment_count = $conn->prepare("SELECT COUNT(id) AS total FROM comments WHERE user_id = ?");
$stmt_comment_count->bind_param("i", $user_id);
$stmt_comment_count->execute();
$comment_count = $stmt_comment_count->get_result()->fetch_assoc()['total'] ?? 0;

// 3. Lấy ĐIỂM CAO NHẤT
$stmt_score = $conn->prepare("SELECT MAX(score) AS high_score FROM scores WHERE user_id = ?");
$stmt_score->bind_param("i", $user_id);
$stmt_score->execute();
$high_score = $stmt_score->get_result()->fetch_assoc()['high_score'] ?? 0;

/* === KHỐI MỚI: LẤY DỮ LIỆU CHO TABS === */

// TAB 1: Bài viết gần đây (giữ nguyên)
$stmt_posts = $conn->prepare("
    SELECT id, title, created_at FROM posts 
    WHERE user_id = ? ORDER BY created_at DESC LIMIT 10
");
$stmt_posts->bind_param("i", $user_id);
$stmt_posts->execute();
$posts_result = $stmt_posts->get_result();

// TAB 2: Bình luận gần đây (MỚI)
$stmt_comments = $conn->prepare("
    SELECT c.content, c.created_at, p.id AS post_id, p.title AS post_title 
    FROM comments c
    JOIN posts p ON c.post_id = p.id
    WHERE c.user_id = ? 
    ORDER BY c.created_at DESC 
    LIMIT 15
");
$stmt_comments->bind_param("i", $user_id);
$stmt_comments->execute();
$comments_result = $stmt_comments->get_result();

// TAB 3: Lịch sử điểm (MỚI)
$stmt_score_history = $conn->prepare("
    SELECT score, enemies_killed, gold_left, duration_seconds, created_at 
    FROM scores 
    WHERE user_id = ? 
    ORDER BY score DESC 
    LIMIT 15
");
$stmt_score_history->bind_param("i", $user_id);
$stmt_score_history->execute();
$score_history_result = $stmt_score_history->get_result();

?>

<style>
    /* CSS CŨ (giữ nguyên) */
    :root {
        --profile-bg: #ffffff; --page-bg: #f0f2f5; --text-primary: #1c1e21;
        --text-secondary: #65676b; --border-color: #ddd; --online-color: #31a24c;
        --offline-color: #8a8d91; --link-color: #0866ff; --shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    }
    .profile-container { max-width: 800px; margin: 2rem auto; padding: 1.5rem; background-color: var(--profile-bg); border-radius: 12px; box-shadow: var(--shadow); }
    .profile-header { display: flex; gap: 1.5rem; align-items: center; padding-bottom: 1.5rem; border-bottom: 1px solid var(--border-color); }
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
    
    /* === CSS MỚI (1): CHO BIO (TIỂU SỬ) === */
    .profile-bio {
        font-size: 1rem;
        color: var(--text-primary);
        font-style: italic;
        margin: 0.75rem 0 0.5rem;
        padding-left: 1rem;
        border-left: 3px solid var(--border-color);
    }

    /* === CSS MỚI (2): CHO TABS === */
    .profile-tabs-nav {
        display: flex;
        border-bottom: 1px solid var(--border-color);
        margin-top: 1.5rem;
    }
    .tab-button {
        padding: 0.75rem 1.5rem;
        cursor: pointer;
        background: none;
        border: none;
        font-size: 1rem;
        font-weight: 600;
        color: var(--text-secondary);
        border-bottom: 3px solid transparent;
        transition: color 0.2s, border-bottom 0.2s;
    }
    .tab-button:hover {
        color: var(--text-primary);
    }
    .tab-button.active {
        color: var(--link-color);
        border-bottom-color: var(--link-color);
    }

    .profile-tab-content {
        padding-top: 1.5rem;
    }
    .tab-pane {
        display: none; /* Ẩn tất cả các tab-pane */
    }
    .tab-pane.active {
        display: block; /* Hiện tab-pane đang active */
    }

    /* === CSS MỚI (3): CHO NỘI DUNG TABS (Bài viết, Bình luận, Bảng điểm) === */
    /* Tab Bài viết */
    .post-list { list-style: none; padding: 0; margin: 0; }
    .post-item { margin-bottom: 0.75rem; background-color: #f9f9f9; border-radius: 8px; transition: box-shadow 0.2s ease; }
    .post-item:hover { box-shadow: 0 2px 5px rgba(0,0,0,0.07); }
    .post-item a { display: block; padding: 1rem 1.25rem; text-decoration: none; color: var(--link-color); font-weight: 500; }
    .post-item .post-date { display: block; font-size: 0.85em; color: var(--text-secondary); margin-top: 0.25rem; padding: 0 1.25rem 1rem; }

    /* Tab Bình luận */
    .comment-list { list-style: none; padding: 0; margin: 0; }
    .comment-item {
        margin-bottom: 1rem;
        padding: 1rem;
        background-color: #f9f9f9;
        border-radius: 8px;
    }
    .comment-item .comment-content {
        font-style: italic;
        color: var(--text-primary);
        margin-bottom: 0.5rem;
    }
    .comment-item .comment-meta {
        font-size: 0.9em;
        color: var(--text-secondary);
    }
    .comment-item .comment-meta a {
        color: var(--link-color);
        text-decoration: none;
        font-weight: 500;
    }

    /* Tab Lịch sử điểm */
    .score-history-table {
        width: 100%;
        border-collapse: collapse;
        text-align: left;
    }
    .score-history-table th, 
    .score-history-table td {
        padding: 0.75rem 1rem;
        border-bottom: 1px solid var(--border-color);
    }
    .score-history-table th {
        background-color: #f9f9f9;
        font-weight: 600;
        color: var(--text-secondary);
        font-size: 0.9rem;
    }
    .score-history-table td {
        color: var(--text-primary);
    }
    .score-history-table td.score-highlight {
        font-weight: 700;
        color: var(--link-color);
    }

    /* Responsive */
    @media (max-width: 600px) {
        .profile-container { margin: 1rem; padding: 1rem; }
        .profile-header { flex-direction: column; text-align: center; }
        .profile-avatar { width: 100px; height: 100px; }
        .profile-info h2 { font-size: 1.75rem; }
        .profile-extra-info { justify-content: center; }
        .profile-stats { flex-direction: column; }
        .profile-bio { text-align: center; border-left: none; padding-left: 0; }
        .profile-tabs-nav { justify-content: space-around; }
        .tab-button { padding: 0.75rem 0.5rem; font-size: 0.9rem; }
        .score-history-table { font-size: 0.85rem; }
        .score-history-table th, .score-history-table td { padding: 0.5rem; }
    }
</style>

<div class="profile-container">

    <div class="profile-header">
        <img class="profile-avatar" src="<?= htmlspecialchars($avatar) ?>" alt="Avatar">
        
        <div class="profile-info">
            <h2><?= htmlspecialchars($user['name']) ?></h2>
            <p class="email"><?= htmlspecialchars($user['email']) ?></p>

            <p class="profile-bio"><?= htmlspecialchars($bio) ?></p>

            <?php if ($is_online): ?>
                <span class="status-badge online">● Đang hoạt động</span>
            <?php else: ?>
                <span class="status-badge offline">● Ngoại tuyến</span>
            <?php endif; ?>

            <p class="last-activity">
                Hoạt động gần nhất: <?= $last_activity_formatted ?>
            </p>

            <div class="profile-extra-info">
                <span class="info-item">
                    <strong>Tham gia:</strong> <?= $join_date ?>
                </span>
            </div>
        </div>
    </div>

    <div class="profile-stats">
        <div class="stat-item">
            <span class="stat-number"><?= $post_count ?></span>
            <span class="stat-label">Bài viết</span>
        </div>
        <div class="stat-item">
            <span class="stat-number"><?= $comment_count ?></span>
            <span class="stat-label">Bình luận</span>
        </div>
        <div class="stat-item">
            <span class="stat-number"><?= number_format($high_score) ?></span>
            <span class="stat-label">Điểm Cao Nhất</span>
        </div>
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
                    <p class="alert alert-info">Người dùng chưa đăng bài nào.</p>
                <?php else: ?>
                    <ul class="post-list">
                        <?php while ($p = $posts_result->fetch_assoc()): ?>
                            <li class="post-item">
                                <a href="forum_view.php?id=<?= $p['id'] ?>">
                                    <?= htmlspecialchars($p['title']) ?>
                                </a>
                                <span class="post-date">
                                    Đăng lúc: <?= date("H:i, d/m/Y", strtotime($p['created_at'])) ?>
                                </span>
                            </li>
                        <?php endwhile; ?>
                    </ul>
                <?php endif; ?>
            </div>

            <div id="tab-comments" class="tab-pane">
                <?php if ($comments_result->num_rows == 0): ?>
                    <p class="alert alert-info">Người dùng chưa bình luận bài nào.</p>
                <?php else: ?>
                    <ul class="comment-list">
                        <?php while ($c = $comments_result->fetch_assoc()): ?>
                            <li class="comment-item">
                                <p class="comment-content">"<?= htmlspecialchars($c['content']) ?>"</p>
                                <span class="comment-meta">
                                    Bình luận trong bài viết 
                                    <a href="forum_view.php?id=<?= $c['post_id'] ?>">
                                        <?= htmlspecialchars($c['post_title']) ?>
                                    </a>
                                    • lúc <?= date("H:i, d/m/Y", strtotime($c['created_at'])) ?>
                                </span>
                            </li>
                        <?php endwhile; ?>
                    </ul>
                <?php endif; ?>
            </div>

            <div id="tab-scores" class="tab-pane">
                 <?php if ($score_history_result->num_rows == 0): ?>
                    <p class="alert alert-info">Người dùng chưa chơi ván nào.</p>
                <?php else: ?>
                    <table class="score-history-table">
                        <thead>
                            <tr>
                                <th>Điểm số</th>
                                <th>Quái (Diệt)</th>
                                <th>Vàng (Còn)</th>
                                <th>Thời gian (Giây)</th>
                                <th>Ngày chơi</th>
                            </tr>
                        </thead>
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
    document.addEventListener('DOMContentLoaded', function() {
        const tabButtons = document.querySelectorAll('.tab-button');
        const tabPanes = document.querySelectorAll('.tab-pane');

        tabButtons.forEach(button => {
            button.addEventListener('click', () => {
                // Lấy target từ data-target
                const targetPaneId = button.getAttribute('data-target');
                const targetPane = document.querySelector(targetPaneId);

                // 1. Tắt active trên tất cả button
                tabButtons.forEach(btn => btn.classList.remove('active'));
                
                // 2. Bật active trên button vừa click
                button.classList.add('active');

                // 3. Ẩn tất cả các tab-pane
                tabPanes.forEach(pane => pane.classList.remove('active'));

                // 4. Hiển thị tab-pane tương ứng
                if (targetPane) {
                    targetPane.classList.add('active');
                }
            });
        });
    });
</script>

<?php include "includes/footer.php"; ?>
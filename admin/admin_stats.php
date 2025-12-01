<?php
// ======================== 1. CẤU HÌNH & XỬ LÝ LOGIC ========================
require_once __DIR__ . '/../includes/admin_auth.php';
require_admin();
require_once __DIR__ . '/../db/connect.php';

// --- A. XỬ LÝ BỘ LỌC NGÀY ---
$days_preset = isset($_GET['days']) ? $_GET['days'] : 30;
$start_input = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$end_input   = isset($_GET['end_date']) ? $_GET['end_date'] : '';

// 1. Xác định khoảng thời gian
if (!empty($start_input) && !empty($end_input)) {
    // Nếu chọn lịch thủ công
    $start_date = $start_input;
    $end_date   = $end_input;
} else {
    // Nếu chọn Mốc nhanh (Preset)
    $end_date = date('Y-m-d'); 
    if ($days_preset === 'all') {
        $start_date = '2020-01-01'; // Lấy từ đầu dự án
    } else {
        $d = intval($days_preset);
        if ($d < 1) $d = 30;
        $start_date = date('Y-m-d', strtotime("-$d days"));
    }
}

// --- B. CẤU HÌNH PHÂN TRANG ---
$limit = 5; // Số dòng hiển thị mỗi trang

// Lấy số trang hiện tại từ URL (Mặc định là 1)
$user_page   = isset($_GET['user_page']) ? max(1, intval($_GET['user_page'])) : 1;
$post_page   = isset($_GET['post_page']) ? max(1, intval($_GET['post_page'])) : 1;
$author_page = isset($_GET['author_page']) ? max(1, intval($_GET['author_page'])) : 1;

// Tính vị trí bắt đầu (Offset)
$user_offset   = ($user_page - 1) * $limit;
$post_offset   = ($post_page - 1) * $limit;
$author_offset = ($author_page - 1) * $limit;

// Hàm tạo URL giữ nguyên các tham số khác
function getUrl($params) {
    return '?' . http_build_query(array_merge($_GET, $params));
}

// --- C. TRUY VẤN DỮ LIỆU ---

// 1. KPI (Không phân trang)
$stmt = $conn->prepare("SELECT SUM(enemies_killed) as k, SUM(gold_left) as g, SUM(duration_seconds) as t, AVG(score) as a, MAX(score) as m FROM scores");
$stmt->execute();
$kpi = $stmt->get_result()->fetch_assoc();
$stmt->close();

// 2. USER TREND (CÓ PHÂN TRANG)
// 2a. Đếm tổng dòng
$stmt = $conn->prepare("SELECT COUNT(DISTINCT DATE(created_at)) FROM users WHERE DATE(created_at) BETWEEN ? AND ?");
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$total_user_rows = $stmt->get_result()->fetch_row()[0];
$total_user_pages = ceil($total_user_rows / $limit);
$stmt->close();

// 2b. Lấy dữ liệu
$stmt = $conn->prepare("SELECT DATE(created_at) as day, COUNT(*) as cnt FROM users WHERE DATE(created_at) BETWEEN ? AND ? GROUP BY DATE(created_at) ORDER BY day DESC LIMIT ? OFFSET ?");
$stmt->bind_param("ssii", $start_date, $end_date, $limit, $user_offset);
$stmt->execute();
$users_trend = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// 3. POSTS STATS (CÓ PHÂN TRANG)
// 3a. Đếm tổng dòng
$stmt = $conn->prepare("SELECT COUNT(DISTINCT DATE(created_at)) FROM posts WHERE DATE(created_at) BETWEEN ? AND ?");
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$total_post_rows = $stmt->get_result()->fetch_row()[0];
$total_post_pages = ceil($total_post_rows / $limit);
$stmt->close();

// 3b. Lấy dữ liệu
$stmt = $conn->prepare("SELECT DATE(created_at) as day, COUNT(*) as cnt FROM posts WHERE DATE(created_at) BETWEEN ? AND ? GROUP BY DATE(created_at) ORDER BY day DESC LIMIT ? OFFSET ?");
$stmt->bind_param("ssii", $start_date, $end_date, $limit, $post_offset);
$stmt->execute();
$posts_by_day = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// 4. TOP AUTHORS (CÓ PHÂN TRANG)
// 4a. Đếm tổng tác giả có bài viết trong khoảng thời gian này
$stmt = $conn->prepare("SELECT COUNT(DISTINCT p.user_id) FROM posts p WHERE p.created_at BETWEEN ? AND ?");
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$total_author_rows = $stmt->get_result()->fetch_row()[0];
$total_author_pages = ceil($total_author_rows / $limit);
$stmt->close();

// 4b. Lấy dữ liệu
$stmt = $conn->prepare("
    SELECT u.id, u.name, COUNT(p.id) AS num_posts
    FROM users u 
    JOIN posts p ON p.user_id = u.id
    WHERE p.created_at BETWEEN ? AND ?
    GROUP BY u.id
    ORDER BY num_posts DESC
    LIMIT ? OFFSET ?
");
$stmt->bind_param("ssii", $start_date, $end_date, $limit, $author_offset);
$stmt->execute();
$top_auth = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// ======================== 2. GIAO DIỆN NGƯỜI DÙNG ========================
$CURRENT_PAGE = 'stats';
$PAGE_TITLE = 'Analytics Center';
require_once __DIR__ . '/admin_header.php';
?>

<style>
    :root {
        --filter-bg: #1a1d2d; --input-bg: #252a3e; --border-color: #3f4b63;
        --primary-btn: #8b5cf6; --text-label: #a0aec0;
    }
    /* Filter Styles */
    .filter-container { display: flex; align-items: center; gap: 15px; background: var(--filter-bg); padding: 12px 20px; border-radius: 12px; border: 1px solid #2d3446; flex-wrap: wrap; }
    .filter-group { display: flex; align-items: center; gap: 10px; }
    .filter-label { color: var(--text-label); font-size: 12px; font-weight: 600; text-transform: uppercase; }
    .filter-input { padding: 8px 12px; border-radius: 8px; background: var(--input-bg); color: #fff; border: 1px solid var(--border-color); outline: none; font-size: 13px; }
    .vertical-divider { width: 1px; height: 24px; background-color: var(--border-color); margin: 0 5px; }
    .btn-filter { padding: 8px 20px; border-radius: 8px; border: none; background: var(--primary-btn); color: #fff; cursor: pointer; font-weight: 700; margin-left: 5px; }
    
    /* Pagination Styles */
    .pagination { display: flex; justify-content: center; align-items: center; gap: 8px; margin-top: auto; padding-top: 15px; border-top: 1px solid #2d3446; }
    .page-btn { padding: 4px 10px; border-radius: 6px; background: var(--input-bg); color: #a0aec0; text-decoration: none; font-size: 12px; border: 1px solid var(--border-color); transition: 0.2s; }
    .page-btn:hover:not(.disabled) { background: var(--primary-btn); color: #fff; border-color: var(--primary-btn); }
    .page-btn.disabled { opacity: 0.5; cursor: not-allowed; pointer-events: none; }
    .page-info { font-size: 12px; color: #fff; font-weight: 600; }
    
    .table-wrap { display: flex; flex-direction: column; height: 100%; min-height: 350px; }
    .progress-bg { background: rgba(255,255,255,0.1); height: 6px; border-radius: 3px; width: 100px; overflow: hidden; }
    .progress-bar { height: 100%; background: var(--secondary); }
</style>

<div class="header">
    <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 20px;">
        <h1 style="margin:0">📊 Trung Tâm Phân Tích</h1>
        <form method="GET" class="filter-container">
            <div class="filter-group">
                <span class="filter-label">Mốc nhanh:</span>
                <select name="days" class="filter-input" onchange="updateDatesFromPreset(this)">
                    <option value="0" <?= $days_preset == 0 ? 'selected' : '' ?>>-- Tùy chọn --</option>
                    <option value="7" <?= $days_preset == 7 ? 'selected' : '' ?>>7 ngày qua</option>
                    <option value="30" <?= $days_preset == 30 ? 'selected' : '' ?>>30 ngày qua</option>
                    <option value="90" <?= $days_preset == 90 ? 'selected' : '' ?>>90 ngày qua</option>
                    <option value="all" <?= $days_preset === 'all' ? 'selected' : '' ?>>Tất cả</option>
                </select>
            </div>
            <div class="vertical-divider"></div>
            <div class="filter-group">
                <span class="filter-label">Tùy chỉnh:</span>
                <input type="date" name="start_date" id="startDate" class="filter-input" value="<?= htmlspecialchars($start_date) ?>">
                <span style="color:#6b7280">➝</span>
                <input type="date" name="end_date" id="endDate" class="filter-input" value="<?= htmlspecialchars($end_date) ?>">
                <button type="submit" class="btn-filter">LỌC DỮ LIỆU</button>
            </div>
        </form>
    </div>
</div>

<script>
function updateDatesFromPreset(select) {
    const val = select.value;
    const end = new Date();
    let start = new Date();
    const formatDate = (d) => d.toISOString().split('T')[0];

    if (val === 'all') {
        document.getElementById('endDate').value = formatDate(end);
        document.getElementById('startDate').value = '2020-01-01';
    } else {
        const days = parseInt(val);
        if (days > 0) {
            start.setDate(end.getDate() - days);
            document.getElementById('endDate').value = formatDate(end);
            document.getElementById('startDate').value = formatDate(start);
        }
    }
}
</script>

<div class="grid" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); margin-bottom: 24px;">
    <div class="card">
        <h3>💀 Total Kills</h3>
        <div class="value" style="color: var(--danger);"><?= number_format($kpi['k'] ?? 0) ?></div>
    </div>
    <div class="card">
        <h3>⏳ Playtime</h3>
        <div class="value" style="color: var(--secondary);"><?= number_format(($kpi['t'] ?? 0) / 3600, 1) ?>h</div>
    </div>
    <div class="card">
        <h3>💰 Gold</h3>
        <div class="value" style="color: var(--warning);"><?= number_format($kpi['g'] ?? 0) ?></div>
    </div>
    <div class="card">
        <h3>🏆 Avg Score</h3>
        <div class="value" style="color: var(--primary);"><?= number_format($kpi['a'] ?? 0) ?></div>
    </div>
</div>

<div class="grid" style="grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: var(--gap);">

    <section class="table-wrap">
        <div>
            <h3 style="margin-top:0; color: var(--success); font-size: 16px;">
                📈 User mới (<?= date('d/m/Y', strtotime($start_date)) ?> - <?= date('d/m/Y', strtotime($end_date)) ?>)
            </h3>
            <table class="table">
                <thead><tr><th>Ngày</th><th>Số lượng</th><th>Biểu đồ</th></tr></thead>
                <tbody>
                    <?php if (count($users_trend) > 0): ?>
                        <?php foreach($users_trend as $r): $percent = min(100, ($r['cnt'] * 10)); ?>
                        <tr>
                            <td><?= htmlspecialchars($r['day']) ?></td>
                            <td style="font-weight: 700;"><?= htmlspecialchars($r['cnt']) ?></td>
                            <td><div class="progress-bg"><div class="progress-bar" style="width: <?= $percent ?>%; background: var(--success);"></div></div></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="3" style="text-align:center; padding: 20px; color: #777;">Chưa có dữ liệu.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php if ($total_user_pages > 1): ?>
        <div class="pagination">
            <a href="<?= getUrl(['user_page' => $user_page - 1]) ?>" class="page-btn <?= $user_page <= 1 ? 'disabled' : '' ?>">❮</a>
            <span class="page-info"><?= $user_page ?> / <?= $total_user_pages ?></span>
            <a href="<?= getUrl(['user_page' => $user_page + 1]) ?>" class="page-btn <?= $user_page >= $total_user_pages ? 'disabled' : '' ?>">❯</a>
        </div>
        <?php endif; ?>
    </section>

    <section class="table-wrap">
        <div>
            <h3 style="margin-top:0; color: var(--primary); font-size: 16px;">
                📝 Bài viết (<?= date('d/m/Y', strtotime($start_date)) ?> - <?= date('d/m/Y', strtotime($end_date)) ?>)
            </h3>
            <table class="table">
                <thead><tr><th>Ngày</th><th>Số bài</th><th>Biểu đồ</th></tr></thead>
                <tbody>
                    <?php if (count($posts_by_day) > 0): ?>
                        <?php foreach($posts_by_day as $r): $percent = min(100, ($r['cnt'] * 5)); ?>
                        <tr>
                            <td><?= htmlspecialchars($r['day']) ?></td>
                            <td style="font-weight: 700;"><?= htmlspecialchars($r['cnt']) ?></td>
                            <td><div class="progress-bg"><div class="progress-bar" style="width: <?= $percent ?>%; background: var(--primary);"></div></div></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="3" style="text-align:center; padding: 20px; color: #777;">Chưa có dữ liệu.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php if ($total_post_pages > 1): ?>
        <div class="pagination">
            <a href="<?= getUrl(['post_page' => $post_page - 1]) ?>" class="page-btn <?= $post_page <= 1 ? 'disabled' : '' ?>">❮</a>
            <span class="page-info"><?= $post_page ?> / <?= $total_post_pages ?></span>
            <a href="<?= getUrl(['post_page' => $post_page + 1]) ?>" class="page-btn <?= $post_page >= $total_post_pages ? 'disabled' : '' ?>">❯</a>
        </div>
        <?php endif; ?>
    </section>

    <section class="table-wrap">
        <div>
            <h3 style="margin-top:0; color: var(--warning); font-size: 16px;">
                👑 Tác giả tích cực (<?= date('d/m/Y', strtotime($start_date)) ?> - <?= date('d/m/Y', strtotime($end_date)) ?>)
            </h3>
            <table class="table">
                <thead><tr><th>Tên</th><th>Số bài</th><th>Hạng</th></tr></thead>
                <tbody>
                    <?php if (count($top_auth) > 0): 
                        // Tính hạng dựa trên offset (Trang 2 bắt đầu từ hạng 6...)
                        $rank = $author_offset + 1; 
                        foreach($top_auth as $a): ?>
                    <tr>
                        <td><strong style="color: var(--text-main);"><?= htmlspecialchars($a['name']) ?></strong></td>
                        <td style="font-weight: 700; color: var(--warning);"><?= htmlspecialchars($a['num_posts']) ?></td>
                        <td>
                            <?php 
                                if($rank == 1) echo '🥇';
                                elseif($rank == 2) echo '🥈';
                                elseif($rank == 3) echo '🥉';
                                else echo '#'.$rank;
                            ?>
                        </td>
                    </tr>
                    <?php $rank++; endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="3" style="text-align:center; padding: 20px; color: #777;">Chưa có dữ liệu.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php if ($total_author_pages > 1): ?>
        <div class="pagination">
            <a href="<?= getUrl(['author_page' => $author_page - 1]) ?>" class="page-btn <?= $author_page <= 1 ? 'disabled' : '' ?>">❮</a>
            <span class="page-info"><?= $author_page ?> / <?= $total_author_pages ?></span>
            <a href="<?= getUrl(['author_page' => $author_page + 1]) ?>" class="page-btn <?= $author_page >= $total_author_pages ? 'disabled' : '' ?>">❯</a>
        </div>
        <?php endif; ?>
    </section>

</div>

<?php
$conn->close();
require_once __DIR__ . '/admin_footer.php';
?>
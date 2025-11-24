<?php
// ======================== 1. LOGIC & PROCESS ========================
require_once __DIR__ . '/../includes/admin_auth.php';
require_admin();
require_once __DIR__ . '/../db/connect.php';

// --- XỬ LÝ THAM SỐ NGÀY ---
$days_preset = isset($_GET['days']) ? intval($_GET['days']) : 30;
$start_input = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$end_input   = isset($_GET['end_date']) ? $_GET['end_date'] : '';

// Logic ưu tiên:
// 1. Nếu người dùng nhập ngày cụ thể (Custom Range) -> Dùng ngày đó.
// 2. Nếu không, dùng Preset (7, 30 ngày...) để tự tính toán.

if (!empty($start_input) && !empty($end_input)) {
    // Trường hợp: Người dùng chọn lịch thủ công
    $start_date = $start_input;
    $end_date   = $end_input;
    // Nếu ngày nhập không khớp với preset nào thì reset preset về 0 (Tùy chọn)
    // Đoạn này giữ nguyên logic hiển thị preset nếu muốn, hoặc set về 0
} else {
    // Trường hợp: Mặc định hoặc chọn Preset
    if ($days_preset < 1) $days_preset = 30;
    $end_date   = date('Y-m-d');
    $start_date = date('Y-m-d', strtotime("-$days_preset days"));
}

// --- A. KPI / GENERAL STATS (Toàn thời gian) ---
// KPI thường xem tổng quan nên ta không filter theo ngày (tùy nhu cầu của bạn)
$stmt = $conn->prepare("
    SELECT 
        SUM(enemies_killed) as total_kills, 
        SUM(gold_left) as total_gold, 
        SUM(duration_seconds) as total_time,
        AVG(score) as avg_score,
        MAX(score) as max_score
    FROM scores
");
$stmt->execute();
$kpi = $stmt->get_result()->fetch_assoc();
$stmt->close();

// --- B. USER REGISTRATION TREND (Theo khoảng ngày) ---
$stmt = $conn->prepare("
  SELECT DATE(created_at) as day, COUNT(*) as cnt
  FROM users
  WHERE DATE(created_at) BETWEEN ? AND ?
  GROUP BY DATE(created_at)
  ORDER BY day DESC
");
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$users_trend = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// --- C. POSTS STATS (Theo khoảng ngày) ---
$stmt = $conn->prepare("
  SELECT DATE(created_at) as day, COUNT(*) as cnt
  FROM posts
  WHERE DATE(created_at) BETWEEN ? AND ?
  GROUP BY DATE(created_at)
  ORDER BY day DESC
");
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$posts_by_day = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// --- D. TOP AUTHORS (Toàn thời gian) ---
$stmt = $conn->prepare("
  SELECT u.id, u.name, COUNT(p.id) AS num_posts
  FROM users u LEFT JOIN posts p ON p.user_id = u.id
  GROUP BY u.id
  ORDER BY num_posts DESC
  LIMIT 5
");
$stmt->execute();
$top_auth = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// ======================== 2. PAGE SETUP ========================
$CURRENT_PAGE = 'stats';
$PAGE_TITLE = 'Analytics Center';

require_once __DIR__ . '/admin_header.php';
?>

<style>
    :root {
        --filter-bg: #1a1d2d;
        --input-bg: #252a3e;
        --border-color: #3f4b63;
        --primary-btn: #8b5cf6;
        --primary-btn-hover: #7c3aed;
        --text-label: #a0aec0;
    }

    /* Container bộ lọc */
    .filter-container {
        display: flex;
        align-items: center;
        gap: 15px;
        background: var(--filter-bg);
        padding: 12px 20px;
        border-radius: 12px;
        border: 1px solid #2d3446;
        box-shadow: 0 4px 15px rgba(0,0,0,0.15);
        flex-wrap: wrap;
    }

    /* Các nhóm phần tử */
    .filter-group {
        display: flex;
        align-items: center;
        gap: 10px;
    }

    /* Label nhỏ */
    .filter-label {
        color: var(--text-label);
        font-size: 12px;
        font-weight: 600;
        margin-right: 5px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    /* Input & Select */
    .filter-input {
        padding: 8px 12px;
        border-radius: 8px;
        background: var(--input-bg);
        color: #fff;
        border: 1px solid var(--border-color);
        font-family: inherit;
        outline: none;
        font-size: 13px;
        transition: all 0.2s;
    }
    .filter-input:focus {
        border-color: var(--primary-btn);
        box-shadow: 0 0 0 2px rgba(139, 92, 246, 0.2);
    }

    /* Đường kẻ dọc phân cách */
    .vertical-divider {
        width: 1px;
        height: 24px;
        background-color: var(--border-color);
        margin: 0 5px;
    }

    /* Nút Lọc */
    .btn-filter {
        padding: 8px 20px;
        border-radius: 8px;
        border: none;
        background: var(--primary-btn);
        color: #fff;
        cursor: pointer;
        font-weight: 700;
        font-size: 13px;
        transition: all 0.2s;
        margin-left: 5px;
        box-shadow: 0 4px 6px rgba(139, 92, 246, 0.25);
    }
    .btn-filter:hover {
        background: var(--primary-btn-hover);
        transform: translateY(-1px);
    }

    /* Arrow icon */
    .arrow-sep { color: #6b7280; font-size: 14px; }

    /* Progress bars trong bảng */
    .progress-bg { background: rgba(255,255,255,0.1); height: 6px; border-radius: 3px; width: 100px; overflow: hidden; }
    .progress-bar { height: 100%; background: var(--secondary); }

    /* Mobile Responsive */
    @media (max-width: 768px) {
        .filter-container { flex-direction: column; align-items: stretch; gap: 10px; }
        .vertical-divider { display: none; }
        .filter-group { justify-content: space-between; }
        .btn-filter { width: 100%; margin-left: 0; margin-top: 5px; }
    }
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
                    <option value="14" <?= $days_preset == 14 ? 'selected' : '' ?>>14 ngày qua</option>
                    <option value="30" <?= $days_preset == 30 ? 'selected' : '' ?>>30 ngày qua</option>
                    <option value="60" <?= $days_preset == 60 ? 'selected' : '' ?>>60 ngày qua</option>
                    <option value="90" <?= $days_preset == 90 ? 'selected' : '' ?>>90 ngày qua</option>
                    <option value="365" <?= $days_preset == 365 ? 'selected' : '' ?>>1 năm qua</option>
                </select>
            </div>

            <div class="vertical-divider"></div>

            <div class="filter-group">
                <span class="filter-label">Tùy chỉnh:</span>
                <input type="date" name="start_date" id="startDate" class="filter-input" value="<?= htmlspecialchars($start_date) ?>" required>
                <span class="arrow-sep">➝</span>
                <input type="date" name="end_date" id="endDate" class="filter-input" value="<?= htmlspecialchars($end_date) ?>" required>
                
                <button type="submit" class="btn-filter">LỌC DỮ LIỆU</button>
            </div>
        </form>
    </div>
</div>

<script>
function updateDatesFromPreset(select) {
    const days = parseInt(select.value);
    if (days > 0) {
        const end = new Date();
        const start = new Date();
        start.setDate(end.getDate() - days);
        
        // Format YYYY-MM-DD (Xử lý múi giờ địa phương đơn giản)
        const formatDate = (date) => {
            let d = new Date(date),
                month = '' + (d.getMonth() + 1),
                day = '' + d.getDate(),
                year = d.getFullYear();

            if (month.length < 2) month = '0' + month;
            if (day.length < 2) day = '0' + day;

            return [year, month, day].join('-');
        }
        
        document.getElementById('endDate').value = formatDate(end);
        document.getElementById('startDate').value = formatDate(start);
        
        // Tự động submit form (Tùy chọn, nếu muốn bấm chọn xong load luôn)
        // select.form.submit(); 
    }
}
</script>

<div class="grid" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); margin-bottom: 24px;">
    <div class="card">
        <h3>💀 Total Kills</h3>
        <div class="value" style="color: var(--danger);"><?= number_format($kpi['total_kills'] ?? 0) ?></div>
        <div style="font-size:12px; color:var(--text-muted)">Quái vật đã bị tiêu diệt</div>
    </div>
    <div class="card">
        <h3>⏳ Total Playtime</h3>
        <div class="value" style="color: var(--secondary);"><?= number_format(($kpi['total_time'] ?? 0) / 3600, 1) ?>h</div>
        <div style="font-size:12px; color:var(--text-muted)">Giờ chơi tích lũy</div>
    </div>
    <div class="card">
        <h3>💰 Gold Hoarded</h3>
        <div class="value" style="color: var(--warning);"><?= number_format($kpi['total_gold'] ?? 0) ?></div>
        <div style="font-size:12px; color:var(--text-muted)">Vàng chưa sử dụng</div>
    </div>
    <div class="card">
        <h3>🏆 Avg Score</h3>
        <div class="value" style="color: var(--primary);"><?= number_format($kpi['avg_score'] ?? 0) ?></div>
        <div style="font-size:12px; color:var(--text-muted)">Max: <?= number_format($kpi['max_score'] ?? 0) ?></div>
    </div>
</div>

<div class="grid" style="grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: var(--gap);">

    <section class="table-wrap">
        <h3 style="margin-top:0; color: var(--success); font-size: 16px;">
            📈 User mới (<?= date('d/m', strtotime($start_date)) ?> - <?= date('d/m', strtotime($end_date)) ?>)
        </h3>
        <table class="table">
            <thead>
                <tr>
                    <th>Ngày</th>
                    <th>Đăng ký</th>
                    <th>Biểu đồ</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($users_trend) > 0): ?>
                    <?php foreach($users_trend as $r): 
                        $percent = min(100, ($r['cnt'] * 10)); // Giả sử 10 user là full cây
                    ?>
                    <tr>
                        <td><?= htmlspecialchars($r['day']) ?></td>
                        <td style="font-weight: 700;"><?= htmlspecialchars($r['cnt']) ?></td>
                        <td>
                            <div class="progress-bg">
                                <div class="progress-bar" style="width: <?= $percent ?>%; background: var(--success);"></div>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="3" style="color:var(--text-muted); text-align:center; padding: 20px;">Không có dữ liệu.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </section>

    <section class="table-wrap">
        <h3 style="margin-top:0; color: var(--primary); font-size: 16px;">
            📝 Bài viết (<?= date('d/m', strtotime($start_date)) ?> - <?= date('d/m', strtotime($end_date)) ?>)
        </h3>
        <table class="table">
            <thead>
                <tr>
                    <th>Ngày</th>
                    <th>Số bài</th>
                    <th>Biểu đồ</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($posts_by_day) > 0): ?>
                    <?php foreach($posts_by_day as $r): 
                         $percent = min(100, ($r['cnt'] * 5));
                    ?>
                    <tr>
                        <td><?= htmlspecialchars($r['day']) ?></td>
                        <td style="font-weight: 700;"><?= htmlspecialchars($r['cnt']) ?></td>
                        <td>
                             <div class="progress-bg">
                                <div class="progress-bar" style="width: <?= $percent ?>%; background: var(--primary);"></div>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="3" style="color:var(--text-muted); text-align:center; padding: 20px;">Không có dữ liệu.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </section>

    <section class="table-wrap">
        <h3 style="margin-top:0; color: var(--warning); font-size: 16px;">👑 Top Tác giả (All Time)</h3>
        <table class="table">
            <thead>
                <tr>
                    <th>Tác giả</th>
                    <th>Số bài</th>
                    <th>Rank</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($top_auth) > 0): $rank = 1; ?>
                    <?php foreach($top_auth as $a): ?>
                    <tr>
                        <td><strong style="color: var(--text-main);"><?= htmlspecialchars($a['name']) ?></strong></td>
                        <td style="font-weight: 700; color: var(--warning);"><?= htmlspecialchars($a['num_posts']) ?></td>
                        <td>
                            <?php if($rank == 1): ?>🥇<?php elseif($rank==2): ?>🥈<?php elseif($rank==3): ?>🥉<?php else: ?>#<?=$rank?><?php endif; ?>
                        </td>
                    </tr>
                    <?php $rank++; endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="3" style="color:var(--text-muted); text-align:center; padding: 20px;">Không có dữ liệu.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </section>

</div>

<?php
$conn->close();
require_once __DIR__ . '/admin_footer.php';
?>
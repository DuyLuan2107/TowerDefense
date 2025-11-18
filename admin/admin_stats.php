<?php
// ======================== 1. LOGIC & PROCESS ========================
require_once __DIR__ . '/../includes/admin_auth.php';
require_admin();
require_once __DIR__ . '/../db/connect.php';

// --- XỬ LÝ THAM SỐ NGÀY (MỚI) ---
// Mặc định là 30 ngày nếu không chọn
$days = isset($_GET['days']) ? intval($_GET['days']) : 30;
if ($days < 1) $days = 30; // Bảo vệ: không cho số âm

// --- A. KPI / GENERAL STATS (Toàn thời gian - Giữ nguyên) ---
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

// --- B. USER REGISTRATION TREND (Theo số ngày chọn) ---
// Thay đổi: Dùng tham số ? thay vì số cứng
$stmt = $conn->prepare("
  SELECT DATE(created_at) as day, COUNT(*) as cnt
  FROM users
  WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
  GROUP BY DATE(created_at)
  ORDER BY day DESC
");
$stmt->bind_param("i", $days); // Gán biến $days vào dấu hỏi
$stmt->execute();
$users_trend = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// --- C. POSTS STATS (Theo số ngày chọn) ---
// Thay đổi: Dùng tham số ? thay vì số cứng
$stmt = $conn->prepare("
  SELECT DATE(created_at) as day, COUNT(*) as cnt
  FROM posts
  WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
  GROUP BY DATE(created_at)
  ORDER BY day DESC
");
$stmt->bind_param("i", $days); // Gán biến $days vào dấu hỏi
$stmt->execute();
$posts_by_day = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// --- D. TOP AUTHORS (Toàn thời gian - Giữ nguyên) ---
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
    .progress-bg { background: rgba(255,255,255,0.1); height: 6px; border-radius: 3px; width: 100px; overflow: hidden; }
    .progress-bar { height: 100%; background: var(--secondary); }
    
    /* Style cho Form chọn ngày */
    .filter-form select {
        padding: 8px 12px;
        border-radius: 6px;
        background: var(--bg-card);
        color: var(--text-main);
        border: 1px solid var(--border-subtle);
        font-family: inherit;
        cursor: pointer;
        outline: none;
    }
    .filter-form select:focus {
        border-color: var(--primary);
    }
</style>

<div class="header">
    <div style="display: flex; align-items: center; gap: 15px;">
        <h1 style="margin:0">📊 Trung Tâm Phân Tích</h1>
        
        <!-- FORM CHỌN NGÀY (MỚI) -->
        <form method="GET" class="filter-form" style="display:flex; align-items:center; margin:0;">
            <select name="days" onchange="this.form.submit()">
                <option value="7" <?= $days == 7 ? 'selected' : '' ?>>7 ngày qua</option>
                <option value="14" <?= $days == 14 ? 'selected' : '' ?>>14 ngày qua</option>
                <option value="30" <?= $days == 30 ? 'selected' : '' ?>>30 ngày qua</option>
                <option value="60" <?= $days == 60 ? 'selected' : '' ?>>60 ngày qua</option>
                <option value="90" <?= $days == 90 ? 'selected' : '' ?>>90 ngày qua</option>
                <option value="365" <?= $days == 365 ? 'selected' : '' ?>>1 năm qua</option>
            </select>
        </form>
    </div>
</div>

<!-- KPI CARDS -->
<div class="grid" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); margin-bottom: 24px;">
    
    <div class="card">
        <h3>💀 Total Kills</h3>
        <div class="value" style="color: var(--danger);">
            <?= number_format($kpi['total_kills'] ?? 0) ?>
        </div>
        <div style="font-size:12px; color:var(--text-muted)">Quái vật đã bị tiêu diệt</div>
    </div>

    <div class="card">
        <h3>⏳ Total Playtime</h3>
        <div class="value" style="color: var(--secondary);">
            <?= number_format(($kpi['total_time'] ?? 0) / 3600, 1) ?>h
        </div>
        <div style="font-size:12px; color:var(--text-muted)">Giờ chơi tích lũy</div>
    </div>

    <div class="card">
        <h3>💰 Gold Hoarded</h3>
        <div class="value" style="color: var(--warning);">
            <?= number_format($kpi['total_gold'] ?? 0) ?>
        </div>
        <div style="font-size:12px; color:var(--text-muted)">Vàng chưa sử dụng</div>
    </div>

    <div class="card">
        <h3>🏆 Avg Score</h3>
        <div class="value" style="color: var(--primary);">
            <?= number_format($kpi['avg_score'] ?? 0) ?>
        </div>
        <div style="font-size:12px; color:var(--text-muted)">Max: <?= number_format($kpi['max_score'] ?? 0) ?></div>
    </div>

</div>

<!-- DETAILED STATS GRID -->
<div class="grid" style="grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: var(--gap);">

    <!-- Users Trend (Dynamic Title) -->
    <section class="table-wrap">
        <h3 style="margin-top:0; color: var(--success);">📈 User mới (<?= $days ?> ngày)</h3>
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
                    <tr><td colspan="3" style="color:var(--text-muted); text-align:center;">Không có dữ liệu trong <?= $days ?> ngày qua.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </section>

    <!-- Posts Stats (Dynamic Title) -->
    <section class="table-wrap">
        <h3 style="margin-top:0; color: var(--primary);">📝 Bài viết (<?= $days ?> ngày)</h3>
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
                    <tr><td colspan="3" style="color:var(--text-muted); text-align:center;">Không có dữ liệu trong <?= $days ?> ngày qua.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </section>

    <!-- Top Authors -->
    <section class="table-wrap">
        <h3 style="margin-top:0; color: var(--warning);">👑 Top Tác giả (All Time)</h3>
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
                        <td>
                            <strong style="color: var(--text-main);"><?= htmlspecialchars($a['name']) ?></strong>
                        </td>
                        <td style="font-weight: 700; color: var(--warning);"><?= htmlspecialchars($a['num_posts']) ?></td>
                        <td>
                            <?php if($rank == 1): ?>🥇<?php elseif($rank==2): ?>🥈<?php elseif($rank==3): ?>🥉<?php else: ?>#<?=$rank?><?php endif; ?>
                        </td>
                    </tr>
                    <?php $rank++; endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="3" style="color:var(--text-muted); text-align:center;">Không có dữ liệu.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </section>

</div>

<?php
$conn->close();
require_once __DIR__ . '/admin_footer.php';
?>
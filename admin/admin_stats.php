<?php
// ======================== 1. LOGIC & PROCESS ========================
require_once __DIR__ . '/../includes/admin_auth.php';
require_admin();
require_once __DIR__ . '/../db/connect.php';

// --- posts last 30 days ---
$stmt = $conn->prepare("
  SELECT DATE(created_at) as day, COUNT(*) as cnt
  FROM posts
  WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
  GROUP BY DATE(created_at)
  ORDER BY day DESC
");
$stmt->execute();
$res_posts = $stmt->get_result();
$posts_by_day = $res_posts->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// --- top 10 authors by posts ---
$stmt = $conn->prepare("
  SELECT u.id, u.name, COUNT(p.id) AS num_posts
  FROM users u LEFT JOIN posts p ON p.user_id = u.id
  GROUP BY u.id
  ORDER BY num_posts DESC
  LIMIT 10
");
$stmt->execute();
$res_auth = $stmt->get_result();
$top_auth = $res_auth->fetch_all(MYSQLI_ASSOC);
$stmt->close();


// ======================== 2. PAGE SETUP ========================
$CURRENT_PAGE = 'stats';
$PAGE_TITLE = 'Thống kê hệ thống';

// Gọi Header (đã bao gồm auth, sidebar, CSS)
require_once __DIR__ . '/admin_header.php';
?>

<div class="header">
    <h1 style="margin:0">📊 Thống kê hệ thống</h1>
    <a href="admin_panel.php" class="btn-neutral">← Quay lại Dashboard</a>
</div>

<div class="grid" style="grid-template-columns: 1fr 1fr; gap: var(--gap); margin-top: 18px;">

    <section class="table-wrap">
        <h3 style="margin-top:0;">🗓 Posts trong 30 ngày</h3>
        
        <table class="table">
            <thead>
                <tr>
                    <th>Ngày</th>
                    <th>Số bài đăng</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($posts_by_day) > 0): ?>
                    <?php foreach($posts_by_day as $r): ?>
                    <tr>
                        <td><?= htmlspecialchars($r['day']) ?></td>
                        <td style="font-weight: 700; color: var(--accent);"><?= htmlspecialchars($r['cnt']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="2" style="color:var(--muted); text-align:center;">Không có dữ liệu.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </section>

    <section class="table-wrap">
        <h3 style="margin-top:0;">🏆 Top tác giả</h3>
        
        <table class="table">
            <thead>
                <tr>
                    <th>Tác giả</th>
                    <th>Số bài</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($top_auth) > 0): ?>
                    <?php foreach($top_auth as $a): ?>
                    <tr>
                        <td><?= htmlspecialchars($a['name']) ?></td>
                        <td style="font-weight: 700; color: var(--accent);"><?= htmlspecialchars($a['num_posts']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="2" style="color:var(--muted); text-align:center;">Không có dữ liệu.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </section>

</div>


<?php
// Đóng kết nối
$conn->close();

// Gọi Footer
require_once __DIR__ . '/admin_footer.php';
?>
<?php
// =========================== 1. LOGIC & PROCESS (PHẢI Ở TRÊN CÙNG) ===========================
require_once __DIR__ . '/../db/connect.php';
require_once __DIR__ . '/../includes/admin_auth.php';
require_admin();
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/admin_log.php';

/* --- EXPORT CSV --- */
if (isset($_GET['export']) && $_GET['export']=='csv') {
    // Xử lý export ngay lập tức trước khi có bất kỳ output nào
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=scores_export_'.date('Ymd_Hi').'.csv');
    $out = fopen('php://output', 'w');
    
    // Add BOM for Excel utf-8 compatibility
    fputs($out, "\xEF\xBB\xBF"); 
    
    fputcsv($out, ['ID', 'User ID', 'User Name', 'Score', 'Enemies Killed', 'Gold Left', 'Duration (s)', 'Created At']);
    
    // Lấy dữ liệu (không phân trang)
    $res = $conn->query("SELECT s.*, u.name AS user_name FROM scores s LEFT JOIN users u ON u.id = s.user_id ORDER BY s.created_at DESC");
    while ($row = $res->fetch_assoc()) {
        fputcsv($out, [
            $row['id'],
            $row['user_id'],
            $row['user_name'],
            $row['score'],
            $row['enemies_killed'],
            $row['gold_left'],
            $row['duration_seconds'],
            $row['created_at']
        ]);
    }
    fclose($out);
    exit;
}

/* --- DELETE --- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validate($_POST['csrf_token'] ?? '')) die('Invalid CSRF');
    $action = $_POST['action'] ?? '';
    $sid = intval($_POST['score_id'] ?? 0);
    
    if ($action === 'delete' && $sid) {
        $stmt = $conn->prepare("DELETE FROM scores WHERE id = ?");
        $stmt->bind_param("i", $sid);
        $stmt->execute();
        $stmt->close();
        if (function_exists('admin_log')) admin_log($_SESSION['user']['id'], 'delete_score', 'scores', $sid);
    }
    // Redirect để tránh resubmit form
    $search = trim($_GET['search'] ?? '');
    $page = max(1, intval($_GET['page'] ?? 1));
    $query = http_build_query(['page' => $page, 'search' => $search]);
    header("Location: admin_scores.php?$query");
    exit;
}

/* --- PAGE SETUP --- */
$CURRENT_PAGE = 'scores';
$PAGE_TITLE = 'Quản lý Điểm số';
require_once __DIR__ . '/admin_header.php'; // Gọi giao diện Admin

/* --- SEARCH + PAGINATION --- */
$search = trim($_GET['search'] ?? '');
$page   = max(1, intval($_GET['page'] ?? 1));
$limit  = 20;
$offset = ($page - 1) * $limit;

$where_sql = "";
$params = [];
$types = "";

if ($search !== "") {
    $where_sql = "WHERE u.name LIKE ? OR u.id = ?";
    $params[] = "%$search%";  $types .= "s";
    $params[] = intval($search); $types .= "i";
}

/* COUNT */
$count_sql = "SELECT COUNT(*) AS total FROM scores s LEFT JOIN users u ON u.id = s.user_id $where_sql";
$stmt = $conn->prepare($count_sql);
if ($where_sql) $stmt->bind_param($types, ...$params);
$stmt->execute();
$total = $stmt->get_result()->fetch_assoc()['total'] ?? 0;
$stmt->close();

$pages = max(1, ceil($total / $limit));

/* MAIN DATA */
$sql = "
  SELECT s.id, s.user_id, s.score, s.enemies_killed, s.gold_left,
         s.duration_seconds, s.created_at, u.name AS user_name
  FROM scores s 
  LEFT JOIN users u ON u.id = s.user_id
  $where_sql
  ORDER BY s.created_at DESC
  LIMIT ?, ?
";
$stmt = $conn->prepare($sql);
if ($where_sql) {
    $types2 = $types . "ii";
    $bind = [...$params, $offset, $limit];
    $stmt->bind_param($types2, ...$bind);
} else {
    $stmt->bind_param("ii", $offset, $limit);
}
$stmt->execute();
$res = $stmt->get_result();
$stmt->close();
?>

<!-- BẮT ĐẦU NỘI DUNG TRANG -->

<div class="header">
    <h1 style="margin:0">Quản lý Scores (<?= $total ?>)</h1>
    
    <div style="display:flex; gap:10px; align-items:center;">
        <!-- Form tìm kiếm -->
        <form class="searchbar" method="get" style="margin:0;">
            <input type="search" name="search" 
                   value="<?=htmlspecialchars($search)?>" 
                   placeholder="Tìm ID hoặc Tên user...">
            <button type="submit" class="btn-neutral" style="border-radius:10px; padding: 10px 14px; border:0; cursor:pointer;">Tìm</button>
        </form>
        
        <!-- Nút Export CSV -->
        <a href="admin_scores.php?export=csv" class="btn-neutral" style="text-decoration:none; font-weight:600; display:flex; align-items:center; gap:5px;">
            <span>⬇ CSV</span>
        </a>
    </div>
</div>

<!-- Table Wrap -->
<section class="table-wrap">
    <table class="table">
        <thead>
            <tr>
                <th>ID</th>
                <th>User</th>
                <th>Score</th>
                <th>Enemies</th>
                <th>Gold</th>
                <th>Time(s)</th>
                <th>Created</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
        <?php if ($res->num_rows > 0): ?>
            <?php while ($r = $res->fetch_assoc()): ?>
            <tr>
                <td><?=htmlspecialchars($r['id'])?></td>
                <td>
                    <strong><?=htmlspecialchars($r['user_name'])?></strong>
                    <div style="color:var(--muted); font-size:12px;">ID: <?=htmlspecialchars($r['user_id'])?></div>
                </td>
                <td style="font-weight:bold; color:var(--accent);"><?=number_format($r['score'])?></td>
                <td><?=number_format($r['enemies_killed'])?></td>
                <td><?=number_format($r['gold_left'])?></td>
                <td><?=gmdate("H:i:s", $r['duration_seconds'])?></td>
                <td style="color:var(--muted); font-size:13px;"><?=htmlspecialchars($r['created_at'])?></td>
                <td>
                    <form method="post" style="display:inline;">
                        <input type="hidden" name="csrf_token" value="<?=htmlspecialchars(csrf_token())?>">
                        <input type="hidden" name="score_id" value="<?=htmlspecialchars($r['id'])?>">
                        
                        <button class="btn-danger" name="action" value="delete" 
                                onclick="return confirm('Xác nhận xóa lượt chơi này?')"
                                style="padding: 6px 10px; font-size: 12px;">
                            Xóa
                        </button>
                    </form>
                </td>
            </tr>
            <?php endwhile; ?>
        <?php else: ?>
            <tr>
                <td colspan="8" style="text-align:center; padding: 20px; color:var(--muted);">
                    Không tìm thấy dữ liệu nào.
                </td>
            </tr>
        <?php endif; ?>
        </tbody>
    </table>
</section>

<!-- Pagination -->
<div class="pagination">
    <?php 
    $q = $search ? '&search='.urlencode($search) : ''; 
    
    if ($page > 1): ?>
        <a href="?page=<?=$page-1?><?=$q?>">« Trước</a>
    <?php endif; ?>

    <?php 
    // Hiển thị rút gọn nếu quá nhiều trang
    $start = max(1, $page - 2);
    $end = min($pages, $page + 2);
    
    if ($start > 1) echo '<a href="?page=1'.$q.'">1</a><span>...</span>';

    for ($i = $start; $i <= $end; $i++): 
        if ($i == $page): ?>
            <span class="current"><?=$i?></span>
        <?php else: ?>
            <a href="?page=<?=$i?><?=$q?>"><?=$i?></a>
        <?php endif; 
    endfor; 
    
    if ($end < $pages) echo '<span>...</span><a href="?page='.$pages.$q.'">'.$pages.'</a>';
    ?>

    <?php if ($page < $pages): ?>
        <a href="?page=<?=$page+1?><?=$q?>">Tiếp »</a>
    <?php endif; ?>
</div>

<!-- KẾT THÚC NỘI DUNG TRANG -->

<?php
// Gọi Footer
require_once __DIR__ . '/admin_footer.php';
?>
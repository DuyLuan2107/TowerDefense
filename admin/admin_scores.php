<?php
// =========================== 1. CONFIG & AUTH (PHẢI Ở TRÊN CÙNG) ===========================
require_once __DIR__ . '/../db/connect.php';
require_once __DIR__ . '/../includes/admin_auth.php';
require_admin();
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/admin_log.php';

// --- CẤU HÌNH ---
$limit = 20; // Số dòng mỗi trang

// --- XỬ LÝ PARAMETERS (TÌM KIẾM & SẮP XẾP) ---
$search = trim($_GET['search'] ?? '');
$page   = max(1, intval($_GET['page'] ?? 1));
$sort_col = $_GET['sort'] ?? 'created_at'; // Cột mặc định
$sort_order = $_GET['order'] ?? 'DESC';    // Thứ tự mặc định

// Bảo mật cột sắp xếp (Whitelist)
$allowed_sort = ['id', 'score', 'enemies_killed', 'gold_left', 'duration_seconds', 'created_at'];
if (!in_array($sort_col, $allowed_sort)) {
    $sort_col = 'created_at';
}
$sort_order = (strtoupper($sort_order) === 'ASC') ? 'ASC' : 'DESC';

// Xây dựng câu WHERE cho SQL
$where_sql = "";
$params = [];
$types = "";

if ($search !== "") {
    $where_sql = "WHERE u.name LIKE ? OR s.user_id = ?";
    $params[] = "%$search%";  $types .= "s";
    $params[] = intval($search); $types .= "i";
}

// Helper tạo Link Sắp xếp
function sortLink($col, $currentCol, $currentOrder, $search) {
    $newOrder = ($currentCol === $col && $currentOrder === 'DESC') ? 'ASC' : 'DESC';
    $q = $search ? '&search='.urlencode($search) : '';
    return "?sort=$col&order=$newOrder$q";
}

// Helper tạo Mũi tên chỉ hướng
function sortArrow($col, $currentCol, $currentOrder) {
    if ($col !== $currentCol) return '<span style="color:#444; font-size:10px; margin-left:4px">↕</span>';
    return ($currentOrder === 'DESC') ? '<span style="color:var(--accent); margin-left:4px">▼</span>' : '<span style="color:var(--accent); margin-left:4px">▲</span>';
}

// =========================== 2. XỬ LÝ ACTIONS (EXPORT & DELETE) ===========================

/* --- EXPORT CSV --- */
if (isset($_GET['export']) && $_GET['export'] == 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=scores_export_'.date('Ymd_Hi').'.csv');
    $out = fopen('php://output', 'w');
    fputs($out, "\xEF\xBB\xBF"); // BOM cho Excel

    fputcsv($out, ['ID', 'User ID', 'User Name', 'Score', 'Enemies Killed', 'Gold Left', 'Duration (s)', 'Date']);

    // Query lấy tất cả dữ liệu theo bộ lọc hiện tại (không phân trang)
    $sql_export = "SELECT s.*, u.name AS user_name FROM scores s LEFT JOIN users u ON u.id = s.user_id $where_sql ORDER BY s.$sort_col $sort_order";
    $stmt = $conn->prepare($sql_export);
    if ($where_sql) $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $res = $stmt->get_result();

    while ($row = $res->fetch_assoc()) {
        fputcsv($out, [
            $row['id'], $row['user_id'], $row['user_name'],
            $row['score'], $row['enemies_killed'], $row['gold_left'],
            $row['duration_seconds'], $row['created_at']
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
        if (function_exists('admin_log')) admin_log($_SESSION['user']['id'], 'delete_score', 'scores', $sid);
    }
    
    // Redirect giữ nguyên filter
    $q_build = http_build_query(['page' => $page, 'search' => $search, 'sort' => $sort_col, 'order' => $sort_order]);
    header("Location: admin_scores.php?$q_build");
    exit;
}

// =========================== 3. LẤY DỮ LIỆU HIỂN THỊ ===========================

// Trang hiện tại
$CURRENT_PAGE = 'scores';
$PAGE_TITLE = 'Quản lý Điểm số';
require_once __DIR__ . '/admin_header.php';

// Đếm tổng số dòng (Pagination)
$count_sql = "SELECT COUNT(*) AS total FROM scores s LEFT JOIN users u ON u.id = s.user_id $where_sql";
$stmt = $conn->prepare($count_sql);
if ($where_sql) $stmt->bind_param($types, ...$params);
$stmt->execute();
$total = $stmt->get_result()->fetch_assoc()['total'] ?? 0;
$pages = max(1, ceil($total / $limit));
$offset = ($page - 1) * $limit;

// Lấy dữ liệu chính
$sql = "
  SELECT s.id, s.user_id, s.score, s.enemies_killed, s.gold_left,
         s.duration_seconds, s.created_at, u.name AS user_name, u.avatar
  FROM scores s 
  LEFT JOIN users u ON u.id = s.user_id
  $where_sql
  ORDER BY s.$sort_col $sort_order
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
?>

<div class="header">
    <h1 style="margin:0">Bảng Xếp Hạng (<?= $total ?>)</h1>
    
    <div style="display:flex; gap:10px; align-items:center;">
        <form class="searchbar" method="get" style="margin:0;">
            <input type="hidden" name="sort" value="<?=htmlspecialchars($sort_col)?>">
            <input type="hidden" name="order" value="<?=htmlspecialchars($sort_order)?>">
            
            <input type="search" name="search" value="<?=htmlspecialchars($search)?>" 
            style="width: 400px; padding: 10px; border-radius: 8px; border: 1px solid #444; background: #222; color: white;" 
            placeholder="Tìm theo Tên người chơi hoặc ID...">
            
            <button type="submit" class="btn-neutral" style="border-radius:10px; padding: 10px 14px; border:0; cursor:pointer;">Tìm</button>
        </form>
        
        <?php 
            $export_link = "?export=csv&search=".urlencode($search)."&sort=$sort_col&order=$sort_order";
        ?>
        <a href="<?=$export_link?>" class="btn-neutral" style="text-decoration:none; font-weight:600; display:flex; align-items:center; gap:5px;" title="Xuất ra Excel">
            <span>⬇ CSV</span>
        </a>
    </div>
</div>

<section class="table-wrap">
    <table class="table">
        <thead>
            <tr>
                <th>
                    <a href="<?= sortLink('id', $sort_col, $sort_order, $search) ?>" style="color:inherit; text-decoration:none; display:flex; align-items:center;">
                        STT <?= sortArrow('id', $sort_col, $sort_order) ?>
                    </a>
                </th>

                <th>Người chơi</th>
                
                <th>
                    <a href="<?= sortLink('score', $sort_col, $sort_order, $search) ?>" style="color:inherit; text-decoration:none; display:flex; align-items:center;">
                        Điểm <?= sortArrow('score', $sort_col, $sort_order) ?>
                    </a>
                </th>
                <th>
                    <a href="<?= sortLink('enemies_killed', $sort_col, $sort_order, $search) ?>" style="color:inherit; text-decoration:none; display:flex; align-items:center;">
                        Quái diệt <?= sortArrow('enemies_killed', $sort_col, $sort_order) ?>
                    </a>
                </th>
                <th>
                    <a href="<?= sortLink('gold_left', $sort_col, $sort_order, $search) ?>" style="color:inherit; text-decoration:none; display:flex; align-items:center;">
                        Vàng dư <?= sortArrow('gold_left', $sort_col, $sort_order) ?>
                    </a>
                </th>
                <th>
                    <a href="<?= sortLink('duration_seconds', $sort_col, $sort_order, $search) ?>" style="color:inherit; text-decoration:none; display:flex; align-items:center;">
                        Thời gian <?= sortArrow('duration_seconds', $sort_col, $sort_order) ?>
                    </a>
                </th>
                <th>
                    <a href="<?= sortLink('created_at', $sort_col, $sort_order, $search) ?>" style="color:inherit; text-decoration:none; display:flex; align-items:center;">
                        Ngày chơi <?= sortArrow('created_at', $sort_col, $sort_order) ?>
                    </a>
                </th>
                
                <th style="text-align:right">Hành động</th>
            </tr>
        </thead>
        <tbody>
        <?php if ($res->num_rows > 0): ?>
            <?php while ($r = $res->fetch_assoc()): ?>
            <tr>
                <td><?=htmlspecialchars($r['id'])?></td>
                <td>
                    <div style="display:flex; align-items:center; gap:10px;">
                        <?php
                            $avt = (!empty($r['avatar']) && file_exists("../".$r['avatar'])) ? "../".$r['avatar'] : "../uploads/avatar/default.png";
                        ?>
                        <img src="<?=$avt?>" style="width:30px; height:30px; border-radius:50%; object-fit:cover;">
                        <div>
                            <strong><?=htmlspecialchars($r['user_name'] ?? 'Unknown')?></strong>
                            <div style="font-size:11px; color:var(--muted);">ID: <?=$r['user_id']?></div>
                        </div>
                    </div>
                </td>
                <td style="font-weight:bold; color:var(--accent); font-size:1.1em;">
                    <?=number_format($r['score'])?>
                </td>
                <td><?=number_format($r['enemies_killed'])?></td>
                <td><?=number_format($r['gold_left'])?></td>
                <td>
                    <span style="background:rgba(255,255,255,0.05); padding:2px 6px; border-radius:4px; font-size:13px;">
                        <?=gmdate("H:i:s", $r['duration_seconds'])?>
                    </span>
                </td>
                <td style="color:var(--muted); font-size:13px;">
                    <?=date("d/m/Y H:i", strtotime($r['created_at']))?>
                </td>
                <td style="text-align:right;">
                    <form method="post" style="display:inline;">
                        <input type="hidden" name="csrf_token" value="<?=htmlspecialchars(csrf_token())?>">
                        <input type="hidden" name="score_id" value="<?=htmlspecialchars($r['id'])?>">
                        
                        <button class="btn-danger" name="action" value="delete" 
                                onclick="return confirm('Xác nhận xóa kỷ lục điểm số này?')"
                                style="padding: 6px 10px; font-size: 12px;">
                            Xóa
                        </button>
                    </form>
                </td>
            </tr>
            <?php endwhile; ?>
        <?php else: ?>
            <tr>
                <td colspan="8" style="text-align:center; padding: 40px; color:var(--muted);">
                    Không tìm thấy dữ liệu nào phù hợp.
                </td>
            </tr>
        <?php endif; ?>
        </tbody>
    </table>
</section>

<div class="pagination">
    <?php 
    // Giữ lại các tham số search và sort khi chuyển trang
    $q = '&search='.urlencode($search) . "&sort=$sort_col&order=$sort_order"; 
    
    if ($page > 1): ?>
        <a href="?page=<?=$page-1?><?=$q?>">« Trước</a>
    <?php endif; ?>

    <?php 
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

<?php
// Đóng kết nối
$stmt->close();
$conn->close();
require_once __DIR__ . '/admin_footer.php';
?>
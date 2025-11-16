<?php
require_once __DIR__ . '/../db/connect.php';
require_once __DIR__ . '/../includes/admin_auth.php';
require_admin();
require_once __DIR__ . '/../includes/csrf.php';


// ======================== PROCESS POST ========================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validate($_POST['csrf_token'] ?? '')) die('Invalid CSRF');
    $action = $_POST['action'] ?? '';
    $pid = intval($_POST['post_id'] ?? 0);

    if ($action === 'delete') {
        $stmt = $conn->prepare("DELETE FROM posts WHERE id = ?");
        $stmt->bind_param("i", $pid);
        $stmt->execute();
        $stmt->close();
    } elseif ($action === 'feature') {
        $stmt = $conn->prepare("UPDATE posts SET featured = 1 WHERE id = ?");
        $stmt->bind_param("i", $pid);
        $stmt->execute();
        $stmt->close();
    }

    header("Location: admin_posts.php");
    exit;
}


// ======================== PAGINATION + SEARCH ========================
$limit = 20;
$page = max(1, intval($_GET['page'] ?? 1));
$offset = ($page - 1) * $limit;

$search = trim($_GET['search'] ?? '');

$whereSql = "";
$params = "";
$types = "";

if ($search !== "") {
    $whereSql = "WHERE p.title LIKE ? OR u.name LIKE ?";
    $like = "%$search%";
    $params = [$like, $like];
    $types = "ss";
}


// ====== Total rows for pagination ======
$sqlCount = "SELECT COUNT(*) 
             FROM posts p 
             LEFT JOIN users u ON u.id = p.user_id 
             $whereSql";
$stmt = $conn->prepare($sqlCount);

if ($whereSql !== "") $stmt->bind_param($types, ...$params);

$stmt->execute();
$stmt->bind_result($total);
$stmt->fetch();
$stmt->close();

$totalPages = max(1, ceil($total / $limit));


// ====== Fetch posts ======
$sql = "SELECT p.id, p.title, p.user_id, p.created_at, u.name 
        FROM posts p 
        LEFT JOIN users u ON u.id = p.user_id
        $whereSql
        ORDER BY p.created_at DESC
        LIMIT $limit OFFSET $offset";

$stmt = $conn->prepare($sql);
if ($whereSql !== "") $stmt->bind_param($types, ...$params);
$stmt->execute();
$res = $stmt->get_result();
?>

<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>Quản lý bài viết</title>

<style>
    body { font-family: Arial; background:#f3f4f6; margin:0; padding:20px; }
    h1 { text-align:center; margin-bottom:25px; }
    .container {
        max-width:1100px; margin:auto; background:white;
        padding:25px; border-radius:12px; 
        box-shadow:0 4px 14px rgba(0,0,0,0.1);
    }

    /* Search Bar */
    .search-box { margin-bottom:15px; display:flex; gap:10px; }
    .search-box input {
        flex:1; padding:10px; font-size:15px;
        border:1px solid #ccc; border-radius:8px;
    }
    .search-box button {
        padding:10px 15px; background:#2563eb;
        color:white; border:none; border-radius:8px; cursor:pointer;
    }
    .search-box button:hover { background:#1e4fd6; }

    table { width:100%; border-collapse:collapse; margin-top:10px; }
    th {
        background:#2563eb; color:white;
        padding:12px; text-align:left; font-size:15px;
    }
    td {
        padding:10px 12px; border-bottom:1px solid #e5e7eb;
        font-size:14px; color:#333;
    }
    tr:hover { background:#f1f5f9; }

    a.view-btn {
        padding:6px 10px; background:#10b981; color:white;
        border-radius:6px; text-decoration:none; font-size:13px;
    }
    a.view-btn:hover { background:#0d946b; }

    .delete-btn { background:#ef4444; color:white; border:none; padding:6px 10px; border-radius:6px; }
    .delete-btn:hover { background:#c92828; }

    .pagination { margin-top:20px; text-align:center; }
    .pagination a, .pagination span {
        display:inline-block; padding:8px 12px;
        margin:0 4px; border-radius:6px; text-decoration:none;
        background:#e5e7eb; color:#333;
    }
    .pagination .current { background:#2563eb; color:white; }
    /* Back Button */
    .back-btn {
        display: inline-block;
        margin-bottom: 20px;
        background: #4b5563;
        color: white;
        padding: 8px 12px;
        border-radius: 6px;
        text-decoration: none;
    }
    .back-btn:hover { background: #374151; }
</style>
</head>

<body>
<h1>Quản lý bài viết</h1>

<div class="container">

    <a class="back-btn" href="admin_panel.php">← Quay lại Dashboard</a>
    <!-- SEARCH BAR -->
    <form class="search-box" method="get">
        <input type="text" name="search" placeholder="Tìm theo tiêu đề hoặc tác giả..." value="<?=htmlspecialchars($search)?>">
        <button type="submit">Tìm kiếm</button>
    </form>


    <!-- TABLE -->
    <table>
        <tr>
            <th>ID</th>
            <th>Tiêu đề</th>
            <th>Tác giả</th>
            <th>Ngày tạo</th>
            <th>Hành động</th>
        </tr>

    <?php while ($r = $res->fetch_assoc()): ?>
    <tr>
        <td><?=htmlspecialchars($r['id'])?></td>
        <td><?=htmlspecialchars($r['title'])?></td>
        <td><?=htmlspecialchars($r['name'])?></td>
        <td><?=htmlspecialchars($r['created_at'])?></td>
        <td>
            <a class="view-btn" href="../forum_view.php?id=<?=urlencode($r['id'])?>" target="_blank">Xem</a>

            <form method="post" style="display:inline">
                <input type="hidden" name="csrf_token" value="<?=csrf_token()?>">
                <input type="hidden" name="post_id" value="<?=htmlspecialchars($r['id'])?>">
                <button class="delete-btn" name="action" value="delete" onclick="return confirm('Xác nhận xoá?')">Xoá</button>
            </form>
        </td>
    </tr>
    <?php endwhile; ?>
    </table>


    <!-- PAGINATION -->
    <div class="pagination">
        <?php if ($page > 1): ?>
            <a href="?page=<?=$page-1?>&search=<?=urlencode($search)?>">« Trước</a>
        <?php endif; ?>

        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
            <?php if ($i == $page): ?>
                <span class="current"><?=$i?></span>
            <?php else: ?>
                <a href="?page=<?=$i?>&search=<?=urlencode($search)?>"><?=$i?></a>
            <?php endif; ?>
        <?php endfor; ?>

        <?php if ($page < $totalPages): ?>
            <a href="?page=<?=$page+1?>&search=<?=urlencode($search)?>">Tiếp »</a>
        <?php endif; ?>
    </div>

</div>

</body>
</html>

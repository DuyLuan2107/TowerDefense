<?php 
require_once __DIR__ . '/../db/connect.php';
require_once __DIR__ . '/../includes/admin_auth.php';
require_admin();
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/admin_log.php';


// =============================
// PROCESS POST
// =============================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validate($_POST['csrf_token'] ?? '')) die('Invalid CSRF');

    $action = $_POST['action'] ?? '';
    $cid = intval($_POST['comment_id'] ?? 0);

    if ($action === 'delete' && $cid) {
        $stmt = $conn->prepare("DELETE FROM comments WHERE id = ?");
        $stmt->bind_param("i", $cid);
        $stmt->execute();
        $stmt->close();

        admin_log($_SESSION['user']['id'], 'delete_comment', 'comments', $cid);

    } elseif ($action === 'hide' && $cid) {
        $stmt = $conn->prepare("UPDATE comments SET content = '[deleted by admin]' WHERE id = ?");
        $stmt->bind_param("i", $cid);
        $stmt->execute();
        $stmt->close();

        admin_log($_SESSION['user']['id'], 'hide_comment', 'comments', $cid);
    }

    header('Location: admin_comments.php');
    exit;
}


// =============================
// PAGINATION + SEARCH
// =============================
$limit = 30;
$page = max(1, intval($_GET['page'] ?? 1));
$offset = ($page - 1) * $limit;

$search = trim($_GET['search'] ?? "");

$whereSql = "";
$types = "";
$params = [];

if ($search !== "") {
    $whereSql = "WHERE c.content LIKE ? OR u.name LIKE ? OR p.title LIKE ?";
    $like = "%$search%";
    $params = [$like, $like, $like];
    $types = "sss";
}

// Count total for pagination
$sqlCount = "
SELECT COUNT(*) 
FROM comments c
LEFT JOIN users u ON u.id = c.user_id
LEFT JOIN posts p ON p.id = c.post_id
$whereSql
";

$stmt = $conn->prepare($sqlCount);
if ($whereSql !== "") $stmt->bind_param($types, ...$params);
$stmt->execute();
$stmt->bind_result($total);
$stmt->fetch();
$stmt->close();

$totalPages = max(1, ceil($total / $limit));

// Fetch comments
$sql = "
SELECT c.id, c.content, c.created_at, c.post_id, c.user_id,
       u.name AS user_name, p.title AS post_title
FROM comments c
LEFT JOIN users u ON u.id = c.user_id
LEFT JOIN posts p ON p.id = c.post_id
$whereSql
ORDER BY c.created_at DESC
LIMIT $limit OFFSET $offset
";

$stmt = $conn->prepare($sql);
if ($whereSql !== "") $stmt->bind_param($types, ...$params);
$stmt->execute();
$res = $stmt->get_result();

?>

<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>Quản lý bình luận</title>

<style>
    body {
        font-family: Arial, sans-serif;
        background: #f3f4f6;
        margin: 0; padding: 20px;
    }
    h1 {
        text-align: center;
        margin-bottom: 25px;
        color: #333;
    }
    .container {
        max-width: 1200px;
        margin: auto;
        background: white;
        padding: 25px;
        border-radius: 12px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    }

    /* Search bar */
    .search-box {
        display: flex;
        gap: 10px;
        margin-bottom: 20px;
    }
    .search-box input {
        flex: 1;
        padding: 10px;
        border-radius: 8px;
        border: 1px solid #ccc;
        font-size: 14px;
    }
    .search-box button {
        background: #2563eb;
        color: white;
        border: none;
        padding: 10px 15px;
        border-radius: 8px;
        cursor: pointer;
    }
    .search-box button:hover {
        background: #1e4fd6;
    }

    /* Table */
    table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 10px;
    }
    th {
        background: #2563eb;
        color: white;
        padding: 12px;
        text-align: left;
        font-size: 15px;
    }
    td {
        padding: 10px 12px;
        border-bottom: 1px solid #e5e7eb;
    }
    tr:hover { background: #f1f5f9; }

    /* Buttons */
    .hide-btn {
        background: #f59e0b;
        color: white;
        padding: 6px 10px;
        border: none;
        border-radius: 6px;
        cursor: pointer;
        margin-right: 4px;
    }
    .hide-btn:hover { background: #d98308; }

    .delete-btn {
        background: #ef4444;
        color: white;
        padding: 6px 10px;
        border: none;
        border-radius: 6px;
        cursor: pointer;
    }
    .delete-btn:hover { background: #c92a2a; }

    /* Pagination */
    .pagination {
        text-align: center;
        margin-top: 20px;
    }
    .pagination a, .pagination span {
        display: inline-block;
        padding: 8px 12px;
        margin: 0 4px;
        background: #e5e7eb;
        color: #333;
        border-radius: 6px;
        text-decoration: none;
    }
    .pagination .current {
        background: #2563eb;
        color: white;
    }

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

<h1>Quản lý bình luận</h1>

<div class="container">

    <a class="back-btn" href="admin_panel.php">← Quay lại Dashboard</a>

    <!-- Search -->
    <form class="search-box" method="get">
        <input type="text" name="search" placeholder="Tìm bình luận, tên user hoặc tiêu đề bài viết..."
               value="<?=htmlspecialchars($search)?>">
        <button type="submit">Tìm</button>
    </form>

    <table>
        <tr>
            <th>ID</th>
            <th>User</th>
            <th>Post</th>
            <th>Nội dung</th>
            <th>Ngày tạo</th>
            <th>Hành động</th>
        </tr>

        <?php while ($r = $res->fetch_assoc()): ?>
        <tr>
            <td><?= $r['id'] ?></td>
            <td><?= htmlspecialchars($r['user_name']) ?> (ID <?= $r['user_id'] ?>)</td>
            <td>
                <a href="../forum_view.php?id=<?= $r['post_id'] ?>" target="_blank">
                    <?= htmlspecialchars($r['post_title']) ?>
                </a>
            </td>
            <td><?= nl2br(htmlspecialchars($r['content'])) ?></td>
            <td><?= $r['created_at'] ?></td>
            <td>
                <form method="post" style="display:inline">
                    <input type="hidden" name="csrf_token" value="<?=csrf_token()?>">
                    <input type="hidden" name="comment_id" value="<?= $r['id'] ?>">

                    <button class="hide-btn" name="action" value="hide" onclick="return confirm('Ẩn bình luận?')">Ẩn</button>
                    <button class="delete-btn" name="action" value="delete" onclick="return confirm('Xóa vĩnh viễn?')">Xóa</button>
                </form>
            </td>
        </tr>
        <?php endwhile; ?>
    </table>

    <!-- Pagination -->
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

<?php
// =============================
// PROCESS POST (PHẢI Ở TRÊN CÙNG)
// =============================
// Tải các file cần thiết cho việc xử lý POST
require_once __DIR__ . '/../db/connect.php';
require_once __DIR__ . '/../includes/admin_auth.php';
require_admin(); // Xác thực admin
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/admin_log.php';

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

    }

    // Chuyển hướng về chính trang này để làm mới
    header('Location: admin_comments.php');
    exit;
}


// =============================
// PAGE SETUP
// =============================
// (Các file này đã được gọi ở trên, nhưng gọi lại bằng require_once
//  để đảm bảo an toàn nếu logic POST không chạy)
require_once __DIR__ . '/../db/connect.php';

// Định nghĩa biến cho header
$CURRENT_PAGE = 'comments'; // Giúp tô sáng link "Bình luận"
$PAGE_TITLE = 'Quản lý Bình luận';

// Gọi Header (đã bao gồm auth, sidebar, CSS)
require_once __DIR__ . '/admin_header.php';


// =============================
// PAGINATION + SEARCH (Logic lấy dữ liệu)
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

<div class="header">
    <h1 style="margin:0">Quản lý Bình luận (<?= $total ?>)</h1>
    <form class="searchbar" method="get" style="margin:0;">
        <input type="search" name="search" placeholder="Tìm bình luận, user, bài viết..."
               value="<?= htmlspecialchars($search) ?>">
        <button type="submit" class="btn-neutral" style="border-radius:10px; padding: 10px 14px; border:0; cursor:pointer;">Tìm</button>
    </form>
</div>

<section class="table-wrap">
    <table class="table">
        <thead>
            <tr>
                <th>ID</th>
                <th>User</th>
                <th>Post</th>
                <th>Nội dung</th>
                <th>Ngày tạo</th>
                <th>Hành động</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($res->num_rows > 0): ?>
                <?php while ($r = $res->fetch_assoc()): ?>
                <tr>
                    <td><?= $r['id'] ?></td>
                    <td><?= htmlspecialchars($r['user_name']) ?> (ID <?= $r['user_id'] ?>)</td>
                    <td>
                        <a href="../forum_view.php?id=<?= $r['post_id'] ?>#comment-<?= $r['id'] ?>"
                        style="color: var(--text); text-decoration: underline;">
                            <?= htmlspecialchars($r['post_title']) ?>
                        </a>
                    </td>
                    <td style="max-width:300px; word-break:break-word;">
                        <?= trim($r['content']) === '' ? '<i>[Tệp đính kèm]</i>' : nl2br(htmlspecialchars($r['content'])) ?>
                    </td>

                    <td style="color:var(--muted)"><?= $r['created_at'] ?></td>
                    <td>
                        <form method="post" style="display:inline">
                            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                            <input type="hidden" name="comment_id" value="<?= $r['id'] ?>">
                            <button class="btn-danger" name="action" value="delete" onclick="return confirm('Bạn có chắc muốn XÓA VĨNH VIỄN bình luận này?')">Xóa</button>
                        </form>
                    </td>
                </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="6" style="text-align: center; color: var(--muted);">
                        Không tìm thấy bình luận nào.
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</section>

<div class="pagination">
    <?php if ($page > 1): ?>
        <a href="?page=<?= $page-1 ?>&search=<?= urlencode($search) ?>">« Trước</a>
    <?php endif; ?>

    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
        <?php if ($i == $page): ?>
            <span class="current"><?= $i ?></span>
        <?php else: ?>
            <a href="?page=<?= $i ?>&search=<?= urlencode($search) ?>"><?= $i ?></a>
        <?php endif; ?>
    <?php endfor; ?>

    <?php if ($page < $totalPages): ?>
        <a href="?page=<?= $page+1 ?>&search=<?= urlencode($search) ?>">Tiếp »</a>
    <?php endif; ?>
</div>


<?php
// Gọi Footer
require_once __DIR__ . '/admin_footer.php';
?>
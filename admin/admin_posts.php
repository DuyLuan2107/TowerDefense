<?php
// ======================== PROCESS POST (PHẢI Ở TRÊN CÙNG) ========================
require_once __DIR__ . '/../db/connect.php';
require_once __DIR__ . '/../includes/admin_auth.php';
require_admin();
require_once __DIR__ . '/../includes/csrf.php';
// (Chúng ta có thể thêm admin_log.php nếu muốn ghi lại hành động)
// require_once __DIR__ . '/../includes/admin_log.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validate($_POST['csrf_token'] ?? '')) die('Invalid CSRF');
    $action = $_POST['action'] ?? '';
    $pid = intval($_POST['post_id'] ?? 0);

    if ($action === 'delete' && $pid) {
        $stmt = $conn->prepare("DELETE FROM posts WHERE id = ?");
        $stmt->bind_param("i", $pid);
        $stmt->execute();
        $stmt->close();
        // admin_log($_SESSION['user']['id'], 'delete_post', 'posts', $pid);

    } elseif ($action === 'feature' && $pid) {
        // Hành động: GHIM
        $stmt = $conn->prepare("UPDATE posts SET featured = 1 WHERE id = ?");
        $stmt->bind_param("i", $pid);
        $stmt->execute();
        $stmt->close();
        // admin_log($_SESSION['user']['id'], 'pin_post', 'posts', $pid);

    } elseif ($action === 'unfeature' && $pid) {
        // Hành động: BỎ GHIM
        $stmt = $conn->prepare("UPDATE posts SET featured = 0 WHERE id = ?");
        $stmt->bind_param("i", $pid);
        $stmt->execute();
        $stmt->close();
        // admin_log($_SESSION['user']['id'], 'unpin_post', 'posts', $pid);
    }

    header("Location: admin_posts.php");
    exit;
}


// ======================== PAGE SETUP ========================
// (File connect.php đã được gọi ở trên)

// Định nghĩa biến cho header
$CURRENT_PAGE = 'posts'; // Giúp tô sáng link "Bài viết"
$PAGE_TITLE = 'Quản lý Bài viết';

// Gọi Header (đã bao gồm auth, sidebar, CSS)
require_once __DIR__ . '/admin_header.php';


// ======================== PAGINATION + SEARCH (Logic lấy dữ liệu) ========================
$limit = 20;
$page = max(1, intval($_GET['page'] ?? 1));
$offset = ($page - 1) * $limit;

$search = trim($_GET['search'] ?? '');

$whereSql = "";
$params = []; 
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
$sql = "SELECT p.id, p.title, p.user_id, p.created_at, p.featured, u.name 
        FROM posts p 
        LEFT JOIN users u ON u.id = p.user_id
        $whereSql
        ORDER BY p.featured DESC, p.created_at DESC 
        LIMIT $limit OFFSET $offset"; 
        // Lưu ý: Thêm ORDER BY p.featured DESC để bài ghim lên đầu

$stmt = $conn->prepare($sql);
if ($whereSql !== "") $stmt->bind_param($types, ...$params);
$stmt->execute();
$res = $stmt->get_result();

?>

<div class="header">
    <h1 style="margin:0">Quản lý Bài viết (<?= $total ?>)</h1>
    <form class="searchbar" method="get" style="margin:0;">
        <input type="search" name="search" placeholder="Tìm theo tiêu đề hoặc tác giả..."
               value="<?= htmlspecialchars($search) ?>">
        <button type="submit" class="btn-neutral" style="border-radius:10px; padding: 10px 14px; border:0; cursor:pointer;">Tìm</button>
    </form>
</div>

<section class="table-wrap">
    <table class="table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Tiêu đề</th>
                <th>Tác giả</th>
                <th>Ngày tạo</th>
                <th>Hành động</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($res->num_rows > 0): ?>
                <?php while ($r = $res->fetch_assoc()): ?>
                <tr <?php if ($r['featured'] == 1) echo 'style="background: rgba(245, 158, 11, 0.1);"'; // Tô sáng hàng "ghim" ?> >
                    <td><?= htmlspecialchars($r['id']) ?></td>
                    <td>
                        <?php if ($r['featured'] == 1): ?>
                            <span style="color: var(--warning); font-weight: 700;" title="Đã ghim">📌 </span>
                        <?php endif; ?>
                        <?= htmlspecialchars($r['title']) ?>
                    </td>
                    <td><?= htmlspecialchars($r['name']) ?></td>
                    <td style="color:var(--muted)"><?= htmlspecialchars($r['created_at']) ?></td>
                    <td>
                        

                        <form method="post" style="display:inline">
                            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                            <input type="hidden" name="post_id" value="<?= htmlspecialchars($r['id']) ?>">
                            <a class="btn-neutral" name="action" href="../forum_view.php?id=<?= urlencode($r['id']) ?>">
                                XEM
                            </a>

                            <?php if ($r['featured'] == 0): ?>
                                <!-- Chưa ghim -> Hiện nút Ghim -->
                                <button class="btn-warning" name="action" value="feature" title="Ghim lên đầu trang">GHIM</button>
                            <?php else: ?>
                                <!-- Đã ghim -> Hiện nút Bỏ ghim -->
                                <button class="btn-neutral" name="action" value="unfeature" title="Bỏ ghim">BỎ GHIM</button>
                            <?php endif; ?>
                            
                            <button class="btn-danger" name="action" value="delete" onclick="return confirm('Xác nhận xoá bài viết này?')" >Xoá</button>
                        </form>
                    </td>
                </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="5" style="text-align: center; color: var(--muted);">
                        Không tìm thấy bài viết nào.
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
<?php
// =============================
// 1. PROCESS POST (PHẢI Ở TRÊN CÙNG)
// =============================
require_once __DIR__ . '/../db/connect.php';
require_once __DIR__ . '/../includes/admin_auth.php';
require_admin();
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/admin_log.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validate($_POST['csrf_token'] ?? '')) die('Invalid CSRF');

    $action = $_POST['action'] ?? '';
    $contact_id = intval($_POST['contact_id'] ?? 0);

    if ($action === 'delete' && $contact_id) {
        $stmt = $conn->prepare("DELETE FROM contacts WHERE id = ?");
        $stmt->bind_param("i", $contact_id);
        $stmt->execute();
        $stmt->close();
        if (function_exists('admin_log')) admin_log($_SESSION['user']['id'], 'delete_contact', 'contacts', $contact_id);

    } elseif ($action === 'mark_read' && $contact_id) {
        $stmt = $conn->prepare("UPDATE contacts SET is_read = 1 WHERE id = ?");
        $stmt->bind_param("i", $contact_id);
        $stmt->execute();
        $stmt->close();
        if (function_exists('admin_log')) admin_log($_SESSION['user']['id'], 'read_contact', 'contacts', $contact_id);
    }

    header('Location: admin_contacts.php');
    exit;
}

// =============================
// 2. PAGE SETUP & HEADER
// =============================
$CURRENT_PAGE = 'contacts';
$PAGE_TITLE = 'Hòm thư liên hệ';

// Gọi Header (Đã bao gồm Sidebar có Badge đỏ)
require_once __DIR__ . '/admin_header.php';


// =============================
// 3. PAGINATION + SEARCH LOGIC
// =============================
$limit = 30; 
$page = max(1, intval($_GET['page'] ?? 1));
$offset = ($page - 1) * $limit;

$search = trim($_GET['search'] ?? "");

$whereSql = "";
$types = "";
$params = [];

if ($search !== "") {
    $whereSql = "WHERE name LIKE ? OR email LIKE ? OR message LIKE ?";
    $like = "%$search%";
    $params = [$like, $like, $like];
    $types = "sss";
}

// Đếm tổng
$sqlCount = "SELECT COUNT(*) FROM contacts $whereSql";
$stmt = $conn->prepare($sqlCount);
if ($whereSql !== "") $stmt->bind_param($types, ...$params);
$stmt->execute();
$stmt->bind_result($total);
$stmt->fetch();
$stmt->close();

$totalPages = max(1, ceil($total / $limit));

// Lấy dữ liệu
$sql = "
SELECT * FROM contacts
$whereSql
ORDER BY is_read ASC, created_at DESC
LIMIT $limit OFFSET $offset
";
$stmt = $conn->prepare($sql);
if ($whereSql !== "") $stmt->bind_param($types, ...$params);
$stmt->execute();
$res = $stmt->get_result();
?>

<div class="header">
  <h1 style="margin:0">Hòm thư (<?= $total ?>)</h1>
  <form method="get">
    <input type="search" name="search" placeholder="Tìm tên, email, nội dung..." value="<?=htmlspecialchars($search)?>">
    <button type="submit">Tìm</button>
  </form>
</div>

<a class="btn-neutral" href="admin_panel.php" style="margin-bottom: 18px; display: inline-block;">
    ← Quay lại Dashboard
</a>

<section class="table-wrap">
  <table class="table">
    <thead>
        <tr>
            <th>ID</th>
            <th>Người gửi</th>
            <th>Email</th>
            <th>Nội dung</th>
            <th>Ngày gửi</th>
            <th>Hành động</th>
        </tr>
    </thead>
    <tbody>
        <?php if ($res->num_rows > 0): ?>
            <?php while ($r = $res->fetch_assoc()): ?>
            <tr class="<?= $r['is_read'] ? '' : 'unread' ?>">
                <td><?= $r['id'] ?></td>
                <td><?= htmlspecialchars($r['name']) ?></td>
                <td><?= htmlspecialchars($r['email']) ?></td>
                <td style="max-width: 350px; word-wrap: break-word;">
                    <?= nl2br(htmlspecialchars($r['message'])) ?>
                </td>
                <td style="color:var(--muted); font-size:13px;"><?= $r['created_at'] ?></td>
                <td class="actions">
                    <form method="post" style="display:inline">
                        <input type="hidden" name="csrf_token" value="<?=csrf_token()?>">
                        <input type="hidden" name="contact_id" value="<?= $r['id'] ?>">

                        <?php if ($r['is_read'] == 0): ?>
                        <button type="submit" class="btn-warning" name="action" value="mark_read">Đã đọc</button>
                        <?php endif; ?>
                        
                        <button type="submit" class="btn-danger" name="action" value="delete" onclick="return confirm('Xóa vĩnh viễn tin nhắn này?')">Xóa</button>
                    </form>
                </td>
            </tr>
            <?php endwhile; ?>
        <?php else: ?>
            <tr><td colspan="6" style="text-align:center; color:var(--muted); padding:20px;">Không có tin nhắn nào.</td></tr>
        <?php endif; ?>
    </tbody>
  </table>
</section>

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

<?php
require_once __DIR__ . '/admin_footer.php';
?>
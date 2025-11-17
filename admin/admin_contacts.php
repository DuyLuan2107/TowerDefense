<?php
// 1. GỌI CÁC FILE BẢO MẬT
require_once __DIR__ . '/../db/connect.php';
require_once __DIR__ . '/../includes/admin_auth.php';
require_admin(); // Bắt buộc đăng nhập admin
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/admin_log.php';

// =============================
// PROCESS POST (Xử lý hành động qua POST)
// =============================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validate($_POST['csrf_token'] ?? '')) die('Invalid CSRF');

    $action = $_POST['action'] ?? '';
    $contact_id = intval($_POST['contact_id'] ?? 0);

    if ($action === 'delete' && $contact_id) {
        $stmt = $conn->prepare("DELETE FROM contacts WHERE id = ?");
        $stmt->bind_param("i", $contact_id);
        $stmt->execute();
        $stmt->close();
        admin_log($_SESSION['user']['id'], 'delete_contact', 'contacts', $contact_id);

    } elseif ($action === 'mark_read' && $contact_id) {
        $stmt = $conn->prepare("UPDATE contacts SET is_read = 1 WHERE id = ?");
        $stmt->bind_param("i", $contact_id);
        $stmt->execute();
        $stmt->close();
        admin_log($_SESSION['user']['id'], 'read_contact', 'contacts', $contact_id);
    }

    // Tải lại trang (để xóa POST data)
    header('Location: admin_contacts.php');
    exit;
}


// =============================
// PAGINATION + SEARCH
// =============================
$limit = 30; // Số tin nhắn mỗi trang
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

// Đếm tổng số tin nhắn
$sqlCount = "SELECT COUNT(*) FROM contacts $whereSql";

$stmt = $conn->prepare($sqlCount);
if ($whereSql !== "") $stmt->bind_param($types, ...$params);
$stmt->execute();
$stmt->bind_result($total);
$stmt->fetch();
$stmt->close();

$totalPages = max(1, ceil($total / $limit));

// Lấy danh sách tin nhắn (ưu tiên chưa đọc)
$sql = "
SELECT *
FROM contacts
$whereSql
ORDER BY is_read ASC, created_at DESC
LIMIT $limit OFFSET $offset
";

$stmt = $conn->prepare($sql);
if ($whereSql !== "") $stmt->bind_param($types, ...$params);
$stmt->execute();
$res = $stmt->get_result();

?>
<!doctype html>
<html lang="vi">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width,initial-scale=1" />
<title>Admin Hòm Thư — Tower Defense</title>

<!-- Copy CSS từ admin_panel.php và thêm CSS cho pagination/buttons -->
<style>
:root{
  --bg:#0f1724; --card:#0b1220; --muted:#94a3b8; --accent:#06b6d4; --accent-2:#7c3aed;
  --text:#e6eef6; --danger:#ef4444; --warning: #f59e0b; /* Thêm màu Vàng */
  --gap:18px;
}
*{box-sizing:border-box}
body{
  margin:0;
  font-family:Inter, "Segoe UI", Roboto, Arial, sans-serif;
  background:linear-gradient(180deg,#071126 0%, #071530 100%);
  color:var(--text);
  -webkit-font-smoothing:antialiased;
  -moz-osx-font-smoothing:grayscale;
  min-height:100vh;
}

/* Layout */
.app {
  display:grid;
  grid-template-columns:260px 1fr;
  gap:var(--gap);
  padding:28px;
}

/* Sidebar */
.sidebar{
  background:linear-gradient(180deg, rgba(255,255,255,0.02), rgba(255,255,255,0.01));
  border:1px solid rgba(255,255,255,0.03);
  padding:18px;
  border-radius:12px;
  height:calc(100vh - 56px);
  position:sticky;
  top:28px;
}
.brand{
  display:flex; gap:12px; align-items:center; margin-bottom:18px;
}
.brand h2{margin:0;font-size:18px;color:var(--text)}
.nav{margin-top:12px; display:flex;flex-direction:column; gap:8px}
.nav a{
  color:var(--muted); text-decoration:none; padding:10px 12px; border-radius:8px; display:flex; align-items:center; gap:10px;
}
.nav a.active, .nav a:hover{ background: rgba(255,255,255,0.03); color:var(--text); }

/* Content */
.content{
  padding:18px;
}
.header{
  display:flex; justify-content:space-between; align-items:center; gap:12px; margin-bottom:18px;
}
/* Sửa style nút search (từ .searchbar -> .header form) */
.header form { display: flex; gap: 10px; }
.header input{
  padding:10px 12px; border-radius:10px; border:1px solid rgba(255,255,255,0.04); background:rgba(255,255,255,0.02); color:var(--text);
}
.header button {
    background: var(--accent);
    color: var(--text);
    border: none;
    padding: 10px 15px;
    border-radius: 8px;
    cursor: pointer;
    font-weight: 600;
}
.header button:hover {
    background: #0891b2;
}

/* Table */
.table-wrap{ background:var(--card); padding:12px; border-radius:12px; border:1px solid rgba(255,255,255,0.02);}
.table{ width:100%; border-collapse:collapse; color:var(--text); font-size:14px;}
.table th{ text-align:left; padding:10px; color:var(--muted); font-size:13px; }
.table td{ padding:10px; border-top:1px solid rgba(255,255,255,0.03); vertical-align:middle; }
.table tr:hover { background: rgba(255,255,255,0.03); }

/* CSS cho hàng "chưa đọc" */
.table tr.unread td {
    background: rgba(124, 58, 237, 0.2); /* Nền tím (accent-2) */
    font-weight: 600;
}

.actions button{ margin-right:8px; padding:6px 10px; border-radius:8px; border:0; cursor:pointer; font-weight:600;}
.btn-danger{ background:linear-gradient(180deg,#ef4444,#dc2626); color:white;}
.btn-warning{ background:linear-gradient(180deg,#f59e0b,#d98308); color:white;} /* Thêm style nút Vàng */
.btn-neutral{ background:rgba(255,255,255,0.03); color:var(--text); border:1px solid rgba(255,255,255,0.02);}

/* Footer small */
.footer{ margin-top:18px; color:var(--muted); font-size:13px; }

/* CSS cho Pagination */
.pagination {
    text-align: center;
    margin-top: 20px;
}
.pagination a, .pagination span {
    display: inline-block;
    padding: 8px 12px;
    margin: 0 4px;
    background: rgba(255,255,255,0.03);
    color: var(--muted);
    border-radius: 6px;
    text-decoration: none;
    border: 1px solid rgba(255,255,255,0.02);
}
.pagination span.current { 
    background: var(--accent);
    color: var(--text);
    font-weight: 700;
}

/* Responsive */
@media (max-width:900px){
  .app{ grid-template-columns: 1fr; padding:16px; }
  .sidebar{ position:relative; height:auto; display:flex; overflow:auto; }
  .header{ flex-direction:column; align-items:flex-start; gap:10px; }
}
</style>
</head>
<body>
<div class="app">
  <!-- SIDEBAR -->
  <aside class="sidebar" role="navigation">
    <div class="brand">
      <img src="../assets/logo.png" alt="logo" style="width:44px;height:44px;border-radius:8px;object-fit:cover">
      <div>
        <h2>TowerDefense Admin</h2>
        <div style="color:var(--muted);font-size:13px">Xin chào, <?=htmlspecialchars($_SESSION['user']['name'] ?? 'Admin')?></div>
      </div>
    </div>

    <nav class="nav" aria-label="Admin menu">
      <a href="admin_panel.php">Dashboard</a>
      <a href="admin_users.php">Người dùng</a>
      <a href="admin_posts.php">Bài viết</a>
      <a href="admin_comments.php">Bình luận</a>
      <a href="admin_scores.php">Scores</a>
      
      <!-- === SET ACTIVE CHO LINK NÀY === -->
      <a class="active" href="admin_contacts.php">Hòm thư</a>
      
      <a href="admin_stats.php">Thống kê</a>
      <a href="../index.php" style="margin-top:12px;color:var(--muted)">Quay về trang chính</a>
    </nav>

    <div class="footer">
      <div>Version: 1.0</div>
      <div style="margin-top:8px">© <?=date('Y')?> TowerDefense</div>
    </div>
  </aside>

  <!-- MAIN CONTENT -->
  <main class="content">
    <div class="header">
      <h1 style="margin:0">Hòm thư</h1>
      
      <!-- Search (Dùng form GET) -->
      <form class="searchbar" method="get">
        <input type="search" name="search" placeholder="Tìm tên, email, nội dung..." value="<?=htmlspecialchars($search)?>">
        <button type="submit">Tìm</button>
      </form>
    </div>

    <!-- Bảng Hòm Thư -->
    <section class="table-wrap">
      <h3 style="margin-top:0;">Tin nhắn nhận được (<?= $total ?>)</h3>
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
            <?php while ($r = $res->fetch_assoc()): ?>
            <!-- Thêm class .unread nếu chưa đọc -->
            <tr class="<?= $r['is_read'] ? '' : 'unread' ?>">
                <td><?= $r['id'] ?></td>
                <td><?= htmlspecialchars($r['name']) ?></td>
                <td><?= htmlspecialchars($r['email']) ?></td>
                <td style="max-width: 300px; word-wrap: break-word;"><?= nl2br(htmlspecialchars($r['message'])) ?></td>
                <td style="color:var(--muted)"><?= $r['created_at'] ?></td>
                <td class="actions">
                    <!-- Dùng form POST cho bảo mật -->
                    <form method="post" style="display:inline">
                        <input type="hidden" name="csrf_token" value="<?=csrf_token()?>">
                        <input type="hidden" name="contact_id" value="<?= $r['id'] ?>">

                        <?php if ($r['is_read'] == 0): // Chỉ hiện nút nếu chưa đọc ?>
                        <button type="submit" class="btn-warning" name="action" value="mark_read">Đã đọc</button>
                        <?php endif; ?>
                        
                        <button type="submit" class="btn-danger" name="action" value="delete" onclick="return confirm('Xóa vĩnh viễn tin nhắn này?')">Xóa</button>
                    </form>
                </td>
            </tr>
            <?php endwhile; ?>
            <?php if ($res->num_rows === 0): ?>
                <tr><td colspan="6" style="text-align:center; color:var(--muted)">Không có tin nhắn nào.</td></tr>
            <?php endif; ?>
        </tbody>
      </table>
    </section>
    
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
    
  </main>
</div>
</body>
</html>
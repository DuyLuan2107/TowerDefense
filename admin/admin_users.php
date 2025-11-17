<?php
// admin/admin_users.php
require_once __DIR__ . '/../db/connect.php';
require_once __DIR__ . '/../includes/admin_auth.php';
require_admin();
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/admin_log.php'; // optional, nếu bạn tạo admin_log

// Xử lý form hành động (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validate($_POST['csrf_token'] ?? '')) die('Invalid CSRF');

    $action = $_POST['action'] ?? '';
    $uid = intval($_POST['user_id'] ?? 0);

    if ($uid > 0) {
        if ($action === 'lock') {
            $stmt = $conn->prepare("UPDATE users SET is_locked = 1 WHERE id = ?");
            $stmt->bind_param("i", $uid); $stmt->execute(); $stmt->close();
            if (function_exists('admin_log')) admin_log($_SESSION['user']['id'], 'lock_user', 'users', $uid);
        } elseif ($action === 'unlock') {
            $stmt = $conn->prepare("UPDATE users SET is_locked = 0 WHERE id = ?");
            $stmt->bind_param("i", $uid); $stmt->execute(); $stmt->close();
            if (function_exists('admin_log')) admin_log($_SESSION['user']['id'], 'unlock_user', 'users', $uid);
        } elseif ($action === 'make_admin') {
            $stmt = $conn->prepare("UPDATE users SET role = 'admin' WHERE id = ?");
            $stmt->bind_param("i", $uid); $stmt->execute(); $stmt->close();
            if (function_exists('admin_log')) admin_log($_SESSION['user']['id'], 'make_admin', 'users', $uid);
        } elseif ($action === 'revoke_admin') {
            $stmt = $conn->prepare("UPDATE users SET role = 'user' WHERE id = ?");
            $stmt->bind_param("i", $uid); $stmt->execute(); $stmt->close();
            if (function_exists('admin_log')) admin_log($_SESSION['user']['id'], 'revoke_admin', 'users', $uid);
        } elseif ($action === 'delete') {
            // delete user (cascade will remove posts/scores if FK set)
            $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
            $stmt->bind_param("i", $uid); $stmt->execute(); $stmt->close();
            if (function_exists('admin_log')) admin_log($_SESSION['user']['id'], 'delete_user', 'users', $uid);
        }
    }
    header('Location: admin_users.php');
    exit;
}

// Search & pagination params
$search = trim($_GET['q'] ?? '');
$page = max(1, intval($_GET['p'] ?? 1));
$limit = 10;
$offset = ($page - 1) * $limit;

// Build query
$params = [];
$sql_where = "";
if ($search !== '') {
    $sql_where = "WHERE name LIKE ? OR email LIKE ?";
    $like = "%{$search}%";
    $params[] = $like;
    $params[] = $like;
}

// Count total for pagination
$count_sql = "SELECT COUNT(*) FROM users " . ($sql_where ? $sql_where : "");
$count_stmt = $conn->prepare($count_sql);
if ($sql_where) { $count_stmt->bind_param(str_repeat('s', count($params)), ...$params); }
$count_stmt->execute();
$count_stmt->bind_result($total_users);
$count_stmt->fetch();
$count_stmt->close();

$total_pages = max(1, ceil($total_users / $limit));

// Fetch rows
$select_sql = "
  SELECT id, name, email, role, is_locked, COALESCE(last_login, last_activity) AS created_at, avatar
  FROM users
  " . ($sql_where ? $sql_where : "") . "
  ORDER BY created_at DESC
  LIMIT ? OFFSET ?
";
$select_stmt = $conn->prepare($select_sql);
if ($sql_where) {
    $types = str_repeat('s', count($params)) . "ii";
    $bind_values = array_merge($params, [$limit, $offset]);
    $select_stmt->bind_param($types, ...$bind_values);
} else {
    $select_stmt->bind_param("ii", $limit, $offset);
}

$select_stmt->execute();
$result = $select_stmt->get_result();
?>
<!doctype html>
<html lang="vi">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Admin — Quản lý người dùng</title>
<style>
:root{
  --bg:#071026; --panel:#071827; --muted:#9aa8bb; --accent:#06b6d4; --accent-2:#7c3aed; --text:#e6eef6;
  --danger:#ef4444; --ok:#10b981;
}
*{box-sizing:border-box}
body{
  margin:0; font-family:Inter, system-ui, Arial, sans-serif; background:linear-gradient(180deg,#06111e,#071227);
  color:var(--text); padding:22px;
}
.container{
  max-width:1200px; margin:0 auto;
}

/* header */
.header{
  display:flex; gap:12px; align-items:center; justify-content:space-between; margin-bottom:18px;
}
.header h1{margin:0;font-size:20px}
.header .controls{display:flex;gap:8px;align-items:center}
.search input{
  padding:10px 12px; border-radius:10px; border:1px solid rgba(255,255,255,0.04); background:rgba(255,255,255,0.02);
  color:var(--text); width:320px;
}

/* panel */
.panel{
  background:linear-gradient(180deg, rgba(255,255,255,0.02), rgba(255,255,255,0.01));
  border:1px solid rgba(255,255,255,0.03);
  border-radius:12px; padding:14px;
}

/* table */
.table-wrap{ overflow:auto; margin-top:12px; }
.table{
  width:100%; border-collapse:collapse; min-width:900px;
}
.table thead th{
  text-align:left; padding:12px 10px; font-size:13px; color:var(--muted);
  border-bottom:1px solid rgba(255,255,255,0.03);
}
.table tbody td{ padding:12px 10px; vertical-align:middle; border-top:1px solid rgba(255,255,255,0.02); font-size:14px}
.table img.avatar{ width:42px; height:42px; border-radius:8px; object-fit:cover; margin-right:10px; vertical-align:middle; }

/* role badge */
.badge{ display:inline-block; padding:6px 8px; border-radius:8px; font-weight:700; font-size:12px; }
.badge-admin{ background:linear-gradient(90deg, #7c3aed, #4f46e5); color:#fff;}
.badge-user{ background:rgba(255,255,255,0.03); color:var(--muted)}

/* action buttons */
.form-inline{ display:flex; gap:6px; align-items:center; }
.btn{ padding:8px 10px; border-radius:8px; border:0; cursor:pointer; font-weight:700; font-size:13px; }
.btn-sm{ padding:6px 8px; font-size:12px; }
.btn-danger{ background:var(--danger); color:white; }
.btn-warning{ background:#f59e0b; color:white; }
.btn-neutral{ background:rgba(255,255,255,0.03); color:var(--text); border:1px solid rgba(255,255,255,0.03); }

/* pagination */
.pagination{ display:flex; gap:6px; align-items:center; margin-top:12px; }
.page-item{ padding:8px 10px; background:rgba(255,255,255,0.02); border-radius:8px; color:var(--muted); text-decoration:none; }
.page-item.active{ background:var(--accent); color:#012; font-weight:800; }

/* small */
.small-muted{ color:var(--muted); font-size:13px; }

/* responsive */
@media (max-width:900px){
  .search input{ width:160px; }
  .table{ min-width:720px; }
}
@media (max-width:560px){
  .header{ flex-direction:column; align-items:flex-start; gap:10px; }
  .table{ min-width:600px; }
}
</style>
</head>
<body>
<div class="container">
  <div class="header">
    <h1>Quản lý người dùng</h1>
    <div class="controls">
      <form class="search" method="get" action="admin_users.php">
        <input type="search" name="q" value="<?=htmlspecialchars($search)?>" placeholder="Tìm theo tên hoặc email...">
      </form>
      <a href="admin_panel.php" class="btn btn-neutral">Quay về Dashboard</a>
    </div>
  </div>

  <div class="panel">
    <div class="table-wrap">
      <table class="table" role="table" aria-label="Danh sách người dùng">
        <thead>
          <tr>
            <th>ID</th>
            <th>Người dùng</th>
            <th>Email</th>
            <th>Role</th>
            <th>Locked</th>
            <th>Created</th>
            <th style="width:220px">Action</th>
          </tr>
        </thead>
        <tbody>
        <?php while ($r = $result->fetch_assoc()): ?>
          <tr>
            <td><?=htmlspecialchars($r['id'])?></td>
            <td>
            <?php
                $avatarPath = (!empty($r['avatar']) && file_exists("../" . $r['avatar']))
                    ? "../" . $r['avatar']
                    : "../uploads/default.png";
            ?>
          <img class="avatar" src="<?= htmlspecialchars($avatarPath) ?>" alt="avatar">
              <strong><?=htmlspecialchars($r['name'])?></strong>
              <div class="small-muted">id <?=htmlspecialchars($r['id'])?></div>
            </td>
            <td><div style="max-width:240px;word-break:break-word;"><?=htmlspecialchars($r['email'])?></div></td>
            <td>
              <?php if ($r['role'] === 'admin'): ?>
                <span class="badge badge-admin">ADMIN</span>
              <?php else: ?>
                <span class="badge badge-user">User</span>
              <?php endif; ?>
            </td>
            <td><?= $r['is_locked'] ? '<span style="color:var(--danger);font-weight:700">Yes</span>' : '<span style="color:var(--ok);font-weight:700">No</span>' ?></td>
            <td class="small-muted"><?=htmlspecialchars($r['created_at'])?></td>
            <td>
              <form method="post" class="form-inline">
                <input type="hidden" name="csrf_token" value="<?=htmlspecialchars(csrf_token())?>">
                <input type="hidden" name="user_id" value="<?=htmlspecialchars($r['id'])?>">
                <?php if (!$r['is_locked']): ?>
                  <button class="btn btn-sm btn-warning" name="action" value="lock" onclick="return confirm('Khoá tài khoản này?')">Khoá</button>
                <?php else: ?>
                  <button class="btn btn-sm btn-neutral" name="action" value="unlock">Mở khoá</button>
                <?php endif; ?>

                <?php if ($r['role'] !== 'admin'): ?>
                  <button class="btn btn-sm btn-neutral" name="action" value="make_admin">Lên Admin</button>
                <?php else: ?>
                  <button class="btn btn-sm btn-neutral" name="action" value="revoke_admin">Hạ Admin</button>
                <?php endif; ?>

                <button class="btn btn-sm btn-danger" name="action" value="delete" onclick="return confirm('Xác nhận xoá người dùng và tất cả dữ liệu liên quan?')">Xoá</button>
              </form>
            </td>
          </tr>
        <?php endwhile; ?>
        </tbody>
      </table>
    </div>

    <!-- pagination -->
    <div style="display:flex; justify-content:space-between; align-items:center; margin-top:12px;">
      <div class="small-muted">Tổng: <?=htmlspecialchars($total_users)?> users</div>
      <div class="pagination" role="navigation" aria-label="Pagination">
        <?php
        $start = max(1, $page-3);
        $end = min($total_pages, $page+3);
        if ($page > 1){
            $prev = $page-1;
            $q = $search ? '&q='.urlencode($search) : '';
            echo '<a class="page-item" href="admin_users.php?p='.$prev.$q.'">&laquo; Prev</a>';
        }
        for ($i=$start;$i<=$end;$i++){
            $q = $search ? '&q='.urlencode($search) : '';
            $active = $i === $page ? ' active' : '';
            echo '<a class="page-item'.$active.'" href="admin_users.php?p='.$i.$q.'">'.$i.'</a>';
        }
        if ($page < $total_pages){
            $next = $page+1;
            $q = $search ? '&q='.urlencode($search) : '';
            echo '<a class="page-item" href="admin_users.php?p='.$next.$q.'">Next &raquo;</a>';
        }
        ?>
      </div>
    </div>
  </div>
</div>
</body>
</html>
<?php
$select_stmt->close();
$conn->close();
?>

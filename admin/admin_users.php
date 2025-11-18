<?php
// ======================== PROCESS POST (PHẢI Ở TRÊN CÙNG) ========================
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

    if ($uid > 0 && $uid != $_SESSION['user']['id']) { // Thêm kiểm tra: Admin không thể tự thao tác chính mình
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
            $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
            $stmt->bind_param("i", $uid); $stmt->execute(); $stmt->close();
            if (function_exists('admin_log')) admin_log($_SESSION['user']['id'], 'delete_user', 'users', $uid);
        }
    }
    // Thêm tham số tìm kiếm và trang vào URL khi chuyển hướng để giữ nguyên bộ lọc
    $search = trim($_GET['q'] ?? '');
    $page = max(1, intval($_GET['p'] ?? 1));
    $query = http_build_query(['p' => $page, 'q' => $search]);
    header("Location: admin_users.php?$query");
    exit;
}

// ======================== PAGE SETUP ========================
// (Các file cần thiết đã được gọi ở trên)

// Định nghĩa biến cho header
$CURRENT_PAGE = 'users'; // Giúp tô sáng link "Người dùng"
$PAGE_TITLE = 'Quản lý Người dùng';

// Gọi Header (đã bao gồm auth, sidebar, CSS)
require_once __DIR__ . '/admin_header.php';

// ======================== PAGINATION + SEARCH (Logic lấy dữ liệu) ========================
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

<div class="header">
  <h1 style="margin:0">Quản lý người dùng (<?= $total_users ?>)</h1>
  <div style="display:flex; gap:8px; align-items:center;">
    <form class="searchbar" method="get" action="admin_users.php" style="margin:0;">
        <?php if (isset($_GET['p'])) echo '<input type="hidden" name="p" value="'.htmlspecialchars($_GET['p']).'">' ?>
        <input type="search" name="q" value="<?=htmlspecialchars($search)?>" placeholder="Tìm theo tên hoặc email...">
        <button type="submit" class="btn-neutral" style="border-radius:10px; padding: 10px 14px; border:0; cursor:pointer;">Tìm</button>
    </form>
    <a href="admin_panel.php" class="btn-neutral">Quay về Dashboard</a>
  </div>
</div>

<section class="table-wrap">
    <table class="table" role="table" aria-label="Danh sách người dùng">
      <thead>
        <tr>
          <th>ID</th>
          <th>Người dùng</th>
          <th>Email</th>
          <th>Role</th>
          <th>Locked</th>
          <th>Last Active</th> <th style="width:250px">Action</th>
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
            <img style="width:42px; height:42px; border-radius:8px; object-fit:cover; margin-right:10px; vertical-align:middle;"
                 src="<?= htmlspecialchars($avatarPath) ?>" alt="avatar">
            
            <div style="display:inline-block; vertical-align:middle;">
              <strong><?=htmlspecialchars($r['name'])?></strong>
              <div style="color:var(--muted); font-size:13px;">id <?=htmlspecialchars($r['id'])?></div>
            </div>
          </td>
          <td><div style="max-width:240px;word-break:break-word;"><?=htmlspecialchars($r['email'])?></div></td>
          <td>
            <?php if ($r['role'] === 'admin'): ?>
              <span style="background:var(--accent-2); color:white; padding: 4px 8px; border-radius: 6px; font-weight: 700; font-size: 12px;">ADMIN</span>
            <?php else: ?>
              <span style="background:rgba(255,255,255,0.03); color:var(--muted); padding: 4px 8px; border-radius: 6px; font-size: 12px;">User</span>
            <?php endif; ?>
          </td>
          <td><?= $r['is_locked'] ? '<span style="color:var(--danger);font-weight:700">Yes</span>' : '<span style="color:var(--accent);font-weight:700">No</span>' ?></td>
          <td style="color:var(--muted); font-size: 13px;"><?=htmlspecialchars($r['created_at'])?></td>
          <td>
            <form method="post" class="form-inline" style="display:flex; gap:6px; align-items:center;">
              <input type="hidden" name="csrf_token" value="<?=htmlspecialchars(csrf_token())?>">
              <input type="hidden" name="user_id" value="<?=htmlspecialchars($r['id'])?>">
              
              <?php if ($r['id'] != $_SESSION['user']['id']): ?> 
                <?php if (!$r['is_locked']): ?>
                  <button class="btn-warning" name="action" value="lock" onclick="return confirm('Khoá tài khoản này?')">Khoá</button>
                <?php else: ?>
                  <button class="btn-neutral" name="action" value="unlock">Mở khoá</button>
                <?php endif; ?>

                <?php if ($r['role'] !== 'admin'): ?>
                  <button class="btn-neutral" name="action" value="make_admin">Lên Admin</button>
                <?php else: ?>
                  <button class="btn-neutral" name="action" value="revoke_admin">Hạ Admin</button>
                <?php endif; ?>

                <button class="btn-danger" name="action" value="delete" onclick="return confirm('Xác nhận XOÁ VĨNH VIỄN người dùng này?')">Xoá</T>
              <?php else: ?>
                <span style="color:var(--muted); font-size: 12px;">(Đây là bạn)</span>
              <?php endif; ?>
            </form>
          </td>
        </tr>
      <?php endwhile; ?>
      </tbody>
    </table>
</section>

<div style="display:flex; justify-content:space-between; align-items:center; margin-top:18px;">
  <div style="color:var(--muted); font-size:13px;">Tổng: <?=htmlspecialchars($total_users)?> users</div>
  
  <div class="pagination" role="navigation" aria-label="Pagination">
      <?php
      $q = $search ? '&q='.urlencode($search) : ''; // Param là 'q'
      
      if ($page > 1){
          $prev = $page - 1;
          echo '<a href="admin_users.php?p='.$prev.$q.'">« Trước</a>';
      }

      // Hiển thị một vài trang xung quanh trang hiện tại
      $start = max(1, $page - 2);
      $end = min($total_pages, $page + 2);

      if ($start > 1) {
          echo '<a href="admin_users.php?p=1'.$q.'">1</a>';
          if ($start > 2) echo '<span>...</span>';
      }

      for ($i = $start; $i <= $end; $i++){
          if ($i === $page) {
              echo '<span class="current">'.$i.'</span>';
          } else {
              echo '<a href="admin_users.php?p='.$i.$q.'">'.$i.'</a>';
          }
      }
      
      if ($end < $total_pages) {
          if ($end < $total_pages - 1) echo '<span>...</span>';
          echo '<a href="admin_users.php?p='.$total_pages.$q.'">'.$total_pages.'</a>';
      }

      if ($page < $total_pages){
          $next = $page + 1;
          echo '<a href="admin_users.php?p='.$next.$q.'">Tiếp »</a>';
      }
      ?>
  </div>
</div>


<?php
// Đóng kết nối TRƯỚC khi gọi footer
$select_stmt->close();
$conn->close();

// Gọi Footer
require_once __DIR__ . '/admin_footer.php';
?>
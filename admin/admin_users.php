<?php
// ======================== PROCESS POST (PHẢI Ở TRÊN CÙNG) ========================
require_once __DIR__ . '/../db/connect.php';
require_once __DIR__ . '/../includes/admin_auth.php';
require_admin();
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/admin_log.php'; 

// Xử lý form hành động (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validate($_POST['csrf_token'] ?? '')) die('Invalid CSRF');

    $action = $_POST['action'] ?? '';
    $uid = intval($_POST['user_id'] ?? 0);

    if ($uid > 0 && $uid != $_SESSION['user']['id']) { // Admin không thể tự thao tác chính mình
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
    
    $search = trim($_GET['q'] ?? '');
    $page = max(1, intval($_GET['p'] ?? 1));
    $query = http_build_query(['p' => $page, 'q' => $search]);
    header("Location: admin_users.php?$query");
    exit;
}

// ======================== PAGE SETUP ========================
$CURRENT_PAGE = 'users';
$PAGE_TITLE = 'Quản lý Người dùng';
require_once __DIR__ . '/admin_header.php';

// ======================== PAGINATION + SEARCH ========================
$search = trim($_GET['q'] ?? '');
$page = max(1, intval($_GET['p'] ?? 1));
$limit = 10;
$offset = ($page - 1) * $limit;

// Build query params
$params = [];
$sql_where = "";
if ($search !== '') {
    $sql_where = "WHERE name LIKE ? OR email LIKE ?";
    $like = "%{$search}%";
    $params[] = $like;
    $params[] = $like;
}

// Count total
$count_sql = "SELECT COUNT(*) FROM users " . ($sql_where ? $sql_where : "");
$count_stmt = $conn->prepare($count_sql);
if ($sql_where) { $count_stmt->bind_param(str_repeat('s', count($params)), ...$params); }
$count_stmt->execute();
$count_stmt->bind_result($total_users);
$count_stmt->fetch();
$count_stmt->close();

$total_pages = max(1, ceil($total_users / $limit));

// Fetch rows - ĐÃ SỬA ORDER BY
// Ưu tiên role (admin 'a' < user 'u'), sau đó đến ID giảm dần (mới nhất lên trên)
$select_sql = "
  SELECT id, name, email, role, is_locked, created_at, 
         COALESCE(last_login, last_activity) AS last_active_time, 
         avatar
  FROM users
  " . ($sql_where ? $sql_where : "") . "
  ORDER BY role ASC, id DESC
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
        <input type="search" name="q" value="<?=htmlspecialchars($search)?>" 
        style="width: 320px; padding: 10px; border-radius: 8px; border: 1px solid #ddd;" 
        placeholder="Tìm theo tên hoặc email...">
        <button type="submit" class="btn-neutral" style="border-radius:10px; padding: 10px 14px; border:0; cursor:pointer;">Tìm</button>
    </form>
  </div>
</div>

<section class="table-wrap">
    <table class="table" role="table" aria-label="Danh sách người dùng">
      <thead>
        <tr>
          <th width="50">ID</th>
          <th>Tên người dùng</th>
          <th>Email</th>
          <th width="80">Vai trò</th>
          <th width="60">Locked</th>
          <th>Tham gia</th> <!-- Cột mới thêm -->
          <th>HĐ Cuối</th> <!-- Last Active -->
          <th style="width:250px">Hành động</th>
        </tr>
      </thead>
      <tbody>
      <?php while ($r = $result->fetch_assoc()): ?>
        <tr>
          <td><strong><?=htmlspecialchars($r['id'])?></strong></td>
          <td>
            <?php
                $avatarPath = (!empty($r['avatar']) && file_exists("../" . $r['avatar']))
                    ? "../" . $r['avatar']
                    : "../uploads/avatar/default.png"; // Đảm bảo đường dẫn đúng
            ?>
            <img style="width:36px; height:36px; border-radius:50%; object-fit:cover; margin-right:8px; vertical-align:middle; border:1px solid #eee;"
                 src="<?= htmlspecialchars($avatarPath) ?>" alt="avt">
            
            <strong style="vertical-align:middle;"><?=htmlspecialchars($r['name'])?></strong>
          </td>
          <td><div style="max-width:200px; overflow:hidden; text-overflow:ellipsis;"><?=htmlspecialchars($r['email'])?></div></td>
          <td>
            <?php if ($r['role'] === 'admin'): ?>
              <span style="background:#e0f2fe; color:#0284c7; padding: 4px 8px; border-radius: 6px; font-weight: 700; font-size: 11px; text-transform:uppercase; border:1px solid #bae6fd;">ADMIN</span>
            <?php else: ?>
              <span style="background:#f3f4f6; color:#6b7280; padding: 4px 8px; border-radius: 6px; font-size: 11px; text-transform:uppercase; border:1px solid #e5e7eb;">User</span>
            <?php endif; ?>
          </td>
          <td>
            <?= $r['is_locked'] 
                ? '<span style="color:#dc2626;font-weight:700;font-size:12px">Khoá</span>' 
                : '<span style="color:#10b981;font-weight:700;font-size:12px">Mở</span>' ?>
          </td>
          
          <!-- CỘT NGÀY THAM GIA -->
          <td style="font-size: 13px; color:var(--text-main);">
            <?= date('d/m/Y', strtotime($r['created_at'])) ?>
            <div style="font-size:11px; color:var(--muted);"><?= date('H:i', strtotime($r['created_at'])) ?></div>
          </td>

          <!-- CỘT LAST ACTIVE -->
          <td style="font-size: 13px; color:var(--muted);">
             <?php 
                if ($r['last_active_time']) {
                    // Tính khoảng thời gian tương đối (VD: 5 phút trước)
                    $diff = time() - strtotime($r['last_active_time']);
                    if ($diff < 60) echo 'Vừa xong';
                    elseif ($diff < 3600) echo floor($diff/60) . ' phút trước';
                    elseif ($diff < 86400) echo floor($diff/3600) . ' giờ trước';
                    else echo date('d/m/Y', strtotime($r['last_active_time']));
                } else {
                    echo 'Chưa rõ';
                }
             ?>
          </td>

          <td>
            <form method="post" class="form-inline" style="display:flex; gap:6px; align-items:center;">
              <input type="hidden" name="csrf_token" value="<?=htmlspecialchars(csrf_token())?>">
              <input type="hidden" name="user_id" value="<?=htmlspecialchars($r['id'])?>">
              
              <?php if ($r['id'] != $_SESSION['user']['id']): ?> 
                <?php if (!$r['is_locked']): ?>
                  <button class="btn-warning" name="action" value="lock" onclick="return confirm('Khoá tài khoản này?')">Khoá</button>
                <?php else: ?>
                  <button class="btn-neutral" name="action" value="unlock">Mở</button>
                <?php endif; ?>

                <?php if ($r['role'] !== 'admin'): ?>
                  <button class="btn-neutral" name="action" value="make_admin" title="Nâng quyền Admin">Nâng Admin</button>
                <?php else: ?>
                  <button class="btn-neutral" name="action" value="revoke_admin" title="Hạ quyền xuống User">Hạ Admin</button>
                <?php endif; ?>

                <button class="btn-danger" name="action" value="delete" onclick="return confirm('CẢNH BÁO: Hành động này không thể hoàn tác!\nXác nhận XOÁ VĨNH VIỄN user này?')">Xoá</button>
              <?php else: ?>
                <span style="color:var(--muted); font-size: 12px; font-style:italic;">(Tài khoản hiện tại)</span>
              <?php endif; ?>
            </form>
          </td>
        </tr>
      <?php endwhile; ?>
      </tbody>
    </table>
</section>

<div style="display:flex; justify-content:space-between; align-items:center; margin-top:18px;">
  <div style="color:var(--muted); font-size:13px;">Tổng: <strong><?=htmlspecialchars($total_users)?></strong> thành viên</div>
  
  <div class="pagination" role="navigation" aria-label="Pagination">
      <?php
      $q = $search ? '&q='.urlencode($search) : ''; 
      
      if ($page > 1){
          $prev = $page - 1;
          echo '<a href="admin_users.php?p='.$prev.$q.'">« Trước</a>';
      }

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
$select_stmt->close();
$conn->close();
require_once __DIR__ . '/admin_footer.php';
?>
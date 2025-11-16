<?php
// (Code PHP của bạn ở trên...)
require_once __DIR__ . '/../includes/admin_auth.php'; 
require_admin();
require_once __DIR__ . '/../includes/csrf.php';

// statistics (simple, safe)
$total_users = (int) $conn->query("SELECT COUNT(*) FROM users")->fetch_row()[0];
$total_posts = (int) $conn->query("SELECT COUNT(*) FROM posts")->fetch_row()[0];
$total_scores = (int) $conn->query("SELECT COUNT(*) FROM scores")->fetch_row()[0];

$stmt = $conn->prepare("SELECT COUNT(*) FROM posts WHERE DATE(created_at) = CURDATE()");
$stmt->execute();
$stmt->bind_result($posts_today);
$stmt->fetch();
$stmt->close();
$posts_today = (int) $posts_today;
?>
<!doctype html>
<html lang="vi">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width,initial-scale=1" />
<title>Admin Dashboard — Tower Defense</title>

<!-- Basic, self-contained CSS for admin -->
<style>
:root{
  --bg:#0f1724; --card:#0b1220; --muted:#94a3b8; --accent:#06b6d4; --accent-2:#7c3aed;
  --text:#e6eef6; --danger:#ef4444; --warning: #f59e0b; /* Thêm màu Vàng/Cảnh báo */
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
.searchbar input{
  padding:10px 12px; border-radius:10px; border:1px solid rgba(255,255,255,0.04); background:rgba(255,255,255,0.02); color:var(--text);
}

/* Cards */
.grid{
  display:grid;
  grid-template-columns:repeat(auto-fit, minmax(220px,1fr));
  gap:16px;
  margin-bottom:20px;
}
.card{
  background:var(--card);
  border-radius:12px; padding:16px; box-shadow: 0 6px 18px rgba(2,6,23,0.6); border:1px solid rgba(255,255,255,0.02);
}
.card h3{margin:0;font-size:14px;color:var(--muted)}
.card .value{font-size:28px;margin-top:8px;font-weight:700;color:var(--text)}

/* Table */
.table-wrap{ background:var(--card); padding:12px; border-radius:12px; border:1px solid rgba(255,255,255,0.02);}
.table{ width:100%; border-collapse:collapse; color:var(--text); font-size:14px;}
.table th{ text-align:left; padding:10px; color:var(--muted); font-size:13px; }
.table td{ padding:10px; border-top:1px solid rgba(255,255,255,0.03); vertical-align:middle; }
.actions button{ margin-right:8px; padding:6px 10px; border-radius:8px; border:0; cursor:pointer; font-weight:600;}
.btn-danger{ background:linear-gradient(180deg,#ef4444,#dc2626); color:white;}
.btn-warning{ background:linear-gradient(180deg,#f59e0b,#d98308); color:white;} /* Thêm style nút Vàng */
.btn-neutral{ background:rgba(255,255,255,0.03); color:var(--text); border:1px solid rgba(255,255,255,0.02);}

/* Footer small */
.footer{ margin-top:18px; color:var(--muted); font-size:13px; }

/* Thêm CSS cho Pagination */
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
.pagination span.current { /* Đổi tên class 'active' thành 'current' */
    background: var(--accent);
    color: var(--text);
    font-weight: 700;
}
/* CSS cho hàng "chưa đọc" */
.table tr.unread td {
    background: rgba(124, 58, 237, 0.2); /* Nền tím (accent-2) */
    font-weight: 600;
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
      <img src="../assets/logo.png" alt="logo" style="width:44px;height:44px;border-radius:8px;object-fit:cover"> <!-- Sửa đường dẫn logo -->
      <div>
        <h2>TowerDefense Admin</h2>
        <div style="color:var(--muted);font-size:13px">Xin chào, <?=htmlspecialchars($_SESSION['user']['name'] ?? 'Admin')?></div>
      </div>
    </div>

    <nav class="nav" aria-label="Admin menu">
      <a class="active" href="admin_panel.php">Dashboard</a>
      <a href="admin_users.php">Người dùng</a>
      <a href="admin_posts.php">Bài viết</a>
      <a href="admin_comments.php">Bình luận</a>
      <a href="admin_scores.php">Scores</a>
      
      <!-- === THÊM LINK MỚI VÀO ĐÂY === -->
      <a href="admin_contacts.php">Hòm thư</a>
      
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
      <h1 style="margin:0">Dashboard</h1>
      <div class="searchbar">
        <input type="search" placeholder="Tìm user, post, id..." oninput="/* you can attach search logic */">
      </div>
    </div>

    <div class="grid">
      <div class="card">
        <h3>Tổng users</h3>
        <div class="value"><?=htmlspecialchars($total_users)?></div>
        <div style="color:var(--muted); margin-top:8px; font-size:13px">Người dùng đã đăng ký</div>
      </div>

      <div class="card">
        <h3>Tổng bài viết</h3>
        <div class="value"><?=htmlspecialchars($total_posts)?></div>
        <div style="color:var(--muted); margin-top:8px; font-size:13px">Bài trên diễn đàn</div>
      </div>

      <div class="card">
        <h3>Tổng lượt chơi</h3>
        <div class="value"><?=htmlspecialchars($total_scores)?></div>
        <div style="color:var(--muted); margin-top:8px; font-size:13px">Số lượt chơi được ghi</div>
      </div>

      <div class="card">
        <h3>Bài hôm nay</h3>
        <div class="value"><?=htmlspecialchars($posts_today)?></div>
        <div style="color:var(--muted); margin-top:8px; font-size:13px">Bài được tạo hôm nay</div>
      </div>
    </div>

    <section class="table-wrap">
      <h3 style="margin-top:0;">Hoạt động gần đây</h3>
      <table class="table">
        <thead><tr><th>ID</th><th>Loại</th><th>Chi tiết</th><th>Thời gian</th></tr></thead>
        <tbody>
          <?php
          // show 10 recent actions (posts/comments/scores) as a simple activity feed
          $logQ = $conn->query("
            (SELECT id, 'post' AS type, title AS detail, created_at FROM posts ORDER BY created_at DESC LIMIT 5)
            UNION
            (SELECT id, 'score' AS type, CONCAT('Score: ', score) AS detail, created_at FROM scores ORDER BY created_at DESC LIMIT 5)
            ORDER BY created_at DESC LIMIT 10
          ");
          if ($logQ && $logQ->num_rows > 0) {
            while ($row = $logQ->fetch_assoc()):
          ?>
          <tr>
            <td><?=htmlspecialchars($row['id'])?></td>
            <td><?=htmlspecialchars(ucfirst($row['type']))?></td>
            <td><?=htmlspecialchars($row['detail'])?></td>
            <td style="color:var(--muted)"><?=htmlspecialchars($row['created_at'])?></td>
          </tr>
          <?php
            endwhile;
          } else {
            echo '<tr><td colspan="4" style="color:var(--muted)">Không có dữ liệu</td></tr>';
          }
          ?>
        </tbody>
      </table>
    </section>

    <p style="margin-top:18px"><a class="btn-neutral" href="admin_users.php">Mở trang quản lý users</a></p>
  </main>
</div>
</body>
</html>
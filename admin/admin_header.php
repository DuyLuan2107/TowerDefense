<?php
// File này nên chứa các tệp xác thực chung
require_once __DIR__ . '/../includes/admin_auth.php'; 
require_admin();
require_once __DIR__ . '/../includes/csrf.php';

// Biến $CURRENT_PAGE sẽ được định nghĩa ở mỗi trang con (như admin_panel.php)
if (!isset($CURRENT_PAGE)) {
    $CURRENT_PAGE = 'dashboard'; // Mặc định
}
?>
<!doctype html>
<html lang="vi">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width,initial-scale=1" />
<title><?= htmlspecialchars($PAGE_TITLE ?? 'Admin Dashboard') ?> — Tower Defense</title>

<style>
:root{
  --bg:#0f1724; --card:#0b1220; --muted:#94a3b8; --accent:#06b6d4; --accent-2:#7c3aed;
  --text:#e6eef6; --danger:#ef4444; --warning: #f59e0b;
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
/* ... (Toàn bộ CSS của bạn giữ nguyên ở đây) ... */
.app { display:grid; grid-template-columns:260px 1fr; gap:var(--gap); padding:28px; }
.sidebar{ background:linear-gradient(180deg, rgba(255,255,255,0.02), rgba(255,255,255,0.01)); border:1px solid rgba(255,255,255,0.03); padding:18px; border-radius:12px; height:calc(100vh - 56px); position:sticky; top:28px; }
.brand{ display:flex; gap:12px; align-items:center; margin-bottom:18px; }
.brand h2{margin:0;font-size:18px;color:var(--text)}
.nav{margin-top:12px; display:flex;flex-direction:column; gap:8px}
.nav a{ color:var(--muted); text-decoration:none; padding:10px 12px; border-radius:8px; display:flex; align-items:center; gap:10px; }
.nav a.active, .nav a:hover{ background: rgba(255,255,255,0.03); color:var(--text); }
.content{ padding:18px; }
.header{ display:flex; justify-content:space-between; align-items:center; gap:12px; margin-bottom:18px; }
.searchbar input{ padding:10px 12px; border-radius:10px; border:1px solid rgba(255,255,255,0.04); background:rgba(255,255,255,0.02); color:var(--text); }
.grid{ display:grid; grid-template-columns:repeat(auto-fit, minmax(220px,1fr)); gap:16px; margin-bottom:20px; }
.card{ background:var(--card); border-radius:12px; padding:16px; box-shadow: 0 6px 18px rgba(2,6,23,0.6); border:1px solid rgba(255,255,255,0.02); }
.card h3{margin:0;font-size:14px;color:var(--muted)}
.card .value{font-size:28px;margin-top:8px;font-weight:700;color:var(--text)}
.table-wrap{ background:var(--card); padding:12px; border-radius:12px; border:1px solid rgba(255,255,255,0.02);}
.table{ width:100%; border-collapse:collapse; color:var(--text); font-size:14px;}
.table th{ text-align:left; padding:10px; color:var(--muted); font-size:13px; }
.table td{ padding:10px; border-top:1px solid rgba(255,255,255,0.03); vertical-align:middle; }
.actions button{ margin-right:8px; padding:6px 10px; border-radius:8px; border:0; cursor:pointer; font-weight:600;}
.btn-danger{ background:linear-gradient(180deg,#ef4444,#dc2626); color:white;}
.btn-warning{ background:linear-gradient(180deg,#f59e0b,#d98308); color:white;}
.btn-neutral{ background:rgba(255,255,255,0.03); color:var(--text); border:1px solid rgba(255,255,255,0.02);}
.footer{ margin-top:18px; color:var(--muted); font-size:13px; }
.pagination { text-align: center; margin-top: 20px; }
.pagination a, .pagination span { display: inline-block; padding: 8px 12px; margin: 0 4px; background: rgba(255,255,255,0.03); color: var(--muted); border-radius: 6px; text-decoration: none; border: 1px solid rgba(255,255,255,0.02); }
.pagination span.current { background: var(--accent); color: var(--text); font-weight: 700; }
.table tr.unread td { background: rgba(124, 58, 237, 0.2); font-weight: 600; }
@media (max-width:900px){
  .app{ grid-template-columns: 1fr; padding:16px; }
  .sidebar{ position:relative; height:auto; display:flex; overflow:auto; }
  .header{ flex-direction:column; align-items:flex-start; gap:10px; }
}
</style>
</head>
<body>
<div class="app">
  <aside class="sidebar" role="navigation">
    <div class="brand">
      <img src="../assets/logo.png" alt="logo" style="width:44px;height:44px;border-radius:8px;object-fit:cover">
      <div>
        <h2>TowerDefense Admin</h2>
        <div style="color:var(--muted);font-size:13px">Xin chào, <?=htmlspecialchars($_SESSION['user']['name'] ?? 'Admin')?></div>
      </div>
    </div>

    <nav class="nav" aria-label="Admin menu">
      <a class="<?= ($CURRENT_PAGE == 'dashboard') ? 'active' : '' ?>" href="admin_panel.php">Dashboard</a>
      <a class="<?= ($CURRENT_PAGE == 'users') ? 'active' : '' ?>" href="admin_users.php">Người dùng</a>
      <a class="<?= ($CURRENT_PAGE == 'posts') ? 'active' : '' ?>" href="admin_posts.php">Bài viết</a>
      <a class="<?= ($CURRENT_PAGE == 'comments') ? 'active' : '' ?>" href="admin_comments.php">Bình luận</a>
      <a class="<?= ($CURRENT_PAGE == 'scores') ? 'active' : '' ?>" href="admin_scores.php">Scores</a>
      <a class="<?= ($CURRENT_PAGE == 'contacts') ? 'active' : '' ?>" href="admin_contacts.php">Hòm thư</a>
      <a class="<?= ($CURRENT_PAGE == 'stats') ? 'active' : '' ?>" href="admin_stats.php">Thống kê</a>
      
      <a href="../index.php" style="margin-top:12px;color:var(--muted)">Quay về trang chính</a>
    </nav>

    <div class="footer">
      <div>Version: 1.0</div>
      <div style="margin-top:8px">© <?=date('Y')?> TowerDefense</div>
    </div>
  </aside>

  <main class="content">
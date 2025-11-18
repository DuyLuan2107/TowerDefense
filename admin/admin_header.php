<?php
// admin/admin_header.php

// 1. File này chứa các tệp xác thực chung
require_once __DIR__ . '/../includes/admin_auth.php'; 
require_admin();
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../db/connect.php';

// 2. LOGIC ĐẾM TIN NHẮN
$stmtUnread = $conn->prepare("SELECT COUNT(*) FROM contacts WHERE is_read = 0");
$stmtUnread->execute();
$stmtUnread->bind_result($unreadCount);
$stmtUnread->fetch();
$stmtUnread->close();

if (!isset($CURRENT_PAGE)) { $CURRENT_PAGE = 'dashboard'; }
?>
<!doctype html>
<html lang="vi">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width,initial-scale=1" />
<title><?= htmlspecialchars($PAGE_TITLE ?? 'Admin Panel') ?> — Tower Defense</title>

<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700&display=swap" rel="stylesheet">
<style>
:root{
  /* === NEO-FUTURISTIC THEME PALETTE === */
  --bg-body: #0a0e1a;       /* Xanh tím than rất đậm */
  --bg-panel: #13172a;      /* Xám xanh đậm */
  --bg-card: #1c2138;       /* Xám tím nhạt hơn một chút cho card */
  
  --primary: #9d52ff;       /* Tím điện (Electric Violet) - Chủ đạo */
  --primary-glow: rgba(157, 82, 255, 0.4);
  
  --secondary: #00e0ff;     /* Xanh ngọc (Teal) - Điểm nhấn phụ */
  --secondary-glow: rgba(0, 224, 255, 0.3);
  
  --text-main: #e0e6f2;     /* Trắng xám */
  --text-muted: #8c98ad;    /* Xám mờ */
  
  --border-subtle: rgba(255, 255, 255, 0.08); /* Viền mờ */
  --border-strong: rgba(255, 255, 255, 0.15); /* Viền mạnh hơn */
  
  --danger: #ff4d4d;        /* Đỏ rực */
  --warning: #ffb84d;       /* Cam vàng */
  --success: #4dff9c;       /* Xanh lá tươi */
  
  --gap: 20px;
  --radius-soft: 8px;       /* Bo tròn nhẹ */
  --radius-medium: 12px;
}

*{ box-sizing:border-box; }

body{
  margin:0; 
  font-family: 'Montserrat', sans-serif; /* Font hiện đại, dễ đọc, hỗ trợ TV */
  background-color: var(--bg-body);
  /* Hiệu ứng nền grid/gradient tinh tế */
  background-image: 
    radial-gradient(circle at top left, var(--bg-panel) 1px, transparent 1px),
    radial-gradient(circle at bottom right, var(--bg-panel) 1px, transparent 1px),
    linear-gradient(rgba(10, 14, 26, 0.8), rgba(10, 14, 26, 0.8));
  background-size: 50px 50px, 50px 50px, 100% 100%;
  color: var(--text-main);
  min-height: 100vh;
}

h1, h2, h3 {
    font-weight: 700;
    letter-spacing: 0.5px;
    color: var(--text-main);
}

/* Layout */
.app { display:grid; grid-template-columns: 280px 1fr; gap: var(--gap); padding: var(--gap); }

/* === SIDEBAR (Modern, hơi Glassmorphism) === */
.sidebar{
  background: rgba(19, 23, 42, 0.7); /* Bán trong suốt */
  backdrop-filter: blur(8px);
  border: 1px solid var(--border-subtle);
  padding: var(--gap); 
  border-radius: var(--radius-medium);
  height: calc(100vh - (var(--gap) * 2));
  position: sticky; 
  top: var(--gap);
  box-shadow: 0 10px 40px rgba(0,0,0,0.4);
  display: flex; flex-direction: column; justify-content: space-between;
}

.brand{ 
    padding-bottom: var(--gap); border-bottom: 1px solid var(--border-subtle);
    margin-bottom: var(--gap);
    display: flex; gap: 12px; align-items: center;
}
.brand img { border: 2px solid var(--primary); box-shadow: 0 0 15px var(--primary-glow); border-radius: 50%; /* Tròn */ }
.brand h2 { 
    margin:0; font-size: 22px; color: var(--secondary); 
    text-shadow: 0 0 10px var(--secondary-glow);
    font-weight: 700;
    /* Nếu dùng Michroma: font-family: 'Michroma', sans-serif; */
}
.brand div { font-size: 13px; color: var(--text-muted); }

.nav{ display:flex; flex-direction:column; gap: 8px; }

/* Link Menu */
.nav a{
  color: var(--text-muted); text-decoration:none; 
  padding: 12px 18px; 
  border-radius: var(--radius-soft);
  display:flex; align-items:center; justify-content: space-between;
  transition: all 0.3s ease;
  font-weight: 600;
  font-size: 15px;
  background: transparent;
  position: relative;
  overflow: hidden;
}

.nav a::before { /* Hiệu ứng gạch chân khi hover/active */
    content: ''; position: absolute; bottom: 0; left: 0; 
    width: 0; height: 3px; background: var(--secondary);
    transition: width 0.3s ease-out;
}

.nav a:hover { 
    color: var(--text-main); 
    background: rgba(255, 255, 255, 0.05); 
}
.nav a:hover::before { width: 100%; }

.nav a.active{ 
    background: linear-gradient(90deg, rgba(157, 82, 255, 0.15), transparent);
    color: var(--primary);
    font-weight: 700;
    border: 1px solid rgba(157, 82, 255, 0.3);
    box-shadow: 0 0 15px var(--primary-glow);
}
.nav a.active::before { width: 100%; background: var(--primary); }

/* === BADGE (Hình viên thuốc phát sáng) === */
.badge-count {
    background: var(--danger);
    color: white;
    font-size: 11px;
    font-weight: 700;
    padding: 2px 8px;
    border-radius: 12px; /* Dạng viên thuốc */
    min-width: 20px;
    text-align: center;
    box-shadow: 0 0 10px rgba(255, 77, 77, 0.6); /* Glow đỏ */
    animation: badgePulse 2s infinite;
}

@keyframes badgePulse {
    0% { box-shadow: 0 0 0 0 rgba(255, 77, 77, 0.4); }
    70% { box-shadow: 0 0 0 8px rgba(255, 77, 77, 0); }
    100% { box-shadow: 0 0 0 0 rgba(255, 77, 77, 0); }
}

/* === CONTENT AREA === */
.content{ padding: 0; }
.header{ 
    display:flex; justify-content:space-between; align-items:center; margin-bottom: var(--gap); 
    background: var(--bg-panel); padding: 18px 25px; 
    border-radius: var(--radius-medium); 
    border: 1px solid var(--border-subtle);
    box-shadow: 0 5px 20px rgba(0,0,0,0.3);
}
.header h1 { font-size: 28px; margin: 0; color: var(--secondary); }

/* Form Inputs */
.header form { display: flex; gap: 10px; }
.header input, .searchbar input, .search input { 
    padding:10px 18px; border-radius: var(--radius-soft); border:1px solid var(--border-subtle); 
    background: rgba(0,0,0,0.4); color: var(--text-main); outline: none;
    transition: border-color 0.3s, box-shadow 0.3s;
    font-family: 'Montserrat', sans-serif;
    font-size: 15px;
}
.header input::placeholder { color: var(--text-muted); opacity: 0.7; }
.header input:focus { border-color: var(--primary); box-shadow: 0 0 12px var(--primary-glow); }

.header button { 
    background: linear-gradient(135deg, var(--secondary) 0%, #00aaff 100%);
    color: #000; border: none; padding: 10px 22px; border-radius: var(--radius-soft); 
    cursor: pointer; font-weight: 700; 
    text-transform: uppercase; letter-spacing: 0.5px;
    box-shadow: 0 4px 15px rgba(0, 224, 255, 0.3);
    transition: all 0.3s ease;
}
.header button:hover { transform: translateY(-2px); box-shadow: 0 6px 20px var(--secondary-glow); }

/* === TABLES === */
.table-wrap{ 
    background: var(--bg-card); 
    padding: var(--gap); border-radius: var(--radius-medium); 
    border: 1px solid var(--border-subtle);
    box-shadow: 0 10px 30px rgba(0,0,0,0.3);
}
.table{ width:100%; border-collapse:collapse; color: var(--text-main); font-size:15px;}
.table th{ 
    text-align:left; padding:15px; color: var(--text-muted); 
    text-transform: uppercase; font-size: 12px; letter-spacing: 0.8px;
    border-bottom: 2px solid var(--border-strong);
}
.table td{ padding:15px; border-bottom: 1px solid var(--border-subtle); vertical-align:middle; }
.table tr:hover { background: rgba(255,255,255,0.03); }
.table tr:last-child td { border-bottom: none; }

/* Hàng chưa đọc */
.table tr.unread td { 
    background: rgba(157, 82, 255, 0.1); 
    color: #fff;
    border-left: 3px solid var(--primary);
    font-weight: 600;
}

/* === BUTTONS (Dạng Glow) === */
.actions button, .btn-danger, .btn-warning, .btn-neutral {
    border: 1px solid transparent; /* Mặc định không có viền */
    padding: 8px 15px; 
    border-radius: var(--radius-soft); 
    cursor:pointer; font-weight: 600;
    transition: all 0.2s ease-in-out;
    text-decoration: none; display: inline-block;
    font-size: 14px;
    text-transform: uppercase;
    letter-spacing: 0.3px;
}

.btn-danger{ background: rgba(255, 77, 77, 0.15); color: var(--danger); }
.btn-danger:hover { 
    background: var(--danger); color: white; 
    box-shadow: 0 0 15px rgba(255, 77, 77, 0.6); 
    border-color: var(--danger);
}

.btn-warning{ background: rgba(255, 184, 77, 0.15); color: var(--warning); }
.btn-warning:hover { 
    background: var(--warning); color: var(--bg-body); 
    box-shadow: 0 0 15px rgba(255, 184, 77, 0.6); 
    border-color: var(--warning);
}

.btn-neutral{ background: rgba(255,255,255,0.08); color: var(--text-main); }
.btn-neutral:hover { 
    background: rgba(255,255,255,0.15); color: white; 
    border-color: var(--text-muted);
}

/* === CARD STATS === */
.grid { display:grid; grid-template-columns:repeat(auto-fit, minmax(250px,1fr)); gap: var(--gap); margin-bottom: var(--gap); }
.card {
    background: var(--bg-card);
    border: 1px solid var(--border-subtle);
    padding: var(--gap); border-radius: var(--radius-medium);
    position: relative; overflow: hidden;
    box-shadow: 0 5px 20px rgba(0,0,0,0.2);
}
.card::before { /* Hiệu ứng thanh gradient ở trên */
    content: ''; position: absolute; top: 0; left: 0; width: 100%; height: 4px;
    background: linear-gradient(90deg, var(--primary), var(--secondary), transparent);
}
.card h3 { color: var(--text-muted); font-size: 13px; margin: 0; text-transform: uppercase; letter-spacing: 0.8px; }
.card .value { font-size: 38px; color: var(--text-main); font-weight: 700; margin-top: 10px; }
.card .value span { color: var(--primary); text-shadow: 0 0 8px var(--primary-glow); } /* Highlight số liệu chính */

/* === PAGINATION === */
.pagination { text-align: center; margin-top: var(--gap); display: flex; justify-content: center; gap: 8px; }
.pagination a, .pagination span { 
    display: inline-block; padding: 10px 16px; 
    border: 1px solid var(--border-subtle);
    color: var(--text-muted); text-decoration: none; 
    background: var(--bg-panel);
    border-radius: var(--radius-soft);
    font-weight: 600;
    transition: all 0.2s;
}
.pagination span.current { 
    background: var(--primary); color: white; border-color: var(--primary); 
    box-shadow: 0 0 15px var(--primary-glow);
}
.pagination a:hover { border-color: var(--primary); color: var(--primary); background: rgba(157, 82, 255, 0.05); }

/* === FOOTER === */
.footer{ 
    margin-top: calc(var(--gap) * 1.5); color:var(--text-muted); font-size:12px; 
    text-align: center; border-top: 1px solid var(--border-subtle); padding-top: var(--gap);
}

@media (max-width:900px){ 
    .app{ grid-template-columns: 1fr; padding: 15px; } 
    .sidebar{ height:auto; position:relative; margin-bottom: 20px; padding: 15px; } 
    .header{ flex-direction:column; align-items:flex-start; gap:15px; padding: 15px; } 
    .header form { width: 100%; }
    .header input { width: 100%; }
}
</style>
</head>
<body>
<div class="app">
  <aside class="sidebar" role="navigation">
    <div> <div class="brand">
          <img src="../assets/logo.png" alt="logo" style="width:52px;height:52px;object-fit:cover">
          <div>
            <h2>TD Command</h2>
            <div>Operator: <?=htmlspecialchars($_SESSION['user']['name'] ?? 'Admin')?></div>
          </div>
        </div>

        <nav class="nav" aria-label="Admin menu">
          <a class="<?= ($CURRENT_PAGE == 'dashboard') ? 'active' : '' ?>" href="admin_panel.php">Dashboard</a>
          <a class="<?= ($CURRENT_PAGE == 'users') ? 'active' : '' ?>" href="admin_users.php">Người Dùng</a>
          <a class="<?= ($CURRENT_PAGE == 'posts') ? 'active' : '' ?>" href="admin_posts.php">Bài Viết</a>
          <a class="<?= ($CURRENT_PAGE == 'comments') ? 'active' : '' ?>" href="admin_comments.php">Bình Luận</a>
          <a class="<?= ($CURRENT_PAGE == 'scores') ? 'active' : '' ?>" href="admin_scores.php">Kỉ Lục</a>
          
          <a class="<?= ($CURRENT_PAGE == 'contacts') ? 'active' : '' ?>" href="admin_contacts.php">
              <span>Hòm Thư</span>
              <?php if (isset($unreadCount) && $unreadCount > 0): ?>
                  <span class="badge-count"><?= $unreadCount ?></span>
              <?php endif; ?>
          </a>
          
          <a class="<?= ($CURRENT_PAGE == 'stats') ? 'active' : '' ?>" href="admin_stats.php">Phân Tích</a>
        </nav>
    </div>

    <a href="../index.php" class="btn-danger" style="text-align:center; display:block; margin-top: auto; ">
        Quay Lại
    </a>
  </aside>

  <main class="content">
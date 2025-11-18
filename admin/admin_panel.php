<?php
// Định nghĩa các biến cho header
$CURRENT_PAGE = 'dashboard'; // Giúp tô sáng link "Dashboard"
$PAGE_TITLE = 'Dashboard';     // Đặt tiêu đề cho trang

// Gọi Header (đã bao gồm auth và sidebar)
require_once __DIR__ . '/admin_header.php';

// (Code PHP xử lý logic riêng của trang này phải ở SAU KHI GỌI HEADER)
// Chuyển code logic từ trên đầu file cũ xuống đây:

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

<?php
// Gọi Footer
require_once __DIR__ . '/admin_footer.php';
?>
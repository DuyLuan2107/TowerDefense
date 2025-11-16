<?php
require_once __DIR__ . '/../includes/admin_auth.php';
require_admin();
require_once __DIR__ . '/../db/connect.php';

// posts last 30 days
$stmt = $conn->prepare("
  SELECT DATE(created_at) as day, COUNT(*) as cnt
  FROM posts
  WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
  GROUP BY DATE(created_at)
  ORDER BY day
");
$stmt->execute();
$res = $stmt->get_result();
$posts_by_day = $res->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// top 10 authors by posts
$stmt = $conn->prepare("
  SELECT u.id, u.name, COUNT(p.id) AS num_posts
  FROM users u LEFT JOIN posts p ON p.user_id = u.id
  GROUP BY u.id
  ORDER BY num_posts DESC
  LIMIT 10
");
$stmt->execute();
$top_auth = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>
<!doctype html>
<html lang="vi">
<head>
<meta charset="utf-8">
<title>Thống kê</title>

<!-- Bootstrap 5 -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

<style>
body {
    background: #f5f6fa;
}
.card {
    border-radius: 14px;
    box-shadow: 0 4px 14px rgba(0,0,0,0.05);
}
h1 {
    font-size: 30px;
    font-weight: 700;
}
/* Back Button */
    .back-btn {
        display: inline-block;
        margin-bottom: 20px;
        background: #4b5563;
        color: white;
        padding: 8px 12px;
        border-radius: 6px;
        text-decoration: none;
    }
    .back-btn:hover { background: #374151; }
</style>
</head>
<body class="p-4">

<div class="container">

    <h1 class="mb-4">📊 Thống kê hệ thống</h1>
    <a class="back-btn" href="admin_panel.php">← Quay lại Dashboard</a>

    <div class="row g-4">

        <!-- Posts 30 days -->
        <div class="col-md-6">
            <div class="card p-3">
                <h4 class="mb-3">🗓 Posts trong 30 ngày</h4>

                <table class="table table-striped table-hover align-middle">
                    <thead class="table-primary">
                        <tr>
                            <th>Ngày</th>
                            <th>Số bài đăng</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($posts_by_day as $r): ?>
                        <tr>
                            <td><?= htmlspecialchars($r['day']) ?></td>
                            <td><?= htmlspecialchars($r['cnt']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

            </div>
        </div>

        <!-- Top authors -->
        <div class="col-md-6">
            <div class="card p-3">
                <h4 class="mb-3">🏆 Top tác giả nhiều bài đăng</h4>

                <table class="table table-striped table-hover align-middle">
                    <thead class="table-warning">
                        <tr>
                            <th>Tác giả</th>
                            <th>Số bài</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($top_auth as $a): ?>
                        <tr>
                            <td><?= htmlspecialchars($a['name']) ?></td>
                            <td><?= htmlspecialchars($a['num_posts']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

            </div>
        </div>

    </div>

</div>

</body>
</html>

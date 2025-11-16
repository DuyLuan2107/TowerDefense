<?php
// admin/admin_scores.php
require_once __DIR__ . '/../db/connect.php';
require_once __DIR__ . '/../includes/admin_auth.php';
require_admin();
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/admin_log.php';

/* ===========================
   EXPORT CSV
   =========================== */
if (isset($_GET['export']) && $_GET['export']=='csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=scores.csv');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['id','user_id','user_name','score','enemies_killed','gold_left','duration_seconds','created_at']);
    $res = $conn->query("SELECT s.*, u.name AS user_name FROM scores s LEFT JOIN users u ON u.id = s.user_id ORDER BY s.created_at DESC");
    while ($row = $res->fetch_assoc()) {
        fputcsv($out, [$row['id'],$row['user_id'],$row['user_name'],$row['score'],$row['enemies_killed'],$row['gold_left'],$row['duration_seconds'],$row['created_at']]);
    }
    fclose($out);
    exit;
}

/* ===========================
   DELETE
   =========================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validate($_POST['csrf_token'] ?? '')) die('Invalid CSRF');
    $action = $_POST['action'] ?? '';
    $sid = intval($_POST['score_id'] ?? 0);
    if ($action === 'delete' && $sid) {
        $stmt = $conn->prepare("DELETE FROM scores WHERE id = ?");
        $stmt->bind_param("i", $sid);
        $stmt->execute();
        $stmt->close();
        admin_log($_SESSION['user']['id'], 'delete_score', 'scores', $sid);
    }
    header('Location: admin_scores.php');
    exit;
}

/* ===========================
   SEARCH + PAGINATION
   =========================== */

$search = trim($_GET['search'] ?? '');
$page   = max(1, intval($_GET['page'] ?? 1));
$limit  = 20;
$offset = ($page - 1) * $limit;

$where_sql = "";
$params = [];
$types = "";

if ($search !== "") {
    $where_sql = "WHERE u.name LIKE ? OR u.id = ?";
    $params[] = "%$search%";  $types .= "s";
    $params[] = intval($search); $types .= "i";
}

/* COUNT */
$count_sql = "
  SELECT COUNT(*) AS total
  FROM scores s 
  LEFT JOIN users u ON u.id = s.user_id
  $where_sql
";

$stmt = $conn->prepare($count_sql);
if ($where_sql) $stmt->bind_param($types, ...$params);
$stmt->execute();
$total = $stmt->get_result()->fetch_assoc()['total'] ?? 0;
$stmt->close();

$pages = max(1, ceil($total / $limit));

/* MAIN DATA */
$sql = "
  SELECT s.id, s.user_id, s.score, s.enemies_killed, s.gold_left,
         s.duration_seconds, s.created_at, u.name AS user_name
  FROM scores s 
  LEFT JOIN users u ON u.id = s.user_id
  $where_sql
  ORDER BY s.created_at DESC
  LIMIT ?, ?
";

$stmt = $conn->prepare($sql);

if ($where_sql) {
    $types2 = $types . "ii";
    $bind = [...$params, $offset, $limit];
    $stmt->bind_param($types2, ...$bind);
} else {
    $stmt->bind_param("ii", $offset, $limit);
}

$stmt->execute();
$res = $stmt->get_result();
$stmt->close();
?>
<!doctype html>
<html lang="vi">
<head>
<meta charset="utf-8">
<title>Quản lý Scores</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

<style>
body {
    background: #f3f4f6;
}
.card {
    border-radius: 14px;
    box-shadow: 0 4px 14px rgba(0,0,0,0.08);
}
table th {
    background: #1f2937;
    color: white;
}
</style>
</head>

<body class="p-4">

<div class="container">

    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="fw-bold">📊 Quản lý Scores</h2>
        <a href="admin_panel.php" class="btn btn-secondary">⬅ Back Dashboard</a>
    </div>

    <!-- SEARCH FORM -->
    <form method="get" class="mb-3 d-flex gap-2">
        <input 
            type="text" 
            name="search" 
            class="form-control"
            placeholder="Tìm theo User ID hoặc Tên user..."
            value="<?=htmlspecialchars($search)?>"
        >
        <button class="btn btn-primary">Tìm</button>
        <a href="admin_scores.php" class="btn btn-outline-secondary">Reset</a>
    </form>

    <!-- EXPORT -->
    <a href="admin_scores.php?export=csv" class="btn btn-success mb-3">⬇ Export CSV</a>

    <!-- CARD TABLE -->
    <div class="card p-3">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>User</th>
                        <th>Score</th>
                        <th>Enemies</th>
                        <th>Gold</th>
                        <th>Time(s)</th>
                        <th>Created</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>

                <?php while ($r = $res->fetch_assoc()): ?>
                <tr>
                    <td><?=htmlspecialchars($r['id'])?></td>
                    <td><?=htmlspecialchars($r['user_name'])?> (<?=htmlspecialchars($r['user_id'])?>)</td>
                    <td><?=htmlspecialchars($r['score'])?></td>
                    <td><?=htmlspecialchars($r['enemies_killed'])?></td>
                    <td><?=htmlspecialchars($r['gold_left'])?></td>
                    <td><?=htmlspecialchars($r['duration_seconds'])?></td>
                    <td><?=htmlspecialchars($r['created_at'])?></td>
                    <td>
                        <form method="post" onsubmit="return confirm('Xóa điểm này?')" class="d-inline">
                            <input type="hidden" name="csrf_token" value="<?=htmlspecialchars(csrf_token())?>">
                            <input type="hidden" name="score_id" value="<?=htmlspecialchars($r['id'])?>">
                            <button name="action" value="delete" class="btn btn-sm btn-danger">
                                Xóa
                            </button>
                        </form>
                    </td>
                </tr>
                <?php endwhile; ?>

                </tbody>
            </table>
        </div>
    </div>

    <!-- PAGINATION -->
    <nav class="mt-4">
        <ul class="pagination justify-content-center">

            <?php for ($i = 1; $i <= $pages; $i++): ?>
            <li class="page-item <?=($i == $page ? 'active' : '')?>">
                <a class="page-link" href="?page=<?=$i?>&search=<?=urlencode($search)?>">
                    <?=$i?>
                </a>
            </li>
            <?php endfor; ?>

        </ul>
    </nav>

</div>

</body>
</html>

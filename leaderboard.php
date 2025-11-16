<?php
require_once 'db/connect.php';
include 'includes/header.php';

/* ============================
   LẤY TỔNG SỐ USER CÓ ĐIỂM
   ============================ */
$perPage = 10;
$page = max(1, intval($_GET['page'] ?? 1));
$offset = ($page - 1) * $perPage;

$sqlCount = "SELECT COUNT(*) AS total_users FROM (SELECT user_id FROM scores GROUP BY user_id) t";
$totalUsers = $conn->query($sqlCount)->fetch_assoc()['total_users'] ?? 0;
$totalPages = max(1, ceil($totalUsers / $perPage));

/* ============================
   LẤY BXH
   ============================ */
$sql = "
  SELECT u.name, MAX(s.score) AS best_score
  FROM scores s
  JOIN users u ON u.id = s.user_id
  GROUP BY s.user_id
  ORDER BY best_score DESC
  LIMIT ? OFFSET ?
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $perPage, $offset);
$stmt->execute();
$result = $stmt->get_result();
?>

<div class="profile-container" style="max-width:700px;">

    <h2>🏆 Bảng Xếp Hạng (điểm cao nhất)</h2>
    <p class="muted">
        Bạn không cần đăng nhập để xem BXH, nhưng phải đăng nhập mới lưu điểm và có tên trong bảng.
    </p>

    <table style="width:100%; border-collapse:collapse">
        <tr style="background:#f1f1f1">
            <th style="text-align:left;padding:8px">#</th>
            <th style="text-align:left;padding:8px">Người chơi</th>
            <th style="text-align:right;padding:8px">Điểm cao nhất</th>
        </tr>

        <?php
        $rank = $offset + 1;
        while ($row = $result->fetch_assoc()):
        ?>
        <tr>
            <td style="padding:8px"><?= $rank++ ?></td>
            <td style="padding:8px"><?= htmlspecialchars($row['name']) ?></td>
            <td style="padding:8px; text-align:right"><?= (int)$row['best_score'] ?></td>
        </tr>
        <?php endwhile; ?>
    </table>


    <!-- ============================
         PHÂN TRANG – giống forum
         ============================ -->
    <?php if ($totalPages > 1): ?>
    <div class="pagination">

        <!-- prev -->
        <a class="<?= $page <= 1 ? 'disabled' : '' ?>"
           href="<?= $page > 1 ? '?page='.($page-1) : '#' ?>">
           «
        </a>

        <!-- page numbers -->
        <?php for ($p = 1; $p <= $totalPages; $p++): ?>
            <a class="<?= $p == $page ? 'active' : '' ?>"
               href="?page=<?= $p ?>">
                <?= $p ?>
            </a>
        <?php endfor; ?>

        <!-- next -->
        <a class="<?= $page >= $totalPages ? 'disabled' : '' ?>"
           href="<?= $page < $totalPages ? '?page='.($page+1) : '#' ?>">
           »
        </a>

    </div>
    <?php endif; ?>

</div>

<?php include 'includes/footer.php'; ?>

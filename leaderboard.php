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

<div class="leaderboard-wrapper">
    <div class="leaderboard-container">

        <h2>🏆 Bảng Xếp Hạng</h2>
        <p class="leaderboard-muted">
            Thành tích cao nhất của tất cả người chơi. Hãy leo lên đỉnh!
        </p>

        <div class="leaderboard-header">
            <span class="header-rank"># Hạng</span>
            <span class="header-name">Người chơi</span>
            <span class="header-score">Điểm</span>
        </div>

        <div class="leaderboard-list">
            <?php
            $rank = $offset + 1;
            while ($row = $result->fetch_assoc()):
                
                // Gán class đặc biệt cho Top 3
                $rank_class = '';
                if ($rank == 1) $rank_class = 'rank-1';
                elseif ($rank == 2) $rank_class = 'rank-2';
                elseif ($rank == 3) $rank_class = 'rank-3';
            ?>
            
            <div class="leaderboard-item <?= $rank_class ?>">
                <span class="rank">
                    <?php
                    // Hiển thị Icon cho Top 3
                    if ($rank == 1) echo '<i class="fa-solid fa-crown rank-1-icon"></i>';
                    elseif ($rank == 2) echo '<i class="fa-solid fa-trophy rank-2-icon"></i>';
                    elseif ($rank == 3) echo '<i class="fa-solid fa-medal rank-3-icon"></i>';
                    else echo $rank;
                    ?>
                </span>
                <span class="name"><?= htmlspecialchars($row['name']) ?></span>
                <span class="score"><?= (int)$row['best_score'] ?></span>
            </div>

            <?php
                $rank++; // Tăng hạng cho người tiếp theo
            endwhile; 
            
            if ($totalUsers == 0):
            ?>
                <div class="leaderboard-item-empty">
                    Chưa có ai trên bảng xếp hạng. Hãy là người đầu tiên!
                </div>
            <?php endif; ?>
        </div>


        <?php if ($totalPages > 1): ?>
        <div class="pagination">

            <a class="<?= $page <= 1 ? 'disabled' : '' ?>"
               href="<?= $page > 1 ? '?page='.($page-1) : '#' ?>">
               «
            </a>

            <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                <a class="<?= $p == $page ? 'active' : '' ?>"
                   href="?page=<?= $p ?>">
                   <?= $p ?>
                </a>
            <?php endfor; ?>

            <a class="<?= $page >= $totalPages ? 'disabled' : '' ?>"
               href="<?= $page < $totalPages ? '?page='.($page+1) : '#' ?>">
               »
            </a>

        </div>
        <?php endif; ?>

    </div>
</div>

<?php include 'includes/footer.php'; ?>
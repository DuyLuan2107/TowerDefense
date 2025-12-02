<?php
require_once 'db/connect.php';
include 'includes/header.php';

// ... (Giữ nguyên logic PHP lấy dữ liệu của bạn) ...
$perPage = 10;
$page = max(1, intval($_GET['page'] ?? 1));
$offset = ($page - 1) * $perPage;

$sqlCount = "SELECT COUNT(*) AS total_users FROM (SELECT user_id FROM scores GROUP BY user_id) t";
$totalUsers = $conn->query($sqlCount)->fetch_assoc()['total_users'] ?? 0;
$totalPages = max(1, ceil($totalUsers / $perPage));

$sql = "
    SELECT u.id, u.name, u.avatar, MAX(s.score) AS best_score
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

<style>
    body { background-color: #0b0e14; color: #fff; font-family: 'Montserrat', sans-serif; }

    .leaderboard-wrapper {
        display: flex; justify-content: center; padding: 40px 20px;
    }

    .leaderboard-container {
        width: 100%; max-width: 700px;
        background: rgba(23, 28, 41, 0.9);
        backdrop-filter: blur(10px);
        padding: 30px; border-radius: 16px;
        border: 1px solid rgba(0, 247, 255, 0.2);
        box-shadow: 0 0 30px rgba(0, 0, 0, 0.5);
    }

    .leaderboard-container h2 {
        text-align: center; color: #00f7ff; text-transform: uppercase;
        letter-spacing: 2px; text-shadow: 0 0 10px rgba(0, 247, 255, 0.5);
        margin-bottom: 10px;
    }

    .leaderboard-muted { text-align: center; color: #94a3b8; font-size: 0.9rem; margin-bottom: 30px; }

    /* Header Bảng */
    .leaderboard-header {
        display: flex; padding: 10px 15px;
        background: rgba(255, 255, 255, 0.05);
        border-radius: 8px; margin-bottom: 10px;
        font-weight: 700; color: #94a3b8; text-transform: uppercase; font-size: 0.8rem;
    }
    .header-rank { flex: 0 0 60px; text-align: center; }
    .header-name { flex: 1; padding-left: 10px; }
    .header-score { flex: 0 0 100px; text-align: right; }

    /* Item List */
    .leaderboard-item {
        display: flex; align-items: center; padding: 15px;
        background: rgba(255, 255, 255, 0.02);
        border-radius: 10px; margin-bottom: 8px;
        transition: 0.2s; border: 1px solid transparent;
    }
    .leaderboard-item:hover {
        background: rgba(255, 255, 255, 0.08);
        transform: scale(1.01);
        border-color: rgba(255, 255, 255, 0.1);
    }

    /* Rank Styles */
    .rank { flex: 0 0 60px; text-align: center; font-weight: 800; font-size: 1.1rem; color: #64748b; }
    .name { flex: 1; padding-left: 10px; font-weight: 600; display: flex; align-items: center; gap: 10px; }
    .name a { color: #fff; text-decoration: none; }
    .name a:hover { color: #00f7ff; text-decoration: underline; }
    
    .avt-small { width: 30px; height: 30px; border-radius: 50%; object-fit: cover; border: 1px solid #3f4b63; }

    .score { flex: 0 0 100px; text-align: right; font-family: monospace; font-size: 1.1rem; color: #00f7ff; font-weight: 700; }

    /* Top 3 Effects */
    .leaderboard-item.rank-1 { background: linear-gradient(90deg, rgba(255, 215, 0, 0.15), transparent); border: 1px solid rgba(255, 215, 0, 0.3); }
    .rank-1-icon { color: #ffd700; font-size: 1.3rem; text-shadow: 0 0 10px #ffd700; }
    
    .leaderboard-item.rank-2 { background: linear-gradient(90deg, rgba(192, 192, 192, 0.15), transparent); border: 1px solid rgba(192, 192, 192, 0.3); }
    .rank-2-icon { color: #c0c0c0; font-size: 1.2rem; text-shadow: 0 0 8px #c0c0c0; }

    .leaderboard-item.rank-3 { background: linear-gradient(90deg, rgba(205, 127, 50, 0.15), transparent); border: 1px solid rgba(205, 127, 50, 0.3); }
    .rank-3-icon { color: #cd7f32; font-size: 1.2rem; text-shadow: 0 0 8px #cd7f32; }

    /* Pagination */
    .pagination { display: flex; justify-content: center; margin-top: 30px; gap: 5px; }
    .pagination a {
        padding: 8px 14px; border-radius: 6px; background: rgba(255, 255, 255, 0.05);
        color: #94a3b8; text-decoration: none; font-size: 0.9rem; transition: 0.2s;
    }
    .pagination a:hover { background: #00f7ff; color: #000; }
    .pagination a.active { background: #00f7ff; color: #000; font-weight: 700; }
    .pagination a.disabled { opacity: 0.3; pointer-events: none; }
</style>

<div class="leaderboard-wrapper">
    <div class="leaderboard-container">
        <h2>🏆 Bảng Xếp Hạng</h2>
        <p class="leaderboard-muted">Thành tích cao nhất của các chiến binh.</p>

        <div class="leaderboard-header">
            <span class="header-rank">#</span>
            <span class="header-name">Người chơi</span>
            <span class="header-score">Điểm số</span>
        </div>

        <div class="leaderboard-list">
            <?php
            $rank = $offset + 1;
            while ($row = $result->fetch_assoc()):
                $rank_class = '';
                if ($rank == 1) $rank_class = 'rank-1';
                elseif ($rank == 2) $rank_class = 'rank-2';
                elseif ($rank == 3) $rank_class = 'rank-3';
                
                $avt = !empty($row['avatar']) ? $row['avatar'] : 'assets/images/default_avatar.png';
            ?>
            
            <div class="leaderboard-item <?= $rank_class ?>">
                <span class="rank">
                    <?php
                    if ($rank == 1) echo '<i class="fa-solid fa-crown rank-1-icon"></i>';
                    elseif ($rank == 2) echo '<i class="fa-solid fa-trophy rank-2-icon"></i>';
                    elseif ($rank == 3) echo '<i class="fa-solid fa-medal rank-3-icon"></i>';
                    else echo $rank;
                    ?>
                </span>
                <span class="name">
                    <img src="<?= htmlspecialchars($avt) ?>" class="avt-small">
                    <a href="profile.php?id=<?= $row['id'] ?>&from=leaderboard"><?= htmlspecialchars($row['name']) ?></a>
                </span>
                <span class="score"><?= number_format($row['best_score']) ?></span>
            </div>

            <?php $rank++; endwhile; ?>
            
            <?php if ($totalUsers == 0): ?>
                <div style="text-align:center; padding:30px; color:#64748b; font-style:italic;">
                    Chưa có dữ liệu.
                </div>
            <?php endif; ?>
        </div>

        <?php if ($totalPages > 1): ?>
        <div class="pagination">
            <a class="<?= $page <= 1 ? 'disabled' : '' ?>" href="<?= $page > 1 ? '?page='.($page-1) : '#' ?>">«</a>
            <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                <a class="<?= $p == $page ? 'active' : '' ?>" href="?page=<?= $p ?>"><?= $p ?></a>
            <?php endfor; ?>
            <a class="<?= $page >= $totalPages ? 'disabled' : '' ?>" href="<?= $page < $totalPages ? '?page='.($page+1) : '#' ?>">»</a>
        </div>
        <?php endif; ?>

    </div>
</div>

<?php include 'includes/footer.php'; ?>
<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once 'db/connect.php';

// ========================= ONLINE COUNT =========================
$sqlOnline = "
    SELECT COUNT(*) AS total 
    FROM users 
    WHERE last_activity >= NOW() - INTERVAL 60 SECOND
";
$onlineCount = $conn->query($sqlOnline)->fetch_assoc()['total'] ?? 0;

include 'includes/header.php';

// ===================== TÌM KIẾM =====================
$q = trim($_GET['q'] ?? '');
$perPage = 5;
$page = max(1, intval($_GET['page'] ?? 1));
$offset = ($page - 1) * $perPage;

$where = "";
$params = [];
$types = "";

// Nếu có từ khóa tìm kiếm
if ($q !== '') {
    $where = " WHERE p.title LIKE ? OR p.content LIKE ? ";
    $like = "%$q%";
    $params[] = $like;
    $params[] = $like;
    $types .= "ss";
}

// ===================== COUNT =====================
$sqlCount = "SELECT COUNT(*) AS total FROM posts p $where";
$stmtCount = $conn->prepare($sqlCount);

if ($types !== "") {
    $stmtCount->bind_param($types, ...$params);
}

$stmtCount->execute();
$total = $stmtCount->get_result()->fetch_assoc()['total'] ?? 0;
$totalPages = max(1, ceil($total / $perPage));

// ===================== LẤY DANH SÁCH BÀI =====================
$sql = "
    SELECT p.*, u.name AS author,
           (SELECT COUNT(*) FROM post_likes pl WHERE pl.post_id = p.id) AS like_count,
           (SELECT COUNT(*) FROM comments c WHERE c.post_id = p.id) AS comment_count
    FROM posts p
    JOIN users u ON u.id = p.user_id
    $where
    ORDER BY p.created_at DESC
    LIMIT ? OFFSET ?
";



$stmt = $conn->prepare($sql);
$types2 = $types . "ii";
$params2 = $params;
$params2[] = $perPage;
$params2[] = $offset;

$stmt->bind_param($types2, ...$params2);
$stmt->execute();
$result = $stmt->get_result();


?>

<div class="forum-container">
  <!-- TIÊU ĐỀ + ONLINE -->
  <div style="display:flex; justify-content:space-between; align-items:center;">
      <h2>💬 Cộng Đồng Game</h2>

      <div style="font-size:0.9em; color:#555;">
          <span style="color:limegreen; font-size:14px;">●</span>
          <?= $onlineCount ?> thành viên đang online
      </div>
  </div>

  <!-- Form tìm kiếm + gợi ý -->
  <form method="get" style="position:relative; margin-bottom:15px; display:flex; gap:8px;">
    
    <div style="flex:1; position:relative;">
      <input type="text" name="q" id="searchInput" placeholder="Tìm bài viết..."
             value="<?= htmlspecialchars($q) ?>"
             autocomplete="off"
             style="width:100%;padding:8px;border-radius:8px;border:1px solid #ccc;">

      <!-- Gợi ý -->
      <div id="suggest-box">
      </div>
    </div>

    <button class="btn-send">Tìm</button>
  </form>

  <div style="margin-bottom:15px;">
    <?php if (isset($_SESSION['user'])): ?>
      <a href="forum_create_post.php" class="btn-send">✍️ Đăng bài mới</a>
    <?php else: ?>
      <span class="muted">Bạn cần <a href="auth.php">đăng nhập</a> để đăng bài.</span>
    <?php endif; ?>
  </div>

  <?php if ($total == 0): ?>
    <p class="muted">Không có bài phù hợp.</p>
  <?php else: ?>
    <div class="forum-list">
<?php while ($row = $result->fetch_assoc()): ?>
  <div class="forum-card">
    <div class="forum-icon">💬</div>

    <div class="forum-content">
      <a class="forum-title" href="forum_view.php?id=<?= $row['id'] ?>">
        <?= htmlspecialchars($row['title']) ?>
      </a>

      <div class="forum-info">
        <span class="author"><?= htmlspecialchars($row['author']) ?></span> • 
        <span class="date"><?= $row['created_at'] ?></span>
      </div>

      <div class="forum-excerpt">
        <?= nl2br(htmlspecialchars(mb_substr($row['content'], 0, 160))) ?>...
      </div>
    </div>

    <!-- Thêm stats Like || Comment -->
    <div class="forum-stats">
        Like: <?= $row['like_count'] ?> || Comment: <?= $row['comment_count'] ?>
    </div>
  </div>
<?php endwhile; ?>
</div>


    <!-- PHÂN TRANG -->
    <?php if ($totalPages > 1): ?>
<div class="pagination">

    <!-- Nút previous -->
    <a class="<?= $page <= 1 ? 'disabled' : '' ?>"
       href="<?= $page > 1 ? '?page='.($page-1).($q!==''?'&q='.urlencode($q):'') : '#' ?>">
       «
    </a>

    <!-- Số trang -->
    <?php for ($p = 1; $p <= $totalPages; $p++): ?>
        <a class="<?= $p == $page ? 'active' : '' ?>"
           href="?page=<?= $p . ($q !== '' ? '&q='.urlencode($q) : '') ?>">
            <?= $p ?>
        </a>
    <?php endfor; ?>

    <!-- Nút next -->
    <a class="<?= $page >= $totalPages ? 'disabled' : '' ?>"
       href="<?= $page < $totalPages ? '?page='.($page+1).($q!==''?'&q='.urlencode($q):'') : '#' ?>">
       »
    </a>

</div>
<?php endif; ?>


  <?php endif; ?>
</div>

<script>
const input = document.getElementById('searchInput');
const box   = document.getElementById('suggest-box');
let timer = null;

input.addEventListener('keyup', function() {
    const q = this.value.trim();

    if (timer) clearTimeout(timer);

    timer = setTimeout(() => {
        if (q === "") {
            box.style.display = "none";
            box.innerHTML = "";
            return;
        }

        fetch("api/forum_search_suggest.php?q=" + encodeURIComponent(q))
            .then(res => res.json())
            .then(data => {
                if (data.length === 0) {
                    box.style.display = "none";
                    box.innerHTML = "";
                    return;
                }

                box.innerHTML = data.map(item =>
                    `<div style='padding:8px; cursor:pointer; border-bottom:1px solid #eee;'
                          onclick="selectSuggest('${item.title.replace(/'/g, "\\'")}', ${item.id})">
                        ${item.title}
                     </div>`
                ).join("");

                box.style.display = "block";
            });
    }, 200);
});

function selectSuggest(title, id) {
    input.value = title;
    box.style.display = "none";
    window.location = "forum_view.php?id=" + id;
}

document.addEventListener('click', function(e) {
    if (!input.contains(e.target)) {
        box.style.display = "none";
    }
});
</script>

<?php include 'includes/footer.php'; ?>
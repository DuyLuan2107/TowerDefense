<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once 'db/connect.php';
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
    SELECT p.*, u.name AS author, u.avatar,
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
  <h2>💬 Cộng Đồng Game</h2>

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
  <div class="forum-card" data-href="forum_view.php?id=<?= $row['id'] ?>" onclick="goToPost(event, this)">
    <div class="forum-icon">
    <img src="<?= $row['avatar'] ?: 'uploads/default.png' ?>" 
     alt="avatar" class="avatar-img">

</div>


    <div class="forum-content">

    <!-- TÊN USER -->
    <div class="author" style="font-weight:bold; color:#000;">
        <?= htmlspecialchars($row['author']) ?>
    </div>


    <!-- NGÀY THÁNG -->
    <?php
    $date = new DateTime($row['created_at']);
    $monthNames = [
        1 => 'Tháng 1', 2 => 'Tháng 2', 3 => 'Tháng 3', 4 => 'Tháng 4',
        5 => 'Tháng 5', 6 => 'Tháng 6', 7 => 'Tháng 7', 8 => 'Tháng 8',
        9 => 'Tháng 9', 10 => 'Tháng 10', 11 => 'Tháng 11', 12 => 'Tháng 12'
    ];
    $formattedDate = $date->format('d') . ' ' . $monthNames[(int)$date->format('m')] . ' ' . $date->format('Y');
    ?>
    <div class="date" style="font-size:0.9em; color:#777;">
        <?= $formattedDate ?>
    </div>

    <!-- TIÊU ĐỀ -->
    <a class="forum-title"
       href="forum_view.php?id=<?= $row['id'] ?>"
       style="display:block; font-size:1.1em; font-weight:bold; margin:6px 0;">
        <?= htmlspecialchars($row['title']) ?>
    </a>

    <!-- NỘI DUNG -->
    <div class="forum-excerpt">
        <?= nl2br(htmlspecialchars(mb_substr($row['content'], 0, 160))) ?>...
    </div>

</div>


    <div class="forum-stats">
        ❤️ <?= $row['like_count'] ?>   💬  <?= $row['comment_count'] ?>
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
function goToPost(e, card) {
    // Nếu click vào link/nút bên trong, không chuyển hướng
    if (e.target.tagName === 'A' || e.target.tagName === 'BUTTON') return;

    // Điều hướng đến bài viết
    window.location.href = card.dataset.href;
}
</script>


<?php include 'includes/footer.php'; ?>
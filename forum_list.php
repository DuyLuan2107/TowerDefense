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
    SELECT p.*, u.name AS author
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

<div class="profile-container" style="max-width:900px; text-align:left">
  <h2>💬 Cộng Đồng Game</h2>

  <!-- Form tìm kiếm + gợi ý -->
  <form method="get" style="position:relative; margin-bottom:15px; display:flex; gap:8px;">
    
    <div style="flex:1; position:relative;">
      <input type="text" name="q" id="searchInput" placeholder="Tìm bài viết..."
             value="<?= htmlspecialchars($q) ?>"
             autocomplete="off"
             style="width:100%;padding:8px;border-radius:8px;border:1px solid #ccc;">

      <!-- Gợi ý -->
      <div id="suggest-box"
           style="
             position:absolute;
             background:white;
             border:1px solid #ccc;
             width:100%;
             max-height:200px;
             overflow-y:auto;
             display:none;
             z-index:100;
             border-radius:8px;
             box-shadow:0 2px 6px rgba(0,0,0,0.15);
           ">
      </div>
    </div>

    <button class="btn-send" style="padding:8px 16px">Tìm</button>
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
    <?php while ($row = $result->fetch_assoc()): ?>
      <div style="padding:15px;border-bottom:1px solid #eee;">
        <h3 style="margin:0 0 5px;">
          <a href="forum_view.php?id=<?= $row['id'] ?>">
            <?= htmlspecialchars($row['title']) ?>
          </a>
        </h3>
        <div class="muted" style="font-size:0.9em;">
          By <?= htmlspecialchars($row['author']) ?> • 
          <?= $row['created_at'] ?>
        </div>
        <p style="margin-top:8px;">
          <?= nl2br(htmlspecialchars(mb_substr($row['content'], 0, 160))) ?>...
        </p>
      </div>
    <?php endwhile; ?>

    <!-- PHÂN TRANG -->
    <?php if ($totalPages > 1): ?>

      <?php
          $qs = $q !== '' ? '&q='.urlencode($q) : '';

          $prev = $page - 1;
          $next = $page + 1;

          // Số trang hiển thị xung quanh
          $range = 2;
          $start = max(1, $page - $range);
          $end   = min($totalPages, $page + $range);
      ?>

      <div style="margin-top:20px; display:flex; gap:8px; flex-wrap:wrap;">

          <!-- Đầu -->
          <?php if ($page > 1): ?>
            <a href="?page=1<?= $qs ?>" style="padding:5px 10px;border:1px solid #ccc;border-radius:6px;">
              <<
            </a>
          <?php endif; ?>

          <!-- Trước -->
          <?php if ($page > 1): ?>
            <a href="?page=<?= $prev . $qs ?>" style="padding:5px 10px;border:1px solid #ccc;border-radius:6px;">
              <
            </a>
          <?php endif; ?>

          <!-- Dấu ... phía trước nếu start > 1 -->
          <?php if ($start > 2): ?>
              <span style="padding:5px 10px;">...</span>
          <?php endif; ?>

          <!-- Các số trang -->
          <?php for ($p = $start; $p <= $end; $p++): ?>
            <?php
              $active = $p == $page;
              $style = $active
                ? "padding:5px 10px; font-weight:bold; background:#ddd; border-radius:6px;"
                : "padding:5px 10px;border:1px solid #ccc;border-radius:6px;";
            ?>
            <a href="?page=<?= $p . $qs ?>" style="<?= $style ?>"><?= $p ?></a>
          <?php endfor; ?>

          <!-- Dấu ... phía sau nếu end < totalPages -->
          <?php if ($end < $totalPages - 1): ?>
              <span style="padding:5px 10px;">...</span>
          <?php endif; ?>

          <!-- Sau -->
          <?php if ($page < $totalPages): ?>
            <a href="?page=<?= $next . $qs ?>" style="padding:5px 10px;border:1px solid #ccc;border-radius:6px;">
              >
            </a>
          <?php endif; ?>

          <!-- Cuối -->
          <?php if ($page < $totalPages): ?>
            <a href="?page=<?= $totalPages . $qs ?>" style="padding:5px 10px;border:1px solid #ccc;border-radius:6px;">
              >>
            </a>
          <?php endif; ?>

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
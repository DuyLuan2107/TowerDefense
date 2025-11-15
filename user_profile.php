<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once "db/connect.php";
include "includes/header.php";

$user_id = (int)($_GET['id'] ?? 0);
if ($user_id <= 0) {
    echo "<div class='profile-container'><p>Không tìm thấy người dùng.</p></div>";
    include "includes/footer.php"; exit;
}

/* Lấy thông tin người dùng */
$stmt = $conn->prepare("
    SELECT id, name, email, avatar, last_activity 
    FROM users WHERE id = ?
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if (!$user) {
    echo "<div class='profile-container'><p>Người dùng không tồn tại.</p></div>";
    include "includes/footer.php"; exit;
}

/* Kiểm tra trạng thái online */
$isOnline = false;
if ($user['last_activity']) {
    $last = strtotime($user['last_activity']);
    $isOnline = (time() - $last) <= 60; // 1 phút
}
?>

<div class="profile-container" style="max-width:700px;">

    <h2>👤 Hồ Sơ Người Dùng</h2>

    <div class="profile-card" style="
        display:flex;
        gap:20px;
        align-items:center;
        padding:20px;
        background:#f9f9f9;
        border-radius:12px;
        box-shadow:0 2px 6px rgba(0,0,0,0.1);
    ">
        <img src="<?= htmlspecialchars($user['avatar']) ?>"
             style="width:120px;height:120px;border-radius:50%;object-fit:cover;border:3px solid #ddd;">

        <div>
            <h3 style="margin:0;">
                <?= htmlspecialchars($user['name']) ?>
            </h3>

            <p>Email: <?= htmlspecialchars($user['email']) ?></p>

            <p>
                Trạng thái:
                <span style="color:<?= $isOnline ? "green" : "gray" ?>;">
                    ● <?= $isOnline ? "Đang hoạt động" : "Ngoại tuyến" ?>
                </span>
            </p>

            <p style="font-size:0.9em;color:#555;">
                Lần hoạt động gần nhất:
                <?= $user['last_activity'] ?? "Không rõ" ?>
            </p>
        </div>
    </div>

    <hr>

    <!-- Bài viết của người này -->
    <h3>Bài viết gần đây</h3>

    <?php
    $posts = $conn->query("
        SELECT id, title, created_at 
        FROM posts 
        WHERE user_id = $user_id
        ORDER BY created_at DESC
        LIMIT 10
    ");
    ?>

    <?php if ($posts->num_rows == 0): ?>
        <p class="muted">Người dùng chưa đăng bài nào.</p>
    <?php else: ?>
        <ul>
            <?php while ($p = $posts->fetch_assoc()): ?>
                <li style="margin-bottom:6px;">
                    <a href="forum_view.php?id=<?= $p['id'] ?>">
                        <?= htmlspecialchars($p['title']) ?>
                    </a>
                    <span class="muted" style="font-size:0.85em;">
                        • <?= $p['created_at'] ?>
                    </span>
                </li>
            <?php endwhile; ?>
        </ul>
    <?php endif; ?>

</div>

<?php include "includes/footer.php"; ?>

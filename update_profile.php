<?php
include "db/connect.php";

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user'])) {
    die("Bạn chưa đăng nhập.");
}

$user = $_SESSION['user'];
$user_id = $user['id'];

/* ------------------------- 1. ĐỔI AVATAR ------------------------- */
if (isset($_POST['change_avatar'])) {

    if (!empty($_FILES['avatar']['name'])) {
        $file = $_FILES['avatar'];
        $filename = "uploads/" . time() . "_" . basename($file['name']);
        move_uploaded_file($file['tmp_name'], $filename);

        $query = $conn->prepare("UPDATE users SET avatar=? WHERE id=?");
        $query->execute([$filename, $user_id]);

        $_SESSION['user']['avatar'] = $filename;
    }

    header("Location: profile.php");
    exit;
}


/* ------------------------- 2. ĐỔI TÊN ------------------------- */
if (isset($_POST['change_name'])) {
    $newName = $_POST['new_name'];

    $query = $conn->prepare("UPDATE users SET name=? WHERE id=?");
    $query->execute([$newName, $user_id]);

    $_SESSION['user']['name'] = $newName;

    header("Location: profile.php");
    exit;
}


/* ------------------------- 3. ĐỔI MẬT KHẨU ------------------------- */
if (isset($_POST['change_password'])) {
    $old = $_POST['old_password'];
    $new = $_POST['new_password'];

    // Lấy mật khẩu hiện tại
    $query = $conn->prepare("SELECT password FROM users WHERE id=?");
    $query->execute([$user_id]);
    $row = $query->fetch();

    if (!password_verify($old, $row['password'])) {
        die("❌ Mật khẩu cũ không chính xác.");
    }

    $newHash = password_hash($new, PASSWORD_DEFAULT);

    $update = $conn->prepare("UPDATE users SET password=? WHERE id=?");
    $update->execute([$newHash, $user_id]);

    echo "✔ Mật khẩu đã được thay đổi!";
    header("Refresh: 2; URL=profile.php");
    exit;
}
?>

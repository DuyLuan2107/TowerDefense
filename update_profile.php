<?php
include "db/connect.php";

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user'])) {
    $_SESSION['update_error'] = "Bạn chưa đăng nhập.";
    header("Location: profile.php");
    exit;
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
        $_SESSION['update_success'] = "Avatar đã được cập nhật!";
    }

    header("Location: profile.php#update");
    exit;
}


/* ------------------------- 2. ĐỔI TÊN ------------------------- */
if (isset($_POST['change_name'])) {

    $newName = trim($_POST['new_name']);

    if ($newName === "") {
        $_SESSION['update_error'] = "Tên không được để trống!";
        header("Location: profile.php#update");
        exit;
    }

    $query = $conn->prepare("UPDATE users SET name=? WHERE id=?");
    $query->execute([$newName, $user_id]);

    $_SESSION['user']['name'] = $newName;
    $_SESSION['update_success'] = "Tên đã được thay đổi!";

    header("Location: profile.php#update");
    exit;
}


/* ------------------------- 3. ĐỔI MẬT KHẨU ------------------------- */
if (isset($_POST['change_password'])) {

    $old = $_POST['old_password'];
    $new = $_POST['new_password'];

    // Lấy mật khẩu hiện tại
    $query = $conn->prepare("SELECT password FROM users WHERE id=?");
    $query->bind_param("i", $user_id);
    $query->execute();
    $result = $query->get_result();
    $row = $result->fetch_assoc();

    if (!$row) {
        $_SESSION['update_error'] = "Lỗi hệ thống: không tìm thấy tài khoản.";
        header("Location: profile.php#update");
        exit;
    }

    if (!password_verify($old, $row['password'])) {
        $_SESSION['update_error'] = "Mật khẩu cũ không chính xác.";
        header("Location: profile.php#update");
        exit;
    }

    if (strlen($new) < 6) {
        $_SESSION['update_error'] = "Mật khẩu mới phải ít nhất 6 ký tự.";
        header("Location: profile.php#update");
        exit;
    }

    $newHash = password_hash($new, PASSWORD_DEFAULT);

    $update = $conn->prepare("UPDATE users SET password=? WHERE id=?");
    $update->bind_param("si", $newHash, $user_id);
    $update->execute();

    $_SESSION['update_success'] = "Mật khẩu đã được cập nhật thành công!";
    header("Location: profile.php#update");
    exit;
}

?>
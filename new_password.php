<?php
session_start();
require "db/connect.php";

if (!isset($_SESSION['reset_user'])) {
    header("Location: auth.php");
    exit;
}

$message = "";

if (isset($_POST['change'])) {
    $pass = $_POST['password'];
    $confirm = $_POST['confirm_password'];

    if ($pass !== $confirm) {
        $message = "<div class='auth-message error'>❌ Mật khẩu nhập lại không khớp!</div>";
    } else {
        $hash = password_hash($pass, PASSWORD_BCRYPT);
        $id = $_SESSION['reset_user'];

        $conn->query("UPDATE users SET password='$hash' WHERE id=$id");

        unset($_SESSION['reset_user']);

        $message = "<div class='auth-message success'>🎉 Đổi mật khẩu thành công! Hãy đăng nhập.</div>";
    }
}

include "includes/header.php";
?>

<div class="auth-container">
    <?= $message ?>

    <div class="form-box">
        <h2>🔄 Đặt mật khẩu mới</h2>
        <form method="post">
            <input type="password" name="password" placeholder="Mật khẩu mới" required>
            <input type="password" name="confirm_password" placeholder="Nhập lại mật khẩu" required>

            <button type="submit" name="change">Đổi mật khẩu</button>
        </form>
    </div>
</div>

<?php include "includes/footer.php"; ?>

<?php
session_start();
require "db/connect.php";

$message = "";

if (isset($_POST['reset'])) {
    $email = $conn->real_escape_string($_POST['email']);
    $secret = $_POST['secret_code'];

    $res = $conn->query("SELECT * FROM users WHERE email='$email'");
    if ($res && $res->num_rows > 0) {
        $user = $res->fetch_assoc();

        if (password_verify($secret, $user['secret_code'])) {
            $_SESSION['reset_user'] = $user['id'];
            header("Location: new_password.php");
            exit;
        } else {
            $message = "<div class='auth-message error'>❌ Mã bí mật không đúng!</div>";
        }
    } else {
        $message = "<div class='auth-message error'>❌ Email không tồn tại!</div>";
    }
}

include "includes/header.php";
?>

<div class="auth-container">
    <?= $message ?>

    <div class="form-box">
        <h2>🔐 Khôi phục mật khẩu</h2>
        <form method="post">
            <input type="email" name="email" placeholder="Nhập email" required>
            <input type="text" name="secret_code" placeholder="Nhập mã bí mật" required>

            <button type="submit" name="reset">Xác nhận</button>
        </form>
    </div>
</div>

<?php include "includes/footer.php"; ?>

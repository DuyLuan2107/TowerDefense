<?php
include "includes/header.php";
include "db/connect.php";
if (session_status() === PHP_SESSION_NONE) {
    session_start();
};

$message = "";

// Xử lý Đăng ký
if (isset($_POST['register'])) {
    $name = $conn->real_escape_string($_POST['name']);
    $email = $conn->real_escape_string($_POST['email']);
    $password = password_hash($_POST['password'], PASSWORD_BCRYPT);

    $check = $conn->query("SELECT * FROM users WHERE email='$email'");
    if ($check && $check->num_rows > 0) {
        $message = "<div class='auth-message error'>Email đã tồn tại!</div>";
    } else {
        $conn->query("INSERT INTO users(name, email, password) VALUES('$name', '$email', '$password')");
        $message = "<div class='auth-message success'>🎉 Đăng ký thành công! Vui lòng đăng nhập.</div>";
    }
}

// Xử lý Đăng nhập
if (isset($_POST['login'])) {
    $email = $conn->real_escape_string($_POST['email']);
    $password = $_POST['password'];

    $res = $conn->query("SELECT * FROM users WHERE email='$email'");
    if ($res && $res->num_rows > 0) {
        $user = $res->fetch_assoc();
        if (password_verify($password, $user['password'])) {
            $_SESSION['user'] = $user;
            header("Location: profile.php");
            exit;
        } else {
            $message = "<div class='auth-message error'>❌ Sai mật khẩu!</div>";
        }
    } else {
        $message = "<div class='auth-message error'>❌ Email không tồn tại!</div>";
    }
}
?>

<div class="auth-container">
  <?= $message ?>

  <!-- Form Đăng nhập -->
  <div class="form-box" id="login-form">
    <h2>🔑 Đăng Nhập</h2>
    <form method="post">
      <input type="email" name="email" placeholder="Email" required>
      <input type="password" name="password" placeholder="Mật khẩu" required>
      <button type="submit" name="login">Đăng Nhập</button>
    </form>
    <p>Chưa có tài khoản?
      <a href="#" onclick="showRegister()">Đăng ký ngay</a>
    </p>
  </div>

  <!-- Form Đăng ký -->
  <div class="form-box hidden" id="register-form">
    <h2>📝 Đăng Ký</h2>
    <form method="post">
      <input type="text" name="name" placeholder="Họ và tên" required>
      <input type="email" name="email" placeholder="Email" required>
      <input type="password" name="password" placeholder="Mật khẩu" required>
      <button type="submit" name="register">Đăng Ký</button>
    </form>
    <p>Đã có tài khoản?
      <a href="#" onclick="showLogin()">Đăng nhập ngay</a>
    </p>
  </div>
</div>

<script>
function showRegister() {
  document.getElementById("login-form").classList.add("hidden");
  document.getElementById("register-form").classList.remove("hidden");
}

function showLogin() {
  document.getElementById("register-form").classList.add("hidden");
  document.getElementById("login-form").classList.remove("hidden");
}
</script>

<?php include "includes/footer.php"; ?>
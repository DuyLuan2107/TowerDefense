<?php
session_start();                 // 1. BẮT BUỘC nằm trên cùng
require "db/connect.php";        // 2. Kết nối DB

$message = "";

// =================================================
// 1. TỰ ĐỘNG ĐĂNG NHẬP NẾU CÓ COOKIE REMEMBER TOKEN
// =================================================
if (!isset($_SESSION['user']) && isset($_COOKIE['remember_token'])) {
    $token = $conn->real_escape_string($_COOKIE['remember_token']);

    $res = $conn->query("
        SELECT users.* FROM login_tokens
        JOIN users ON users.id = login_tokens.user_id
        WHERE token='$token' AND expiry > NOW()
    ");

    if ($res && $res->num_rows > 0) {
        $_SESSION['user'] = $res->fetch_assoc();
        header("Location: profile.php");
        exit;
    }
}

// ============================
// 2. XỬ LÝ ĐĂNG KÝ
// ============================
if (isset($_POST['register'])) {
    $name = trim($conn->real_escape_string($_POST['name']));
    $email = trim($conn->real_escape_string($_POST['email']));
    $ingame = trim($conn->real_escape_string($_POST['ingame_name']));
    $secret = trim($_POST['secret_code']);

    $password = $_POST['password'];
    $confirm = $_POST['confirm_password'];

    if (!preg_match("/^[A-Za-z0-9_]+$/", $name)) {
        $message = "<div class='auth-message error'>❌ Tên chỉ được chứa chữ cái, số và dấu gạch dưới!</div>";
    }
    elseif (strlen($secret) < 4) {
        $message = "<div class='auth-message error'>❌ Mã bí mật phải ít nhất 4 ký tự!</div>";
    }
    elseif ($password !== $confirm) {
        $message = "<div class='auth-message error'>❌ Mật khẩu nhập lại không khớp!</div>";
    }
    else {
        $check = $conn->query("SELECT * FROM users WHERE email='$email'");
        if ($check && $check->num_rows > 0) {
            $message = "<div class='auth-message error'>❌ Email đã tồn tại!</div>";
        } else {

            // Xử lý ảnh
            $avatarPath = "uploads/default.png";

            if (!empty($_FILES['avatar']['name'])) {
                $file = $_FILES['avatar'];
                $nameFile = $file['name'];
                $tmp = $file['tmp_name'];
                $size = $file['size'];

                $ext = strtolower(pathinfo($nameFile, PATHINFO_EXTENSION));
                $allowed = ["jpg","jpeg","png"];

                if (!in_array($ext, $allowed)) {
                    $message = "<div class='auth-message error'>❌ Chỉ chấp nhận JPG, JPEG, PNG!</div>";
                }
                elseif ($size > 2 * 1024 * 1024) {
                    $message = "<div class='auth-message error'>❌ Ảnh phải nhỏ hơn 2MB!</div>";
                }
                else {
                    $newFile = "avatar_" . time() . rand(1000,9999) . ".$ext";
                    $upload = "uploads/$newFile";
                    if (move_uploaded_file($tmp, $upload)) {
                        $avatarPath = $upload;
                    }
                }
            }

            // Lưu người dùng
            if ($message == "") {
                $hashed = password_hash($password, PASSWORD_BCRYPT);
                $secretHash = password_hash($secret, PASSWORD_BCRYPT);

                $conn->query("
                    INSERT INTO users(name, email, password, avatar, ingame_name, secret_code)
                    VALUES('$name', '$email', '$hashed', '$avatarPath', '$ingame', '$secretHash')
                ");

                $message = "<div class='auth-message success'>🎉 Đăng ký thành công! Hãy đăng nhập.</div>";
            }
        }
    }
}


// ============================
// 3. XỬ LÝ ĐĂNG NHẬP
// ============================
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
        }
        else {
            $message = "<div class='auth-message error'>❌ Sai mật khẩu!</div>";
        }
    }
    else {
        $message = "<div class='auth-message error'>❌ Email không tồn tại!</div>";
    }
}

include "includes/header.php";
?>

<!-- GIAO DIỆN AUTH -->
<div class="auth-container">
  <?= $message ?>

  <!-- FORM LOGIN -->
  <div class="form-box" id="login-form">
    <h2>🔑 Đăng Nhập</h2>
    <form method="post">

      <input type="email" name="email" placeholder="Email" required>
      <input type="password" name="password" placeholder="Mật khẩu" required>

      <label style="margin-top:10px;display:flex;align-items:center;gap:6px;">
        <input type="checkbox" name="remember"> Ghi nhớ đăng nhập
      </label>

      <button type="submit" name="login">Đăng Nhập</button>
    </form>

    <p>
      <a href="forgot_password.php">Quên mật khẩu?</a>
    </p>

    <p>Chưa có tài khoản?
      <a href="#" onclick="showRegister()">Đăng ký ngay</a>
    </p>
  </div>

  <!-- FORM REGISTER -->
  <div class="form-box hidden" id="register-form">
    <h2>📝 Đăng Ký</h2>
    <form method="post" enctype="multipart/form-data">

      <input type="text" name="name" placeholder="Tên đăng nhập" required>
      <input type="email" name="email" placeholder="Email" required>

      <input type="text" name="ingame_name" placeholder="Tên Ingame" required>
      <input type="text" name="secret_code" placeholder="Mã bí mật (dùng khi quên mật khẩu)" required>

      <label>Ảnh đại diện:</label>
      <input type="file" name="avatar" accept="image/*">

      <input type="password" name="password" placeholder="Mật khẩu" required>
      <input type="password" name="confirm_password" placeholder="Xác nhận mật khẩu" required>

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

<?php
session_start();
require "db/connect.php";

$message = "";

// Xử lý cookie remember token
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

// Xử lý đăng ký
if (isset($_POST['register'])) {
    $name = trim($conn->real_escape_string($_POST['name']));
    $email = trim($conn->real_escape_string($_POST['email']));
    $secret = trim($_POST['secret_code']);
    $password = $_POST['password'];
    $confirm = $_POST['confirm_password'];

    if (!preg_match('/^[\p{L}\p{N}_ ]+$/u', $name)) {
    $message = "<div class='auth-message error'>❌ Tên chỉ được chứa chữ, số, khoảng trắng và dấu gạch dưới!</div>";
    } elseif (strlen($secret) < 4) {
        $message = "<div class='auth-message error'>❌ Mã bí mật phải ≥ 4 ký tự!</div>";
    } elseif ($password !== $confirm) {
        $message = "<div class='auth-message error'>❌ Mật khẩu nhập lại không khớp!</div>";
    } else {
        $check = $conn->query("SELECT * FROM users WHERE email='$email'");
        if ($check && $check->num_rows > 0) {
            $message = "<div class='auth-message error'>❌ Email đã tồn tại!</div>";
        } else {
            $avatarPath = "uploads/default.png";
            if (!empty($_FILES['avatar']['name'])) {
                $file = $_FILES['avatar'];
                $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                if (!in_array($ext, ["jpg","jpeg","png"])) {
                    $message = "<div class='auth-message error'>❌ Chỉ JPG, JPEG, PNG!</div>";
                } elseif ($file['size'] > 2*1024*1024) {
                    $message = "<div class='auth-message error'>❌ Ảnh < 2MB!</div>";
                } else {
                    $newFile = "avatar_".time().rand(1000,9999).".$ext";
                    $upload = "uploads/$newFile";
                    if (move_uploaded_file($file['tmp_name'], $upload)) {
                        $avatarPath = $upload;
                    }
                }
            }
            if ($message == "") {
                $hashed = password_hash($password, PASSWORD_BCRYPT);
                $secretHash = password_hash($secret, PASSWORD_BCRYPT);

                $conn->query("
                    INSERT INTO users(name,email,password,avatar,secret_code,role)
                    VALUES('$name','$email','$hashed','$avatarPath','$secretHash','user')
                ");

                $message = "<div class='auth-message success'>🎉 Đăng ký thành công! Hãy đăng nhập.</div>";
            }
        }
    }
}

// Xử lý đăng nhập
if (isset($_POST['login'])) {
    $email = $conn->real_escape_string($_POST['email']);
    $password = $_POST['password'];

    $res = $conn->query("SELECT id, name, email, role, password FROM users WHERE email='$email'");
    if ($res && $res->num_rows > 0) {
        $user = $res->fetch_assoc();
        if (password_verify($password, $user['password'])) {
            // Lưu vào session chỉ những trường cần thiết
            $_SESSION['user'] = [
                'id' => $user['id'],
                'name' => $user['name'],
                'email' => $user['email'],
                'role' => $user['role']    // <--- cực quan trọng cho admin
            ];
            header("Location: profile.php");
            exit;
        } else {
            $message = "<div class='auth-message error'>❌ Sai mật khẩu!</div>";
        }
    } else {
        $message = "<div class='auth-message error'>❌ Email không tồn tại!</div>";
    }
}


include "includes/header.php"; // navbar
?>

<style>
/* ====== AUTH CONTAINER GIỮ NỀN TRẮNG VÀ CĂN GIỮA ====== */
.auth-wrapper {
    min-height: calc(100vh - 120px); /* trừ header + footer nếu khoảng 120px */
    display: flex;
    justify-content: center;
    align-items: center;
    background: #f5f5f5;
    padding: 40px 20px;
}

.auth-container {
    width: 420px;
    background: #fff;
    padding: 30px;
    border-radius: 15px;
    box-shadow: 0 0 20px rgba(0,0,0,0.1);
}

/* MESSAGE */
.auth-message {
    padding: 12px;
    text-align: center;
    margin-bottom: 15px;
    border-radius: 8px;
    font-weight: 600;
}

.auth-message.error { background: #ffeded; color: #ff3b3b; border: 1px solid #ff6b6b55; }
.auth-message.success { background: #e0f7f1; color: #1abc9c; border: 1px solid #1abc9c55; }

/* FORM */
.form-box { display: block; }
.form-box.hidden { display: none; }
.form-box h2 { text-align: center; margin-bottom: 15px; font-size: 26px; color: #007bff; }

.form-box input {
    width: 100%;
    padding: 12px;
    margin: 8px 0;
    border-radius: 8px;
    border: 1px solid #ccc;
    background: #f0f0f0;
    color: #333;
}

.form-box input:focus {
    border-color: #007bff;
    box-shadow: 0 0 5px #007bff55;
}

.form-box button {
    width: 100%;
    padding: 12px;
    margin-top: 12px;
    border-radius: 8px;
    border: none;
    background: linear-gradient(135deg, #00eaff, #007bff);
    color: #fff;
    font-weight: 600;
    cursor: pointer;
}

.form-box button:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px #007bff33;
}

.form-box p { text-align: center; color: #555; margin-top: 10px; }
.form-box a { color: #007bff; text-decoration: none; font-weight: 600; }
.form-box a:hover { text-decoration: underline; }
</style>

<div class="auth-wrapper">
    <div class="auth-container">
        <?= $message ?>

        <!-- FORM LOGIN -->
        <div class="form-box" id="login-form">
            <h2>🔑 Đăng Nhập</h2>
            <form method="post">
                <input type="email" name="email" placeholder="Email" required>
                <input type="password" name="password" placeholder="Mật khẩu" required>
                <label style="display:flex;align-items:center;gap:8px;color:#555;">
                    <input type="checkbox" name="remember" style="width:18px;height:18px;"> Ghi nhớ đăng nhập
                </label>
                <button type="submit" name="login">Đăng Nhập</button>
            </form>
            <p><a href="forgot_password.php">Quên mật khẩu?</a></p>
            <p>Chưa có tài khoản? <a href="#" onclick="showRegister()">Đăng ký ngay</a></p>
        </div>

        <!-- FORM REGISTER -->
        <div class="form-box hidden" id="register-form">
            <h2>📝 Đăng Ký</h2>
            <form method="post" enctype="multipart/form-data">
                <input type="email" name="email" placeholder="Email" required>
                <input type="text" name="name" placeholder="Tên Ingame" required>
                <input type="text" name="secret_code" placeholder="Mã bí mật" required>
                <label style="color:#555;">Ảnh đại diện:</label>
                <input type="file" name="avatar" accept="image/*">
                <input type="password" name="password" placeholder="Mật khẩu" required>
                <input type="password" name="confirm_password" placeholder="Xác nhận mật khẩu" required>
                <button type="submit" name="register">Đăng Ký</button>
            </form>
            <p>Đã có tài khoản? <a href="#" onclick="showLogin()">Đăng nhập ngay</a></p>
        </div>
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

<?php
session_start();
require "db/connect.php"; // Kết nối CSDL

// ----------------------------------------------------------------
// [LOGIC 1] KIỂM TRA ĐĂNG NHẬP & REMEMBER ME (FIX LỖI TOKEN)
// ----------------------------------------------------------------

// A. Nếu đã có Session (đang đăng nhập) -> Vào thẳng Profile
if (isset($_SESSION['user'])) {
    header("Location: profile.php");
    exit;
}

// B. Nếu chưa có Session nhưng có Cookie (Tự động đăng nhập)
if (isset($_COOKIE['remember_token'])) {
    $token = $_COOKIE['remember_token'];

    // Kiểm tra token trong Database xem có hợp lệ và còn hạn không
    $stmt = $conn->prepare("
        SELECT users.id, users.name, users.email, users.role, users.password 
        FROM login_tokens
        JOIN users ON users.id = login_tokens.user_id
        WHERE token = ? AND expiry > NOW()
    ");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res && $res->num_rows > 0) {
        // ==> Token Hợp lệ: Tạo Session và vào Profile
        $user = $res->fetch_assoc();
        $_SESSION['user'] = [
            'id' => $user['id'],
            'name' => $user['name'],
            'email' => $user['email'],
            'role' => $user['role']
        ];
        header("Location: profile.php");
        exit;
    } else {
        // ==> Token KHÔNG hợp lệ (Hết hạn hoặc Token rác do chưa xóa sạch khi Logout cũ)
        // BẮT BUỘC: Xóa ngay cookie này đi để tránh vòng lặp lỗi
        setcookie('remember_token', '', time() - 3600, '/');
        unset($_COOKIE['remember_token']);
    }
}

$message = "";
$login_email_sticky = ""; 

// ----------------------------------------------------------------
// [LOGIC 2] XỬ LÝ ĐĂNG KÝ
// ----------------------------------------------------------------
if (isset($_POST['register'])) {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $secret = trim($_POST['secret_code']);
    $password = $_POST['password'];
    $confirm = $_POST['confirm_password'];

    // Validation cơ bản
    if (empty($name) || empty($email) || empty($password)) {
         $message = "<div class='auth-message error'>❌ Vui lòng điền đầy đủ thông tin!</div>";
    } elseif ($password !== $confirm) {
         $message = "<div class='auth-message error'>❌ Mật khẩu xác nhận không khớp!</div>";
    } else {
        // Kiểm tra Email tồn tại
        $stmt_check = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $stmt_check->bind_param("s", $email);
        $stmt_check->execute();
        
        if ($stmt_check->get_result()->num_rows > 0) {
            $message = "<div class='auth-message error'>❌ Email này đã được sử dụng!</div>";
        } else {
            // Xử lý Avatar (Mặc định hoặc Upload)
            $avatarPath = "uploads/avatar/default.png"; 
            if (!empty($_FILES['avatar']['name'])) {
                $target_dir = "uploads/avatar/";
                if (!file_exists($target_dir)) mkdir($target_dir, 0777, true);
                
                $file_extension = pathinfo($_FILES["avatar"]["name"], PATHINFO_EXTENSION);
                $new_filename = uniqid() . '.' . $file_extension; // Tên file ngẫu nhiên
                $target_file = $target_dir . $new_filename;
                
                if (move_uploaded_file($_FILES["avatar"]["tmp_name"], $target_file)) {
                    $avatarPath = $target_file;
                }
            }

            // Mã hóa mật khẩu & secret
            $hashed_password = password_hash($password, PASSWORD_BCRYPT);
            $hashed_secret = password_hash($secret, PASSWORD_BCRYPT);
            $role = 'user';

            // Insert vào DB
            $stmt_insert = $conn->prepare("INSERT INTO users (name, email, password, avatar, secret_code, role) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt_insert->bind_param("ssssss", $name, $email, $hashed_password, $avatarPath, $hashed_secret, $role);
            
            if ($stmt_insert->execute()) {
                $message = "<div class='auth-message success'>🎉 Đăng ký thành công! Hãy đăng nhập ngay.</div>";
            } else {
                $message = "<div class='auth-message error'>❌ Lỗi hệ thống: " . $conn->error . "</div>";
            }
        }
    }
}

// ----------------------------------------------------------------
// [LOGIC 3] XỬ LÝ ĐĂNG NHẬP
// ----------------------------------------------------------------
if (isset($_POST['login'])) {
    $login_email_sticky = $_POST['email'];
    $password = $_POST['password'];
    $remember = isset($_POST['remember']); // Kiểm tra checkbox

    $stmt = $conn->prepare("SELECT id, name, email, role, password, is_locked FROM users WHERE email = ?");
    $stmt->bind_param("s", $login_email_sticky);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res && $res->num_rows > 0) {
        $user = $res->fetch_assoc();

        if ($user['is_locked'] == 1) {
            $message = "<div class='auth-message error'>❌ Tài khoản bị khóa. Liên hệ Admin.</div>";
        } elseif (password_verify($password, $user['password'])) {
            // 1. Tạo Session
            $_SESSION['user'] = [
                'id'    => $user['id'],
                'name'  => $user['name'],
                'email' => $user['email'],
                'role'  => $user['role']
            ];

            // 2. Xử lý Ghi nhớ đăng nhập (Tạo Token mới)
            if ($remember) {
                $token = bin2hex(random_bytes(64));
                $expiry_time = time() + (86400 * 30); // 30 ngày
                $expiry_db = date("Y-m-d H:i:s", $expiry_time);
                
                // Set Cookie (Quan trọng: path='/', httponly=true)
                setcookie('remember_token', $token, $expiry_time, "/", "", false, true);

                // Xóa token cũ của user này trong DB để tránh rác
                $user_id = $user['id'];
                $conn->query("DELETE FROM login_tokens WHERE user_id = $user_id");

                // Lưu token mới vào DB
                $stmt_token = $conn->prepare("INSERT INTO login_tokens (user_id, token, expiry) VALUES (?, ?, ?)");
                $stmt_token->bind_param("iss", $user_id, $token, $expiry_db);
                $stmt_token->execute();
            }

            header("Location: profile.php");
            exit;
        } else {
            $message = "<div class='auth-message error'>❌ Sai mật khẩu!</div>";
        }
    } else {
        $message = "<div class='auth-message error'>❌ Email không tồn tại!</div>";
    }
}

include "includes/header.php"; 
?>

<style>
/* CSS Auth (Có thể tùy chỉnh) */
.auth-wrapper { min-height: 80vh; display: flex; justify-content: center; align-items: center; background: #f4f7f6; padding: 20px; font-family: sans-serif; }
.auth-container { width: 420px; background: #fff; padding: 35px; border-radius: 16px; box-shadow: 0 10px 30px rgba(0, 0, 0, 0.07); }
.form-box h2 { text-align: center; color: #004aad; margin-bottom: 25px; font-weight: 700; }
.input-group { position: relative; margin-bottom: 18px; }
.form-box input { width: 100%; padding: 14px 16px; border-radius: 10px; border: 1px solid #dcdcdc; background: #f0f3f8; box-sizing: border-box; }
.form-box input:focus { border-color: #6a11cb; outline: none; background: #fff; }
.form-box button { width: 100%; padding: 14px; margin-top: 15px; border-radius: 10px; border: none; background: linear-gradient(135deg, #2575fc 0%, #6a11cb 100%); color: #fff; font-weight: 600; cursor: pointer; transition: 0.3s; }
.form-box button:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(106, 17, 203, 0.3); }
.auth-message { padding: 12px; text-align: center; margin-bottom: 20px; border-radius: 10px; font-weight: 600; font-size: 14px; }
.auth-message.error { background: #fff0f0; color: #d93030; border: 1px solid #f9c0c0; }
.auth-message.success { background: #f0fff4; color: #28a745; border: 1px solid #b8f0c8; }
.toggle-link { text-align: center; margin-top: 15px; font-size: 14px; color: #555; }
.toggle-link a { color: #2575fc; text-decoration: none; font-weight: 600; cursor: pointer; }
.hidden { display: none; }
</style>

<div class="auth-wrapper">
    <div class="auth-container">
        <?= $message ?>

        <div class="form-box" id="login-form">
            <h2>🔑 Đăng Nhập</h2>
            <form method="post" action=""> 
                <div class="input-group">
                    <input type="email" name="email" placeholder="Email" required value="<?= htmlspecialchars($login_email_sticky) ?>">
                </div>
                <div class="input-group">
                    <input type="password" name="password" placeholder="Mật khẩu" required>
                </div>
                
                <div style="display: flex; justify-content: center; margin: 10px 0;">
                    <label style="display: flex; align-items: center; gap: 8px; color: #555; font-size: 14px; cursor: pointer;">
                        <input type="checkbox" name="remember" style="width: 16px; height: 16px; margin: 0;"> Ghi nhớ đăng nhập
                    </label>
                </div>
                
                <button type="submit" name="login">Đăng Nhập</button>
            </form>
            <div class="toggle-link">
                <a href="forgot_password.php">Quên mật khẩu?</a><br><br>
                Chưa có tài khoản? <a onclick="showRegister()">Đăng ký ngay</a>
            </div>
        </div>

        <div class="form-box hidden" id="register-form">
            <h2>📝 Đăng Ký</h2>
            <form method="post" enctype="multipart/form-data" action="">
                <input type="hidden" name="register" value="1">

                <div class="input-group">
                    <input type="email" name="email" placeholder="Email" required>
                </div>
                <div class="input-group">
                    <input type="text" name="name" placeholder="Tên hiển thị" required>
                </div>
                <div class="input-group">
                    <input type="text" name="secret_code" placeholder="Mã bí mật (Để lấy lại pass)" required>
                </div>
                <label style="font-size: 13px; color: #666; display: block; margin-bottom: 5px;">Ảnh đại diện (Tùy chọn):</label>
                <div class="input-group">
                    <input type="file" name="avatar" accept="image/*" style="padding: 10px; background: white;">
                </div>
                <div class="input-group">
                    <input type="password" name="password" placeholder="Mật khẩu (>= 6 ký tự)" required>
                </div>
                <div class="input-group">
                    <input type="password" name="confirm_password" placeholder="Xác nhận mật khẩu" required>
                </div>
                
                <button type="submit">Đăng Ký</button>
            </form>
            <div class="toggle-link">
                Đã có tài khoản? <a onclick="showLogin()">Đăng nhập ngay</a>
            </div>
        </div>
    </div>
</div>

<script>
// Javascript đơn giản để chuyển đổi giữa 2 form
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
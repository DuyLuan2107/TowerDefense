<?php
session_start();
require "db/connect.php";

$message = "";
$messageClass = ""; 

// Biến để lưu lại email người dùng đã nhập (Sticky value)
$entered_email = "";

if (isset($_POST['reset_request'])) {
    $email = trim($_POST['email']);
    $entered_email = $email; // Lưu lại giá trị email để điền lại vào form

    // 1. Validate Email
    if (empty($email)) {
        $message = "Vui lòng nhập email!";
        $messageClass = "error";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
         $message = "Email không hợp lệ!";
         $messageClass = "error";
    } else {
        // 2. Kiểm tra email trong CSDL
        $stmt = $conn->prepare("SELECT id, name, secret_code FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $res = $stmt->get_result();

        if ($res->num_rows > 0) {
            $user = $res->fetch_assoc();
            
            // 3. Kiểm tra mã bí mật
            if (isset($_POST['secret_code'])) {
                $secret_input = $_POST['secret_code'];
                
                if (empty($secret_input)) {
                    $message = "Vui lòng nhập mã bí mật!";
                    $messageClass = "error";
                } elseif (password_verify($secret_input, $user['secret_code'])) {
                     // Mã đúng -> Chuyển hướng đến trang đặt lại mật khẩu
                     $_SESSION['reset_user'] = $user['id'];
                     header("Location: new_password.php");
                     exit;
                } else {
                    $message = "Mã bí mật không đúng!";
                    $messageClass = "error";
                    // Lưu ý: Không lưu lại mã bí mật (sticky) vì lý do bảo mật
                }
            } 
        } else {
            $message = "Email này chưa được đăng ký!";
            $messageClass = "error";
        }
    }
}

include "includes/header.php";
?>

<!-- FontAwesome cho icon -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

<style>
/* ====== GIAO DIỆN SaaS HIỆN ĐẠI (Nhúng trực tiếp) ====== */
body {
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    background-color: #f4f7f6;
    margin: 0; padding: 0;
}
.auth-wrapper {
    min-height: calc(100vh - 120px);
    display: flex;
    justify-content: center;
    align-items: center;
    padding: 40px 20px;
}
.auth-container {
    width: 420px;
    background: #ffffff;
    padding: 35px;
    border-radius: 16px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.07);
    text-align: center; 
}
.form-box h2 { 
    text-align: center; margin-bottom: 25px; font-size: 26px; font-weight: 700; color: #004aad; 
    display: flex; align-items: center; justify-content: center; gap: 10px;
}
.form-box p {
    color: #666; font-size: 14px; margin-bottom: 25px; line-height: 1.5;
}
.input-group {
    position: relative; margin-bottom: 18px;
}
.form-box input {
    width: 100%; padding: 14px 16px; margin: 0; 
    border-radius: 10px; border: 1px solid #dcdcdc; 
    background: #f0f3f8; color: #333; 
    box-sizing: border-box; transition: all 0.2s ease-in-out; font-size: 15px;
}
.form-box input:focus {
    border-color: #6a11cb; box-shadow: 0 0 8px rgba(106, 17, 203, 0.2); outline: none; background: #fff;
}
/* Input Readonly (khi chưa click vào) để tránh autocomplete */
.form-box input[readonly] {
    background-color: #f0f3f8; cursor: text;
}
.form-box button {
    width: 100%; padding: 14px; margin-top: 10px; border-radius: 10px; border: none;
    background: linear-gradient(135deg, #2575fc 0%, #6a11cb 100%);
    color: #ffffff; font-weight: 600; font-size: 16px; cursor: pointer;
    transition: all 0.3s; box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
}
.form-box button:hover { 
    transform: translateY(-3px); box-shadow: 0 8px 20px rgba(106, 17, 203, 0.3);
}
.form-footer { margin-top: 25px; }
.form-footer a { 
    color: #2575fc; text-decoration: none; font-weight: 600; transition: color 0.2s;
}
.form-footer a:hover { color: #6a11cb; text-decoration: underline; }
.auth-message {
    padding: 12px; text-align: center; margin-bottom: 20px; border-radius: 10px; font-weight: 600; font-size: 14px;
}
.auth-message.error { background: #fff0f0; color: #d93030; border: 1px solid #f9c0c0; }
.auth-message.success { background: #f0fff4; color: #28a745; border: 1px solid #b8f0c8; }

/* ICON MẮT */
.toggle-password {
    position: absolute; top: 50%; right: 15px; transform: translateY(-50%); 
    color: #999; cursor: pointer; z-index: 5; font-size: 18px; user-select: none;
}
.toggle-password:hover { color: #2575fc; }
</style>

<div class="auth-wrapper">
    <div class="auth-container">
        
        <?php if (!empty($message)): ?>
            <div class="auth-message <?= $messageClass ?>">
                <?= $messageClass == 'success' ? '✅' : '❌' ?> <?= $message ?>
            </div>
        <?php endif; ?>

        <div class="form-box">
            <h2>
                <i class="fa-solid fa-key" style="color: #fbc02d;"></i> 
                Khôi Phục Mật Khẩu
            </h2>
            
            <p>
                Vui lòng nhập email và mã bí mật của bạn để đặt lại mật khẩu.
            </p>

            <!-- Form tắt autocomplete -->
            <form method="post" autocomplete="off">
                
                <!-- Hack: Input ẩn để đánh lừa trình duyệt -->
                <input style="display:none" type="text" name="fakeusernameremembered"/>
                <input style="display:none" type="password" name="fakepasswordremembered"/>

                <div class="input-group">
                    <!-- STICKY FORM: value="<?= htmlspecialchars($entered_email) ?>" -->
                    <input type="email" name="email" placeholder="Nhập email của bạn..." required 
                           autocomplete="off" 
                           readonly 
                           onfocus="this.removeAttribute('readonly');"
                           value="<?= htmlspecialchars($entered_email) ?>">
                </div>
                
                <div class="input-group">
                    <input type="password" name="secret_code" id="secret_code" placeholder="Nhập mã bí mật..." required 
                           autocomplete="new-password" 
                           readonly 
                           onfocus="this.removeAttribute('readonly');">
                    <!-- Icon mắt -->
                    <i class="fa-solid fa-eye toggle-password" onclick="togglePassword('secret_code', this)"></i>
                </div>
                
                <button type="submit" name="reset_request">Xác nhận</button>
            </form>

            <div class="form-footer">
                <p style="margin-bottom: 10px;">
                    <a href="auth.php">← Quay lại Đăng nhập</a>
                </p>
                <p>
                    Chưa có tài khoản? <a href="auth.php">Đăng ký ngay</a>
                </p>
            </div>
        </div>
    </div>
</div>

<script>
// Hàm bật tắt hiển thị mật khẩu
function togglePassword(fieldId, icon) {
    const input = document.getElementById(fieldId);
    if (input.type === "password") {
        input.type = "text";
        icon.classList.remove("fa-eye");
        icon.classList.add("fa-eye-slash");
    } else {
        input.type = "password";
        icon.classList.remove("fa-eye-slash");
        icon.classList.add("fa-eye");
    }
}
</script>

<?php include "includes/footer.php"; ?>
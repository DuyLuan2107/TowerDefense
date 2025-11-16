<?php
session_start();
require "db/connect.php"; // Chắc chắn rằng $conn là đối tượng mysqli

$message = "";

// ----------------------------------------------------
// 1. Xử lý cookie remember token
// ----------------------------------------------------
if (!isset($_SESSION['user']) && isset($_COOKIE['remember_token'])) {
    $token = $_COOKIE['remember_token'];

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
        $user = $res->fetch_assoc();
        $_SESSION['user'] = [
            'id' => $user['id'],
            'name' => $user['name'],
            'email' => $user['email'],
            'role' => $user['role']
        ];
        header("Location: profile.php");
        exit;
    }
}

// ----------------------------------------------------
// 2. Xử lý đăng ký (Đã xóa lỗi $message)
// ----------------------------------------------------
if (isset($_POST['register'])) {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $secret = trim($_POST['secret_code']);
    $password = $_POST['password'];
    $confirm = $_POST['confirm_password'];

    // --- Server-side Validation ---
    
    if (!empty($name) && !preg_match('/^[\p{L}\p{N}_ ]+$/u', $name)) {
        // $message = "<div class='auth-message error'>❌ Tên không hợp lệ!</div>";
    } 
    elseif (!empty($secret) && strlen($secret) < 4) {
        // $message = "<div class='auth-message error'>❌ Mã bí mật quá ngắn!</div>";
    } 
    elseif (strlen($password) < 6) {
        // $message = "<div class='auth-message error'>❌ Mật khẩu quá ngắn!</div>";
    }
    elseif ($password !== $confirm) {
        // $message = "<div class='auth-message error'>❌ Mật khẩu không khớp!</div>";
    } 
    else {
        $stmt_check = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $stmt_check->bind_param("s", $email);
        $stmt_check->execute();
        $check_res = $stmt_check->get_result();
        
        if ($check_res->num_rows > 0) {
            // $message = "<div class='auth-message error'>❌ Email đã tồn tại!</div>";
        } else {
            $avatarPath = "uploads/default.png";
            $upload_ok = true;

            // Xử lý Upload Avatar
            if (!empty($_FILES['avatar']['name'])) {
                $file = $_FILES['avatar'];
                $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                
                if (!in_array($ext, ["jpg","jpeg","png"])) {
                    // $message = "<div class='auth-message error'>❌ Chỉ JPG, JPEG, PNG!</div>"; 
                    $upload_ok = false;
                } elseif ($file['size'] > 2*1024*1024) {
                    // $message = "<div class='auth-message error'>❌ Ảnh < 2MB!</div>"; 
                    $upload_ok = false;
                } else {
                    $newFile = "avatar_".time().rand(1000,9999).".$ext";
                    $upload = "uploads/$newFile";
                    if (!move_uploaded_file($file['tmp_name'], $upload)) {
                         // $message = "<div class='auth-message error'>❌ Lỗi khi upload ảnh.</div>"; 
                         $upload_ok = false;
                    } else {
                        $avatarPath = $upload;
                    }
                }
            }
            
            if (empty($message) && $upload_ok) {
                $hashed = password_hash($password, PASSWORD_BCRYPT);
                $secretHash = password_hash($secret, PASSWORD_BCRYPT);
                $role = 'user';

                $stmt_insert = $conn->prepare("
                    INSERT INTO users (name, email, password, avatar, secret_code, role)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt_insert->bind_param("ssssss", $name, $email, $hashed, $avatarPath, $secretHash, $role);
                $stmt_insert->execute();

                $message = "<div class='auth-message success'>🎉 Đăng ký thành công! Hãy đăng nhập.</div>";
            }
        }
    }
}

// ----------------------------------------------------
// 3. Xử lý đăng nhập
// ----------------------------------------------------
if (isset($_POST['login'])) {
    $email = $_POST['email'];
    $password = $_POST['password'];
    $remember = isset($_POST['remember']);

    $stmt = $conn->prepare("SELECT id, name, email, role, password FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res && $res->num_rows > 0) {
        $user = $res->fetch_assoc();
        if (password_verify($password, $user['password'])) {
            $_SESSION['user'] = [
                'id' => $user['id'],
                'name' => $user['name'],
                'email' => $user['email'],
                'role' => $user['role']
            ];

            if ($remember) {
                $token = bin2hex(random_bytes(64));
                $expiry_time = time() + (86400 * 30); 
                $expiry_db = date("Y-m-d H:i:s", $expiry_time);
                
                setcookie('remember_token', $token, $expiry_time, "/", "", false, true); 
                
                $user_id = $user['id'];
                $conn->query("DELETE FROM login_tokens WHERE user_id = $user_id");
                
                $stmt_token = $conn->prepare("
                    INSERT INTO login_tokens (user_id, token, expiry) VALUES (?, ?, ?)
                ");
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


include "includes/header.php"; // navbar
?>

<style>
/* ====== GIAO DIỆN SaaS HIỆN ĐẠI (MỚI) ====== */

/* NỀN CHUNG */
.auth-wrapper {
    min-height: calc(100vh - 120px);
    display: flex;
    justify-content: center;
    align-items: center;
    background: #f4f7f6; /* Nền trắng xám */
    padding: 40px 20px;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
}

/* CONTAINER */
.auth-container {
    width: 420px;
    background: #ffffff; /* Nền trắng */
    padding: 35px;
    border-radius: 16px; /* Bo tròn mềm mại */
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.07); /* Đổ bóng nhẹ */
    text-align: center; 
}

/* TIÊU ĐỀ */
.form-box h2 { 
    text-align: center; 
    margin-bottom: 25px; 
    font-size: 28px; 
    font-weight: 700;
    /* Màu tiêu đề chuyên nghiệp */
    color: #004aad; 
}

/* INPUT FIELDS */
.input-group {
    position: relative;
    margin-bottom: 18px; /* Tăng khoảng cách */
}

.form-box input {
    width: 100%;
    padding: 14px 16px; /* Tăng padding */
    margin: 0; 
    border-radius: 10px;
    border: 1px solid #dcdcdc; 
    background: #f0f3f8; /* Nền input xám nhạt */
    color: #333; 
    box-sizing: border-box; 
    transition: all 0.2s ease-in-out; 
}

.form-box input:focus {
    border-color: #6a11cb; /* Màu tím khi focus */
    box-shadow: 0 0 8px rgba(106, 17, 203, 0.2);
    outline: none;
    background: #fff;
}

/* Validation Lỗi */
.form-box input.error-border {
    border-color: #e74c3c; /* Đỏ */
    box-shadow: 0 0 8px rgba(231, 76, 60, 0.2);
}

/* NÚT BUTTON */
.form-box button {
    width: 100%;
    padding: 14px;
    margin-top: 15px;
    border-radius: 10px;
    border: none;
    /* Gradient Xanh - Tím */
    background: linear-gradient(135deg, #2575fc 0%, #6a11cb 100%);
    color: #ffffff;
    font-weight: 600;
    font-size: 16px;
    cursor: pointer;
    transition: all 0.3s;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
}

.form-box button:hover:not(:disabled) { 
    transform: translateY(-3px); 
    box-shadow: 0 8px 20px rgba(106, 17, 203, 0.3);
}

/* TEXT và LINKS */
.form-box p { 
    text-align: center; 
    color: #555; 
    margin-top: 20px; 
    font-size: 14px;
}
.form-box a { 
    color: #2575fc; /* Màu xanh gradient */
    text-decoration: none; 
    font-weight: 600; 
    transition: color 0.2s;
}
.form-box a:hover { 
    color: #6a11cb; /* Màu tím gradient */
    text-decoration: underline; 
}

/* MESSAGES */
.auth-message {
    padding: 12px;
    text-align: center;
    margin-bottom: 20px;
    border-radius: 10px;
    font-weight: 600;
    font-size: 14px;
}

.auth-message.error { 
    background: #fff0f0; 
    color: #d93030; 
    border: 1px solid #f9c0c0; 
}
.auth-message.success { 
    background: #f0fff4; 
    color: #28a745; 
    border: 1px solid #b8f0c8; 
}

/* --- CSS CHO TOOLTIP LỖI (Validate on Submit) --- */
.input-tooltip {
    background: #e74c3c; /* Đỏ */
    color: #ffffff;
    font-weight: 600;
    top: -38px; /* Nằm trên input */
    
    position: absolute;
    left: 50%;
    transform: translateX(-50%);
    padding: 6px 10px;
    border-radius: 5px;
    font-size: 13px;
    white-space: nowrap;
    z-index: 10;
    opacity: 0;
    visibility: hidden;
    transition: opacity 0.3s, visibility 0.3s, top 0.3s;
    pointer-events: none;
}
.input-tooltip.visible {
    opacity: 1;
    visibility: visible;
    top: -48px; /* Hiệu ứng trượt lên */
}
.input-tooltip::after {
    content: '';
    position: absolute;
    top: 100%;
    left: 50%;
    margin-left: -5px;
    border-width: 5px;
    border-style: solid;
    border-color: #e74c3c transparent transparent transparent;
}
.input-tooltip.error {
    background: #e74c3c;
}
.input-tooltip.error::after {
    border-color: #e74c3c transparent transparent transparent;
}

/* --- CSS CHO ICON ẨN/HIỆN MẬT KHẨU --- */
.toggle-password {
    position: absolute;
    top: 50%;
    right: 15px;
    /* Dịch chuyển icon lên trên 1 nửa (do input không còn margin) */
    transform: translateY(-50%); 
    color: #999; /* Màu icon mặc định */
    cursor: pointer;
    z-index: 5;
    font-size: 20px; 
    user-select: none; /* Chống bôi đen */
}

.toggle-password:hover {
    color: #2575fc; /* Màu xanh khi hover */
}
</style>

<div class="auth-wrapper">
    <div class="auth-container">
        <?= $message ?>

        <div class="form-box" id="login-form">
            <h2>🔑 Đăng Nhập</h2>
            <form method="post" novalidate>
                <div class="input-group">
                    <input type="email" name="email" placeholder="Email" required>
                </div>
                
                <div class="input-group">
                    <input type="password" name="password" placeholder="Mật khẩu" required id="login-pass">
                    <span class="toggle-password" onclick="togglePasswordVisibility('login-pass')">👁️</span>
                </div>
                
                <div style="display: flex; justify-content: center; margin: 10px 0;">
                    <label style="display: flex; align-items: center; gap: 8px; color: #555; font-size: 14px;">
                        <input type="checkbox" name="remember" style="width: 16px; height: 16px; margin: 0;"> Ghi nhớ đăng nhập
                    </label>
                </div>
                
                <button type="submit" name="login">Đăng Nhập</button>
            </form>
            <p><a href="forgot_password.php">Quên mật khẩu?</a></p>
            <p>Chưa có tài khoản? <a href="#" onclick="showRegister()">Đăng ký ngay</a></p>
        </div>

        <div class="form-box hidden" id="register-form">
            <h2>📝 Đăng Ký</h2>
            <form method="post" enctype="multipart/form-data" id="register-form-data" onsubmit="return validateFormOnSubmit(event)" novalidate>
                
                <div class="input-group">
                    <input type="email" name="email" placeholder="Email" required>
                    <span class="input-tooltip" id="email-tip" data-default-message="Email phải đúng định dạng (@, .)">Email phải đúng định dạng (@, .)</span>
                </div>

                <div class="input-group">
                    <input type="text" name="name" placeholder="Tên Ingame" required>
                    <span class="input-tooltip" id="name-tip" data-default-message="Chữ, số, khoảng trắng và gạch dưới">Chữ, số, khoảng trắng và gạch dưới</span>
                </div>

                <div class="input-group">
                    <input type="text" name="secret_code" placeholder="Mã bí mật" required>
                    <span class="input-tooltip" id="secret-tip" data-default-message="Ít nhất 4 ký tự">Ít nhất 4 ký tự</span>
                </div>

                <label style="color:#555; display:block; text-align:left; margin-top:10px; font-size: 14px;">Ảnh đại diện (JPG, PNG, < 2MB):</label>
                <input type="file" name="avatar" accept="image/*" style="margin-bottom:15px; background: #fff; border: none; padding-left: 0;">

                <div class="input-group">
                    <input type="password" name="password" placeholder="Mật khẩu" required id="reg-pass">
                    <span class="toggle-password" onclick="togglePasswordVisibility('reg-pass')">👁️</span>
                    <span class="input-tooltip" id="pass-tip" data-default-message="Mật khẩu nên dài và khó đoán (≥ 6 ký tự)">Mật khẩu nên dài và khó đoán (≥ 6 ký tự)</span>
                </div>

                <div class="input-group">
                    <input type="password" name="confirm_password" placeholder="Xác nhận mật khẩu" required id="reg-confirm-pass">
                    <span class="toggle-password" onclick="togglePasswordVisibility('reg-confirm-pass')">👁️</span>
                    <span class="input-tooltip" id="confirm-tip" data-default-message="Phải khớp với mật khẩu đã nhập">Phải khớp với mật khẩu đã nhập</span>
                </div>
                
                <button type="submit" name="register" id="register-btn">Đăng Ký</button>
            </form>
            <p>Đã có tài khoản? <a href="#" onclick="showLogin()">Đăng nhập ngay</a></p>
        </div>
    </div>
</div>

<script>
function showRegister() {
    document.getElementById("login-form").classList.add("hidden");
    document.getElementById("register-form").classList.remove("hidden");
    clearAllErrors(); // Xóa lỗi cũ khi chuyển tab
}
function showLogin() {
    document.getElementById("register-form").classList.add("hidden");
    document.getElementById("login-form").classList.remove("hidden");
    clearAllErrors(); // Xóa lỗi cũ khi chuyển tab
}

// ----------------------------------------------------
// HÀM MỚI: ẨN/HIỆN MẬT KHẨU
// ----------------------------------------------------
function togglePasswordVisibility(inputId) {
    const input = document.getElementById(inputId);
    // Tìm span .toggle-password nằm *cùng cấp* với input
    const icon = input.nextElementSibling; 

    if (!input) return;

    if (input.type === "password") {
        input.type = "text";
        icon.textContent = "🙈"; // Icon khi đang hiện
    } else {
        input.type = "password";
        icon.textContent = "👁️"; // Icon khi đang ẩn
    }
}


// Hàm hiển thị Tooltip (chỉ dùng bởi hàm validate)
function showTooltip(id, isError, message) {
    const tooltip = document.getElementById(id);
    if (!tooltip) return;

    if (isError) {
        tooltip.textContent = message;
        tooltip.classList.add('error');
    }
    tooltip.classList.add('visible');
}

// Hàm Reset Lỗi
function clearAllErrors() {
    // Tìm trong toàn bộ container
    const tooltips = document.querySelectorAll('.auth-container .input-tooltip');
    tooltips.forEach(tip => {
        tip.classList.remove('visible', 'error');
        tip.textContent = tip.dataset.defaultMessage || tip.textContent;
    });
    
    const inputs = document.querySelectorAll('.auth-container input.error-border');
    inputs.forEach(input => input.classList.remove('error-border'));
}

// ----------------------------------------------------
// HÀM VALIDATION CHÍNH (chạy khi submit)
// ----------------------------------------------------
function validateFormOnSubmit(event) {
    clearAllErrors(); // Xóa lỗi cũ
    let isFormValid = true; // Cờ hiệu

    const email = document.querySelector('#register-form input[name="email"]');
    const name = document.querySelector('#register-form input[name="name"]');
    const secret = document.querySelector('#register-form input[name="secret_code"]');
    const password = document.querySelector('#register-form input[name="password"]');
    const confirmPass = document.querySelector('#register-form input[name="confirm_password"]');

    // 2. Kiểm tra Email
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!email.value.trim() || !emailRegex.test(email.value.trim())) {
        isFormValid = false;
        email.classList.add('error-border');
        showTooltip('email-tip', true, 'Email không hợp lệ!');
    }

    // 3. Kiểm tra Tên
    const nameRegex = /^[\p{L}\p{N}_ ]+$/u;
    if (!name.value.trim()) {
        isFormValid = false;
        name.classList.add('error-border');
        showTooltip('name-tip', true, 'Tên không được để trống!');
    } else if (!nameRegex.test(name.value.trim())) {
        isFormValid = false;
        name.classList.add('error-border');
        showTooltip('name-tip', true, 'Tên chỉ chứa chữ, số, khoảng trắng, gạch dưới.');
    }
    
    // 4. Kiểm tra Mã bí mật
    if (secret.value.trim().length < 4) {
        isFormValid = false;
        secret.classList.add('error-border');
        showTooltip('secret-tip', true, 'Mã bí mật phải ≥ 4 ký tự!');
    }

    // 5. Kiểm tra Mật khẩu
    if (password.value.length < 6) {
        isFormValid = false;
        password.classList.add('error-border');
        showTooltip('pass-tip', true, 'Mật khẩu phải ≥ 6 ký tự!');
    }
    
    // 6. Kiểm tra Xác nhận Mật khẩu
    if (!confirmPass.value.trim()) {
         isFormValid = false;
         confirmPass.classList.add('error-border');
         showTooltip('confirm-tip', true, 'Hãy xác nhận mật khẩu!');
    }
    else if (password.value.length >= 6 && confirmPass.value !== password.value) {
        isFormValid = false;
        confirmPass.classList.add('error-border');
        showTooltip('confirm-tip', true, 'Mật khẩu nhập lại không khớp!');
    }

    if (!isFormValid) {
        event.preventDefault(); // Ngăn form submit
        return false;
    }

    return true; // Cho phép form submit
}
</script>

<?php include "includes/footer.php"; ?>
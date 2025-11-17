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
        // Lỗi: Tên không hợp lệ
    } 
    elseif (!empty($secret) && strlen($secret) < 4) {
        // Lỗi: Mã bí mật
    } 
    elseif (strlen($password) < 6) {
        // Lỗi: Mật khẩu
    }
    elseif ($password !== $confirm) {
        // Lỗi: Xác nhận
    } 
    else {
        $stmt_check = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $stmt_check->bind_param("s", $email);
        $stmt_check->execute();
        $check_res = $stmt_check->get_result();
        
        if ($check_res->num_rows > 0) {
            // $message = "<div class='auth-message error'>❌ Email đã tồn tại!</div>"; // Đã xóa
        } else {
            $avatarPath = "uploads/avatar/default.png";
            $upload_ok = true;

            // Xử lý Upload Avatar
            if (!empty($_FILES['avatar']['name'])) {
                $file = $_FILES['avatar'];
                $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                
                if (!in_array($ext, ["jpg","jpeg","png"])) {
                    $upload_ok = false;
                } elseif ($file['size'] > 2*1024*1024) {
                    $upload_ok = false;
                } else {
                    $newFile = "avatar_".time().rand(1000,9999).".$ext";
                    $upload = "uploads/avatar/$newFile";
                    if (!move_uploaded_file($file['tmp_name'], $upload)) {
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


include "includes/header.php"; // navbar
?>

<style>
/* ====== GIAO DIỆN SaaS HIỆN ĐẠI (Không đổi) ====== */
.auth-wrapper {
    min-height: calc(100vh - 120px);
    display: flex;
    justify-content: center;
    align-items: center;
    background: #f4f7f6;
    padding: 40px 20px;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
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
    text-align: center; 
    margin-bottom: 25px; 
    font-size: 28px; 
    font-weight: 700;
    color: #004aad; 
}
.input-group {
    position: relative;
    margin-bottom: 18px;
}
.form-box input {
    width: 100%;
    padding: 14px 16px; 
    margin: 0; 
    border-radius: 10px;
    border: 1px solid #dcdcdc; 
    background: #f0f3f8; 
    color: #333; 
    box-sizing: border-box; 
    transition: all 0.2s ease-in-out; 
}
.form-box input:focus {
    border-color: #6a11cb; 
    box-shadow: 0 0 8px rgba(106, 17, 203, 0.2);
    outline: none;
    background: #fff;
}
.form-box input.error-border {
    border-color: #e74c3c; 
    box-shadow: 0 0 8px rgba(231, 76, 60, 0.2);
}
.form-box button {
    width: 100%;
    padding: 14px;
    margin-top: 15px;
    border-radius: 10px;
    border: none;
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
.form-box p { 
    text-align: center; 
    color: #555; 
    margin-top: 20px; 
    font-size: 14px;
}
.form-box a { 
    color: #2575fc; 
    text-decoration: none; 
    font-weight: 600; 
    transition: color 0.2s;
}
.form-box a:hover { 
    color: #6a11cb; 
    text-decoration: underline; 
}
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
.input-tooltip {
    background: #e74c3c; 
    color: #ffffff;
    font-weight: 600;
    top: -38px;
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
    top: -48px;
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
.toggle-password {
    position: absolute;
    top: 50%;
    right: 15px;
    transform: translateY(-50%); 
    color: #999;
    cursor: pointer;
    z-index: 5;
    font-size: 20px; 
    user-select: none;
}
.toggle-password:hover {
    color: #2575fc;
}
</style>

<div class="auth-wrapper">
    <div class="auth-container">
        <?= $message ?>

        <div class="form-box" id="login-form">
            <h2>🔑 Đăng Nhập</h2>
            <form method="post" novalidate onsubmit="return validateLoginForm(event)">
                <div class="input-group">
                    <input type="email" name="email" placeholder="Email" required id="login-email">
                    <span class="input-tooltip" id="login-email-tip" data-default-message="Email không được để trống">Email không được để trống</span>
                </div>
                
                <div class="input-group">
                    <input type="password" name="password" placeholder="Mật khẩu" required id="login-pass">
                    <span class="toggle-password" onclick="togglePasswordVisibility('login-pass')">👁️</span>
                    <span class="input-tooltip" id="login-pass-tip" data-default-message="Mật khẩu không được để trống">Mật khẩu không được để trống</span>
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
            <form method="post" enctype="multipart/form-data" id="register-form-data" onsubmit="validateFormOnSubmit(event)" novalidate>
                
                <div class="input-group">
                    <input type="email" name="email" placeholder="Email" required oninput="handleEmailInput(this)">
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
// Biến toàn cục cho bộ đếm thời gian (debounce)
let emailCheckTimer;

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
// HÀM ẨN/HIỆN MẬT KHẨU
// ----------------------------------------------------
function togglePasswordVisibility(inputId) {
    const input = document.getElementById(inputId);
    const icon = input.nextElementSibling; 
    if (!input) return;
    if (input.type === "password") {
        input.type = "text";
        icon.textContent = "🙈"; 
    } else {
        input.type = "password";
        icon.textContent = "👁️"; 
    }
}

// Hàm hiển thị Tooltip
function showTooltip(id, isError, message) {
    const tooltip = document.getElementById(id);
    if (!tooltip) return;
    if (isError) {
        tooltip.textContent = message;
        tooltip.classList.add('error');
    }
    tooltip.classList.add('visible');
}
// Hàm Ẩn Tooltip
function hideTooltip(id) {
    const tooltip = document.getElementById(id);
    if (!tooltip) return;
    tooltip.classList.remove('visible', 'error');
    tooltip.textContent = tooltip.dataset.defaultMessage || tooltip.textContent;
}

// ----------------------------------------------------
// HÀM RESET LỖI (ĐÃ SỬA LỖI $message)
// ----------------------------------------------------
function clearAllErrors() {
    // SỬA LỖI: Ẩn thông báo lỗi Server-side (như "Sai mật khẩu")
    const serverMessage = document.querySelector('.auth-container .auth-message');
    if (serverMessage) {
        serverMessage.style.display = 'none';
    }

    // Xóa lỗi client-side (tooltips)
    const tooltips = document.querySelectorAll('.auth-container .input-tooltip');
    tooltips.forEach(tip => {
        tip.classList.remove('visible', 'error');
        tip.textContent = tip.dataset.defaultMessage || tip.textContent;
    });
    
    // Xóa lỗi client-side (viền đỏ)
    const inputs = document.querySelectorAll('.auth-container input.error-border');
    inputs.forEach(input => input.classList.remove('error-border'));
}

// ----------------------------------------------------
// HÀM VALIDATION CHO FORM ĐĂNG NHẬP
// ----------------------------------------------------
function validateLoginForm(event) {
    clearAllErrors(); 
    let isFormValid = true;
    const email = document.getElementById('login-email');
    const password = document.getElementById('login-pass');
    if (email.value.trim() === '') {
        isFormValid = false;
        email.classList.add('error-border');
        showTooltip('login-email-tip', true, 'Email không được để trống!');
    }
    if (password.value.trim() === '') {
        isFormValid = false;
        password.classList.add('error-border');
        showTooltip('login-pass-tip', true, 'Mật khẩu không được để trống!');
    }
    if (!isFormValid) {
        event.preventDefault(); 
        return false;
    }
    return true; 
}

// ----------------------------------------------------
// HÀM MỚI: BỘ ĐỆM "DEBOUNCE" CHO VIỆC GÕ EMAIL
// ----------------------------------------------------
function handleEmailInput(inputElement) {
    clearTimeout(emailCheckTimer);
    const email = inputElement.value.trim();
    const tipId = 'email-tip';

    if (email === "") {
        inputElement.classList.remove('error-border');
        hideTooltip(tipId);
        return;
    }

    emailCheckTimer = setTimeout(() => {
        validateEmailRealtime(inputElement);
    }, 500); // Đợi 500ms sau khi ngừng gõ
}


// ----------------------------------------------------
// HÀM KIỂM TRA EMAIL (AJAX - Không đổi)
// ----------------------------------------------------
async function validateEmailRealtime(inputElement) {
    const email = inputElement.value.trim();
    const tipId = 'email-tip';
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

    if (!email || !emailRegex.test(email)) {
        inputElement.classList.add('error-border');
        showTooltip(tipId, true, 'Email không hợp lệ!');
        return false; 
    }

    try {
        const response = await fetch('check_email.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ email: email })
        });
        if (!response.ok) throw new Error('Lỗi network khi kiểm tra email');
        
        const data = await response.json();

        if (data.exists) {
            inputElement.classList.add('error-border');
            showTooltip(tipId, true, 'Email này đã tồn tại!');
            return false;
        } else {
            inputElement.classList.remove('error-border');
            hideTooltip(tipId);
            return true;
        }
    } catch (error) {
        console.error('Lỗi khi kiểm tra email:', error);
        inputElement.classList.add('error-border');
        showTooltip(tipId, true, 'Lỗi! Không thể kiểm tra email.');
        return false;
    }
}


// ----------------------------------------------------
// HÀM VALIDATION ĐĂNG KÝ (ON SUBMIT)
// ----------------------------------------------------
async function validateFormOnSubmit(event) {
    event.preventDefault(); 
    clearAllErrors(); 
    let isFormValid = true; 

    const emailInput = document.querySelector('#register-form input[name="email"]');
    const name = document.querySelector('#register-form input[name="name"]');
    const secret = document.querySelector('#register-form input[name="secret_code"]');
    const password = document.querySelector('#register-form input[name="password"]');
    const confirmPass = document.querySelector('#register-form input[name="confirm_password"]');

    // Chạy kiểm tra sync
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
    if (secret.value.trim().length < 4) {
        isFormValid = false;
        secret.classList.add('error-border');
        showTooltip('secret-tip', true, 'Mã bí mật phải ≥ 4 ký tự!');
    }
    if (password.value.length < 6) {
        isFormValid = false;
        password.classList.add('error-border');
        showTooltip('pass-tip', true, 'Mật khẩu phải ≥ 6 ký tự!');
    }
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

    // Chạy kiểm tra async (email)
    const isEmailValid = await validateEmailRealtime(emailInput);

    // Quyết định cuối cùng
    if (isFormValid && isEmailValid) {
        document.getElementById('register-form-data').submit();
    }
}
</script>

<?php include "includes/footer.php"; ?>
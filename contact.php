<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once 'db/connect.php';
include 'includes/header.php';

$message_sent = false; // Cờ hiệu

// Xử lý khi người dùng gửi form
if (isset($_POST['send'])) {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $message = trim($_POST['message'] ?? '');

    // Chỉ lưu nếu có đủ thông tin
    if (!empty($name) && !empty($email) && !empty($message) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
        
        // Lưu vào CSDL
        $stmt = $conn->prepare("INSERT INTO contacts (name, email, message) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $name, $email, $message);
        $stmt->execute();
        
        $message_sent = true;
    }
}
?>

<div class="contact-container">
    <h2>📬 Liên Hệ Với Chúng Tôi</h2>

    <?php if ($message_sent): ?>
        <div class='contact-success'>✅ Cảm ơn bạn đã liên hệ! Chúng tôi sẽ phản hồi sớm.</div>
        <a href="index.php" class="btn-send" style="text-decoration:none; text-align:center; display:block; max-width: 200px; margin: 20px auto 0 auto;">Về Trang Chủ</a>
    <?php else: ?>
        <p>Hãy để lại lời nhắn, chúng tôi sẽ phản hồi sớm nhất có thể!</p>
        
        <form method="post" class="contact-form">
            <div class="form-group">
                <label for="name">👤 Họ và tên:</label>
                <input type="text" id="name" name="name" placeholder="Nhập họ và tên..." required>
            </div>

            <div class="form-group">
                <label for="email">📧 Email:</label>
                <input type="email" id="email" name="email" placeholder="Nhập địa chỉ email..." required>
            </div>

            <div class="form-group">
                <label for="message">💬 Nội dung:</label>
                <textarea id="message" name="message" rows="5" placeholder="Nhập nội dung liên hệ..." required></textarea>
            </div>

            <button type="submit" name="send" class="btn-send">Gửi Thông Tin</button>
        </form>
    <?php endif; ?>
    
</div>

<?php include "includes/footer.php"; ?>
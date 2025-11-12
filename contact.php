<?php include "includes/header.php"; ?>

<div class="contact-container">
    <h2>📬 Liên Hệ Với Chúng Tôi</h2>
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

    <?php
    if (isset($_POST['send'])) {
        echo "<div class='contact-success'>✅ Cảm ơn bạn đã liên hệ! Chúng tôi sẽ phản hồi sớm.</div>";
    }
    ?>
</div>

<?php include "includes/footer.php"; ?>
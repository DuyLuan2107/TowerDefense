<?php
// check_email.php
require "db/connect.php";

// Lấy email từ request (gửi dưới dạng JSON)
$data = json_decode(file_get_contents('php://input'), true);
$email = $data['email'] ?? '';

$exists = false;

// Chỉ kiểm tra nếu email hợp lệ
if (!empty($email) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $stmt_check = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $stmt_check->bind_param("s", $email);
    $stmt_check->execute();
    $check_res = $stmt_check->get_result();
    
    if ($check_res->num_rows > 0) {
        $exists = true; // Email đã tồn tại
    }
}

// --- PHẦN QUAN TRỌNG NHẤT LÀ ĐÂY ---

// 1. Báo cho trình duyệt biết đây là JSON
header('Content-Type: application/json');

// 2. Mã hóa kết quả thành JSON
echo json_encode(['exists' => $exists]);
?>
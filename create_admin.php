<?php
// create_admin.php — tự chạy khi chưa có admin
require_once __DIR__ . '/db/connect.php';

$name = 'admin';
$email = 'admin@gmail.com';
$password_plain = '123';
$role = 'admin';
$secret_code = bin2hex(random_bytes(16));

$hash = password_hash($password_plain, PASSWORD_BCRYPT);

// kiểm tra nếu email đã tồn tại thì dừng
$check = $conn->prepare("SELECT id FROM users WHERE email = ?");
$check->bind_param("s", $email);
$check->execute();
$exists = $check->get_result()->num_rows > 0;
$check->close();

if ($exists) {
    exit; // im lặng
}

$stmt = $conn->prepare("INSERT INTO users (name, email, password, secret_code, role) VALUES (?, ?, ?, ?, ?)");
$stmt->bind_param("sssss", $name, $email, $hash, $secret_code, $role);
$stmt->execute();
$stmt->close();
$conn->close();
?>

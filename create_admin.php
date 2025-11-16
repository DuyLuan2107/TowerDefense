<?php
// create_admin.php — chạy 1 lần trên dev, xóa file sau khi dùng
require_once __DIR__ . '/db/connect.php'; // dùng __DIR__ để chắc chắn đường dẫn đúng

$name = 'admin';
$email = 'admin@gmail.com';
$password_plain = '123'; // đổi ngay sau khi tạo
$role = 'admin';
$secret_code = bin2hex(random_bytes(16)); // secret random

$hash = password_hash($password_plain, PASSWORD_DEFAULT);

// Nếu connect.php tạo $conn thì dùng $conn->prepare
$stmt = $conn->prepare("INSERT INTO users (name, email, password, secret_code, role) VALUES (?, ?, ?, ?, ?)");
$stmt->bind_param("sssss", $name, $email, $hash, $secret_code, $role);

if ($stmt->execute()) {
    echo "Admin created. Email=$email, password=$password_plain\n";
} else {
    echo "Error: " . $stmt->error . "\n";
}

$stmt->close();
$conn->close();

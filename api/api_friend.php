<?php
// FILE: api_friend.php
// Thêm dòng này để ngăn chặn lỗi hiển thị ra màn hình làm hỏng JSON
ob_start(); 
session_start();
require "../db/connect.php"; 

// Xóa bộ đệm đầu ra để đảm bảo chỉ có JSON được gửi về
ob_clean(); 
header('Content-Type: application/json');

$response = [];

try {
    if (!isset($_SESSION['user'])) {
        throw new Exception('Bạn chưa đăng nhập');
    }

    $my_id = $_SESSION['user']['id'];
    $action = $_POST['action'] ?? '';

    // --- 1. TÌM KIẾM ---
    if ($action === 'search') {
        $keyword = trim($_POST['keyword'] ?? '');
        
        if (strlen($keyword) < 1) {
            throw new Exception('Vui lòng nhập từ khóa');
        }

        $search_term = "%" . $keyword . "%";
        
        // Tìm user KHÔNG PHẢI là mình
        $stmt = $conn->prepare("
            SELECT id, name, email, avatar 
            FROM users 
            WHERE (name LIKE ? OR email LIKE ?) AND id != ? 
            LIMIT 10
        ");
        
        if (!$stmt) {
            throw new Exception("Lỗi SQL: " . $conn->error);
        }

        $stmt->bind_param("ssi", $search_term, $search_term, $my_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $users = $result->fetch_all(MYSQLI_ASSOC);

        // Kiểm tra trạng thái bạn bè
        foreach ($users as &$u) {
            $fid = $u['id'];
            // Check quan hệ trong bảng friends
            $check = $conn->query("SELECT * FROM friends WHERE (sender_id = $my_id AND receiver_id = $fid) OR (sender_id = $fid AND receiver_id = $my_id)");
            
            if ($check->num_rows > 0) {
                $rel = $check->fetch_assoc();
                if ($rel['status'] == 'accepted') {
                    $u['rel_status'] = 'friend'; 
                } elseif ($rel['sender_id'] == $my_id) {
                    $u['rel_status'] = 'sent';   
                } else {
                    $u['rel_status'] = 'received'; 
                }
            } else {
                $u['rel_status'] = 'none'; 
            }
        }
        
        $response = ['status' => 'success', 'data' => $users];
    }

    // --- 2. GỬI LỜI MỜI ---
    elseif ($action === 'add') {
        $target_id = intval($_POST['target_id']);
        $check = $conn->query("SELECT id FROM friends WHERE (sender_id = $my_id AND receiver_id = $target_id) OR (sender_id = $target_id AND receiver_id = $my_id)");
        
        if ($check->num_rows == 0) {
            $stmt = $conn->prepare("INSERT INTO friends (sender_id, receiver_id, status) VALUES (?, ?, 'pending')");
            $stmt->bind_param("ii", $my_id, $target_id);
            if ($stmt->execute()) {
                $response = ['status' => 'success'];
            } else {
                throw new Exception("Lỗi DB: " . $conn->error);
            }
        } else {
            $response = ['status' => 'error', 'message' => 'Đã tồn tại lời mời'];
        }
    }

    // --- 3. CHẤP NHẬN ---
    elseif ($action === 'accept') {
        $target_id = intval($_POST['target_id']);
        $stmt = $conn->prepare("UPDATE friends SET status = 'accepted' WHERE sender_id = ? AND receiver_id = ?");
        $stmt->bind_param("ii", $target_id, $my_id);
        $stmt->execute();
        $response = ['status' => 'success'];
    }

    // --- 4. XÓA ---
    elseif ($action === 'remove') {
        $target_id = intval($_POST['target_id']);
        $stmt = $conn->prepare("DELETE FROM friends WHERE (sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?)");
        $stmt->bind_param("iiii", $my_id, $target_id, $target_id, $my_id);
        $stmt->execute();
        $response = ['status' => 'success'];
    } 
    else {
        $response = ['status' => 'error', 'message' => 'Hành động không hợp lệ'];
    }

} catch (Exception $e) {
    $response = ['status' => 'error', 'message' => $e->getMessage()];
}

echo json_encode($response);
exit;
?>
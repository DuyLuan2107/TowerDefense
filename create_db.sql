CREATE DATABASE tower_defense;
USE tower_defense;

CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100),
  email VARCHAR(100) UNIQUE,
  password VARCHAR(255),
  avatar VARCHAR(255) DEFAULT 'uploads/avatar/default.png',
  secret_code VARCHAR(255) NOT NULL,
  bio VARCHAR(255) NULL DEFAULT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  last_activity DATETIME NULL,
  role VARCHAR(20) NOT NULL DEFAULT 'user',
  last_login DATETIME NULL,
  is_locked TINYINT(1) NOT NULL DEFAULT 0
);

CREATE TABLE IF NOT EXISTS scores (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  score INT NOT NULL,
  enemies_killed INT DEFAULT 0,
  gold_left INT DEFAULT 0,
  duration_seconds INT DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS posts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  title VARCHAR(200) NOT NULL,
  content TEXT,
  screenshot_url VARCHAR(255),
  featured TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS comments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  post_id INT NOT NULL,
  user_id INT NOT NULL,
  content TEXT NOT NULL,
  parent_comment_id INT NULL DEFAULT NULL,
  is_reply TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (parent_comment_id) REFERENCES comments(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS comment_images (
  id INT AUTO_INCREMENT PRIMARY KEY,
  comment_id INT NOT NULL,
  image_path VARCHAR(255) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (comment_id) REFERENCES comments(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS comment_likes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  comment_id INT NOT NULL,
  user_id INT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY unique_comment_like (comment_id, user_id),
  FOREIGN KEY (comment_id) REFERENCES comments(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS post_likes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  post_id INT NOT NULL,
  user_id INT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY unique_like (post_id, user_id),
  FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS post_files (
  id INT AUTO_INCREMENT PRIMARY KEY,
  post_id INT NOT NULL,
  file_path VARCHAR(255),
  file_type VARCHAR(20),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS admin_logs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  admin_id INT NULL,
  action VARCHAR(100) NOT NULL,
  target_table VARCHAR(50) NULL,
  target_id INT NULL,
  ip VARCHAR(45) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE contacts (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `message` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
);

CREATE TABLE login_tokens (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `token` char(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `expiry` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`)
);

CREATE TABLE `friends` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sender_id` int(11) NOT NULL,    -- Người gửi lời mời
  `receiver_id` int(11) NOT NULL,  -- Người nhận lời mời
  `status` enum('pending','accepted') NOT NULL DEFAULT 'pending', -- pending: chờ, accepted: bạn bè
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_relationship` (`sender_id`, `receiver_id`) -- Tránh gửi trùng lặp
);

INSERT INTO users (name, email, password, secret_code, avatar, bio, created_at, last_activity, role) VALUES
('Duy Luân', 'luan@gmail.com', '$2y$10$abcdefghijk1234567890luan', 'sc_1a2b3c4d5e', 'uploads/avatar/1.jpg', 'Admin đẹp trai nhất hệ mặt trời. Cấm hack cheat dưới mọi hình thức!', '2025-01-01 10:00:00','2025-02-01 10:00:00' , 'admin'),
('Trọng Hoài', 'hoai@gmail.com', '$2y$10$abcdefghijk1234567890hoai', 'sc_2b3c4d5e6f', 'uploads/avatar/2.jpg', 'Chơi game vui là chính, thắng là mười. Cao thủ ẩn danh.', '2025-02-14 09:30:00', '2025-05-22 9:00:00', 'user'),
('Minh Trí', 'tri@gmail.com', '$2y$10$abcdefghijk1234567890tri', 'sc_3c4d5e6f7g', 'uploads/avatar/3.jpg', 'Thích spam tháp pháo. Ai solo wave 50 không?', '2025-03-10 15:45:00', '2025-07-01 22:00:00','user'),
('Anh Khoa', 'khoa@gmail.com', '$2y$10$abcdefghijk1234567890khoa', 'sc_4d5e6f7g8h', 'uploads/avatar/4.jpg', 'Đang tìm đồng đội gánh team. Hứa không AFK.', '2025-03-20 20:15:00','2025-07-22 10:00:00' , 'user'),
('Vĩnh Thuận', 'thuan@gmail.com', '$2y$10$abcdefghijk1234567890thuan', 'sc_5e6f7g8h9i', 'uploads/avatar/5.jpg', 'Newbie tập chơi, xin các cao nhân chỉ giáo nhẹ tay.', '2025-04-05 11:20:00','2025-07-12 12:30:00', 'user'),
('Hải Đăng', 'dang@gmail.com', '$2y$10$abcdefghijk1234567890dang', 'sc_6f7g8h9i0j', 'uploads/avatar/6.jpg', 'Sống nội tâm, hay khóc thầm khi để lọt quái.', '2025-05-01 08:00:00', '2025-07-29 16:30:00', 'user'),
('Bảo Nam', 'nam@gmail.com', '$2y$10$abcdefghijk1234567890nam', 'sc_7g8h9i0j1k', 'uploads/avatar/7.jpg', 'Chuyên gia chiến thuật, bậc thầy phòng thủ.', '2025-06-12 14:50:00','2025-08-11 12:20:00', 'user'),
('Hữu Lộc', 'loc@gmail.com', '$2y$10$abcdefghijk1234567890loc', 'sc_8h9i0j1k2l', 'uploads/avatar/8.jpg', 'Game là dễ (dễ thua).', '2025-07-07 19:30:00','2025-09-12 12:30:00', 'user'),
('Thanh Phong', 'phong@gmail.com', '$2y$10$abcdefghijk1234567890phong', 'sc_9i0j1k2l3m', 'uploads/avatar/9.jpg', 'Yêu màu tím, thích sự thủy chung và ghét sự giả dối.', '2025-08-20 10:10:00','2025-11-20 12:30:00', 'user'),
('Hồng Nhung', 'nhung@gmail.com', '$2y$10$abcdefghijk1234567890nhung', 'sc_0j1k2l3m4n', 'uploads/avatar/10.jpg', 'Cô gái vàng trong làng xây tháp.', '2025-09-02 16:40:00','2025-10-12 5:30:00', 'user'),
('Quang Minh', 'minh@gmail.com', '$2y$10$abcdefghijk1234567890minh', 'sc_a1b2c3d4e5', 'uploads/avatar/11.jpg', 'Leo rank là đam mê, rớt hạng là thói quen.', '2025-09-15 22:00:00','2025-09-20 12:30:00', 'user'),
('Ngọc Diệp', 'diep@gmail.com', '$2y$10$abcdefghijk1234567890diep', 'sc_b2c3d4e5f6', 'uploads/avatar/12.jpg', 'Chỉ chơi game vào cuối tuần. Đừng rủ ngày thường.', '2025-10-01 13:25:00','2025-11-01 22:30:00', 'user'),
('Phương Thảo', 'thao@gmail.com', '$2y$10$abcdefghijk1234567890thao', 'sc_c3d4e5f6g7', 'uploads/avatar/13.jpg', 'Mục tiêu: Top 1 Server Global.', '2025-10-20 18:18:00','2025-10-05 20:30:00', 'user'),
('Đức Tài', 'tai@gmail.com', '$2y$10$abcdefghijk1234567890tai', 'sc_d4e5f6g7h8', 'uploads/avatar/14.jpg', 'Kỹ năng có hạn, thủ đoạn vô biên.', '2025-11-11 09:09:00','2025-11-20 13:40:00', 'user'),
('Tấn Dũng', 'dung@gmail.com', '$2y$10$abcdefghijk1234567890dung', 'sc_e5f6g7h8i9', 'uploads/avatar/15.jpg', 'Đang bận giải cứu thế giới, vui lòng để lại lời nhắn.', '2025-11-25 15:30:00','2025-11-28 12:30:00', 'user'),
('Bảo Vy', 'vy@gmail.com', '$2y$10$abcdefghijk1234567890vy', 'sc_f6g7h8i9j0', 'uploads/avatar/15.jpg', 'Fan cứng Tower Defense 10 năm.', '2025-11-01 21:00:00','2025-11-22 12:30:00', 'user'),
('Quốc Huy', 'huy@gmail.com', '$2y$10$abcdefghijk1234567890huy', 'sc_g7h8i9j0k1', 'uploads/avatar/12.jpg', 'Không nạp tiền vẫn mạnh (chắc thế).', '2025-11-12 12:12:00','2025-11-15 4:00:00', 'user'),
('Thảo Linh', 'linh@gmail.com', '$2y$10$abcdefghijk1234567890linh', 'sc_h8i9j0k1l2', 'uploads/avatar/14.jpg', 'Thích đi mid nhưng game này không có mid.', '2025-01-01 00:01:00','2025-07-12 18:50:00', 'user'),
('Đăng Khoa', 'dkhoa@gmail.com', '$2y$10$abcdefghijk1234567890dkhoa', 'sc_i9j0k1l2m3', 'uploads/avatar/15.jpg', 'Mất ngủ vì wave cuối.', '2025-01-10 07:45:00','2025-06-25 12:30:00', 'user'),
('Tuấn Kiệt', 'kiet@gmail.com', '$2y$10$abcdefghijk1234567890kiet', 'sc_j0k1l2m3n4', 'uploads/avatar/2.jpg', 'Chơi game để quên đi deadline.', '2025-01-15 17:20:00','2025-09-23 23:10:00', 'user');

INSERT INTO scores (user_id, score, enemies_killed, gold_left, duration_seconds) VALUES
(1, 116, 29, 50, 26),
(1, 273, 25, 40, 30),
(2, 213, 20, 33, 40),
(2, 240, 22, 30, 36),
(3, 300, 31, 66, 20),
(4, 150, 13, 30, 43),
(5, 260, 23, 46, 33),
(6, 336, 30, 60, 23),
(7, 290, 26, 36, 28),
(8, 186, 18, 31, 40),
(9, 306, 27, 43, 31),
(10, 226, 29, 50, 29),
(11, 270, 32, 70, 25),
(12, 210, 19, 35, 41),
(13, 233, 22, 38, 36),
(14, 283, 25, 46, 30),
(15, 246, 30, 63, 26),
(16, 293, 26, 53, 31),
(17, 223, 29, 56, 28),
(18, 263, 23, 45, 33),
(19, 260, 31, 66, 26),
(20, 303, 27, 50, 30),
(10, 240, 31, 66, 27),
(5, 266, 24, 48, 31),
(3, 293, 30, 60, 21),
(15, 256, 32, 65, 23),
(6, 230, 28, 53, 28),
(12, 220, 20, 38, 38),
(14, 290, 26, 43, 30),
(19, 271, 32, 73, 22);

INSERT INTO posts (user_id, title, content) VALUES
(1, 'Mẹo đạt 300+ điểm trong Tower Defense', 'Hãy đặt tháp ở các đoạn cua để tối ưu sát thương.'),
(2, 'Vừa được 250 điểm!', 'Lần này quái ra nhanh quá nhưng vẫn thủ được.'),
(3, 'Chiến thuật mới thử nghiệm', 'Ưu tiên nâng cấp tháp mạnh thay vì spam tháp thường.'),
(4, 'Map này khó thật sự!', 'Wave 3 đúng kiểu đi vào lòng đất.'),
(5, 'Hướng dẫn dành cho người mới', 'Cố gắng giữ vàng để nâng cấp thay vì mua tháp liên tục.'),

(1, 'Đạt 400 điểm rồi anh em ơi!', 'Chụp vội tấm hình khoe thành tích.'),
(2, 'Tháp thường có mạnh không?', 'Mình nghĩ tỉ lệ sát thương theo giá khá ổn.'),
(3, 'Bug? Quái đứng yên ở góc?', 'Ai gặp lỗi này chưa?'),
(4, 'Tìm người giao lưu chiến thuật', 'Anh em vào thảo luận chiến thuật tại đây!'),
(5, 'Điểm cao quá trời', 'Mãi đỉnh :)))'),

(1, 'Xây tháp ở vị trí nào thì mạnh?', 'Mình thấy đầu đường không hiệu quả bằng giữa map.'),
(2, 'Góp ý UI game', 'Nên thêm nút tăng tốc 2x.'),
(3, 'Làm sao qua wave 3?', 'Mãi vẫn thua :('),
(4, 'Chia sẻ layout tháp siêu mạnh', 'Tối ưu hóa đường đi cực tốt.'),
(5, 'Tối ưu FPS khi chơi trên laptop yếu', 'Tắt hiệu ứng sẽ mượt hơn.'),

(1, 'Đạt kỷ lục 523 điểm!', 'Không tin luôn :)))'),
(2, 'Nâng cấp tháp hay mua tháp mới?', 'Mọi người nghĩ sao?'),
(3, 'Tháp 3 bá thật sự', 'Sát thương kinh khủng.'),
(4, 'Mẹo qua wave khó', 'Chia sẻ cho anh em.'),
(5, 'Game vui thật sự', 'Lâu rồi mới nghiện 1 game.'),

(1, 'Tháp mạnh nhất là gì?', 'Theo mình thì tháp 3.'),
(2, 'Lỗi âm thanh?', 'Không nghe tiếng bắn.'),
(3, 'Cách farm vàng hiệu quả', 'Đặt tháp dồn quái.'),
(4, 'Vừa thua wave 2', 'Buồn thật sự.'),
(5, 'Góc khoe điểm', 'Anh em xem nè: 350 điểm.'),

(1, 'Hardcore mode?', 'Admin thêm mode khó được không?'),
(2, 'Anh em có mẹo gì chia sẻ không?', 'Mình mãi không lên nổi rank.'),
(3, 'Nghi vấn bug sát thương', 'Quái mất máu hơi kỳ.'),
(4, 'Map mới khi nào có?', 'Hóng update.'),
(5, 'Cười xỉu với wave 3', 'Chạy như điên luôn.'),

(1, 'Chiến thuật nâng cấp hợp lý', 'Không nên nâng cấp liên tục.'),
(2, '330 điểm rồi!', 'Lần sau cố 400.'),
(3, 'Game hay quá trời', 'Nghiện rồi.'),
(4, 'Top 10 server!!', 'Không tin nổi luôn.'),
(5, 'Giảm lag thế nào?', 'Tắt animation nhé.');

INSERT INTO comments (post_id, user_id, content) VALUES
(1,1,'Hay quá bạn!'),
(1,2,'Mình sẽ thử ngay.'),
(1,3,'Chiến thuật hợp lý đấy.'),
(2,4,'Điểm cao ghê!'),
(2,5,'Chúc mừng bạn.'),
(3,2,'Cũng đang bị lỗi này.'),
(3,1,'Bạn thử reset wave xem.'),
(4,3,'Chuẩn khó luôn.'),
(4,2,'Map này hành mình hoài.'),
(5,1,'Bài viết hữu ích cho newbie.'),

(6,3,'400 điểm đỉnh thật!'),
(6,4,'Quá khủng.'),
(6,5,'Làm sao được vậy bạn?'),
(7,1,'Tháp thường rẻ mà mạnh.'),
(7,4,'Mình cũng thấy vậy.'),
(8,2,'Mình chưa gặp lỗi này.'),
(9,3,'Vào discord giao lưu nhé.'),
(10,5,'Điểm đẹp quá.'),

(11,4,'Đặt giữa map hiệu quả thiệt.'),
(11,3,'Mình test thử rồi.'),
(12,2,'Đồng ý, nên thêm speed x2.'),
(13,1,'Wave 3 khó thật.'),
(13,5,'Bạn cần thêm tháp mạnh.'),
(14,4,'Cho xin layout bạn ơi.'),
(15,1,'Cảm ơn tip.'),
(15,2,'Laptop yếu cần nhẹ hơn.'),

(16,3,'523 điểm quá khủng.'),
(16,4,'Pro thật sự.'),
(17,5,'Nên nâng cấp trước.'),
(18,1,'Tháp 3 mạnh thiệt.'),
(19,3,'Hay quá admin.'),
(20,2,'Game vui thật.');

INSERT INTO post_files (post_id, file_path, file_type) VALUES
(1, 'uploads/posts/1.mp4', 'video'),
(2, 'uploads/posts/2.png', 'image'),
(3, 'uploads/posts/3.png', 'image'),
(5, 'uploads/posts/5.png', 'image'),
(6, 'uploads/posts/6.png', 'image'),
(8, 'uploads/posts/8.png', 'image'),
(10, 'uploads/posts/11.png', 'image'),
(13, 'uploads/posts/13.png', 'image'),
(17, 'uploads/posts/16.png', 'image'),
(20, 'uploads/posts/14.png', 'image');

INSERT INTO comment_images (comment_id, image_path) VALUES
(1, 'uploads/comments/1.png'),
(2, 'uploads/comments/4.png'),
(3, 'uploads/comments/3.png'),
(5, 'uploads/comments/5.png'),
(7, 'uploads/comments/6.png'),
(9, 'uploads/comments/7.png'),
(11, 'uploads/comments/3.png'),
(13, 'uploads/comments/13.png'),
(15, 'uploads/comments/14.png'),
(19, 'uploads/comments/15.png');

INSERT INTO contacts (`name`, `email`, `message`, `created_at`, `is_read`) VALUES
('Trọng Hoài', 'hoaitruong@gmail.com', 'Tôi không thể đăng nhập vào game sáng nay, xin hãy kiểm tra giúp.', '2025-11-01 08:30:00', 1),
('Duy Luân', 'duyluan@gmail.com', 'Game rất hay, đồ họa đẹp mắt. Cảm ơn đội ngũ phát triển!', '2025-11-02 09:45:00', 0),
('Gia Khánh', 'giakhanh@gmail.com', 'Tôi phát hiện một lỗi là quái vật bị kẹt vào tường.', '2025-11-03 10:15:00', 0),
('Châu Đôn', 'chaudon@gmail.com', 'Làm sao để nạp thẻ vào game vậy admin? Tôi không tìm thấy nút nạp.', '2023-11-04 11:00:00', 1),
('Vĩnh Thuận', 'vinhthuan@gmail.com', 'Tôi bị quên mã bí mật, làm sao để lấy lại?', '2025-11-05 14:20:00', 0),
('Quang Vinh', 'quangvinh@gmail.com', 'Đề nghị thêm tính năng chat voice trong trận đấu.', '2025-11-06 15:30:00', 1),
('Đăng Khoa', 'dangkhoa@gmail.com', 'Tài khoản của tôi bị khóa không rõ lý do, tên nhân vật là ĐăngKhoaaa.', '2023-11-07 16:45:00', 0),
('Thanh Tú', 'thanhtu@gmail.com', 'Sự kiện trung thu vừa rồi phần thưởng chưa được gửi về hòm thư.', '2025-11-08 08:00:00', 1),
('Khải Đăng', 'khaidang@gmail.com', 'Nhạc nền game hơi to so với tiếng hiệu ứng, admin chỉnh lại nhé.', '2025-11-09 09:10:00', 0);
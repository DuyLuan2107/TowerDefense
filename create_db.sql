CREATE DATABASE tower_defense;
USE tower_defense;

CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100),
  email VARCHAR(100) UNIQUE,
  password VARCHAR(255),
  avatar VARCHAR(255) DEFAULT 'uploads/default.png',
  secret_code VARCHAR(255) NOT NULL,
  last_activity DATETIME NULL
);

CREATE TABLE scores (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  score INT NOT NULL,
  enemies_killed INT DEFAULT 0,
  gold_left INT DEFAULT 0,
  duration_seconds INT DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- (Tuỳ chọn) Bảng posts cho Forum (nếu bạn chưa có)
CREATE TABLE IF NOT EXISTS posts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  title VARCHAR(200) NOT NULL,
  content TEXT,
  screenshot_url VARCHAR(255),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
CREATE TABLE IF NOT EXISTS comments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  post_id INT NOT NULL,
  user_id INT NOT NULL,
  content TEXT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
CREATE TABLE IF NOT EXISTS comment_images (
  id INT AUTO_INCREMENT PRIMARY KEY,
  comment_id INT NOT NULL,
  image_path VARCHAR(255) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (comment_id) REFERENCES comments(id) ON DELETE CASCADE
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


-- USERS (20 người chơi mẫu)
INSERT INTO users (name, email, password) VALUES
('Duy Luân', 'luan@example.com', '$2y$10$abcdefghijk1234567890luan'),
('Trọng Hoài', 'hoai@example.com', '$2y$10$abcdefghijk1234567890hoai'),
('Minh Trí', 'tri@example.com', '$2y$10$abcdefghijk1234567890tri'),
('Anh Khoa', 'khoa@example.com', '$2y$10$abcdefghijk1234567890khoa'),
('Vĩnh Thuận', 'thuan@example.com', '$2y$10$abcdefghijk1234567890thuan'),
('Hải Đăng', 'dang@example.com', '$2y$10$abcdefghijk1234567890dang'),
('Bảo Nam', 'nam@example.com', '$2y$10$abcdefghijk1234567890nam'),
('Hữu Lộc', 'loc@example.com', '$2y$10$abcdefghijk1234567890loc'),
('Thanh Phong', 'phong@example.com', '$2y$10$abcdefghijk1234567890phong'),
('Hồng Nhung', 'nhung@example.com', '$2y$10$abcdefghijk1234567890nhung'),
('Quang Minh', 'minh@example.com', '$2y$10$abcdefghijk1234567890minh'),
('Ngọc Diệp', 'diep@example.com', '$2y$10$abcdefghijk1234567890diep'),
('Phương Thảo', 'thao@example.com', '$2y$10$abcdefghijk1234567890thao'),
('Đức Tài', 'tai@example.com', '$2y$10$abcdefghijk1234567890tai'),
('Tấn Dũng', 'dung@example.com', '$2y$10$abcdefghijk1234567890dung'),
('Bảo Vy', 'vy@example.com', '$2y$10$abcdefghijk1234567890vy'),
('Quốc Huy', 'huy@example.com', '$2y$10$abcdefghijk1234567890huy'),
('Thảo Linh', 'linh@example.com', '$2y$10$abcdefghijk1234567890linh'),
('Đăng Khoa', 'dkhoa@example.com', '$2y$10$abcdefghijk1234567890dkhoa'),
('Tuấn Kiệt', 'kiet@example.com', '$2y$10$abcdefghijk1234567890kiet');

-- SCORES (mỗi người vài lượt chơi)
INSERT INTO scores (user_id, score, enemies_killed, gold_left, duration_seconds) VALUES
(1, 950, 88, 150, 80),
(1, 820, 75, 120, 90),
(2, 640, 60, 100, 120),
(2, 720, 68, 90, 110),
(3, 1200, 95, 200, 60),
(4, 450, 40, 90, 130),
(5, 780, 70, 140, 100),
(6, 1010, 90, 180, 70),
(7, 870, 78, 110, 85),
(8, 560, 55, 95, 120),
(9, 920, 82, 130, 95),
(10, 980, 89, 150, 88),
(11, 1110, 96, 210, 75),
(12, 630, 59, 105, 125),
(13, 700, 66, 115, 110),
(14, 850, 77, 140, 90),
(15, 1040, 92, 190, 80),
(16, 880, 80, 160, 95),
(17, 970, 88, 170, 85),
(18, 790, 70, 135, 100),
(19, 1080, 93, 200, 78),
(20, 910, 81, 150, 90),
(10, 1020, 94, 200, 82),
(5, 800, 72, 145, 95),
(3, 1180, 92, 180, 65),
(15, 1070, 97, 195, 70),
(6, 990, 85, 160, 85),
(12, 660, 60, 115, 115),
(14, 870, 79, 130, 92),
(19, 1115, 98, 220, 68);

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

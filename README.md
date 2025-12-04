# Tower Defense – Web Community & Game Platform
**Dự án nhóm – Website cộng đồng + Game Tower Defense + Hệ thống quản trị + ChatbotAI** 
## Giới thiệu
Tower Defense là dự án kết hợp **Game Tower Defense**, **Diễn đàn(Forum) chia sẻ**, **Bảng xếp hạng**,**Chatbot AI** tích hợp giúp người chơi đặt câu hỏi về gameplay và hỗ trợ kỹ thuật và **Hệ thống admin quản lý người dùng**.
## Website cho phép người chơi:
* Chơi game trực tiếp
* Xem bảng điểm
* Đăng bài chia sẻ chiến thuật
* Chỉnh sửa trang cá nhân , kết bạn
* Bình luận, like, upload hình/video
* Gửi phản hồi cho admin

##  1. Gameplay chính
* Người chơi đặt tháp để tiêu diệt kẻ địch theo từng **đợt**.
* Enemy di chuyển theo **đường cố định** → tháp tự động bắn.
* Tháp có **sát thương, tầm bắn, tốc độ bắn**, nâng cấp để mạnh hơn.
* Điểm được tính theo:
  * Số quái tiêu diệt
  * Wave hoàn thành
  * Vàng còn lại

## 2. Tính năng Website Community
###  Người dùng (User)
* Đăng ký / đăng nhập / đổi thông tin cá nhân
* Xem bảng điểm cá nhân & người chơi khác
* Tìm kiếm kết bạn với người chơi khác
* Viết bài chia sẻ chiến thuật
* Bình luận (có trả lời, hình ảnh)
* Like bài viết & bình luận
* Upload hình/video trong bài
### Forum
* Bài viết (title, content, screenshot, featured)
* Bình luận đa cấp
* Hình ảnh đính kèm
* Like / unlike
### Liên hệ (Contact)
* Người dùng gửi góp ý / báo lỗi

## 3. Chức năng Admin
Admin có thể:
* Khóa/mở khóa user
* Nâng / hạ quyền admin
* Xóa bài viết, bình luận
* Quản lý feedback người dùng
* Xem **thống kê hệ thống**:
  * Tổng users
  * Tổng posts
  * Tổng lượt chơi
  * Số bài hôm nay
  * Biểu đồ thống kê cơ bản

## 4. Chatbot AI (Gemini Flash 2.0)
Dự án tích hợp **Chatbot AI** hỗ trợ:
###  Tính năng
* Giải thích cách chơi game
* Gợi ý chiến thuật đặt tháp
* Trả lời câu hỏi về diễn đàn, tài khoản, lỗi hệ thống
* Hỗ trợ người chơi bằng **Tiếng Việt 100%**
* Tự động hiểu:
  * Damage = “sát thương”
  * Range = “tầm bắn”
  * Fire rate = “tốc độ bắn”
### Công nghệ
* **Gemini 2.0 Flash API**
* Hỗ trợ tải ảnh → Chatbot phân tích & trả lời


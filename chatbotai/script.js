const chatBody = document.querySelector(".chat-body");
const messageInput = document.querySelector(".message-input");
const sendMessageButton = document.querySelector("#send-message");
const fileInput = document.querySelector("#file-input");
const fileUploadWrapper = document.querySelector(".file-upload-wrapper");
const fileCancelButton = document.querySelector("#file-cancel");
const chatbotToggler = document.querySelector("#chatbot-toggler");
const closeChatbot = document.querySelector("#close-chatbot");

// ==================================================
// 1. CẤU HÌNH API
// ==================================================
// Đường dẫn đến file PHP xử lý (tương đối so với file index.php)
const API_URL = "chatbotai/chat.php"; 

const userData = {
    message: null,
    file: {
        data: null,
        mime_type: null
    }
};

// Lịch sử chat & Cấu hình nhân vật AI
const chatHistory = [
    {
        role: "user",
        parts: [{
            text: `
    Bạn là AI Assistant của dự án Tower Defense.
    Nhiệm vụ:
    1) Giải thích gameplay, website, forum, admin, database của dự án.
    2) Trả lời mọi câu hỏi đời sống ngoài dự án.
    Luôn xác định loại câu hỏi và trả lời tự nhiên bằng tiếng Việt.

    TỪ ĐIỂN (tự động Việt hóa):
    damage=sát thương, range=tầm bắn, fire_rate=tốc độ bắn, DPS=sát thương mỗi giây,
    gold=vàng, upgrade=nâng cấp, wave=đợt, enemy=quái, tower=tháp, path=đường đi, spawn=xuất hiện.

    GAMEPLAY:
    - Đặt tháp → quái xuất hiện theo đợt → tháp tự bắn trong tầm.
    - Tháp có: sát thương, tầm bắn, tốc độ bắn, chi phí nâng cấp.
    - Điểm dựa trên: quái tiêu diệt, wave hoàn thành, vàng còn lại.

    COMMUNITY WEBSITE:
    - User: đăng ký, đăng nhập, avatar, bio, bài viết, bình luận, like, tìm kiếm người chơi, kết bạn.
    - Forum: bài viết, bình luận đa cấp, ảnh, file đính kèm.
    - Scoreboard: tổng điểm người chơi.
    - Contact: người dùng gửi góp ý.

    ADMIN:
    - Khóa/mở user, nâng quyền, xóa post/comment, quản lý contact, xem logs, xem thống kê hệ thống.

    DATABASE:
    users(id, name, email, password_hash, secret_code, avatar, role, bio, created_at, last_activity, last_login, is_locked)
    scores(id, user_id, score, enemies_killed, gold_left, duration_seconds, created_at)
    posts(id, user_id, title, content, screenshot_url, featured, created_at)
    comments(id, post_id, user_id, content, parent_comment_id, is_reply, created_at)
    comment_images(id, comment_id, image_path)
    comment_likes(unique comment_id + user_id)
    post_likes(unique post_id + user_id)
    post_files(id, post_id, file_path, file_type)
    admin_logs(id, admin_id, action, target_table, target_id, ip, created_at)
    contacts(id, name, email, message, is_read, created_at)

    QUY TẮC TRẢ LỜI:
    - Luôn trả lời hoàn toàn bằng tiếng Việt.
    - Không bao giờ nhắc đến các từ: “module”, “bộ não”, “hệ thống”, “phân tích nội bộ”.
    - Không bịa gameplay, chức năng hoặc bảng SQL không có trong mô tả.
    - Trả lời dễ hiểu, thân thiện.
    - Nếu câu hỏi thuộc dự án → giải thích theo dữ liệu trên.
    - Nếu câu hỏi ngoài dự án → trả lời như trợ lý AI bình thường.
            `
        }]
    }
];

const initialInputHeight = messageInput.scrollHeight;

// ==================================================
// 2. CÁC HÀM XỬ LÝ GIAO DIỆN
// ==================================================

// Tạo element tin nhắn
const createMessageElement = (content, ...classes) => {
    const div = document.createElement("div");
    div.classList.add("message", ...classes);
    div.innerHTML = content;
    return div;
};

// Gửi request lên Server (PHP) và nhận phản hồi
const generateBotResponse = async (incomingMessageDiv) => {
    const messageElement = incomingMessageDiv.querySelector(".message-text");
    
    // Thêm tin nhắn mới nhất của user vào lịch sử để gửi đi
    chatHistory.push({
        role: "user",
        parts: [{ text: userData.message }, ...(userData.file.data ? [{ inline_data: userData.file }] : [])],
    });
    
    const requestOptions = {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
            contents: chatHistory
        })
    }

    try {
        const response = await fetch(API_URL, requestOptions);
        
        // Kiểm tra nếu server trả về lỗi HTTP (404, 500...)
        if (!response.ok) {
            const errorText = await response.text();
            throw new Error(`Server Error: ${response.status} - ${errorText}`);
        }

        const data = await response.json();

        // --- [SỬA LỖI QUAN TRỌNG] Kiểm tra dữ liệu trả về ---
        if (data.error) {
            throw new Error(data.error.message || "Lỗi từ Google API trả về.");
        }
        if (!data.candidates || !data.candidates.length) {
            throw new Error("AI không thể trả lời câu hỏi này (Có thể do bộ lọc an toàn).");
        }
        // ----------------------------------------------------

        // Lấy nội dung trả lời
        const apiResponseText = data.candidates[0].content.parts[0].text.replace(/\*\*(.*?)\*\*/g, "$1").trim();
        messageElement.innerText = apiResponseText;
        
        // Lưu câu trả lời của AI vào lịch sử
        chatHistory.push({
            role: "model",
            parts: [{ text: apiResponseText }]
        });

    } catch (error) {
        console.error(error);
        messageElement.innerText = "⚠️ " + error.message;
        messageElement.style.color = "#ff4444";
    } finally {
        // Reset trạng thái
        userData.file = {};
        incomingMessageDiv.classList.remove("thinking");
        chatBody.scrollTo({ behavior: "smooth", top: chatBody.scrollHeight });
    }
};

// Xử lý khi người dùng nhấn Gửi
const handleOutgoingMessage = (e) => {
    e.preventDefault();
    userData.message = messageInput.value.trim();
    messageInput.value = "";
    fileUploadWrapper.classList.remove("file-uploaded");
    messageInput.dispatchEvent(new Event("input"));

    // Hiển thị tin nhắn của User
    const messageContent = `<div class="message-text"></div>
                            ${userData.file.data ? `<img src="data:${userData.file.mime_type};base64,${userData.file.data}" class="attachment" />` : ""}`;

    const outgoingMessageDiv = createMessageElement(messageContent, "user-message");
    outgoingMessageDiv.querySelector(".message-text").innerText = userData.message;
    chatBody.appendChild(outgoingMessageDiv);
    chatBody.scrollTop = chatBody.scrollHeight;

    // Hiển thị trạng thái "Đang suy nghĩ..." của Bot
    setTimeout(() => {
        const messageContent = `<svg class="bot-avatar" xmlns="http://www.w3.org/2000/svg" width="50" height="50" viewBox="0 0 1024 1024">
                    <path d="M738.3 287.6H285.7c-59 0-106.8 47.8-106.8 106.8v303.1c0 59 47.8 106.8 106.8 106.8h81.5v111.1c0 .7.8 1.1 1.4.7l166.9-110.6 41.8-.8h117.4l43.6-.4c59 0 106.8-47.8 106.8-106.8V394.5c0-59-47.8-106.9-106.8-106.9zM351.7 448.2c0-29.5 23.9-53.5 53.5-53.5s53.5 23.9 53.5 53.5-23.9 53.5-53.5 53.5-53.5-23.9-53.5-53.5zm157.9 267.1c-67.8 0-123.8-47.5-132.3-109h264.6c-8.6 61.5-64.5 109-132.3 109zm110-213.7c-29.5 0-53.5-23.9-53.5-53.5s23.9-53.5 53.5-53.5 53.5 23.9 53.5 53.5-23.9 53.5-53.5 53.5zM867.2 644.5V453.1h26.5c19.4 0 35.1 15.7 35.1 35.1v121.1c0 19.4-15.7 35.1-35.1 35.1h-26.5zM95.2 609.4V488.2c0-19.4 15.7-35.1 35.1-35.1h26.5v191.3h-26.5c-19.4 0-35.1-15.7-35.1-35.1zM561.5 149.6c0 23.4-15.6 43.3-36.9 49.7v44.9h-30v-44.9c-21.4-6.5-36.9-26.3-36.9-49.7 0-28.6 23.3-51.9 51.9-51.9s51.9 23.3 51.9 51.9z"></path>
                </svg>
                <div class="message-text">
                    <div class="thinking-indicator">
                        <div class="dot"></div>
                        <div class="dot"></div>
                        <div class="dot"></div>
                    </div>
                </div>`;

        const incomingMessageDiv = createMessageElement(messageContent, "bot-message", "thinking");
        chatBody.appendChild(incomingMessageDiv);
        chatBody.scrollTo({ behavior: "smooth", top: chatBody.scrollHeight });
        
        // Gọi hàm xử lý logic
        generateBotResponse(incomingMessageDiv);
    }, 600);
};

// ==================================================
// 3. XỬ LÝ SỰ KIỆN (EVENTS)
// ==================================================

// Enter để gửi
messageInput.addEventListener("keydown", (e) => {
    const userMessage = e.target.value.trim();
    if (e.key === "Enter" && userMessage && !e.shiftKey && window.innerWidth > 768) {
        handleOutgoingMessage(e);
    }
});

// Tự động chỉnh độ cao ô nhập liệu
messageInput.addEventListener("input", (e) => {
    messageInput.style.height = `${initialInputHeight}px`;
    messageInput.style.height = `${messageInput.scrollHeight}px`;
    document.querySelector(".chat-form").style.borderRadius = messageInput.scrollHeight > initialInputHeight ? "15px" : "32px";
});

// Xử lý chọn file ảnh (Đã gộp code lại cho gọn)
fileInput.addEventListener("change", async (e) => {
    const file = e.target.files[0];
    if (!file) return;

    // Kiểm tra loại file
    const validImageTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    if (!validImageTypes.includes(file.type)) {
        if (typeof Swal !== 'undefined') {
            await Swal.fire({
                icon: 'error',
                title: 'Lỗi định dạng',
                text: 'Chỉ chấp nhận file ảnh (JPEG, PNG, GIF, WEBP)',
                confirmButtonText: 'OK'
            });
        } else {
            alert('Chỉ chấp nhận file ảnh (JPEG, PNG, GIF, WEBP)');
        }
        resetFileInput();
        return;
    }

    // Đọc file
    const reader = new FileReader();
    reader.onload = (e) => {
        fileUploadWrapper.querySelector("img").src = e.target.result;
        fileUploadWrapper.classList.add("file-uploaded");
        const base64String = e.target.result.split(",")[1];
        
        userData.file = {
            data: base64String,
            mime_type: file.type
        };
        
        // Reset input để có thể chọn lại file giống cũ nếu muốn
        fileInput.value = ""; 
    };
    reader.readAsDataURL(file);
});

// Hủy chọn file
fileCancelButton.addEventListener("click", () => {
    resetFileInput();
});

function resetFileInput() {
    fileInput.value = "";
    fileUploadWrapper.classList.remove("file-uploaded");
    fileUploadWrapper.querySelector("img").src = "#";
    userData.file = { data: null, mime_type: null };
    document.querySelector(".chat-form").reset();
}

// Emoji Picker
const picker = new EmojiMart.Picker({
    theme: "light",
    showSkinTones: "none",
    previewPosition: "none",
    onEmojiSelect: (emoji) => {
        const { selectionStart: start, selectionEnd: end } = messageInput;
        messageInput.setRangeText(emoji.native, start, end, "end");
        messageInput.focus();
    },
    onClickOutside: (e) => {
        if (e.target.id === "emoji-picker") {
            document.body.classList.toggle("show-emoji-picker");
        } else {
            document.body.classList.remove("show-emoji-picker");
        }
    },
});
document.querySelector(".chat-form").appendChild(picker);

// Các nút bấm
sendMessageButton.addEventListener("click", (e) => handleOutgoingMessage(e));
document.querySelector("#file-upload").addEventListener("click", () => fileInput.click());
chatbotToggler.addEventListener("click", () => document.body.classList.toggle("show-chatbot"));
closeChatbot.addEventListener("click", () => document.body.classList.remove("show-chatbot"));
document.querySelector("#emoji-picker").addEventListener("click", () => document.body.classList.toggle("show-emoji-picker"));
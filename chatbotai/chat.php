<?php
// ==========================================
// CẤU HÌNH TRỰC TIẾP API KEY GROQ
// ==========================================
// Dán Key Groq của bạn vào đây (Bắt đầu bằng gsk_...)
$API_KEY = 'gsk_XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX'; 
// ==========================================

header("Content-Type: application/json");

// Kiểm tra key
if (empty($API_KEY)) {
    echo json_encode(["error" => ["message" => "Thiếu API Key"]]);
    exit;
}

// 1. Nhận dữ liệu từ JS (Dạng Gemini)
$input = json_decode(file_get_contents("php://input"), true);
if (!$input || !isset($input["contents"])) {
    echo json_encode(["error" => ["message" => "Invalid request data"]]);
    exit;
}

// 2. CHUYỂN ĐỔI DỮ LIỆU: Gemini format -> Groq format
$groqMessages = [];

// Thêm System Prompt
$groqMessages[] = [
    'role' => 'system',
    'content' => 'Bạn là AI Assistant của dự án Tower Defense. Trả lời ngắn gọn, thân thiện bằng tiếng Việt. Không dùng Markdown (như **đậm**, *nghiêng*) trong câu trả lời.'
];

foreach ($input['contents'] as $msg) {
    // Map role: 'model' (Gemini) -> 'assistant' (Groq)
    $role = ($msg['role'] === 'model') ? 'assistant' : 'user';
    
    // Lấy nội dung text
    $text = '';
    if (isset($msg['parts'][0]['text'])) {
        $text = $msg['parts'][0]['text'];
    }
    
    // Đẩy vào mảng
    if (!empty($text)) {
        $groqMessages[] = [
            'role' => $role,
            'content' => $text
        ];
    }
}

// 3. Gửi request sang GROQ API
$url = "https://api.groq.com/openai/v1/chat/completions";

$payload = json_encode([
    // --- [SỬA LỖI] Đổi sang model mới nhất ---
    "model" => "llama-3.3-70b-versatile", 
    // -----------------------------------------
    "messages" => $groqMessages,
    "temperature" => 0.7,
    "max_tokens" => 1024
]);

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Content-Type: application/json",
    "Authorization: Bearer " . $API_KEY
]);
curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Tắt SSL check cho Localhost

$response = curl_exec($ch);

if (curl_errno($ch)) {
    echo json_encode(["error" => ["message" => "Curl error: " . curl_error($ch)]]);
    exit;
}
curl_close($ch);

// 4. XỬ LÝ KẾT QUẢ
$result = json_decode($response, true);

if (isset($result['error'])) {
    // Nếu Groq báo lỗi
    echo json_encode(["error" => ["message" => "Groq Error: " . ($result['error']['message'] ?? 'Unknown error')]]);
} else {
    // Lấy câu trả lời
    $botReply = $result['choices'][0]['message']['content'] ?? "Xin lỗi, hệ thống đang bận.";

    // Đóng gói lại thành cấu trúc JSON giả lập Gemini
    $geminiResponse = [
        "candidates" => [
            [
                "content" => [
                    "parts" => [
                        ["text" => $botReply]
                    ]
                ]
            ]
        ]
    ];

    echo json_encode($geminiResponse);
}
exit;
?>
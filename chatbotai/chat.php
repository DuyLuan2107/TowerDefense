<?php
header("Content-Type: application/json");

// ***** API KEY ***** 
$API_KEY = "AIzaSyDowlX_Sai1icjx3vGRbQXEncbZPVIIUJQ";

// Nhận dữ liệu từ JS (JSON)
$input = json_decode(file_get_contents("php://input"), true);

if (!$input || !isset($input["contents"])) {
    echo json_encode(["error" => "Invalid request"]);
    exit;
}

// Gửi request sang Gemini API
$url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key=$API_KEY";

$payload = json_encode([ "contents" => $input["contents"] ]);

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json"]);
curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$response = curl_exec($ch);
curl_close($ch);

echo $response;
exit;

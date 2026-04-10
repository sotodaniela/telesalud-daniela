<?php
$apiKey = 'APIBTKhTXviwsK4';
$apiSecret = 'KH9L2S5D54Ls6fpeEY3HbMeunEKOV6GWBWz3SIjyo8eA';
$serverUrl = 'http://localhost:7880';

function createJwt($apiKey, $apiSecret) {
    $header = ['alg' => 'HS256', 'typ' => 'JWT'];
    $payload = [
        'iss' => $apiKey,
        'exp' => time() + 3600,
    ];
    
    $headerEncoded = rtrim(strtr(base64_encode(json_encode($header)), '+/', '-_'), '=');
    $payloadEncoded = rtrim(strtr(base64_encode(json_encode($payload)), '+/', '-_'), '=');
    
    $message = "$headerEncoded.$payloadEncoded";
    $signature = hash_hmac('sha256', $message, $apiSecret, true);
    $signatureEncoded = rtrim(strtr(base64_encode($signature), '+/', '-_'), '=');
    
    return "$headerEncoded.$payloadEncoded.$signatureEncoded";
}

$token = createJwt($apiKey, $apiSecret);

$ch = curl_init("$serverUrl/api/v2/room");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $token,
    'Content-Type: application/json'
]);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
    'name' => 'vc_prueba001',
    'empty_timeout' => 300,
]));

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: $httpCode\n";
echo "Response: $response\n";

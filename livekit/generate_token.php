<?php
$apiKey = "APIRo7z5mD5yfXz";
$apiSecret = "TUN59ewmWYRIb64Oa4oN5xBezC3exnnJir6YcJiX1P6A";

function base64UrlEncode($data) {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function generateToken($apiKey, $apiSecret, $roomName, $participantName, $participantIdentity, $isModerator = false) {
    $expiresAt = time() + 7200;
    
    $header = base64UrlEncode(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
    $payload = base64UrlEncode(json_encode([
        'iss' => $apiKey,
        'sub' => $participantIdentity,
        'room' => $roomName,
        'name' => $participantName,
        'exp' => $expiresAt,
        'canPublish' => true,
        'canSubscribe' => true,
        'canPublishData' => true,
    ]));
    
    $signature = base64UrlEncode(hash_hmac('sha256', "$header.$payload", $apiSecret, true));
    
    return "$header.$payload.$signature";
}

$roomName = $argv[1];
$patientName = $argv[2];
$patientIdentity = $argv[3];
$medicName = $argv[4];
$medicIdentity = $argv[5];

$patientToken = generateToken($apiKey, $apiSecret, $roomName, $patientName, $patientIdentity);
$medicToken = generateToken($apiKey, $apiSecret, $roomName, $medicName, $medicIdentity, true);

echo json_encode([
    'patientToken' => $patientToken,
    'medicToken' => $medicToken
]);

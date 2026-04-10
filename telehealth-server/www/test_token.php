<?php
session_start();

if (!isset($_SESSION['doctor_id'])) {
    header('Location: login.php');
    exit;
}

$conn = new mysqli('telehealth-db', 'telehealth', 'telehealth123', 'telehealth');
$consultation_id = intval($_GET['id'] ?? 0);
$doctor_id = $_SESSION['doctor_id'];

$consultation = $conn->query("SELECT * FROM consultations WHERE id = $consultation_id AND doctor_id = $doctor_id")->fetch_assoc();

if (!$consultation) {
    die("Consulta no encontrada");
}

$room_name = $consultation['room_name'];
$api_key = 'APInewKey123';
$api_secret = 'NewSecret456789012345678901234567890';

function base64url_encode($data) {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

$header = json_encode(['alg' => 'HS256', 'typ' => 'JWT']);
$payload = json_encode([
    'iss' => $api_key,
    'sub' => 'doctor_' . $doctor_id,
    'room' => $room_name,
    'name' => $_SESSION['doctor_name'],
    'exp' => time() + 7200,
    'canPublish' => true,
    'canSubscribe' => true,
    'canPublishData' => true
]);

$headerEncoded = base64url_encode($header);
$payloadEncoded = base64url_encode($payload);
$message = $headerEncoded . '.' . $payloadEncoded;
$signature = hash_hmac('sha256', $message, $api_secret, true);
$signatureEncoded = base64url_encode($signature);

$token = $headerEncoded . '.' . $payloadEncoded . '.' . $signatureEncoded;
?>
<!DOCTYPE html>
<html>
<head>
    <title>Test Token</title>
</head>
<body>
    <h1>Token de Prueba</h1>
    <p><strong>Sala:</strong> <?php echo $room_name; ?></p>
    <p><strong>Token:</strong></p>
    <textarea style="width: 100%; height: 150px;"><?php echo $token; ?></textarea>
    <p><a href="https://jwt.io/?token=<?php echo urlencode($token); ?>" target="_blank">Verificar en jwt.io</a></p>
    <p><a href="join_consultation.php?id=<?php echo $consultation_id; ?>">Ir a videoconsulta</a></p>
</body>
</html>

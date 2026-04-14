<?php
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['id'])) {
    echo json_encode(['success' => false, 'error' => 'ID no proporcionado']);
    exit;
}

$conn = new mysqli('telehealth-db', 'telehealth', 'telehealth123', 'telehealth');

if ($conn->connect_error) {
    echo json_encode(['success' => false, 'error' => 'Error de conexión']);
    exit;
}

$stmt = $conn->prepare('UPDATE consultations SET duration_minutes = ?, notes = ?, status = ? WHERE id = ? AND doctor_id = ?');
$status = $data['status'] ?? 'completed';
$duration = $data['duration'] ?? 0;
$notes = $data['notes'] ?? '';
$id = $data['id'];
$doctor_id = $_SESSION['user_id'];

$stmt->bind_param('issii', $duration, $notes, $status, $id, $doctor_id);

if ($stmt->execute()) {
    $stmt->close();
    $conn->close();
    echo json_encode(['success' => true]);
} else {
    $stmt->close();
    $conn->close();
    echo json_encode(['success' => false, 'error' => $stmt->error]);
}

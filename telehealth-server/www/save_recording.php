<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
    exit;
}

if (!isset($_FILES['file'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'No se encontró archivo']);
    exit;
}

$consultation_id = $_POST['consultation_id'] ?? 0;

if ($consultation_id == 0) {
    echo json_encode(['success' => false, 'error' => 'ID de consulta no válido']);
    exit;
}

// Create recordings directory if not exists
$recordingsDir = __DIR__ . '/recordings';
if (!file_exists($recordingsDir)) {
    mkdir($recordingsDir, 0755, true);
}

// Save file
$file = $_FILES['file'];
$fileName = 'consultation_' . $consultation_id . '_' . time() . '.webm';
$targetPath = $recordingsDir . '/' . $fileName;

if (move_uploaded_file($file['tmp_name'], $targetPath)) {
    // Update database with recording path
    $conn = new mysqli('telehealth-mysql', 'telehealth', 'telehealth123', 'telehealth');
    
    if ($conn->connect_error) {
        echo json_encode(['success' => false, 'error' => 'Error de conexión a BD']);
        exit;
    }
    
    $recordingPath = 'recordings/' . $fileName;
    $stmt = $conn->prepare('UPDATE consultations SET recording_path = ? WHERE id = ? AND doctor_id = ?');
    $stmt->bind_param('sii', $recordingPath, $consultation_id, $_SESSION['user_id']);
    
    if ($stmt->execute()) {
        $stmt->close();
        $conn->close();
        echo json_encode(['success' => true, 'path' => $recordingPath]);
    } else {
        $stmt->close();
        $conn->close();
        echo json_encode(['success' => false, 'error' => 'Error al guardar en BD']);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Error al mover archivo']);
}

<?php
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'No autenticado']);
    exit;
}

$conn = new mysqli('telehealth-db', 'telehealth', 'telehealth123', 'telehealth');

$patient_id = intval($_POST['patient_id'] ?? 0);
$consultation_id = intval($_POST['consultation_id'] ?? 0);
$doctor_id = $_SESSION['user_id'];

if (!$patient_id) {
    echo json_encode(['success' => false, 'error' => 'Paciente no especificado']);
    exit;
}

$consultation_type = $conn->real_escape_string($_POST['consultation_type'] ?? 'Telemedicina');
$reason_consultation = $conn->real_escape_string($_POST['reason_consultation'] ?? '');
$current_illness = $conn->real_escape_string($_POST['current_illness'] ?? '');
$physical_exam = $conn->real_escape_string($_POST['physical_exam'] ?? '');
$management_plan = $conn->real_escape_string($_POST['management_plan'] ?? '');
$recommendations = $conn->real_escape_string($_POST['recommendations'] ?? '');
$diagnosis_code = $conn->real_escape_string($_POST['diagnosis_code'] ?? '');
$diagnosis_name = $conn->real_escape_string($_POST['diagnosis_name'] ?? '');

$vital_signs = json_encode([
    'systolic_bp' => $_POST['systolic_bp'] ?? null,
    'diastolic_bp' => $_POST['diastolic_bp'] ?? null,
    'heart_rate' => $_POST['heart_rate'] ?? null,
    'temperature' => $_POST['temperature'] ?? null,
    'respiratory_rate' => $_POST['respiratory_rate'] ?? null,
    'oxygen_saturation' => $_POST['oxygen_saturation'] ?? null,
    'weight' => $_POST['weight'] ?? null,
    'height' => $_POST['height'] ?? null,
]);

$diagnoses = json_encode([['code' => $diagnosis_code, 'name' => $diagnosis_name, 'type' => 'Principal']]);

$medicines = [];
if (!empty($_POST['medicine_name'])) {
    foreach ($_POST['medicine_name'] as $i => $name) {
        if ($name) {
            $medicines[] = [
                'name' => $name,
                'dose' => $_POST['medicine_dose'][$i] ?? '',
                'frequency' => $_POST['medicine_frequency'][$i] ?? '',
                'duration' => $_POST['medicine_duration'][$i] ?? '',
                'route' => $_POST['medicine_route'][$i] ?? 'Oral'
            ];
        }
    }
}
$prescriptions = json_encode($medicines);

$sql = "INSERT INTO clinical_history 
    (patient_id, doctor_id, consultation_id, consultation_date, consultation_type, 
    reason_consultation, current_illness, vital_signs, diagnoses, prescriptions,
    physical_exam, management_plan, recommendations, status, doctor_name)
    VALUES ($patient_id, $doctor_id, " . ($consultation_id > 0 ? $consultation_id : "NULL") . ", NOW(), '$consultation_type',
    '$reason_consultation', '$current_illness', '$vital_signs', '$diagnoses', '$prescriptions',
    '$physical_exam', '$management_plan', '$recommendations', 'signed', '" . $_SESSION['doctor_name'] . "')";

if ($conn->query($sql)) {
    $id = $conn->insert_id;
    echo json_encode(['success' => true, 'id' => $id]);
} else {
    echo json_encode(['success' => false, 'error' => $conn->error]);
}

$conn->close();

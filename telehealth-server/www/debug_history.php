<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    die("No autenticado");
}

$conn = new mysqli('telehealth-db', 'telehealth', 'telehealth123', 'telehealth');

$patient_id = intval($_GET['patient_id'] ?? 0);
$consultation_id = intval($_GET['consultation_id'] ?? 0);

echo "<h1>Debug Info</h1>";
echo "<p>GET patient_id: " . $patient_id . "</p>";
echo "<p>GET consultation_id: " . $consultation_id . "</p>";

$consultation = null;

if ($consultation_id) {
    $consultation = $conn->query("SELECT c.*, CONCAT(p.first_name, ' ', p.last_name) as patient_name, p.id_number, p.id_type, p.email, p.phone, p.mobile, p.birth_date, p.gender, p.address, p.city, p.eps_name, p.id as pid FROM consultations c JOIN patients p ON c.patient_id = p.id WHERE c.id = $consultation_id AND c.doctor_id = " . $_SESSION['user_id'])->fetch_assoc();
    if ($consultation) {
        $patient_id = $consultation['pid'];
    }
}

echo "<p>Final patient_id: " . $patient_id . "</p>";
echo "<p>consultation exists: " . ($consultation ? "YES" : "NO") . "</p>";

if (!$consultation && $patient_id) {
    $consultation = $conn->query("SELECT p.*, CONCAT(p.first_name, ' ', p.last_name) as patient_name, p.id as pid FROM patients p WHERE p.id = $patient_id AND p.is_active = 1")->fetch_assoc();
    echo "<p>Loaded patient data: " . ($consultation ? "YES" : "NO") . "</p>";
}

if (!$consultation) {
    echo "<p style='color:red;'>ERROR: No se encontró paciente o consulta</p>";
    exit;
}

echo "<p>Patient Name: " . ($consultation['patient_name'] ?? 'N/A') . "</p>";

// Check clinical history
$previous_history = $conn->query("SELECT * FROM clinical_history WHERE patient_id = $patient_id ORDER BY consultation_date DESC LIMIT 5");
echo "<p>Clinical history records: " . $previous_history->num_rows . "</p>";

echo "<h2>Historia Clínica:</h2>";
while ($h = $previous_history->fetch_assoc()) {
    echo "<div style='border:1px solid #ccc; padding:10px; margin:5px;'>";
    echo "<p><strong>Fecha:</strong> " . $h['consultation_date'] . "</p>";
    echo "<p><strong>Tipo:</strong> " . $h['consultation_type'] . "</p>";
    echo "<p><strong>Motivo:</strong> " . ($h['reason_consultation'] ?? 'N/A') . "</p>";
    echo "<a href='teleconsulta_pdf.php?id=" . $patient_id . "&consultation_id=" . $h['id'] . "' target='_blank'>Ver PDF</a>";
    echo "</div>";
}

$conn->close();
?>

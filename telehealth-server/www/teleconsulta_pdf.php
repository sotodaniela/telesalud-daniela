<?php
require_once __DIR__ . '/tcpdf/tcpdf.php';

session_start();

if (!isset($_SESSION['doctor_id'])) {
    header('Location: login.php');
    exit;
}

$conn = new mysqli('telehealth-db', 'telehealth', 'telehealth123', 'telehealth');
$doctor_id = $_SESSION['doctor_id'];

$patient_id = intval($_GET['id'] ?? 0);
$consultation_id = intval($_GET['consultation_id'] ?? 0);

if (!$patient_id) {
    header('Location: dashboard.php');
    exit;
}

// Get patient data
$patient = $conn->query("SELECT * FROM patients WHERE id = $patient_id")->fetch_assoc();

// Get personal history
$personal_history = $conn->query("SELECT * FROM personal_history WHERE patient_id = $patient_id ORDER BY updated_at DESC LIMIT 1")->fetch_assoc();

// Get latest clinical history or current consultation
if ($consultation_id) {
    $clinical = $conn->query("SELECT * FROM clinical_history WHERE consultation_id = $consultation_id ORDER BY created_at DESC LIMIT 1")->fetch_assoc();
}
if (!$clinical) {
    $clinical = $conn->query("SELECT * FROM clinical_history WHERE patient_id = $patient_id ORDER BY consultation_date DESC LIMIT 1")->fetch_assoc();
}

// Calculate age
$birth_date = new DateTime($patient['birth_date']);
$today = new DateTime('today');
$age = $birth_date->diff($today)->y;

// Create PDF
class MYPDF extends TCPDF {
    public function Header() {
        $this->SetFont('helvetica', 'B', 14);
        $this->Cell(0, 10, 'HISTORIA CLINICA TELEMEDICINA', 0, 1, 'C');
        $this->SetFont('helvetica', '', 10);
        $this->Cell(0, 6, 'Organizacion Panamericana de la Salud / OMS', 0, 1, 'C');
        $this->Ln(5);
    }
    
    public function Footer() {
        $this->SetY(-15);
        $this->SetFont('helvetica', 'I', 8);
        $this->Cell(0, 10, 'Pagina ' . $this->getAliasNumPage() . '/' . $this->getAliasNbPages(), 0, 0, 'C');
        $this->Cell(0, 10, 'Documento confidencial - Historia Clinica', 0, 0, 'R');
    }
}

$pdf = new MYPDF('P', 'mm', 'A4', true, 'UTF-8');
$pdf->SetMargins(15, 45, 15);
$pdf->SetAutoPageBreak(true, 20);
$pdf->AddPage();

// Section: Patient Information
$pdf->SetFont('helvetica', 'B', 11);
$pdf->SetFillColor(0, 102, 204);
$pdf->SetTextColor(255, 255, 255);
$pdf->Cell(0, 8, '  1. DATOS DEL PACIENTE', 0, 1, 'L', true);

$pdf->SetTextColor(0, 0, 0);
$pdf->SetFont('helvetica', '', 9);

$pdf->SetFillColor(240, 240, 240);
$rowHeight = 6;

// Patient data
$pdf->Cell(50, $rowHeight, 'Nombre Completo:', 1, 0, 'L', true);
$pdf->Cell(70, $rowHeight, utf8_decode($patient['first_name'] . ' ' . $patient['second_name'] . ' ' . $patient['last_name'] . ' ' . $patient['second_last_name']), 1, 0, 'L');

$pdf->Cell(25, $rowHeight, 'Tipo Doc:', 1, 0, 'L', true);
$pdf->Cell(45, $rowHeight, $patient['id_type'] . ' - ' . $patient['id_number'], 1, 1, 'L');

$pdf->Cell(50, $rowHeight, 'Fecha de Nacimiento:', 1, 0, 'L', true);
$pdf->Cell(35, $rowHeight, date('d/m/Y', strtotime($patient['birth_date'])) . ' (' . $age . ' anos)', 1, 0, 'L');

$pdf->Cell(25, $rowHeight, 'Genero:', 1, 0, 'L', true);
$pdf->Cell(45, $rowHeight, $patient['gender'] === 'M' ? 'Masculino' : ($patient['gender'] === 'F' ? 'Femenino' : 'Otro'), 1, 1, 'L');

$pdf->Cell(50, $rowHeight, 'Direccion:', 1, 0, 'L', true);
$pdf->Cell(140, $rowHeight, utf8_decode($patient['address'] . ', ' . $patient['city']), 1, 1, 'L');

$pdf->Cell(50, $rowHeight, 'Telefono:', 1, 0, 'L', true);
$pdf->Cell(70, $rowHeight, $patient['mobile'] ?: $patient['phone'], 1, 0, 'L');

$pdf->Cell(25, $rowHeight, 'EPS:', 1, 0, 'L', true);
$pdf->Cell(45, $rowHeight, utf8_decode($patient['eps_name'] ?: 'No registrada'), 1, 1, 'L');

$pdf->Ln(5);

// Section: Clinical History
if ($clinical) {
    $pdf->SetFont('helvetica', 'B', 11);
    $pdf->SetFillColor(0, 102, 204);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->Cell(0, 8, '  2. HISTORIA CLINICA', 0, 1, 'L', true);
    
    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetFont('helvetica', '', 9);
    
    $vital = json_decode($clinical['vital_signs'] ?? '{}', true);
    
    // Vital Signs
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->Cell(0, 6, 'Signos Vitales', 0, 1, 'L');
    $pdf->SetFont('helvetica', '', 9);
    
    $pdf->SetFillColor(240, 240, 240);
    $pdf->Cell(45, $rowHeight, 'Presion Arterial:', 1, 0, 'L', true);
    $pdf->Cell(30, $rowHeight, ($vital['systolic_bp'] ?? '-') . '/' . ($vital['diastolic_bp'] ?? '-') . ' mmHg', 1, 0, 'L');
    $pdf->Cell(40, $rowHeight, 'Frecuencia Cardiaca:', 1, 0, 'L', true);
    $pdf->Cell(25, $rowHeight, ($vital['heart_rate'] ?? '-') . ' lpm', 1, 0, 'L');
    $pdf->Cell(25, $rowHeight, 'Temperatura:', 1, 0, 'L', true);
    $pdf->Cell(25, $rowHeight, ($vital['temperature'] ?? '-') . ' C', 1, 1, 'L');
    
    $pdf->Cell(45, $rowHeight, 'Frecuencia Respiratoria:', 1, 0, 'L', true);
    $pdf->Cell(30, $rowHeight, ($vital['respiratory_rate'] ?? '-') . '/min', 1, 0, 'L');
    $pdf->Cell(40, $rowHeight, 'Saturacion O2:', 1, 0, 'L', true);
    $pdf->Cell(25, $rowHeight, ($vital['oxygen_saturation'] ?? '-') . '%', 1, 0, 'L');
    $pdf->Cell(25, $rowHeight, 'Peso:', 1, 0, 'L', true);
    $pdf->Cell(25, $rowHeight, ($vital['weight'] ?? '-') . ' kg', 1, 1, 'L');
    
    $pdf->Ln(3);
    
    // Clinical data
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->Cell(0, 6, 'Datos de la Consulta', 0, 1, 'L');
    $pdf->SetFont('helvetica', '', 9);
    
    $pdf->Cell(50, $rowHeight, 'Fecha/Hora:', 1, 0, 'L', true);
    $pdf->Cell(140, $rowHeight, date('d/m/Y H:i', strtotime($clinical['consultation_date'])), 1, 1, 'L');
    
    $pdf->Cell(50, $rowHeight, 'Tipo de Consulta:', 1, 0, 'L', true);
    $pdf->Cell(140, $rowHeight, $clinical['consultation_type'], 1, 1, 'L');
    
    $pdf->Cell(50, $rowHeight, 'Motivo de Consulta:', 1, 0, 'L', true);
    $pdf->MultiCell(140, $rowHeight, utf8_decode($clinical['reason_consultation'] ?? 'N/A'), 1, 'L');
    
    if ($clinical['current_illness']) {
        $pdf->Cell(50, $rowHeight, 'Enfermedad Actual:', 1, 0, 'L', true);
        $pdf->MultiCell(140, $rowHeight, utf8_decode($clinical['current_illness']), 1, 'L');
    }
    
    if ($clinical['physical_exam']) {
        $pdf->Cell(50, $rowHeight, 'Examen Fisico:', 1, 0, 'L', true);
        $pdf->MultiCell(140, $rowHeight, utf8_decode($clinical['physical_exam']), 1, 'L');
    }
    
    // Diagnosis
    $diagnoses = json_decode($clinical['diagnoses'] ?? '[]', true);
    if ($diagnoses && isset($diagnoses[0]['code'])) {
        $pdf->Cell(50, $rowHeight, 'Diagnostico:', 1, 0, 'L', true);
        $pdf->Cell(140, $rowHeight, utf8_decode($diagnoses[0]['code'] . ' - ' . $diagnoses[0]['name']), 1, 1, 'L');
    }
    
    // Prescriptions
    $prescriptions = json_decode($clinical['prescriptions'] ?? '[]', true);
    if ($prescriptions && count($prescriptions) > 0) {
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->Cell(0, 6, 'Prescripcion de Medicamentos', 0, 1, 'L');
        $pdf->SetFont('helvetica', '', 9);
        
        foreach ($prescriptions as $med) {
            $pdf->Cell(190, $rowHeight, utf8_decode($med['name'] . ' - ' . $med['dose'] . ' - ' . $med['frequency'] . ' - ' . $med['duration']), 1, 1, 'L');
        }
    }
    
    if ($clinical['management_plan']) {
        $pdf->Cell(50, $rowHeight, 'Plan de Manejo:', 1, 0, 'L', true);
        $pdf->MultiCell(140, $rowHeight, utf8_decode($clinical['management_plan']), 1, 'L');
    }
    
    if ($clinical['recommendations']) {
        $pdf->Cell(50, $rowHeight, 'Recomendaciones:', 1, 0, 'L', true);
        $pdf->MultiCell(140, $rowHeight, utf8_decode($clinical['recommendations']), 1, 'L');
    }
    
    $pdf->Ln(5);
    
    // Signature
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->Cell(0, 6, 'Firma del Medico', 0, 1, 'L');
    $pdf->SetFont('helvetica', '', 9);
    $pdf->Cell(95, $rowHeight, 'Nombre:', 1, 0, 'L', true);
    $pdf->Cell(95, $rowHeight, utf8_decode($_SESSION['doctor_name']), 1, 1, 'L');
    $pdf->Cell(95, $rowHeight, 'Fecha/Hora Firma:', 1, 0, 'L', true);
    $pdf->Cell(95, $rowHeight, date('d/m/Y H:i'), 1, 1, 'L');
}

// Section: Personal History
$pdf->AddPage();
$pdf->SetFont('helvetica', 'B', 11);
$pdf->SetFillColor(0, 102, 204);
$pdf->SetTextColor(255, 255, 255);
$pdf->Cell(0, 8, '  3. ANTECEDENTES', 0, 1, 'L', true);

$pdf->SetTextColor(0, 0, 0);
$pdf->SetFont('helvetica', '', 9);

if ($personal_history) {
    // Pathological
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->Cell(0, 6, 'Antecedentes Patologicos', 0, 1, 'L');
    $pdf->SetFont('helvetica', '', 9);
    
    $pdf->Cell(50, $rowHeight, 'Enfermedades:', 1, 0, 'L', true);
    $pdf->MultiCell(140, $rowHeight, utf8_decode($personal_history['pathological_enfermedades'] ?: 'Sin antecedentes significativos'), 1, 'L');
    
    $pdf->Cell(50, $rowHeight, 'Alergias:', 1, 0, 'L', true);
    $pdf->MultiCell(140, $rowHeight, utf8_decode($personal_history['pathological_alergias'] ?: 'Ninguna'), 1, 'L');
    
    $pdf->Cell(50, $rowHeight, 'Cirugias:', 1, 0, 'L', true);
    $pdf->MultiCell(140, $rowHeight, utf8_decode($personal_history['pathological_cirugias'] ?: 'Ninguna'), 1, 'L');
    
    $pdf->Ln(3);
    
    // Family History
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->Cell(0, 6, 'Antecedentes Familiares', 0, 1, 'L');
    $pdf->SetFont('helvetica', '', 9);
    
    $pdf->Cell(63, $rowHeight, 'Diabetes: ' . ($personal_history['family_diabetes'] ?? 'NS'), 1, 0, 'L');
    $pdf->Cell(64, $rowHeight, 'Hipertension: ' . ($personal_history['family_hypertension'] ?? 'NS'), 1, 0, 'L');
    $pdf->Cell(63, $rowHeight, 'Enf. Cardiaca: ' . ($personal_history['family_heart_disease'] ?? 'NS'), 1, 1, 'L');
    
    $pdf->Cell(50, $rowHeight, 'Cancer familiar:', 1, 0, 'L', true);
    $pdf->Cell(140, $rowHeight, utf8_decode($personal_history['family_cancer'] ?: 'Ninguno'), 1, 1, 'L');
    
    $pdf->Ln(3);
    
    // Non Pathological
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->Cell(0, 6, 'Antecedentes No Patologicos', 0, 1, 'L');
    $pdf->SetFont('helvetica', '', 9);
    
    $pdf->Cell(63, $rowHeight, 'Tabaquismo: ' . ($personal_history['non_pathological_smoking'] ?? 'No'), 1, 0, 'L');
    $pdf->Cell(64, $rowHeight, 'Alcohol: ' . ($personal_history['non_pathological_alcohol'] ?? 'No'), 1, 0, 'L');
    $pdf->Cell(63, $rowHeight, 'Ejercicio: ' . ($personal_history['non_pathological_exercise'] ?? 'No'), 1, 1, 'L');
}

$pdf->Ln(10);

// Legal text
$pdf->SetFont('helvetica', 'I', 8);
$pdf->MultiCell(0, 5, utf8_decode('Este documento hace parte integral de la Historia Clinica segun la Resolucion 1995 de 1999 del Ministerio de Proteccion Social de Colombia y la Ley 23 de 1981. Es confidencial y de uso exclusivo de los profesionales de la salud y el paciente.'), 0, 'L');

// Output PDF
$pdf->Output('Historia_Clinica_' . $patient['id_number'] . '_' . date('Ymd') . '.pdf', 'I');

$conn->close();
?>

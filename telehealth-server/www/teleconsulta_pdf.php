<?php
ob_start();
require_once __DIR__ . '/tcpdf/tcpdf.php';

session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$conn = new mysqli('telehealth-db', 'telehealth', 'telehealth123', 'telehealth');
$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];
$user_role = $_SESSION['user_role'];

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

// Get clinical history
if ($consultation_id) {
    $clinical = $conn->query("SELECT * FROM clinical_history WHERE id = $consultation_id ORDER BY created_at DESC LIMIT 1")->fetch_assoc();
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
        // Logo
        $logoPath = __DIR__ . '/images/Logo Ladera ESE.png';
        if (file_exists($logoPath)) {
            $this->Image($logoPath, 15, 10, 30, '', 'PNG', '', 'T', false, 300, '', false, false, 0, false, false, false);
        }
        
        // Institution info
        $this->SetFont('helvetica', 'B', 12);
        $this->Cell(0, 8, 'RED DE SALUD LADERA ESE', 0, 1, 'C');
        $this->SetFont('helvetica', '', 9);
        $this->Cell(0, 5, 'NIT: 900.373.695-9', 0, 1, 'C');
        $this->Cell(0, 5, 'Cra 28 No. 08-08 B/ Antonio Nariño, Cali', 0, 1, 'C');
        $this->Cell(0, 5, 'Tel: 556-2282 | Email: info@redladera.gov.co', 0, 1, 'C');
        $this->Ln(5);
        
        // Title
        $this->SetFont('helvetica', 'B', 14);
        $this->SetFillColor(52, 152, 219);
        $this->SetTextColor(255, 255, 255);
        $this->Cell(0, 10, 'HISTORIA CLÍNICA INTEGRAL', 0, 1, 'C', true);
        $this->SetTextColor(0, 0, 0);
    }
    
    public function Footer() {
        $this->SetY(-15);
        $this->SetFont('helvetica', 'I', 8);
        $this->Cell(0, 10, 'Página ' . $this->getAliasNumPage() . '/' . $this->getAliasNbPages(), 0, 0, 'C');
        $this->Cell(0, 10, 'Documento confidencial - Historia Clínica', 0, 0, 'R');
    }
}

$pdf = new MYPDF('P', 'mm', 'A4', true, 'UTF-8');
$pdf->SetMargins(15, 50, 15);
$pdf->SetAutoPageBreak(true, 25);
$pdf->AddPage();

$pdf->SetFont('helvetica', 'B', 10);
$pdf->SetFillColor(52, 152, 219);
$pdf->SetTextColor(255, 255, 255);
$pdf->Cell(0, 7, '1. IDENTIFICACIÓN DEL PACIENTE', 0, 1, 'L', true);

$pdf->SetTextColor(0, 0, 0);
$pdf->SetFont('helvetica', '', 9);
$pdf->SetFillColor(240, 248, 255);

$rowHeight = 6;

// Patient data
$fullName = trim(($patient['first_name'] ?? '') . ' ' . ($patient['second_name'] ?? '') . ' ' . ($patient['last_name'] ?? '') . ' ' . ($patient['second_last_name'] ?? ''));
$pdf->Cell(60, $rowHeight, 'Apellidos y Nombres:', 1, 0, 'L', true);
$pdf->Cell(130, $rowHeight, strtoupper($fullName), 1, 1, 'L');

$pdf->Cell(60, $rowHeight, 'Tipo de Identificación:', 1, 0, 'L', true);
$pdf->Cell(130, $rowHeight, ($patient['id_type'] ?? 'N/A') . ' - ' . ($patient['id_number'] ?? 'N/A'), 1, 1, 'L');

$pdf->Cell(60, $rowHeight, 'Fecha de Nacimiento:', 1, 0, 'L', true);
$pdf->Cell(130, $rowHeight, isset($patient['birth_date']) ? date('d/m/Y', strtotime($patient['birth_date'])) . ' (' . $age . ' años)' : 'N/A', 1, 1, 'L');

$pdf->Cell(60, $rowHeight, 'Sexo:', 1, 0, 'L', true);
$gender = ($patient['gender'] ?? '') === 'M' ? 'MASCULINO' : (($patient['gender'] ?? '') === 'F' ? 'FEMENINO' : 'OTRO');
$pdf->Cell(130, $rowHeight, $gender, 1, 1, 'L');

$pdf->Cell(60, $rowHeight, 'Estado Civil:', 1, 0, 'L', true);
$pdf->Cell(130, $rowHeight, ($patient['civil_status'] ?? 'N/A'), 1, 1, 'L');

$pdf->Cell(60, $rowHeight, 'Dirección:', 1, 0, 'L', true);
$pdf->Cell(130, $rowHeight, ($patient['address'] ?? 'N/A') . ', ' . ($patient['city'] ?? 'N/A'), 1, 1, 'L');

$pdf->Cell(60, $rowHeight, 'Teléfono Fijo:', 1, 0, 'L', true);
$pdf->Cell(130, $rowHeight, ($patient['phone'] ?? 'N/A'), 1, 1, 'L');

$pdf->Cell(60, $rowHeight, 'Teléfono Celular:', 1, 0, 'L', true);
$pdf->Cell(130, $rowHeight, ($patient['mobile'] ?? 'N/A'), 1, 1, 'L');

$pdf->Cell(60, $rowHeight, 'EPS:', 1, 0, 'L', true);
$pdf->Cell(130, $rowHeight, ($patient['eps_name'] ?? 'NINGUNA'), 1, 1, 'L');

$pdf->Cell(60, $rowHeight, 'Ocupación:', 1, 0, 'L', true);
$pdf->Cell(130, $rowHeight, ($patient['occupation'] ?? 'N/A'), 1, 1, 'L');

$pdf->Cell(60, $rowHeight, 'Responsable:', 1, 0, 'L', true);
$pdf->Cell(130, $rowHeight, ($patient['responsible_name'] ?? 'N/A'), 1, 1, 'L');

$pdf->Cell(60, $rowHeight, 'Teléfono Responsable:', 1, 0, 'L', true);
$pdf->Cell(130, $rowHeight, ($patient['responsible_phone'] ?? 'N/A'), 1, 1, 'L');

$pdf->Ln(5);

// Section 2: Clinical History
if ($clinical) {
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->SetFillColor(52, 152, 219);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->Cell(0, 7, '2. CONSULTA MÉDICA', 0, 1, 'L', true);
    
    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetFont('helvetica', '', 9);
    
    $pdf->Cell(60, $rowHeight, 'Fecha de Consulta:', 1, 0, 'L', true);
    $pdf->Cell(130, $rowHeight, isset($clinical['consultation_date']) ? date('d/m/Y H:i', strtotime($clinical['consultation_date'])) : 'N/A', 1, 1, 'L');
    
    $pdf->Cell(60, $rowHeight, 'Tipo de Consulta:', 1, 0, 'L', true);
    $pdf->Cell(130, $rowHeight, ($clinical['consultation_type'] ?? 'N/A'), 1, 1, 'L');
    
    $pdf->Cell(60, $rowHeight, 'Médico Tratante:', 1, 0, 'L', true);
    $pdf->Cell(130, $rowHeight, $user_name, 1, 1, 'L');
    
    $pdf->Ln(3);
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->Cell(0, 6, 'Motivo de Consulta:', 0, 1, 'L');
    $pdf->SetFont('helvetica', '', 9);
    $pdf->MultiCell(0, $rowHeight, ($clinical['reason_consultation'] ?? 'N/A'), 1, 'L');
    
    if (!empty($clinical['current_illness'])) {
        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->Cell(0, 6, 'Historia de la Enfermedad Actual:', 0, 1, 'L');
        $pdf->SetFont('helvetica', '', 9);
        $pdf->MultiCell(0, $rowHeight, $clinical['current_illness'], 1, 'L');
    }
    
    if (!empty($clinical['illness_evolution'])) {
        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->Cell(0, 6, 'Evolución:', 0, 1, 'L');
        $pdf->SetFont('helvetica', '', 9);
        $pdf->MultiCell(0, $rowHeight, $clinical['illness_evolution'], 1, 'L');
    }
    
    $pdf->Ln(3);
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->SetFillColor(52, 152, 219);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->Cell(0, 7, '2.1 Signos Vitales y Medidas Antropométricas', 0, 1, 'L', true);
    
    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetFont('helvetica', '', 9);
    
    $vital = json_decode($clinical['vital_signs'] ?? '{}', true);
    
    $pdf->Cell(45, $rowHeight, 'Presión Arterial:', 1, 0, 'L', true);
    $pdf->Cell(45, $rowHeight, ($vital['systolic_bp'] ?? '-') . '/' . ($vital['diastolic_bp'] ?? '-') . ' mmHg', 1, 0, 'L');
    $pdf->Cell(50, $rowHeight, 'Frecuencia Cardiaca:', 1, 0, 'L', true);
    $pdf->Cell(50, $rowHeight, ($vital['heart_rate'] ?? '-') . ' lpm', 1, 1, 'L');
    
    $pdf->Cell(45, $rowHeight, 'Temperatura:', 1, 0, 'L', true);
    $pdf->Cell(45, $rowHeight, ($vital['temperature'] ?? '-') . ' °C', 1, 0, 'L');
    $pdf->Cell(50, $rowHeight, 'Frecuencia Respiratoria:', 1, 0, 'L', true);
    $pdf->Cell(50, $rowHeight, ($vital['respiratory_rate'] ?? '-') . ' rpm', 1, 1, 'L');
    
    $pdf->Cell(45, $rowHeight, 'Saturación O2:', 1, 0, 'L', true);
    $pdf->Cell(45, $rowHeight, ($vital['oxygen_saturation'] ?? '-') . '%', 1, 0, 'L');
    $pdf->Cell(50, $rowHeight, 'Peso:', 1, 0, 'L', true);
    $pdf->Cell(50, $rowHeight, ($vital['weight'] ?? '-') . ' kg', 1, 1, 'L');
    
    $pdf->Cell(45, $rowHeight, 'Talla:', 1, 0, 'L', true);
    $pdf->Cell(45, $rowHeight, ($vital['height'] ?? '-') . ' cm', 1, 0, 'L');
    $pdf->Cell(50, $rowHeight, 'IMC:', 1, 0, 'L', true);
    $imc = isset($vital['weight']) && isset($vital['height']) && $vital['height'] > 0 
        ? round($vital['weight'] / (($vital['height']/100) ** 2), 1) 
        : '-';
    $pdf->Cell(50, $rowHeight, $imc, 1, 1, 'L');
    
    if (!empty($clinical['physical_exam'])) {
        $pdf->Ln(3);
        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->Cell(0, 6, 'Examen Físico:', 0, 1, 'L');
        $pdf->SetFont('helvetica', '', 9);
        $pdf->MultiCell(0, $rowHeight, $clinical['physical_exam'], 1, 'L');
    }
    
    $pdf->Ln(3);
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->SetFillColor(52, 152, 219);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->Cell(0, 7, '2.2 Diagnóstico', 0, 1, 'L', true);
    
    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetFont('helvetica', '', 9);
    
    $diagnoses = json_decode($clinical['diagnoses'] ?? '[]', true);
    if ($diagnoses && count($diagnoses) > 0) {
        foreach ($diagnoses as $diag) {
            if (!empty($diag['code']) || !empty($diag['name'])) {
                $pdf->Cell(50, $rowHeight, 'Código CIE-10:', 1, 0, 'L', true);
                $pdf->Cell(140, $rowHeight, ($diag['code'] ?? 'N/A') . ' - ' . ($diag['name'] ?? 'N/A'), 1, 1, 'L');
            }
        }
    } else {
        $pdf->Cell(190, $rowHeight, 'Sin diagnóstico registrado', 1, 1, 'L');
    }
    
    if (!empty($clinical['management_plan'])) {
        $pdf->Ln(3);
        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->Cell(0, 6, 'Plan de Manejo:', 0, 1, 'L');
        $pdf->SetFont('helvetica', '', 9);
        $pdf->MultiCell(0, $rowHeight, $clinical['management_plan'], 1, 'L');
    }
    
    // Prescriptions
    $prescriptions = json_decode($clinical['prescriptions'] ?? '[]', true);
    if ($prescriptions && count($prescriptions) > 0) {
        $pdf->Ln(3);
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->SetFillColor(52, 152, 219);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->Cell(0, 7, '2.3 Prescripción de Medicamentos', 0, 1, 'L', true);
        
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetFont('helvetica', '', 9);
        
        $pdf->Cell(60, $rowHeight, 'Medicamento', 1, 0, 'L', true);
        $pdf->Cell(40, $rowHeight, 'Dosis', 1, 0, 'L', true);
        $pdf->Cell(45, $rowHeight, 'Frecuencia', 1, 0, 'L', true);
        $pdf->Cell(45, $rowHeight, 'Duración', 1, 1, 'L', true);
        
        foreach ($prescriptions as $med) {
            $pdf->Cell(60, $rowHeight, ($med['name'] ?? ''), 1, 0, 'L');
            $pdf->Cell(40, $rowHeight, ($med['dose'] ?? ''), 1, 0, 'L');
            $pdf->Cell(45, $rowHeight, ($med['frequency'] ?? ''), 1, 0, 'L');
            $pdf->Cell(45, $rowHeight, ($med['duration'] ?? ''), 1, 1, 'L');
        }
    }
    
    if (!empty($clinical['recommendations'])) {
        $pdf->Ln(3);
        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->Cell(0, 6, 'Recomendaciones:', 0, 1, 'L');
        $pdf->SetFont('helvetica', '', 9);
        $pdf->MultiCell(0, $rowHeight, $clinical['recommendations'], 1, 'L');
    }
}

// Section 3: Personal History
$pdf->AddPage();
$pdf->SetFont('helvetica', 'B', 10);
$pdf->SetFillColor(52, 152, 219);
$pdf->SetTextColor(255, 255, 255);
$pdf->Cell(0, 7, '3. ANTECEDENTES', 0, 1, 'L', true);

$pdf->SetTextColor(0, 0, 0);
$pdf->SetFont('helvetica', '', 9);

if ($personal_history) {
    // Pathological
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->Cell(0, 6, '3.1 Antecedentes Patológicos', 0, 1, 'L');
    $pdf->SetFont('helvetica', '', 9);
    
    $pdf->Cell(60, $rowHeight, 'Enfermedades:', 1, 0, 'L', true);
    $pdf->Cell(130, $rowHeight, ($personal_history['pathological_enfermedades'] ?: 'Sin antecedentes patológicos'), 1, 1, 'L');
    
    $pdf->Cell(60, $rowHeight, 'Alergias:', 1, 0, 'L', true);
    $pdf->Cell(130, $rowHeight, ($personal_history['pathological_alergias'] ?: 'Ninguna'), 1, 1, 'L');
    
    $pdf->Cell(60, $rowHeight, 'Cirugías:', 1, 0, 'L', true);
    $pdf->Cell(130, $rowHeight, ($personal_history['pathological_cirugias'] ?: 'Ninguna'), 1, 1, 'L');
    
    $pdf->Cell(60, $rowHeight, 'Traumatismos:', 1, 0, 'L', true);
    $pdf->Cell(130, $rowHeight, ($personal_history['pathological_traumatismos'] ?: 'Ninguno'), 1, 1, 'L');
    
    $pdf->Ln(3);
    
    // Family History
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->Cell(0, 6, '3.2 Antecedentes Familiares', 0, 1, 'L');
    $pdf->SetFont('helvetica', '', 9);
    
    $pdf->Cell(63, $rowHeight, 'Diabetes: ' . ($personal_history['family_diabetes'] ?? 'NS'), 1, 0, 'L');
    $pdf->Cell(63, $rowHeight, 'Hipertensión: ' . ($personal_history['family_hypertension'] ?? 'NS'), 1, 0, 'L');
    $pdf->Cell(64, $rowHeight, 'Enf. Cardíaca: ' . ($personal_history['family_heart_disease'] ?? 'NS'), 1, 1, 'L');
    
    $pdf->Cell(60, $rowHeight, 'Cáncer familiar:', 1, 0, 'L', true);
    $pdf->Cell(130, $rowHeight, ($personal_history['family_cancer'] ?: 'Ninguno'), 1, 1, 'L');
    
    $pdf->Ln(3);
    
    // Non Pathological
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->Cell(0, 6, '3.3 Antecedentes Personales no Patológicos', 0, 1, 'L');
    $pdf->SetFont('helvetica', '', 9);
    
    $pdf->Cell(63, $rowHeight, 'Tabaquismo: ' . ($personal_history['non_pathological_smoking'] ?? 'No'), 1, 0, 'L');
    $pdf->Cell(63, $rowHeight, 'Alcohol: ' . ($personal_history['non_pathological_alcohol'] ?? 'No'), 1, 0, 'L');
    $pdf->Cell(64, $rowHeight, 'Ejercicio: ' . ($personal_history['non_pathological_exercise'] ?? 'No'), 1, 1, 'L');
} else {
    $pdf->Cell(190, $rowHeight, 'No hay antecedentes registrados', 1, 1, 'L');
}

$pdf->Ln(10);

// Signature
$pdf->SetFont('helvetica', 'B', 10);
$pdf->SetFillColor(52, 152, 219);
$pdf->SetTextColor(255, 255, 255);
$pdf->Cell(0, 7, '4. FIRMAS', 0, 1, 'L', true);

$pdf->SetTextColor(0, 0, 0);
$pdf->SetFont('helvetica', '', 9);

$pdf->Cell(95, 20, '', 1, 0, 'L');
$pdf->Cell(95, 20, '', 1, 1, 'L');
$pdf->Cell(95, $rowHeight, 'Firma del Paciente o Responsable', 1, 0, 'L', true);
$pdf->Cell(95, $rowHeight, 'Firma y Sello del Médico', 1, 1, 'L', true);

$pdf->Cell(95, $rowHeight, 'Nombre: ' . ($patient['first_name'] ?? ''), 1, 0, 'L');
$pdf->Cell(95, $rowHeight, 'Nombre: ' . $user_name, 1, 1, 'L');

$pdf->Cell(95, $rowHeight, 'C.C.: ' . ($patient['id_number'] ?? ''), 1, 0, 'L');
$pdf->Cell(95, $rowHeight, 'RM/VS: ', 1, 1, 'L');

$pdf->Ln(10);

// Legal text
$pdf->SetFont('helvetica', 'I', 7);
$pdf->MultiCell(0, 4, 'Este documento hace parte integral de la Historia Clínica según la Resolución 1995 de 1999 del Ministerio de Protección Social de Colombia y la Ley 23 de 1981. Es confidencial y de uso exclusivo de los profesionales de la salud y el paciente. La reproducción o copia sin autorización está prohibida.', 0, 'L');

ob_end_clean();
$pdf->Output('Historia_Clinica_' . ($patient['id_number'] ?? '') . '_' . date('Ymd') . '.pdf', 'I');

$conn->close();

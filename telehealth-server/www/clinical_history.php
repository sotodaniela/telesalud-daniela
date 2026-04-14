<?php
session_start();

if (!isset($_SESSION['user_id']) && !isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$conn = new mysqli('telehealth-db', 'telehealth', 'telehealth123', 'telehealth');
$doctor_id = $_SESSION['user_id'] ?? $_SESSION['user_id'] ?? null;
$doctor_name = $_SESSION['user_name'] ?? $_SESSION['doctor_name'] ?? '';
$message = '';

// Get patient ID
$patient_id = isset($_GET['patient_id']) ? intval($_GET['patient_id']) : 0;
if (!$patient_id) {
    header('Location: patients.php');
    exit;
}

// Get patient info
$patient = $conn->query("SELECT * FROM patients WHERE id=$patient_id AND is_active=1")->fetch_assoc();
if (!$patient) {
    header('Location: patients.php');
    exit;
}

// Get clinical histories
$histories = $conn->query("SELECT ch.*, d.full_name as doctor_name 
    FROM clinical_history ch 
    LEFT JOIN doctors d ON ch.doctor_id = d.id 
    WHERE ch.patient_id=$patient_id 
    ORDER BY ch.consultation_date DESC");

// Get personal history
$personal_history = $conn->query("SELECT * FROM personal_history WHERE patient_id=$patient_id ORDER BY updated_at DESC LIMIT 1")->fetch_assoc();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($_POST['action'] === 'save_clinical_history') {
        $consultation_type = $conn->real_escape_string($_POST['consultation_type']);
        $reason_consultation = $conn->real_escape_string($_POST['reason_consultation']);
        $current_illness = $conn->real_escape_string($_POST['current_illness']);
        $illness_evolution = $conn->real_escape_string($_POST['illness_evolution']);
        $physical_exam = $conn->real_escape_string($_POST['physical_exam']);
        $management_plan = $conn->real_escape_string($_POST['management_plan']);
        $recommendations = $conn->real_escape_string($_POST['recommendations']);
        
        // Vital signs
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
        
        // Diagnoses
        $diagnoses = json_encode([
            ['code' => $_POST['diagnosis_code'] ?? '', 'name' => $_POST['diagnosis_name'] ?? '', 'type' => $_POST['diagnosis_type'] ?? 'Principal']
        ]);
        
        // Prescriptions
        $prescriptions = json_encode([]);
        if (!empty($_POST['medicine_name'])) {
            $medicines = [];
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
            $prescriptions = json_encode($medicines);
        }
        
        $sql = "INSERT INTO clinical_history 
            (patient_id, doctor_id, consultation_date, consultation_type, reason_consultation, current_illness, 
            illness_evolution, vital_signs, diagnoses, prescriptions, physical_exam, management_plan, recommendations)
            VALUES ($patient_id, $doctor_id, NOW(), '$consultation_type', '$reason_consultation', '$current_illness',
            '$illness_evolution', '$vital_signs', '$diagnoses', '$prescriptions', '$physical_exam', '$management_plan', '$recommendations')";
        
        $conn->query($sql);
        $message = 'Historia clínica creada exitosamente';
        header("Location: clinical_history.php?patient_id=$patient_id&success=1");
        exit;
    }
    
    if ($_POST['action'] === 'save_personal_history') {
        $pathological_enfermedades = $conn->real_escape_string($_POST['pathological_enfermedades'] ?? '');
        $pathological_cirugias = $conn->real_escape_string($_POST['pathological_cirugias'] ?? '');
        $pathological_traumatismos = $conn->real_escape_string($_POST['pathological_traumatismos'] ?? '');
        $pathological_alergias = $conn->real_escape_string($_POST['pathological_alergias'] ?? '');
        $family_diabetes = $conn->real_escape_string($_POST['family_diabetes'] ?? 'NS');
        $family_hypertension = $conn->real_escape_string($_POST['family_hypertension'] ?? 'NS');
        $family_heart_disease = $conn->real_escape_string($_POST['family_heart_disease'] ?? 'NS');
        $family_cancer = $conn->real_escape_string($_POST['family_cancer'] ?? '');
        $non_pathological_smoking = $conn->real_escape_string($_POST['non_pathological_smoking'] ?? 'No');
        $non_pathological_alcohol = $conn->real_escape_string($_POST['non_pathological_alcohol'] ?? 'No');
        $non_pathological_exercise = $conn->real_escape_string($_POST['non_pathological_exercise'] ?? 'No');
        
        if ($personal_history) {
            $sql = "UPDATE personal_history SET 
                pathological_enfermedades='$pathological_enfermedades',
                pathological_cirugias='$pathological_cirugias',
                pathological_traumatismos='$pathological_traumatismos',
                pathological_alergias='$pathological_alergias',
                family_diabetes='$family_diabetes',
                family_hypertension='$family_hypertension',
                family_heart_disease='$family_heart_disease',
                family_cancer='$family_cancer',
                non_pathological_smoking='$non_pathological_smoking',
                non_pathological_alcohol='$non_pathological_alcohol',
                non_pathological_exercise='$non_pathological_exercise',
                update_doctor_id=$doctor_id
                WHERE patient_id=$patient_id";
        } else {
            $sql = "INSERT INTO personal_history 
                (patient_id, doctor_id, pathological_enfermedades, pathological_cirugias, pathological_traumatismos,
                pathological_alergias, family_diabetes, family_hypertension, family_heart_disease, family_cancer,
                non_pathological_smoking, non_pathological_alcohol, non_pathological_exercise)
                VALUES ($patient_id, $doctor_id, '$pathological_enfermedades', '$pathological_cirugias', '$pathological_traumatismos',
                '$pathological_alergias', '$family_diabetes', '$family_hypertension', '$family_heart_disease', '$family_cancer',
                '$non_pathological_smoking', '$non_pathological_alcohol', '$non_pathological_exercise')";
        }
        
        $conn->query($sql);
        $message = 'Antecedentes actualizados exitosamente';
        header("Location: clinical_history.php?patient_id=$patient_id&updated=1");
        exit;
    }
}

// Refresh personal history
$personal_history = $conn->query("SELECT * FROM personal_history WHERE patient_id=$patient_id ORDER BY updated_at DESC LIMIT 1")->fetch_assoc();

// Calculate age
$birth_date = new DateTime($patient['birth_date']);
$today = new DateTime('today');
$age = $birth_date->diff($today)->y;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historia Clínica - <?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']); ?></title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Segoe UI', Tahoma, sans-serif;
            background: #f5f5f5;
        }
        .header {
            background: linear-gradient(135deg, #3498db, #2980b9);
            color: white;
            padding: 15px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .header-content {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .header img {
            height: 40px;
            width: auto;
            border-radius: 5px;
        }
        .header h1 { font-size: 18px; }
        .nav {
            display: flex;
            gap: 10px;
        }
        .nav a {
            color: white;
            text-decoration: none;
            padding: 8px 15px;
            border-radius: 5px;
            background: rgba(255,255,255,0.1);
            transition: background 0.3s;
        }
        .nav a:hover { background: rgba(255,255,255,0.3); }
        .logout { background: rgba(255,255,255,0.2) !important; }
        
        .container {
            max-width: 1400px;
            margin: 20px auto;
            padding: 0 20px;
        }
        
        .patient-header {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .patient-info h2 {
            color: #333;
            margin-bottom: 5px;
        }
        .patient-info p {
            color: #666;
            font-size: 14px;
        }
        .patient-badge {
            background: #e3f2fd;
            color: #1565c0;
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 14px;
        }
        
        .btn {
            background: #28a745;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            transition: background 0.3s;
        }
        .btn:hover { background: #218838; }
        .btn-primary { background: #0066cc; }
        .btn-primary:hover { background: #0052a3; }
        .btn-secondary { background: #6c757d; }
        
        .message {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .tabs {
            display: flex;
            gap: 5px;
            margin-bottom: 20px;
        }
        .tab {
            padding: 12px 25px;
            background: white;
            border: none;
            border-radius: 8px 8px 0 0;
            cursor: pointer;
            color: #666;
            font-size: 14px;
            transition: all 0.3s;
        }
        .tab.active {
            background: #0066cc;
            color: white;
        }
        .tab:hover:not(.active) {
            background: #e3f2fd;
        }
        
        .card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .card h3 {
            color: #0066cc;
            margin-bottom: 15px;
            font-size: 16px;
            border-bottom: 2px solid #e3f2fd;
            padding-bottom: 10px;
        }
        .card h3::before {
            content: "▶ ";
            font-size: 12px;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 15px;
        }
        .form-grid-3 {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
        }
        .form-grid-2 {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group.full-width {
            grid-column: span 2;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            color: #555;
            font-weight: 500;
            font-size: 13px;
        }
        .form-group input, .form-group select, .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
        }
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus {
            outline: none;
            border-color: #0066cc;
        }
        
        .form-section {
            background: #f9f9f9;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .vital-signs {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 15px;
        }
        .vital-item {
            background: white;
            padding: 15px;
            border-radius: 8px;
            text-align: center;
            border: 1px solid #e3f2fd;
        }
        .vital-item label {
            display: block;
            color: #666;
            font-size: 12px;
            margin-bottom: 5px;
        }
        .vital-item input {
            width: 100%;
            text-align: center;
            font-size: 18px;
            font-weight: bold;
            color: #0066cc;
            border: none;
            border-bottom: 2px solid #0066cc;
            background: transparent;
        }
        
        .medicine-row {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr 1fr 1fr auto;
            gap: 10px;
            margin-bottom: 10px;
            align-items: center;
        }
        .btn-remove {
            background: #dc3545;
            color: white;
            border: none;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            cursor: pointer;
        }
        
        .history-list {
            border-left: 3px solid #0066cc;
            padding-left: 20px;
        }
        .history-item {
            background: #f9f9f9;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 15px;
        }
        .history-item h4 {
            color: #0066cc;
            margin-bottom: 10px;
        }
        .history-item p {
            color: #666;
            font-size: 14px;
            margin-bottom: 5px;
        }
        
        .antecedent-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
        }
        .antecedent-item {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .antecedent-item select {
            width: 80px;
        }
        
        .signature-section {
            background: #f5f5f5;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
            margin-top: 20px;
        }
        .signature-section p {
            margin: 5px 0;
            color: #666;
        }
        
        .form-actions {
            margin-top: 20px;
            display: flex;
            gap: 10px;
        }
        
        @media print {
            .header, .tabs, .btn, .form-actions, .nav { display: none !important; }
            body { background: white; }
            .card { box-shadow: none; border: 1px solid #ddd; }
        }
    </style>
</head>
<body>
    <div class="header">
        <div style="display: flex; align-items: center; gap: 15px;">
            <img src="images/Logo Ladera ESE.png" alt="Logo" style="height: 40px; width: auto; border-radius: 5px;">
            <div>
                <h1>Red de Salud Ladera ESE</h1>
                <span style="font-size: 11px; opacity: 0.8;">NIT: 900.373.695-9 | Cra 28 No. 08-08 B/ Antonio Nariño, Cali | Tel: 556-2282</span>
            </div>
        </div>
        <div class="nav">
            <a href="dashboard.php">Dashboard</a>
            <a href="schedule.php">Agendamiento</a>
            <a href="patients.php">Pacientes</a>
            <a href="logout.php" class="logout">Cerrar Sesión</a>
        </div>
    </div>
    
    <div class="container">
        <?php if (isset($_GET['success'])): ?>
        <div class="message">✓ Historia clínica creada exitosamente</div>
        <?php endif; ?>
        <?php if (isset($_GET['updated'])): ?>
        <div class="message">✓ Antecedentes actualizados exitosamente</div>
        <?php endif; ?>
        
        <div class="patient-header">
            <div class="patient-info">
                <h2><?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['second_name'] . ' ' . $patient['last_name'] . ' ' . $patient['second_last_name']); ?></h2>
                <p><strong><?php echo $patient['id_type']; ?></strong> <?php echo htmlspecialchars($patient['id_number']); ?> | <?php echo $age; ?> años | <?php echo $patient['gender'] === 'M' ? 'Masculino' : 'Femenino'; ?></p>
                <p><?php echo htmlspecialchars($patient['address'] . ', ' . $patient['city']); ?> | <?php echo htmlspecialchars($patient['mobile'] ?: $patient['phone']); ?></p>
                <p><strong>EPS:</strong> <?php echo htmlspecialchars($patient['eps_name'] ?: 'No registrada'); ?></p>
            </div>
            <div class="patient-badge">
                <?php echo $histories->num_rows; ?> Consultas registradas
            </div>
        </div>
        
        <div class="tabs">
            <button class="tab active" onclick="showTab('new-history')">+ Nueva Consulta</button>
            <button class="tab" onclick="showTab('antecedents')">Antecedentes</button>
            <button class="tab" onclick="showTab('history-list')">Historial</button>
        </div>
        
        <!-- Nueva Historia Clínica -->
        <div id="new-history" class="tab-content">
            <form method="POST" action="">
                <input type="hidden" name="action" value="save_clinical_history">
                
                <div class="card">
                    <h3>Datos de la Consulta</h3>
                    <div class="form-grid-3">
                        <div class="form-group">
                            <label>Tipo de Consulta</label>
                            <select name="consultation_type">
                                <option value="Primera Vez">Primera Vez</option>
                                <option value="Control">Control</option>
                                <option value="Urgencia">Urgencia</option>
                                <option value="Telemedicina">Telemedicina</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Fecha y Hora</label>
                            <input type="text" value="<?php echo date('d/m/Y H:i'); ?>" readonly style="background: #f5f5f5;">
                        </div>
                        <div class="form-group">
                            <label>Médico</label>
                            <input type="text" value="<?php echo htmlspecialchars($doctor_name); ?>" readonly style="background: #f5f5f5;">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Motivo de Consulta *</label>
                        <textarea name="reason_consultation" rows="2" required placeholder="描述患者就诊的主要原因..."></textarea>
                    </div>
                </div>
                
                <div class="card">
                    <h3>Signos Vitales</h3>
                    <div class="vital-signs">
                        <div class="vital-item">
                            <label>Presión Sistólica (mmHg)</label>
                            <input type="number" name="systolic_bp" placeholder="120">
                        </div>
                        <div class="vital-item">
                            <label>Presión Diastólica (mmHg)</label>
                            <input type="number" name="diastolic_bp" placeholder="80">
                        </div>
                        <div class="vital-item">
                            <label>Frecuencia Cardíaca (lpm)</label>
                            <input type="number" name="heart_rate" placeholder="72">
                        </div>
                        <div class="vital-item">
                            <label>Temperatura (°C)</label>
                            <input type="number" step="0.1" name="temperature" placeholder="36.5">
                        </div>
                        <div class="vital-item">
                            <label>Frecuencia Respiratoria</label>
                            <input type="number" name="respiratory_rate" placeholder="18">
                        </div>
                        <div class="vital-item">
                            <label>Saturación O2 (%)</label>
                            <input type="number" name="oxygen_saturation" placeholder="98">
                        </div>
                        <div class="vital-item">
                            <label>Peso (kg)</label>
                            <input type="number" step="0.1" name="weight" placeholder="70">
                        </div>
                        <div class="vital-item">
                            <label>Altura (cm)</label>
                            <input type="number" name="height" placeholder="170">
                        </div>
                    </div>
                </div>
                
                <div class="card">
                    <h3>Historia de la Enfermedad</h3>
                    <div class="form-group">
                        <label>Enfermedad Actual</label>
                        <textarea name="current_illness" rows="3" placeholder="描述患者描述的症状和情况..."></textarea>
                    </div>
                    <div class="form-group">
                        <label>Evolución</label>
                        <textarea name="illness_evolution" rows="3" placeholder="描述症状如何发展和变化..."></textarea>
                    </div>
                </div>
                
                <div class="card">
                    <h3>Examen Físico</h3>
                    <textarea name="physical_exam" rows="4" placeholder="描述体格检查的结果..."></textarea>
                </div>
                
                <div class="card">
                    <h3>Diagnóstico (CIE-10)</h3>
                    <div class="form-grid-3">
                        <div class="form-group">
                            <label>Código CIE-10</label>
                            <input type="text" name="diagnosis_code" placeholder="J06.9">
                        </div>
                        <div class="form-group full-width">
                            <label>Nombre del Diagnóstico</label>
                            <input type="text" name="diagnosis_name" placeholder="Infección respiratoria aguda no especificada">
                        </div>
                    </div>
                </div>
                
                <div class="card">
                    <h3>Prescripción de Medicamentos</h3>
                    <div id="medicines-container">
                        <div class="medicine-row">
                            <input type="text" name="medicine_name[]" placeholder="Nombre del medicamento">
                            <input type="text" name="medicine_dose[]" placeholder="Dosis (ej: 500mg)">
                            <input type="text" name="medicine_frequency[]" placeholder="Frecuencia (ej: c/8h)">
                            <input type="text" name="medicine_duration[]" placeholder="Duración (ej: 7 días)">
                            <select name="medicine_route[]">
                                <option value="Oral">Oral</option>
                                <option value="Intravenoso">Intravenoso</option>
                                <option value="Intramuscular">Intramuscular</option>
                                <option value="Tópico">Tópico</option>
                                <option value="Inhalatorio">Inhalatorio</option>
                            </select>
                            <button type="button" class="btn-remove" onclick="removeMedicine(this)">×</button>
                        </div>
                    </div>
                    <button type="button" class="btn btn-secondary" onclick="addMedicine()" style="margin-top: 10px;">+ Agregar Medicamento</button>
                </div>
                
                <div class="card">
                    <h3>Plan de Manejo</h3>
                    <textarea name="management_plan" rows="4" placeholder="描述治疗计划和后续步骤..."></textarea>
                </div>
                
                <div class="card">
                    <h3>Recomendaciones</h3>
                    <textarea name="recommendations" rows="3" placeholder="给患者的建议和指示..."></textarea>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Guardar Historia Clínica</button>
                    <button type="reset" class="btn btn-secondary">Limpiar Formulario</button>
                </div>
                
                <div class="signature-section">
                    <p><strong>Médico Tratante:</strong> <?php echo htmlspecialchars($doctor_name); ?></p>
                    <p><strong>Fecha:</strong> <?php echo date('d/m/Y H:i'); ?></p>
                    <p style="font-size: 12px; color: #999;">Este documento hace parte integral de la Historia Clínica según Resolución 1995 de 1999 y Ley 23 de 1981</p>
                </div>
            </form>
        </div>
        
        <!-- Antecedentes -->
        <div id="antecedents" class="tab-content" style="display: none;">
            <form method="POST" action="">
                <input type="hidden" name="action" value="save_personal_history">
                
                <div class="card">
                    <h3>Antecedentes Patológicos</h3>
                    <div class="form-group">
                        <label>Enfermedades</label>
                        <textarea name="pathological_enfermedades" rows="2" placeholder="列出已诊断的疾病..."><?php echo htmlspecialchars($personal_history['pathological_enfermedades'] ?? ''); ?></textarea>
                    </div>
                    <div class="form-grid-2">
                        <div class="form-group">
                            <label>Cirugías</label>
                            <textarea name="pathological_cirugias" rows="2"><?php echo htmlspecialchars($personal_history['pathological_cirugias'] ?? ''); ?></textarea>
                        </div>
                        <div class="form-group">
                            <label>Traumatismos</label>
                            <textarea name="pathological_traumatismos" rows="2"><?php echo htmlspecialchars($personal_history['pathological_traumatismos'] ?? ''); ?></textarea>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Alergias</label>
                        <textarea name="pathological_alergias" rows="2" placeholder="列出过敏原和反应..."><?php echo htmlspecialchars($personal_history['pathological_alergias'] ?? ''); ?></textarea>
                    </div>
                </div>
                
                <div class="card">
                    <h3>Antecedentes Familiares</h3>
                    <div class="antecedent-grid">
                        <div class="antecedent-item">
                            <label>Diabetes:</label>
                            <select name="family_diabetes">
                                <option value="NS" <?php echo ($personal_history['family_diabetes'] ?? 'NS') === 'NS' ? 'selected' : ''; ?>>NS</option>
                                <option value="Si" <?php echo ($personal_history['family_diabetes'] ?? '') === 'Si' ? 'selected' : ''; ?>>Sí</option>
                                <option value="No" <?php echo ($personal_history['family_diabetes'] ?? '') === 'No' ? 'selected' : ''; ?>>No</option>
                            </select>
                        </div>
                        <div class="antecedent-item">
                            <label>Hipertensión:</label>
                            <select name="family_hypertension">
                                <option value="NS" <?php echo ($personal_history['family_hypertension'] ?? 'NS') === 'NS' ? 'selected' : ''; ?>>NS</option>
                                <option value="Si" <?php echo ($personal_history['family_hypertension'] ?? '') === 'Si' ? 'selected' : ''; ?>>Sí</option>
                                <option value="No" <?php echo ($personal_history['family_hypertension'] ?? '') === 'No' ? 'selected' : ''; ?>>No</option>
                            </select>
                        </div>
                        <div class="antecedent-item">
                            <label>Enf. Cardíaca:</label>
                            <select name="family_heart_disease">
                                <option value="NS" <?php echo ($personal_history['family_heart_disease'] ?? 'NS') === 'NS' ? 'selected' : ''; ?>>NS</option>
                                <option value="Si" <?php echo ($personal_history['family_heart_disease'] ?? '') === 'Si' ? 'selected' : ''; ?>>Sí</option>
                                <option value="No" <?php echo ($personal_history['family_heart_disease'] ?? '') === 'No' ? 'selected' : ''; ?>>No</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group" style="margin-top: 15px;">
                        <label>Cáncer en la familia</label>
                        <input type="text" name="family_cancer" value="<?php echo htmlspecialchars($personal_history['family_cancer'] ?? ''); ?>" placeholder="Especifique tipo y familiar">
                    </div>
                </div>
                
                <div class="card">
                    <h3>Antecedentes No Patológicos</h3>
                    <div class="antecedent-grid">
                        <div class="antecedent-item">
                            <label>Tabaco:</label>
                            <select name="non_pathological_smoking">
                                <option value="No" <?php echo ($personal_history['non_pathological_smoking'] ?? 'No') === 'No' ? 'selected' : ''; ?>>No</option>
                                <option value="Si" <?php echo ($personal_history['non_pathological_smoking'] ?? '') === 'Si' ? 'selected' : ''; ?>>Sí</option>
                                <option value="Ocasional" <?php echo ($personal_history['non_pathological_smoking'] ?? '') === 'Ocasional' ? 'selected' : ''; ?>>Ocasional</option>
                            </select>
                        </div>
                        <div class="antecedent-item">
                            <label>Alcohol:</label>
                            <select name="non_pathological_alcohol">
                                <option value="No" <?php echo ($personal_history['non_pathological_alcohol'] ?? 'No') === 'No' ? 'selected' : ''; ?>>No</option>
                                <option value="Si" <?php echo ($personal_history['non_pathological_alcohol'] ?? '') === 'Si' ? 'selected' : ''; ?>>Sí</option>
                                <option value="Ocasional" <?php echo ($personal_history['non_pathological_alcohol'] ?? '') === 'Ocasional' ? 'selected' : ''; ?>>Ocasional</option>
                            </select>
                        </div>
                        <div class="antecedent-item">
                            <label>Ejercicio:</label>
                            <select name="non_pathological_exercise">
                                <option value="No" <?php echo ($personal_history['non_pathological_exercise'] ?? 'No') === 'No' ? 'selected' : ''; ?>>No</option>
                                <option value="Si" <?php echo ($personal_history['non_pathological_exercise'] ?? '') === 'Si' ? 'selected' : ''; ?>>Sí</option>
                                <option value="Ocasional" <?php echo ($personal_history['non_pathological_exercise'] ?? '') === 'Ocasional' ? 'selected' : ''; ?>>Ocasional</option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Guardar Antecedentes</button>
                </div>
            </form>
        </div>
        
        <!-- Historial de Consultas -->
        <div id="history-list" class="tab-content" style="display: none;">
            <div class="card">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                    <h3>Historial de Consultas</h3>
                    <a href="teleconsulta_pdf.php?id=<?php echo $patient_id; ?>" target="_blank" class="btn" style="background: #dc3545; text-decoration: none;">📄 Generar PDF</a>
                </div>
                <?php if ($histories->num_rows > 0): ?>
                <div class="history-list">
                    <?php while ($h = $histories->fetch_assoc()): ?>
                    <div class="history-item">
                        <h4><?php echo date('d/m/Y H:i', strtotime($h['consultation_date'])); ?> - <?php echo $h['consultation_type']; ?></h4>
                        <p><strong>Médico:</strong> <?php echo htmlspecialchars($h['doctor_name']); ?></p>
                        <p><strong>Motivo:</strong> <?php echo htmlspecialchars($h['reason_consultation']); ?></p>
                        <?php 
                        $vital = json_decode($h['vital_signs'] ?? '{}', true);
                        if ($vital): 
                        ?>
                        <p><strong>TA:</strong> <?php echo ($vital['systolic_bp'] ?? '-') . '/' . ($vital['diastolic_bp'] ?? '-'); ?> mmHg | 
                           <strong>FC:</strong> <?php echo ($vital['heart_rate'] ?? '-'); ?> lpm | 
                           <strong>T:</strong> <?php echo ($vital['temperature'] ?? '-'); ?> °C</p>
                        <?php endif; ?>
                        <?php if ($h['diagnoses']): ?>
                        <?php $diag = json_decode($h['diagnoses'], true); ?>
                        <?php if ($diag && isset($diag[0]['name'])): ?>
                        <p><strong>Diagnóstico:</strong> <?php echo htmlspecialchars($diag[0]['code'] . ' - ' . $diag[0]['name']); ?></p>
                        <?php endif; ?>
                        <?php endif; ?>
                        <a href="teleconsulta_pdf.php?id=<?php echo $patient_id; ?>&consultation_id=<?php echo $h['id']; ?>" target="_blank" class="btn" style="background: #dc3545; font-size: 12px; margin-top: 10px; text-decoration: none;">📄 PDF</a>
                    </div>
                    <?php endwhile; ?>
                </div>
                <?php else: ?>
                <p style="text-align: center; color: #999; padding: 30px;">No hay consultas registradas</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script>
        function showTab(tabId) {
            document.querySelectorAll('.tab-content').forEach(el => el.style.display = 'none');
            document.querySelectorAll('.tab').forEach(el => el.classList.remove('active'));
            document.getElementById(tabId).style.display = 'block';
            event.target.classList.add('active');
        }
        
        function addMedicine() {
            const container = document.getElementById('medicines-container');
            const row = document.createElement('div');
            row.className = 'medicine-row';
            row.innerHTML = `
                <input type="text" name="medicine_name[]" placeholder="Nombre del medicamento">
                <input type="text" name="medicine_dose[]" placeholder="Dosis">
                <input type="text" name="medicine_frequency[]" placeholder="Frecuencia">
                <input type="text" name="medicine_duration[]" placeholder="Duración">
                <select name="medicine_route[]">
                    <option value="Oral">Oral</option>
                    <option value="Intravenoso">Intravenoso</option>
                    <option value="Intramuscular">Intramuscular</option>
                    <option value="Tópico">Tópico</option>
                    <option value="Inhalatorio">Inhalatorio</option>
                </select>
                <button type="button" class="btn-remove" onclick="removeMedicine(this)">×</button>
            `;
            container.appendChild(row);
        }
        
        function removeMedicine(btn) {
            const container = document.getElementById('medicines-container');
            if (container.children.length > 1) {
                btn.parentElement.remove();
            }
        }
    </script>
</body>
</html>
<?php $conn->close(); ?>

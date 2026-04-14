<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$conn = new mysqli('telehealth-db', 'telehealth', 'telehealth123', 'telehealth');
$doctor_id = $_SESSION['user_id'];
$doctor_name = $_SESSION['user_name'] ?? 'Doctor';

$consultation_id = intval($_GET['id'] ?? 0);
$patient_id = intval($_GET['patient_id'] ?? 0);

if (!$consultation_id && !$patient_id) {
    header('Location: teleconsulta.php');
    exit;
}

$consultation = null;

if ($consultation_id) {
    $consultation = $conn->query("SELECT c.*, CONCAT(p.first_name, ' ', p.last_name) as patient_name, p.id_number, p.id_type, p.email, p.phone, p.mobile, p.birth_date, p.gender, p.address, p.city, p.eps_name, p.id as pid FROM consultations c JOIN patients p ON c.patient_id = p.id WHERE c.id = $consultation_id AND c.doctor_id = $doctor_id")->fetch_assoc();
    if ($consultation) {
        $patient_id = $consultation['pid'];
    }
}

$room_name = isset($consultation['room_name']) ? $consultation['room_name'] : 'room_' . time() . '_' . rand(1000, 9999);
$token = '';
$skip_video = isset($_GET['skip_video']);

if (!$consultation && $patient_id) {
    $consultation = $conn->query("SELECT p.*, CONCAT(p.first_name, ' ', p.last_name) as patient_name, p.id as pid FROM patients p WHERE p.id = $patient_id AND p.is_active = 1")->fetch_assoc();
}

if (!$consultation) {
    header('Location: teleconsulta.php');
    exit;
}

// Calculate age
$birth_date = new DateTime($consultation['birth_date']);
$today = new DateTime('today');
$age = $birth_date->diff($today)->y;

// Get personal history
$personal_history = $conn->query("SELECT * FROM personal_history WHERE patient_id = $patient_id ORDER BY updated_at DESC LIMIT 1")->fetch_assoc();

// Get previous consultations
$previous_history = $conn->query("SELECT * FROM clinical_history WHERE patient_id = $patient_id ORDER BY consultation_date DESC LIMIT 5");

// Generate LiveKit token if consultation exists
$token = '';
$room_name = 'room_' . time() . '_' . rand(1000, 9999);

if ($consultation_id && isset($consultation['room_name'])) {
    $room_name = $consultation['room_name'];
    $api_key = 'APInewKey123';
    $api_secret = 'NewSecret456789012345678901234567890';
    
    function base64url_encode($data) {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
    
    $header = json_encode(['alg' => 'HS256', 'typ' => 'JWT']);
    $payload = json_encode([
        'iss' => $api_key,
        'sub' => 'doctor_' . $_SESSION['user_id'],
        'room' => $room_name,
        'name' => $doctor_name,
        'exp' => time() + 3600,
        'video' => [
            'canPublish' => true,
            'canSubscribe' => true,
            'canPublishData' => true
        ]
    ]);
    
    $signature = hash_hmac('sha256', base64url_encode($header) . '.' . base64url_encode($payload), $api_secret, true);
    $token = base64url_encode($header) . '.' . base64url_encode($payload) . '.' . base64url_encode($signature);
}

// Handle form submission
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $consultation_type = $conn->real_escape_string($_POST['consultation_type']);
    $reason_consultation = $conn->real_escape_string($_POST['reason_consultation']);
    $current_illness = $conn->real_escape_string($_POST['current_illness']);
    $physical_exam = $conn->real_escape_string($_POST['physical_exam']);
    $diagnosis_code = $conn->real_escape_string($_POST['diagnosis_code']);
    $diagnosis_name = $conn->real_escape_string($_POST['diagnosis_name']);
    $management_plan = $conn->real_escape_string($_POST['management_plan']);
    $recommendations = $conn->real_escape_string($_POST['recommendations']);
    
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
        physical_exam, management_plan, recommendations, status)
        VALUES ($patient_id, $doctor_id, " . ($consultation_id ?: "NULL") . ", NOW(), '$consultation_type',
        '$reason_consultation', '$current_illness', '$vital_signs', '$diagnoses', '$prescriptions',
        '$physical_exam', '$management_plan', '$recommendations', 'signed')";
    
    if ($conn->query($sql)) {
        $clinical_history_id = $conn->insert_id;
        
        if ($consultation_id) {
            $conn->query("UPDATE consultations SET status = 'completed', clinical_history_id = $clinical_history_id WHERE id = $consultation_id");
        }
        
        $message = 'Historia clinica guardada exitosamente';
    }
}

// Refresh personal history
$personal_history = $conn->query("SELECT * FROM personal_history WHERE patient_id = $patient_id ORDER BY updated_at DESC LIMIT 1")->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teleconsulta - <?php echo htmlspecialchars($consultation['patient_name']); ?></title>
    <script src="https://cdn.jsdelivr.net/npm/livekit-client@2.5.5/+esm" type="module"></script>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Segoe UI', Tahoma, sans-serif;
            background: #f5f5f5;
            height: 100vh;
            overflow: hidden;
        }
        .header {
            background: #6f42c1;
            color: white;
            padding: 10px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .header h1 { font-size: 16px; }
        .header a {
            color: white;
            text-decoration: none;
            padding: 5px 10px;
            background: rgba(255,255,255,0.2);
            border-radius: 5px;
        }
        
        .main-container {
            display: grid;
            grid-template-columns: 1fr 400px;
            height: calc(100vh - 45px);
        }
        .video-section {
            background: #1a1a2e;
            position: relative;
        }
        .video-area {
            height: 100%;
            display: flex;
            flex-direction: column;
        }
        .video-grid {
            flex: 1;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            padding: 10px;
        }
        .participant {
            background: #16213e;
            border-radius: 10px;
            position: relative;
            overflow: hidden;
            min-height: 150px;
        }
        .participant video {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 10px;
        }
        .participant .name-tag {
            position: absolute;
            bottom: 10px;
            left: 10px;
            background: rgba(0,0,0,0.7);
            padding: 5px 10px;
            border-radius: 5px;
            color: white;
            font-size: 11px;
        }
        .controls {
            background: #16213e;
            padding: 10px;
            display: flex;
            justify-content: center;
            gap: 8px;
        }
        .control-btn {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            border: none;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0;
        }
        .control-btn.mute { background: #444; color: white; }
        .control-btn.mute.active { background: #dc3545; }
        .control-btn.video { background: #444; color: white; }
        .control-btn.video.active { background: #dc3545; }
        .control-btn.record { background: #444; color: white; }
        .control-btn.record.active { background: #dc3545; animation: pulse 1.5s infinite; }
        .control-btn.end { background: #dc3545; color: white; width: 60px; }
        .control-btn:hover { transform: scale(1.1); }
        @keyframes pulse {
            0% { box-shadow: 0 0 0 0 rgba(220, 53, 69, 0.7); }
            70% { box-shadow: 0 0 0 10px rgba(220, 53, 69, 0); }
            100% { box-shadow: 0 0 0 0 rgba(220, 53, 69, 0); }
        }
        
        .clinical-section {
            background: white;
            overflow-y: auto;
            padding: 10px;
        }
        .patient-header {
            background: #6f42c1;
            color: white;
            padding: 10px;
            border-radius: 8px;
            margin-bottom: 10px;
        }
        .patient-header h2 {
            font-size: 14px;
            margin-bottom: 3px;
        }
        .patient-header p {
            font-size: 11px;
            opacity: 0.9;
        }
        
        .message {
            background: #d4edda;
            color: #155724;
            padding: 8px;
            border-radius: 5px;
            margin-bottom: 10px;
            font-size: 12px;
        }
        
        .tabs {
            display: flex;
            gap: 5px;
            margin-bottom: 10px;
        }
        .tab {
            padding: 6px 10px;
            background: #e0e0e0;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 11px;
        }
        .tab.active {
            background: #6f42c1;
            color: white;
        }
        
        .tab-content { display: none; }
        .tab-content.active { display: block; }
        
        .form-group {
            margin-bottom: 8px;
        }
        .form-group label {
            display: block;
            margin-bottom: 2px;
            color: #555;
            font-weight: 500;
            font-size: 11px;
        }
        .form-group input, .form-group select, .form-group textarea {
            width: 100%;
            padding: 6px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 12px;
        }
        .form-group textarea {
            resize: vertical;
            min-height: 40px;
        }
        
        .vital-signs {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 5px;
        }
        .vital-item {
            background: #f9f9f9;
            padding: 5px;
            border-radius: 5px;
            text-align: center;
        }
        .vital-item label {
            display: block;
            font-size: 9px;
            color: #666;
        }
        .vital-item input {
            width: 100%;
            text-align: center;
            font-weight: bold;
            color: #6f42c1;
            border: none;
            border-bottom: 2px solid #6f42c1;
            background: transparent;
            font-size: 12px;
        }
        
        .medicine-row {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr 1fr auto;
            gap: 3px;
            margin-bottom: 5px;
            align-items: center;
        }
        .btn-remove {
            background: #dc3545;
            color: white;
            border: none;
            width: 22px;
            height: 22px;
            border-radius: 50%;
            cursor: pointer;
            font-size: 12px;
        }
        
        .form-actions {
            display: flex;
            gap: 5px;
            margin-top: 10px;
        }
        .btn {
            background: #28a745;
            color: white;
            padding: 8px 12px;
            border: none;
            border-radius: 5px;
            font-size: 11px;
            cursor: pointer;
            flex: 1;
        }
        .btn:hover { background: #218838; }
        .btn-primary { background: #6f42c1; }
        .btn-primary:hover { background: #5a32a3; }
        
        .connecting {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 100%;
            color: white;
            text-align: center;
        }
        .spinner {
            width: 30px;
            height: 30px;
            border: 3px solid #333;
            border-top-color: #6f42c1;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin-bottom: 10px;
        }
        @keyframes spin { to { transform: rotate(360deg); } }
    </style>
</head>
<body>
    <div class="header">
        <h1>Teleconsulta - <?php echo htmlspecialchars($consultation['patient_name']); ?></h1>
        <a href="teleconsulta.php">Volver</a>
    </div>
    
    <div class="main-container">
        <div class="video-section">
            <div class="video-area">
                <div class="video-grid" id="videoGrid">
                    <?php if ($skip_video): ?>
                    <div class="connecting" style="background: #16213e;">
                        <div style="text-align: center; color: white; padding: 20px;">
                            <p style="font-size: 48px;">📋</p>
                            <h2>Modo Solo Historia Clínica</h2>
                            <p>La videollamada está deshabilitada</p>
                            <p style="margin-top: 20px;">Complete el formulario de historia clínica y exporte el PDF</p>
                        </div>
                    </div>
                    <?php else: ?>
                    <div class="connecting" id="connectingOverlay">
                        <div class="spinner"></div>
                        <p>Conectando...</p>
                    </div>
                    <div class="participant" id="localParticipant">
                        <video id="localVideo" autoplay playsinline muted></video>
                        <div class="name-tag"><?php echo htmlspecialchars($doctor_name); ?></div>
                    </div>
                    <div class="participant" id="remoteParticipant">
                        <video id="remoteVideo" autoplay playsinline></video>
                        <div class="name-tag" id="remoteName">Esperando paciente...</div>
                    </div>
                    <?php endif; ?>
                </div>
                <?php if (!$skip_video): ?>
                <div class="controls">
                    <button class="control-btn mute" id="muteBtn" onclick="toggleMute()" title="Activar/Desactivar Micrófono">
                        <svg id="micIcon" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M12 1a3 3 0 0 0-3 3v8a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z"></path>
                            <path d="M19 10v2a7 7 0 0 1-14 0v-2"></path>
                            <line x1="12" y1="19" x2="12" y2="23"></line>
                            <line x1="8" y1="23" x2="16" y2="23"></line>
                        </svg>
                    </button>
                    <button class="control-btn video" id="videoBtn" onclick="toggleVideo()" title="Activar/Desactivar Cámara">
                        <svg id="camIcon" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polygon points="23 7 16 12 23 17 23 7"></polygon>
                            <rect x="1" y="5" width="15" height="14" rx="2" ry="2"></rect>
                        </svg>
                    </button>
                    <button class="control-btn record" id="recordBtn" onclick="toggleRecording()" title="Iniciar/Detener Grabación">
                        <svg id="recIcon" width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
                            <circle cx="12" cy="12" r="8"></circle>
                        </svg>
                    </button>
                    <button class="control-btn end" onclick="endCall()" title="Finalizar Llamada">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M12 9c-1.6 0-3.15.25-4.6.72v3.1c0 .39-.23.74-.56.9-.98.49-1.87 1.12-2.66 1.85-.18.18-.43.28-.7.28-.28 0-.53-.11-.71-.29L.29 13.08c-.18-.17-.29-.42-.29-.7 0-.28.11-.53.29-.71C3.34 8.78 7.46 7 12 7s8.66 1.78 11.71 4.67c.18.18.29.43.29.71 0 .28-.11.53-.29.71l-2.48 2.48c-.18.18-.43.29-.71.29-.27 0-.52-.11-.7-.28-.79-.73-1.68-1.36-2.66-1.85-.33-.16-.56-.5-.56-.9v-3.1C15.15 9.25 13.6 9 12 9z"/>
                        </svg>
                    </button>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="clinical-section">
            <div class="patient-header">
                <h2><?php echo htmlspecialchars($consultation['patient_name']); ?></h2>
                <p><?php echo $consultation['id_type']; ?> <?php echo htmlspecialchars($consultation['id_number']); ?> | <?php echo $age; ?> anos | <?php echo $consultation['gender'] === 'M' ? 'Masculino' : 'Femenino'; ?></p>
                <p><?php echo htmlspecialchars($consultation['eps_name'] ?? 'EPS no registrada'); ?></p>
            </div>
            
            <?php if ($message): ?>
            <div class="message"><?php echo $message; ?></div>
            <?php endif; ?>
            
            <div class="tabs">
                <button class="tab active" onclick="showTab('consulta')">Consulta</button>
                <button class="tab" onclick="showTab('historial')">Historial</button>
            </div>
            
            <form method="POST" action="" id="clinicalForm">
                <input type="hidden" name="patient_id" value="<?php echo $patient_id; ?>">
                <input type="hidden" name="consultation_id" value="<?php echo $consultation_id; ?>">
                <div id="consulta" class="tab-content active">
                    <div class="form-group">
                        <label>Tipo de Consulta</label>
                        <select name="consultation_type">
                            <option value="Primera Vez">Primera Vez</option>
                            <option value="Control">Control</option>
                            <option value="Urgencia">Urgencia</option>
                            <option value="Telemedicina" selected>Telemedicina</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Motivo de Consulta *</label>
                        <textarea name="reason_consultation" rows="2" required placeholder="描述就诊的主要原因..."></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label>Signos Vitales</label>
                        <div class="vital-signs">
                            <div class="vital-item">
                                <label>PA Sist.</label>
                                <input type="number" name="systolic_bp" placeholder="120">
                            </div>
                            <div class="vital-item">
                                <label>PA Diast.</label>
                                <input type="number" name="diastolic_bp" placeholder="80">
                            </div>
                            <div class="vital-item">
                                <label>FC</label>
                                <input type="number" name="heart_rate" placeholder="72">
                            </div>
                            <div class="vital-item">
                                <label>Temperatura</label>
                                <input type="number" step="0.1" name="temperature" placeholder="36.5">
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label style="display: flex; justify-content: space-between; align-items: center;">
                            Enfermedad Actual
                            <button type="button" id="transcribeBtn" onclick="toggleTranscription()" style="background: #17a2b8; color: white; border: none; padding: 5px 10px; border-radius: 5px; font-size: 11px; cursor: pointer;">
                                🎤 Transcribir
                            </button>
                        </label>
                        <textarea name="current_illness" id="current_illness" rows="2" placeholder="Describa los sintomas del paciente..."></textarea>
                        <div id="transcriptionStatus" style="font-size: 11px; color: #17a2b8; margin-top: 5px; display: none;">
                            ● Transcribiendo...
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Examen Fisico</label>
                        <textarea name="physical_exam" rows="2" placeholder="描述体格检查结果..."></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label>Diagnostico (CIE-10)</label>
                        <div style="display: grid; grid-template-columns: 80px 1fr; gap: 5px;">
                            <input type="text" name="diagnosis_code" placeholder="Codigo">
                            <input type="text" name="diagnosis_name" placeholder="Nombre">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Medicamentos</label>
                        <div id="medicines-container">
                            <div class="medicine-row">
                                <input type="text" name="medicine_name[]" placeholder="Medicamento">
                                <input type="text" name="medicine_dose[]" placeholder="Dosis">
                                <input type="text" name="medicine_frequency[]" placeholder="Frec.">
                                <input type="text" name="medicine_duration[]" placeholder="Durac.">
                                <button type="button" class="btn-remove" onclick="removeMedicine(this)">x</button>
                            </div>
                        </div>
                        <button type="button" onclick="addMedicine()" style="background: #e0e0e0; border: none; padding: 3px 8px; border-radius: 3px; cursor: pointer; font-size: 10px; margin-top: 3px;">+ Agregar</button>
                    </div>
                    
                    <div class="form-group">
                        <label>Plan de Manejo</label>
                        <textarea name="management_plan" rows="2" placeholder="描述治疗计划..."></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label>Recomendaciones</label>
                        <textarea name="recommendations" rows="2" placeholder="给患者的建议..."></textarea>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">Guardar HC</button>
                        <button type="button" class="btn" onclick="exportPDF()">Exportar PDF</button>
                    </div>
                </div>
                
                <div id="historial" class="tab-content">
                    <?php if ($previous_history->num_rows > 0): ?>
                        <?php while ($h = $previous_history->fetch_assoc()): ?>
                        <div style="background: #f9f9f9; padding: 8px; border-radius: 5px; margin-bottom: 8px;">
                            <div style="display: flex; justify-content: space-between; align-items: center;">
                                <div>
                                    <strong style="font-size: 11px;"><?php echo date('d/m/Y', strtotime($h['consultation_date'])); ?></strong> - <?php echo $h['consultation_type']; ?>
                                    <p style="font-size: 10px; color: #666; margin-top: 3px;">
                                        <?php echo htmlspecialchars(substr($h['reason_consultation'] ?? '', 0, 100)); ?>...
                                    </p>
                                </div>
                                <div style="display: flex; gap: 5px;">
                                    <a href="teleconsulta_pdf.php?id=<?php echo $patient_id; ?>&consultation_id=<?php echo $h['id']; ?>" target="_blank" class="btn" style="padding: 5px 10px; font-size: 10px; background: #0066cc; text-decoration: none;">📄 PDF</a>
                                    <button onclick="window.print()" class="btn" style="padding: 5px 10px; font-size: 10px; background: #28a745;">🖨️ Imprimir</button>
                                </div>
                            </div>
                        </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <p style="color: #999; font-size: 11px; text-align: center;">Sin consultas anteriores</p>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        const SERVER_URL = 'ws://localhost:7880';
        const ROOM_NAME = '<?php echo $room_name; ?>';
        const TOKEN = '<?php echo $token; ?>';
        const SKIP_VIDEO = <?php echo $skip_video ? 'true' : 'false'; ?>;
        
        let room;
        let isMuted = false;
        let isVideoOff = false;
        let isRecording = false;
        let mediaRecorder = null;
        let recordedChunks = [];
        let recordingStartTime = null;
        let localStream = null;
        let recognition = null;
        let isTranscribing = false;
        let transcriptionText = '';
        
        async function connectToRoom() {
            if (SKIP_VIDEO) {
                console.log('Video deshabilitado');
                return;
            }
            
            try {
                localStream = await navigator.mediaDevices.getUserMedia({ video: true, audio: true });
                document.getElementById('localVideo').srcObject = localStream;
                document.getElementById('connectingOverlay').style.display = 'none';
                
                if (TOKEN) {
                    const lk = await import('https://cdn.jsdelivr.net/npm/livekit-client@2.5.5/+esm');
                    const { Room, RoomEvent, Track } = lk;
                    
                    room = new Room({ adaptiveStream: true, dynacast: true });
                    
                    room.on(RoomEvent.TrackSubscribed, (track, publication, participant) => {
                        if (track.kind === Track.Kind.Video) {
                            document.getElementById('remoteVideo').srcObject = track.attach();
                            document.getElementById('remoteName').textContent = participant.name || participant.identity;
                        }
                        if (track.kind === Track.Kind.Audio) {
                            track.attach();
                        }
                    });
                    
                    await room.connect(SERVER_URL, TOKEN);
                    await room.localParticipant.setMicrophoneEnabled(true);
                    await room.localParticipant.setCameraEnabled(true);
                }
            } catch (error) {
                console.error('Error:', error);
                document.getElementById('connectingOverlay').innerHTML = '<p style="color: #ff6b6b;">Sin video</p>';
            }
        }
        
        function toggleMute() {
            isMuted = !isMuted;
            if (room) {
                room.localParticipant.setMicrophoneEnabled(!isMuted);
            } else if (localStream) {
                localStream.getAudioTracks().forEach(track => {
                    track.enabled = !isMuted;
                });
            }
            document.getElementById('muteBtn').classList.toggle('active', isMuted);
            const micIcon = document.querySelector('#micIcon');
            if (isMuted) {
                micIcon.innerHTML = '<line x1="1" y1="1" x2="23" y2="23"></line><path d="M9 9v3a3 3 0 0 0 5.12 2.12M15 9.34V4a3 3 0 0 0-5.94-.6"></path><path d="M17 16.95A7 7 0 0 1 5 12v-2m14 0v2a7 7 0 0 1-.11 1.23"></path><line x1="12" y1="19" x2="12" y2="23"></line><line x1="8" y1="23" x2="16" y2="23"></line>';
            } else {
                micIcon.innerHTML = '<path d="M12 1a3 3 0 0 0-3 3v8a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z"></path><path d="M19 10v2a7 7 0 0 1-14 0v-2"></path><line x1="12" y1="19" x2="12" y2="23"></line><line x1="8" y1="23" x2="16" y2="23"></line>';
            }
        }
        
        function toggleVideo() {
            isVideoOff = !isVideoOff;
            if (room) {
                room.localParticipant.setCameraEnabled(!isVideoOff);
            } else if (localStream) {
                localStream.getVideoTracks().forEach(track => {
                    track.enabled = !isVideoOff;
                });
            }
            document.getElementById('videoBtn').classList.toggle('active', isVideoOff);
            const camIcon = document.querySelector('#camIcon');
            if (isVideoOff) {
                camIcon.innerHTML = '<line x1="1" y1="1" x2="23" y2="23"></line><path d="M21 21H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h3m3-3h6l2 3h4a2 2 0 0 1 2 2v9.34m-7.72-2.06a4 4 0 1 1-5.56-5.56"></path>';
            } else {
                camIcon.innerHTML = '<polygon points="23 7 16 12 23 17 23 7"></polygon><rect x="1" y="5" width="15" height="14" rx="2" ry="2"></rect>';
            }
        }
        
        function toggleRecording() {
            if (!isRecording) {
                startRecording();
            } else {
                stopRecording();
            }
        }
        
        function startRecording() {
            if (!localStream) {
                alert('No hay flujo de video activo');
                return;
            }
            
            recordedChunks = [];
            recordingStartTime = new Date();
            
            const mimeType = MediaRecorder.isTypeSupported('video/webm;codecs=vp9') 
                ? 'video/webm;codecs=vp9' 
                : 'video/webm';
            
            mediaRecorder = new MediaRecorder(localStream, { mimeType: mimeType });
            
            mediaRecorder.ondataavailable = (event) => {
                if (event.data.size > 0) {
                    recordedChunks.push(event.data);
                }
            };
            
            mediaRecorder.onstop = () => {
                const blob = new Blob(recordedChunks, { type: mimeType });
                const url = URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                const duration = Math.round((new Date() - recordingStartTime) / 1000);
                a.download = `grabacion_${Date.now()}.webm`;
                a.click();
                URL.revokeObjectURL(url);
                
                const durationMin = Math.floor(duration / 60);
                const durationSec = duration % 60;
                alert(`Grabación guardada. Duración: ${durationMin}m ${durationSec}s`);
            };
            
            mediaRecorder.start(1000);
            isRecording = true;
            document.getElementById('recordBtn').classList.add('active');
            document.getElementById('recIcon').style.fill = '#fff';
            console.log('Grabación iniciada');
        }
        
        function stopRecording() {
            if (mediaRecorder && mediaRecorder.state !== 'inactive') {
                mediaRecorder.stop();
            }
            isRecording = false;
            document.getElementById('recordBtn').classList.remove('active');
            console.log('Grabación detenida');
        }
        
        function endCall() {
            if (room) room.disconnect();
            if (localStream) localStream.getTracks().forEach(t => t.stop());
            window.location.href = 'teleconsulta.php';
        }
        
        function showTab(tabId) {
            document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
            document.querySelectorAll('.tab').forEach(el => el.classList.remove('active'));
            document.getElementById(tabId).classList.add('active');
            event.target.classList.add('active');
        }
        
        function addMedicine() {
            const container = document.getElementById('medicines-container');
            const row = document.createElement('div');
            row.className = 'medicine-row';
            row.innerHTML = `
                <input type="text" name="medicine_name[]" placeholder="Medicamento">
                <input type="text" name="medicine_dose[]" placeholder="Dosis">
                <input type="text" name="medicine_frequency[]" placeholder="Frec.">
                <input type="text" name="medicine_duration[]" placeholder="Durac.">
                <button type="button" class="btn-remove" onclick="removeMedicine(this)">x</button>
            `;
            container.appendChild(row);
        }
        
        function removeMedicine(btn) {
            if (document.querySelectorAll('.medicine-row').length > 1) {
                btn.parentElement.remove();
            }
        }
        
        function exportPDF() {
            const form = document.getElementById('clinicalForm');
            const formData = new FormData(form);
            
            fetch('save_clinical_history.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    window.open('teleconsulta_pdf.php?id=<?php echo $patient_id; ?>&consultation_id=' + data.id, '_blank');
                } else {
                    alert('Error al guardar: ' + data.error);
                }
            })
            .catch(error => {
                alert('Error: ' + error);
            });
        }
        
        // Speech Recognition
        function initSpeechRecognition() {
            if ('webkitSpeechRecognition' in window) {
                recognition = new webkitSpeechRecognition();
            } else if ('SpeechRecognition' in window) {
                recognition = new SpeechRecognition();
            } else {
                console.log('Speech recognition not supported');
                return null;
            }
            
            recognition.continuous = true;
            recognition.interimResults = true;
            recognition.lang = 'es-CO';
            
            recognition.onstart = function() {
                isTranscribing = true;
                document.getElementById('transcribeBtn').textContent = '⏹️ Detener';
                document.getElementById('transcribeBtn').style.background = '#dc3545';
                document.getElementById('transcriptionStatus').style.display = 'block';
            };
            
            recognition.onresult = function(event) {
                let interimTranscript = '';
                let finalTranscript = '';
                
                for (let i = event.resultIndex; i < event.results.length; i++) {
                    const transcript = event.results[i][0].transcript;
                    if (event.results[i].isFinal) {
                        finalTranscript += transcript;
                    } else {
                        interimTranscript += transcript;
                    }
                }
                
                if (finalTranscript) {
                    transcriptionText += finalTranscript + ' ';
                }
                
                const textarea = document.getElementById('current_illness');
                textarea.value = transcriptionText + interimTranscript;
            };
            
            recognition.onerror = function(event) {
                console.error('Speech recognition error:', event.error);
                if (event.error !== 'no-speech') {
                    alert('Error de transcripcion: ' + event.error);
                }
            };
            
            recognition.onend = function() {
                if (isTranscribing) {
                    recognition.start();
                }
            };
            
            return recognition;
        }
        
        function toggleTranscription() {
            if (!recognition) {
                recognition = initSpeechRecognition();
            }
            
            if (isTranscribing) {
                isTranscribing = false;
                recognition.stop();
                document.getElementById('transcribeBtn').textContent = '🎤 Transcribir';
                document.getElementById('transcribeBtn').style.background = '#17a2b8';
                document.getElementById('transcriptionStatus').style.display = 'none';
            } else {
                transcriptionText = document.getElementById('current_illness').value;
                try {
                    recognition.start();
                } catch(e) {
                    console.error('Recognition start error:', e);
                }
            }
        }
        
        connectToRoom();
    </script>
</body>
</html>
<?php $conn->close(); ?>

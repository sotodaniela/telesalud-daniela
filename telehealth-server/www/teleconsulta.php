<?php
session_start();

if (!isset($_SESSION['doctor_id'])) {
    header('Location: login.php');
    exit;
}

$conn = new mysqli('telehealth-db', 'telehealth', 'telehealth123', 'telehealth');
$doctor_id = $_SESSION['doctor_id'];
$doctor_name = $_SESSION['doctor_name'];

$consultation_id = $_GET['id'] ?? 0;
$patient_id = $_GET['patient_id'] ?? 0;

if ($consultation_id) {
    $consultation = $conn->query("SELECT c.*, CONCAT(p.first_name, ' ', p.last_name) as patient_name, p.id_number, p.id_type, p.email, p.phone, p.mobile, p.birth_date, p.gender, p.address, p.city, p.eps_name FROM consultations c JOIN patients p ON c.patient_id = p.id WHERE c.id = $consultation_id AND c.doctor_id = $doctor_id")->fetch_assoc();
    $patient_id = $consultation['patient_id'];
} elseif ($patient_id) {
    $consultation = null;
    $result = $conn->query("SELECT p.*, CONCAT(p.first_name, ' ', p.last_name) as patient_name FROM patients p WHERE p.id = $patient_id AND p.is_active = 1");
    $consultation = $result->fetch_assoc();
}

if (!$consultation) {
    header('Location: dashboard.php');
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
if ($consultation_id) {
    $api_key = 'APInewKey123';
    $api_secret = 'NewSecret456789012345678901234567890';
    
    function base64url_encode($data) {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
    
    $room_name = $consultation['room_name'] ?? 'room_' . time();
    
    $header = json_encode(['alg' => 'HS256', 'typ' => 'JWT']);
    $payload = json_encode([
        'iss' => $api_key,
        'sub' => 'doctor_' . $_SESSION['doctor_id'],
        'room' => $room_name,
        'name' => $doctor_name,
        'exp' => time() + 7200,
        'canPublish' => true,
        'canSubscribe' => true,
        'canPublishData' => true
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
        
        // Update consultation status if exists
        if ($consultation_id) {
            $conn->query("UPDATE consultations SET status = 'completed', clinical_history_id = $clinical_history_id WHERE id = $consultation_id");
        }
        
        $message = 'Historia clinica guardada exitosamente';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teleconsulta - <?php echo htmlspecialchars($consultation['patient_name']); ?></title>
    <script src="https://unpkg.com/livekit-client@2.5.5/dist/livekit-client.umd.cjs"></script>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Segoe UI', Tahoma, sans-serif;
            background: #f5f5f5;
            height: 100vh;
            overflow: hidden;
        }
        .main-container {
            display: grid;
            grid-template-columns: 1fr 450px;
            height: 100vh;
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
            padding: 15px;
        }
        .participant {
            background: #16213e;
            border-radius: 10px;
            position: relative;
            overflow: hidden;
            min-height: 200px;
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
            font-size: 12px;
        }
        .controls {
            background: #16213e;
            padding: 15px;
            display: flex;
            justify-content: center;
            gap: 10px;
        }
        .control-btn {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            border: none;
            cursor: pointer;
            font-size: 18px;
            transition: all 0.3s;
        }
        .control-btn.mute { background: #444; color: white; }
        .control-btn.mute.active { background: #dc3545; }
        .control-btn.video { background: #444; color: white; }
        .control-btn.video.active { background: #dc3545; }
        .control-btn.end { background: #dc3545; color: white; width: 70px; }
        .control-btn:hover { transform: scale(1.1); }
        
        .clinical-section {
            background: white;
            overflow-y: auto;
            padding: 15px;
        }
        .section-title {
            background: #0066cc;
            color: white;
            padding: 10px 15px;
            border-radius: 8px 8px 0 0;
            font-size: 14px;
            font-weight: 600;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .patient-header {
            background: #e3f2fd;
            padding: 15px;
            border-radius: 0 0 8px 8px;
            margin-bottom: 15px;
        }
        .patient-header h2 {
            color: #1565c0;
            font-size: 16px;
            margin-bottom: 5px;
        }
        .patient-header p {
            color: #666;
            font-size: 12px;
        }
        
        .form-group {
            margin-bottom: 12px;
        }
        .form-group label {
            display: block;
            margin-bottom: 4px;
            color: #555;
            font-weight: 500;
            font-size: 12px;
        }
        .form-group input, .form-group select, .form-group textarea {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 13px;
        }
        .form-group textarea {
            resize: vertical;
            min-height: 60px;
        }
        
        .vital-signs {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 8px;
        }
        .vital-item {
            background: #f9f9f9;
            padding: 8px;
            border-radius: 5px;
            text-align: center;
        }
        .vital-item label {
            display: block;
            font-size: 10px;
            color: #666;
        }
        .vital-item input {
            width: 100%;
            text-align: center;
            font-weight: bold;
            color: #0066cc;
            border: none;
            border-bottom: 2px solid #0066cc;
            background: transparent;
        }
        
        .medicine-row {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr 1fr auto;
            gap: 5px;
            margin-bottom: 8px;
            align-items: center;
        }
        .btn-remove {
            background: #dc3545;
            color: white;
            border: none;
            width: 25px;
            height: 25px;
            border-radius: 50%;
            cursor: pointer;
            font-size: 14px;
        }
        
        .form-actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }
        .btn {
            background: #28a745;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            font-size: 13px;
            cursor: pointer;
            flex: 1;
        }
        .btn:hover { background: #218838; }
        .btn-primary { background: #0066cc; }
        .btn-primary:hover { background: #0052a3; }
        .btn-export { background: #ffc107; color: #000; }
        .btn-export:hover { background: #e0a800; }
        
        .message {
            background: #d4edda;
            color: #155724;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 10px;
            font-size: 13px;
        }
        
        .tabs {
            display: flex;
            gap: 5px;
            margin-bottom: 10px;
        }
        .tab {
            padding: 8px 12px;
            background: #f0f0f0;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 12px;
        }
        .tab.active {
            background: #0066cc;
            color: white;
        }
        
        .tab-content {
            display: none;
        }
        .tab-content.active {
            display: block;
        }
        
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
            width: 40px;
            height: 40px;
            border: 4px solid #333;
            border-top-color: #0066cc;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin-bottom: 15px;
        }
        @keyframes spin { to { transform: rotate(360deg); } }
        
        .recording-indicator {
            position: fixed;
            top: 10px;
            left: 50%;
            transform: translateX(-50%);
            background: #dc3545;
            color: white;
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 12px;
            display: none;
            z-index: 1000;
        }
        .recording-indicator.active { display: flex; align-items: center; gap: 8px; }
    </style>
</head>
<body>
    <div class="recording-indicator" id="recordingIndicator">
        <span>GRABANDO</span>
    </div>
    
    <div class="main-container">
        <div class="video-section">
            <div class="video-area">
                <div class="video-grid" id="videoGrid">
                    <div class="connecting" id="connectingOverlay">
                        <div class="spinner"></div>
                        <p>Conectando a la sala...</p>
                    </div>
                    <div class="participant" id="localParticipant">
                        <video id="localVideo" autoplay playsinline muted></video>
                        <div class="name-tag"><?php echo htmlspecialchars($doctor_name); ?> (Medico)</div>
                    </div>
                    <div class="participant" id="remoteParticipant">
                        <video id="remoteVideo" autoplay playsinline></video>
                        <div class="name-tag" id="remoteName">Esperando paciente...</div>
                    </div>
                </div>
                <div class="controls">
                    <button class="control-btn mute" id="muteBtn" onclick="toggleMute()">M</button>
                    <button class="control-btn video" id="videoBtn" onclick="toggleVideo()">V</button>
                    <button class="control-btn" onclick="window.open('teleconsulta_pdf.php?id=<?php echo $patient_id; ?>&consultation_id=<?php echo $consultation_id; ?>', '_blank')">PDF</button>
                    <button class="control-btn end" onclick="endCall()">Fin</button>
                </div>
            </div>
        </div>
        
        <div class="clinical-section">
            <div class="section-title">
                <span>Historia Clinica</span>
                <span><?php echo date('d/m/Y H:i'); ?></span>
            </div>
            
            <div class="patient-header">
                <h2><?php echo htmlspecialchars($consultation['patient_name']); ?></h2>
                <p><?php echo $consultation['id_type']; ?> <?php echo htmlspecialchars($consultation['id_number']); ?> | <?php echo $age; ?> anos | <?php echo $consultation['gender'] === 'M' ? 'Masculino' : 'Femenino'; ?></p>
                <p><?php echo htmlspecialchars($consultation['city'] ?? ''); ?> | <?php echo htmlspecialchars($consultation['eps_name'] ?? 'EPS no registrada'); ?></p>
            </div>
            
            <?php if ($message): ?>
            <div class="message"><?php echo $message; ?></div>
            <?php endif; ?>
            
            <div class="tabs">
                <button class="tab active" onclick="showTab('consulta')">Consulta</button>
                <button class="tab" onclick="showTab('historial')">Historial</button>
            </div>
            
            <form method="POST" action="" id="clinicalForm">
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
                                <label>FC (lpm)</label>
                                <input type="number" name="heart_rate" placeholder="72">
                            </div>
                            <div class="vital-item">
                                <label>Temperatura</label>
                                <input type="number" step="0.1" name="temperature" placeholder="36.5">
                            </div>
                            <div class="vital-item">
                                <label>FR</label>
                                <input type="number" name="respiratory_rate" placeholder="18">
                            </div>
                            <div class="vital-item">
                                <label>Sat O2</label>
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
                    
                    <div class="form-group">
                        <label>Enfermedad Actual</label>
                        <textarea name="current_illness" rows="2" placeholder="描述患者描述的症状..."></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label>Examen Fisico</label>
                        <textarea name="physical_exam" rows="2" placeholder="描述体格检查结果..."></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label>Diagnostico (CIE-10)</label>
                        <div style="display: grid; grid-template-columns: 120px 1fr; gap: 10px;">
                            <input type="text" name="diagnosis_code" placeholder="Codigo">
                            <input type="text" name="diagnosis_name" placeholder="Nombre del diagnostico">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Prescripcion de Medicamentos</label>
                        <div id="medicines-container">
                            <div class="medicine-row">
                                <input type="text" name="medicine_name[]" placeholder="Medicamento">
                                <input type="text" name="medicine_dose[]" placeholder="Dosis">
                                <input type="text" name="medicine_frequency[]" placeholder="Frecuencia">
                                <input type="text" name="medicine_duration[]" placeholder="Duracion">
                                <button type="button" class="btn-remove" onclick="removeMedicine(this)">x</button>
                            </div>
                        </div>
                        <button type="button" onclick="addMedicine()" style="background: #e3f2fd; border: none; padding: 5px 10px; border-radius: 5px; cursor: pointer; font-size: 11px; margin-top: 5px;">+ Agregar</button>
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
                        <button type="submit" class="btn">Guardar Historia Clinica</button>
                        <button type="button" class="btn btn-export" onclick="exportPDF()">Exportar PDF</button>
                    </div>
                </div>
                
                <div id="historial" class="tab-content">
                    <?php if ($previous_history->num_rows > 0): ?>
                        <?php while ($h = $previous_history->fetch_assoc()): ?>
                        <div style="background: #f9f9f9; padding: 10px; border-radius: 5px; margin-bottom: 10px;">
                            <strong><?php echo date('d/m/Y', strtotime($h['consultation_date'])); ?></strong> - <?php echo $h['consultation_type']; ?>
                            <p style="font-size: 12px; color: #666; margin-top: 5px;">
                                Motivo: <?php echo htmlspecialchars($h['reason_consultation'] ?? 'N/A'); ?>
                            </p>
                        </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <p style="color: #999; text-align: center;">No hay consultas anteriores</p>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        const SERVER_URL = 'ws://localhost:7880';
        const ROOM_NAME = '<?php echo $consultation['room_name'] ?? 'room_' . time(); ?>';
        const TOKEN = '<?php echo $token; ?>';
        
        let room;
        let isMuted = false;
        let isVideoOff = false;
        let localStream = null;
        
        async function connectToRoom() {
            try {
                localStream = await navigator.mediaDevices.getUserMedia({ video: true, audio: true });
                document.getElementById('localVideo').srcObject = localStream;
                document.getElementById('connectingOverlay').style.display = 'none';
                
                const lk = await import('https://unpkg.com/livekit-client@2.5.5/+esm');
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
                
                room.on(RoomEvent.Disconnected, () => {
                    document.getElementById('remoteName').textContent = 'Paciente desconectado';
                });
                
                await room.connect(SERVER_URL, TOKEN);
                await room.localParticipant.setMicrophoneEnabled(true);
                await room.localParticipant.setCameraEnabled(true);
                
            } catch (error) {
                console.error('Error:', error);
                document.getElementById('connectingOverlay').innerHTML = '<p style="color: #ff6b6b;">Error de conexion</p>';
            }
        }
        
        function toggleMute() {
            if (room) {
                room.localParticipant.setMicrophoneEnabled(isMuted);
                isMuted = !isMuted;
                document.getElementById('muteBtn').classList.toggle('active', isMuted);
            }
        }
        
        function toggleVideo() {
            if (room) {
                room.localParticipant.setCameraEnabled(isVideoOff);
                isVideoOff = !isVideoOff;
                document.getElementById('videoBtn').classList.toggle('active', isVideoOff);
            }
        }
        
        function endCall() {
            if (room) room.disconnect();
            if (localStream) localStream.getTracks().forEach(t => t.stop());
            document.getElementById('clinicalForm').submit();
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
                <input type="text" name="medicine_frequency[]" placeholder="Frecuencia">
                <input type="text" name="medicine_duration[]" placeholder="Duracion">
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
            document.getElementById('clinicalForm').submit();
            setTimeout(() => {
                window.open('teleconsulta_pdf.php?id=<?php echo $patient_id; ?>&consultation_id=<?php echo $consultation_id; ?>', '_blank');
            }, 500);
        }
        
        <?php if ($token): ?>
        connectToRoom();
        <?php else: ?>
        document.getElementById('connectingOverlay').innerHTML = '<p style="color: #999;">Modo sin video - Agende una cita primero</p>';
        <?php endif; ?>
    </script>
</body>
</html>
<?php $conn->close(); ?>

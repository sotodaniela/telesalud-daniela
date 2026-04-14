<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$conn = new mysqli('telehealth-db', 'telehealth', 'telehealth123', 'telehealth');

$consultation_id = $_GET['id'] ?? '';
$doctor_id = $_SESSION['user_id'];

$stmt = $conn->prepare('
    SELECT c.*, CONCAT(p.first_name, " ", p.last_name) as patient_name, p.id_number as document_id, p.phone, p.email as patient_email
    FROM consultations c
    JOIN patients p ON c.patient_id = p.id
    WHERE c.id = ? AND c.doctor_id = ?
');
$stmt->bind_param('ii', $consultation_id, $doctor_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header('Location: dashboard.php');
    exit;
}

$consultation = $result->fetch_assoc();
$stmt->close();

// Generate LiveKit token
$api_key = 'APInewKey123';
$api_secret = 'NewSecret456789012345678901234567890';

function base64url_encode($data) {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

$header = json_encode(['alg' => 'HS256', 'typ' => 'JWT']);
$payload = json_encode([
    'iss' => $api_key,
    'sub' => 'doctor_' . $_SESSION['user_id'],
    'room' => $consultation['room_name'],
    'name' => $_SESSION['doctor_name'],
    'exp' => time() + 3600,
    'video' => [
        'canPublish' => true,
        'canSubscribe' => true,
        'canPublishData' => true,
        'canPublishSources' => ['camera', 'microphone']
    ]
]);

$headerEncoded = base64url_encode($header);
$payloadEncoded = base64url_encode($payload);
$message = $headerEncoded . '.' . $payloadEncoded;
$signature = hash_hmac('sha256', $message, $api_secret, true);
$signatureEncoded = base64url_encode($signature);

$token = $headerEncoded . '.' . $payloadEncoded . '.' . $signatureEncoded;

// Update status to in_progress if scheduled
if ($consultation['status'] === 'scheduled') {
    $update_stmt = $conn->prepare('UPDATE consultations SET status = ? WHERE id = ?');
    $new_status = 'in_progress';
    $update_stmt->bind_param('si', $new_status, $consultation_id);
    $update_stmt->execute();
    $update_stmt->close();
    $consultation['status'] = 'in_progress';
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Videoconsulta - <?php echo htmlspecialchars($consultation['patient_name']); ?></title>
    <script src="https://cdn.jsdelivr.net/npm/livekit-client@2.5.5/+esm" type="module"></script>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Segoe UI', Tahoma, sans-serif;
            background: #1a1a2e;
            color: white;
        }
        .header {
            background: #0066cc;
            padding: 15px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .header h1 { font-size: 18px; }
        .timer {
            background: rgba(255,255,255,0.2);
            padding: 8px 15px;
            border-radius: 5px;
            font-size: 18px;
            font-family: monospace;
        }
        
        .main-container {
            display: grid;
            grid-template-columns: 1fr 350px;
            height: calc(100vh - 60px);
        }
        
        .video-area {
            display: flex;
            flex-direction: column;
            position: relative;
        }
        
        .video-grid {
            flex: 1;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            padding: 20px;
        }
        
        .participant {
            background: #16213e;
            border-radius: 15px;
            position: relative;
            overflow: hidden;
            aspect-ratio: 16/9;
        }
        .participant video {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .participant .name-tag {
            position: absolute;
            bottom: 15px;
            left: 15px;
            background: rgba(0,0,0,0.7);
            padding: 8px 15px;
            border-radius: 5px;
            font-size: 14px;
        }
        .participant .local-indicator {
            position: absolute;
            top: 15px;
            right: 15px;
            background: #28a745;
            width: 12px;
            height: 12px;
            border-radius: 50%;
        }
        
        .controls {
            background: #16213e;
            padding: 20px;
            display: flex;
            justify-content: center;
            gap: 15px;
        }
        .control-btn {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            border: none;
            cursor: pointer;
            font-size: 20px;
            transition: all 0.3s;
        }
        .control-btn.mute { background: #333; color: white; }
        .control-btn.mute.active { background: #dc3545; }
        .control-btn.video { background: #333; color: white; }
        .control-btn.video.active { background: #dc3545; }
        .control-btn.record { background: #333; color: white; }
        .control-btn.record.active { background: #dc3545; animation: pulse 1s infinite; }
        .control-btn.end { background: #dc3545; color: white; width: 80px; }
        .control-btn:hover { transform: scale(1.1); }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
        
        .sidebar {
            background: #16213e;
            padding: 20px;
            overflow-y: auto;
        }
        .sidebar h3 {
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #0066cc;
        }
        .patient-info {
            background: #1a1a2e;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        .patient-info p {
            margin-bottom: 10px;
            font-size: 14px;
        }
        .patient-info strong { color: #0066cc; }
        
        textarea {
            width: 100%;
            height: 150px;
            padding: 10px;
            border-radius: 5px;
            background: #1a1a2e;
            color: white;
            border: 1px solid #333;
            font-family: inherit;
            resize: vertical;
        }
        
        .recording-indicator {
            display: none;
            position: fixed;
            top: 70px;
            left: 20px;
            background: #dc3545;
            color: white;
            padding: 8px 15px;
            border-radius: 5px;
            font-size: 14px;
            z-index: 100;
        }
        .recording-indicator.active { display: flex; align-items: center; gap: 10px; }
        .recording-indicator::before {
            content: '';
            width: 12px;
            height: 12px;
            background: white;
            border-radius: 50%;
            animation: blink 1s infinite;
        }
        @keyframes blink { 50% { opacity: 0; } }
        
        .connecting {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 100%;
            text-align: center;
        }
        .spinner {
            width: 50px;
            height: 50px;
            border: 4px solid #333;
            border-top-color: #0066cc;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin-bottom: 20px;
        }
        @keyframes spin { to { transform: rotate(360deg); } }
        
        .upload-status {
            margin-top: 10px;
            padding: 10px;
            border-radius: 5px;
            font-size: 12px;
            display: none;
        }
        .upload-status.uploading { background: #fff3cd; color: #856404; display: block; }
        .upload-status.success { background: #d4edda; color: #155724; display: block; }
        .upload-status.error { background: #f8d7da; color: #721c24; display: block; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Videoconsulta con <?php echo htmlspecialchars($consultation['patient_name']); ?></h1>
        <div class="timer" id="timer">00:00:00</div>
    </div>
    
    <div class="recording-indicator" id="recordingIndicator">
        <span>GRABANDO</span>
    </div>
    
    <div class="main-container">
        <div class="video-area">
            <div class="video-grid" id="videoGrid">
                <div class="connecting" id="connectingOverlay">
                    <div class="spinner"></div>
                    <p>Conectando a la sala...</p>
                    <p id="connectionStatus"></p>
                </div>
                
                <div class="participant" id="localParticipant">
                    <video id="localVideo" autoplay playsinline muted></video>
                    <div class="name-tag"><?php echo htmlspecialchars($_SESSION['doctor_name']); ?> (Médico)</div>
                    <div class="local-indicator"></div>
                </div>
                
                <div class="participant" id="remoteParticipant">
                    <video id="remoteVideo" autoplay playsinline></video>
                    <div class="name-tag" id="remoteName">Esperando paciente...</div>
                </div>
            </div>
            
            <div class="controls">
                <button class="control-btn mute" id="muteBtn" onclick="toggleMute()">🔇</button>
                <button class="control-btn video" id="videoBtn" onclick="toggleVideo()">📹</button>
                <button class="control-btn record" id="recordBtn" onclick="toggleRecord()">⏺</button>
                <button class="control-btn end" onclick="endCall()">Fin</button>
            </div>
        </div>
        
        <div class="sidebar">
            <h3>Información del Paciente</h3>
            <div class="patient-info">
                <p><strong>Nombre:</strong> <?php echo htmlspecialchars($consultation['patient_name']); ?></p>
                <p><strong>Documento:</strong> <?php echo htmlspecialchars($consultation['document_id']); ?></p>
                <p><strong>Teléfono:</strong> <?php echo htmlspecialchars($consultation['phone'] ?? 'No disponible'); ?></p>
                <p><strong>Email:</strong> <?php echo htmlspecialchars($consultation['patient_email'] ?? 'No disponible'); ?></p>
                <p><strong>Fecha:</strong> <?php echo date('d/m/Y H:i', strtotime($consultation['scheduled_date'])); ?></p>
            </div>
            
            <h3>Notas de la Consulta</h3>
            <textarea id="consultationNotes" placeholder="Ingrese notas de la consulta..."></textarea>
            
            <h3>Grabación</h3>
            <div id="uploadStatus" class="upload-status"></div>
        </div>
    </div>
    
    <script>
        const SERVER_URL = 'ws://localhost:7880';
        const ROOM_NAME = '<?php echo $consultation['room_name']; ?>';
        const TOKEN = '<?php echo $token; ?>';
        const CONSULTATION_ID = <?php echo $consultation_id; ?>;
        
        let room;
        let isMuted = false;
        let isVideoOff = false;
        let isRecording = false;
        let mediaRecorder = null;
        let recordedChunks = [];
        let localStream = null;
        let startTime = Date.now();
        let timerInterval;
        
        // Timer
        timerInterval = setInterval(() => {
            const elapsed = Date.now() - startTime;
            const hours = Math.floor(elapsed / 3600000);
            const minutes = Math.floor((elapsed % 3600000) / 60000);
            const seconds = Math.floor((elapsed % 60000) / 1000);
            document.getElementById('timer').textContent = 
                String(hours).padStart(2, '0') + ':' +
                String(minutes).padStart(2, '0') + ':' +
                String(seconds).padStart(2, '0');
        }, 1000);
        
        // Request camera and microphone permissions first
        async function requestMediaPermissions() {
            try {
                localStream = await navigator.mediaDevices.getUserMedia({
                    video: true,
                    audio: true
                });
                const localVideo = document.getElementById('localVideo');
                localVideo.srcObject = localStream;
                document.getElementById('connectingOverlay').style.display = 'none';
                return true;
            } catch (error) {
                console.error('Error getting media:', error);
                document.getElementById('connectionStatus').textContent = 'Error: ' + error.message;
                alert('Error al acceder a la cámara/micrófono: ' + error.message);
                return false;
            }
        }
        
        // Connect to LiveKit
        async function connectToRoom() {
            try {
                const hasPermissions = await requestMediaPermissions();
                if (!hasPermissions) return;
                
                const lk = await import('https://cdn.jsdelivr.net/npm/livekit-client@2.5.5/+esm');
                const { Room, RoomEvent, Track } = lk;
                
                room = new Room({
                    adaptiveStream: true,
                    dynacast: true,
                    videoCaptureDefaults: {
                        resolution: { width: 1280, height: 720 }
                    }
                });
                
                room.on(RoomEvent.Connected, () => {
                    console.log('Connected to room');
                });
                
                room.on(RoomEvent.Disconnected, () => {
                    console.log('Disconnected from room');
                    if (isRecording) {
                        stopRecording();
                    }
                });
                
                room.on(RoomEvent.TrackSubscribed, (track, publication, participant) => {
                    console.log('Track subscribed:', track.kind);
                    if (track.kind === Track.Kind.Video) {
                        const remoteVideo = document.getElementById('remoteVideo');
                        track.attach(remoteVideo);
                        document.getElementById('remoteName').textContent = participant.name || participant.identity;
                    }
                    if (track.kind === Track.Kind.Audio) {
                        track.attach();
                    }
                });
                
                room.on(RoomEvent.TrackUnsubscribed, (track) => {
                    track.detach();
                });
                
                await room.connect(SERVER_URL, TOKEN);
                console.log('Connected to LiveKit');
                
                // Publish local tracks
                await room.localParticipant.setMicrophoneEnabled(true);
                await room.localParticipant.setCameraEnabled(true);
                
            } catch (error) {
                console.error('Error connecting:', error);
                document.getElementById('connectionStatus').textContent = 'Error: ' + error.message;
            }
        }
        
        function toggleMute() {
            if (room) {
                if (isMuted) {
                    room.localParticipant.setMicrophoneEnabled(true);
                    document.getElementById('muteBtn').textContent = '🔇';
                } else {
                    room.localParticipant.setMicrophoneEnabled(false);
                    document.getElementById('muteBtn').textContent = '🔊';
                }
                isMuted = !isMuted;
                document.getElementById('muteBtn').classList.toggle('active', isMuted);
            }
        }
        
        function toggleVideo() {
            if (room) {
                if (isVideoOff) {
                    room.localParticipant.setCameraEnabled(true);
                    document.getElementById('videoBtn').textContent = '📹';
                } else {
                    room.localParticipant.setCameraEnabled(false);
                    document.getElementById('videoBtn').textContent = '🎥';
                }
                isVideoOff = !isVideoOff;
                document.getElementById('videoBtn').classList.toggle('active', isVideoOff);
            }
        }
        
        function toggleRecord() {
            if (!isRecording) {
                startRecording();
            } else {
                stopRecording();
            }
        }
        
        function startRecording() {
            const localVideo = document.getElementById('localVideo');
            const stream = localVideo.srcObject;
            
            if (!stream) {
                alert('No hay video disponible para grabar');
                return;
            }
            
            recordedChunks = [];
            
            try {
                mediaRecorder = new MediaRecorder(stream, {
                    mimeType: 'video/webm;codecs=vp8,opus'
                });
            } catch (e) {
                console.error('MediaRecorder not supported:', e);
                alert('Su navegador no soporta grabación de video');
                return;
            }
            
            mediaRecorder.ondataavailable = (event) => {
                if (event.data.size > 0) {
                    recordedChunks.push(event.data);
                }
            };
            
            mediaRecorder.start(1000);
            isRecording = true;
            document.getElementById('recordBtn').classList.add('active');
            document.getElementById('recordingIndicator').classList.add('active');
        }
        
        function stopRecording() {
            if (mediaRecorder && mediaRecorder.state !== 'inactive') {
                mediaRecorder.stop();
            }
            isRecording = false;
            document.getElementById('recordBtn').classList.remove('active');
            document.getElementById('recordingIndicator').classList.remove('active');
            
            if (recordedChunks.length > 0) {
                uploadRecording();
            }
        }
        
        function uploadRecording() {
            const blob = new Blob(recordedChunks, { type: 'video/webm' });
            const fileName = 'recording_<?php echo $consultation_id; ?>_' + Date.now() + '.webm';
            
            const statusDiv = document.getElementById('uploadStatus');
            statusDiv.className = 'upload-status uploading';
            statusDiv.textContent = 'Guardando grabación...';
            
            const formData = new FormData();
            formData.append('file', blob, fileName);
            formData.append('consultation_id', CONSULTATION_ID);
            
            fetch('save_recording.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    statusDiv.className = 'upload-status success';
                    statusDiv.textContent = 'Grabación guardada exitosamente';
                } else {
                    statusDiv.className = 'upload-status error';
                    statusDiv.textContent = 'Error: ' + data.error;
                }
            })
            .catch(error => {
                statusDiv.className = 'upload-status error';
                statusDiv.textContent = 'Error al subir: ' + error.message;
            });
        }
        
        function endCall() {
            if (isRecording) {
                stopRecording();
            }
            
            if (room) {
                room.disconnect();
            }
            
            if (localStream) {
                localStream.getTracks().forEach(track => track.stop());
            }
            
            clearInterval(timerInterval);
            
            const duration = Math.round((Date.now() - startTime) / 60000);
            const notes = document.getElementById('consultationNotes').value;
            
            fetch('update_consultation.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    id: CONSULTATION_ID,
                    duration: duration,
                    notes: notes,
                    status: 'completed'
                })
            })
            .then(response => response.json())
            .then(data => {
                window.location.href = 'dashboard.php';
            })
            .catch(error => {
                window.location.href = 'dashboard.php';
            });
        }
        
        // Start connection
        connectToRoom();
    </script>
</body>
</html>

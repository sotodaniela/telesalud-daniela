<?php
session_start();

if (!isset($_SESSION['patient_consultation_id'])) {
    header('Location: patient_join.php');
    exit;
}

$consultation_id = $_SESSION['patient_consultation_id'];
$patient_name = $_SESSION['patient_name'];
$room_name = $_SESSION['patient_room'];

$conn = new mysqli('telehealth-db', 'telehealth', 'telehealth123', 'telehealth');
$consultation = $conn->query("
    SELECT c.*, CONCAT(d.full_name, ' - ', d.specialty) as doctor_name
    FROM consultations c
    JOIN doctors d ON c.doctor_id = d.id
    WHERE c.id = $consultation_id
")->fetch_assoc();
$conn->close();

if (!$consultation) {
    header('Location: patient_join.php');
    exit;
}

// Get LiveKit config
$livekit_url = 'ws://localhost:7880';
$api_key = 'APInewKey123';
$api_secret = 'NewSecret456789012345678901234567890';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Videoconsulta - Telesalud</title>
    <style>
        * { 
            box-sizing: border-box; 
            margin: 0; 
            padding: 0;
            -webkit-tap-highlight-color: transparent;
        }
        html, body {
            height: 100%;
            overflow: hidden;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
            background: #0f0f1a;
            color: white;
        }
        
        /* Waiting Screen */
        .waiting-screen {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, #1a5276 0%, #2874a6 100%);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            color: white;
            z-index: 1000;
            padding: 20px;
            text-align: center;
        }
        .waiting-screen.hidden {
            display: none;
        }
        .waiting-logo {
            width: 80px;
            height: 80px;
            background: rgba(255,255,255,0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 25px;
        }
        .waiting-logo svg {
            width: 50px;
            height: 50px;
            fill: white;
        }
        .waiting-screen h2 {
            font-size: 22px;
            margin-bottom: 10px;
        }
        .waiting-screen p {
            opacity: 0.9;
            font-size: 15px;
        }
        .spinner {
            width: 40px;
            height: 40px;
            border: 3px solid rgba(255,255,255,0.3);
            border-top-color: white;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin-bottom: 20px;
        }
        @keyframes spin { to { transform: rotate(360deg); } }
        
        /* Header */
        .header {
            background: linear-gradient(135deg, #1a5276, #2874a6);
            padding: 12px 16px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 100;
        }
        .header h1 {
            font-size: 16px;
            font-weight: 600;
        }
        .header .doctor-info {
            text-align: right;
        }
        .header .doctor-info .label {
            font-size: 10px;
            opacity: 0.8;
            display: block;
        }
        .header .doctor-info .name {
            font-size: 14px;
            font-weight: 500;
        }
        
        /* Video Container */
        .video-container {
            height: 100vh;
            display: flex;
            flex-direction: column;
            padding-top: 50px;
        }
        
        /* Remote Video (Doctor) */
        .remote-video-wrapper {
            flex: 1;
            position: relative;
            background: #16213e;
            min-height: 300px;
        }
        .remote-video-wrapper video {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .remote-placeholder {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            text-align: center;
            color: rgba(255,255,255,0.5);
        }
        .remote-placeholder .icon {
            width: 80px;
            height: 80px;
            background: rgba(255,255,255,0.1);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
        }
        .remote-placeholder svg {
            width: 40px;
            height: 40px;
            fill: rgba(255,255,255,0.5);
        }
        .remote-name-tag {
            position: absolute;
            bottom: 15px;
            left: 15px;
            background: rgba(0,0,0,0.7);
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 13px;
        }
        
        /* Local Video (Patient) */
        .local-video-wrapper {
            position: fixed;
            bottom: 100px;
            right: 15px;
            width: 100px;
            height: 140px;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 5px 20px rgba(0,0,0,0.5);
            background: #16213e;
        }
        .local-video-wrapper video {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transform: scaleX(-1);
        }
        .local-placeholder {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            text-align: center;
        }
        .local-placeholder svg {
            width: 30px;
            height: 30px;
            fill: rgba(255,255,255,0.5);
        }
        
        /* Controls */
        .controls {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: linear-gradient(to top, #0f0f1a, transparent);
            padding: 20px;
            display: flex;
            justify-content: center;
            gap: 15px;
            padding-bottom: 35px;
        }
        .control-btn {
            width: 55px;
            height: 55px;
            border-radius: 50%;
            border: none;
            cursor: pointer;
            font-size: 22px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
        }
        .control-btn:active {
            transform: scale(0.95);
        }
        .control-btn.mute, .control-btn.video {
            background: rgba(255,255,255,0.15);
            color: white;
        }
        .control-btn.mute.muted, .control-btn.video.off {
            background: #dc3545;
        }
        .control-btn.end {
            background: #dc3545;
            color: white;
        }
        .control-btn.network {
            background: rgba(255,255,255,0.15);
            font-size: 12px;
            color: #28a745;
        }
        
        /* Connection Status */
        .connection-status {
            position: fixed;
            top: 60px;
            left: 15px;
            background: rgba(0,0,0,0.7);
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 11px;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .connection-status .dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #28a745;
        }
        .connection-status.disconnected .dot {
            background: #dc3545;
        }
        
        /* Error Screen */
        .error-screen {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, #c0392b, #e74c3c);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            color: white;
            padding: 20px;
            text-align: center;
            z-index: 1000;
        }
        .error-screen.hidden {
            display: none;
        }
        .error-screen h2 {
            font-size: 22px;
            margin-bottom: 10px;
        }
        .error-screen p {
            opacity: 0.9;
            margin-bottom: 20px;
        }
        .btn-back {
            background: white;
            color: #c0392b;
            padding: 12px 30px;
            border: none;
            border-radius: 25px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
        }
        
        /* Responsive adjustments */
        @media (max-width: 480px) {
            .local-video-wrapper {
                width: 80px;
                height: 110px;
                bottom: 110px;
                right: 10px;
            }
            .control-btn {
                width: 50px;
                height: 50px;
                font-size: 20px;
            }
        }
    </style>
</head>
<body>
    <!-- Waiting Screen -->
    <div class="waiting-screen" id="waitingScreen">
        <div class="waiting-logo">
            <svg viewBox="0 0 24 24"><path d="M17 10.5V7c0-.55-.45-1-1-1H4c-.55 0-1 .45-1 1v10c0 .55.45 1 1 1h12c.55 0 1-.45 1-1v-3.5l4 4v-11l-4 4zM14 13h-3v3H9v-3H6v-2h3V8h2v3h3v2z"/></svg>
        </div>
        <div class="spinner"></div>
        <h2>Conectando...</h2>
        <p>Aguarde mientras establecemos la videoconsulta</p>
    </div>
    
    <!-- Error Screen -->
    <div class="error-screen hidden" id="errorScreen">
        <h2>⚠️ Error de Conexión</h2>
        <p id="errorMessage">No se pudo conectar al servidor</p>
        <button class="btn-back" onclick="window.location.href='patient_join.php'">Volver al inicio</button>
    </div>
    
    <!-- Header -->
    <div class="header">
        <h1>Telesalud</h1>
        <div class="doctor-info">
            <span class="label">Su médico</span>
            <span class="name"><?php echo htmlspecialchars($consultation['doctor_name']); ?></span>
        </div>
    </div>
    
    <!-- Connection Status -->
    <div class="connection-status" id="connectionStatus">
        <div class="dot"></div>
        <span id="statusText">Conectando</span>
    </div>
    
    <!-- Video Container -->
    <div class="video-container">
        <div class="remote-video-wrapper">
            <video id="remoteVideo" autoplay playsinline></video>
            <div class="remote-placeholder" id="remotePlaceholder">
                <div class="icon">
                    <svg viewBox="0 0 24 24"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>
                </div>
                <p>El médico aún no se ha conectado</p>
            </div>
            <div class="remote-name-tag" id="remoteName">Esperando médico...</div>
        </div>
    </div>
    
    <!-- Local Video -->
    <div class="local-video-wrapper">
        <video id="localVideo" autoplay playsinline muted></video>
        <div class="local-placeholder" id="localPlaceholder">
            <svg viewBox="0 0 24 24"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>
        </div>
    </div>
    
    <!-- Controls -->
    <div class="controls">
        <button class="control-btn mute" id="muteBtn" onclick="toggleMute()">🔇</button>
        <button class="control-btn video" id="videoBtn" onclick="toggleVideo()">📹</button>
        <button class="control-btn network" id="networkBtn" title="Estado de red">📶</button>
        <button class="control-btn end" onclick="endCall()">📞</button>
    </div>
    
    <script type="module">
        import { Room, RoomEvent, Track, ConnectionState } from 'https://cdn.jsdelivr.net/npm/livekit-client@2.5.5/+esm';
        
        const SERVER_URL = '<?php echo $livekit_url; ?>';
        const ROOM_NAME = '<?php echo $room_name; ?>';
        const PATIENT_NAME = '<?php echo addslashes($patient_name); ?>';
        const CONSULTATION_ID = <?php echo $consultation_id; ?>;
        
        let room;
        let isMuted = false;
        let isVideoOff = false;
        let localStream = null;
        
        function updateConnectionStatus(state, text) {
            const statusEl = document.getElementById('connectionStatus');
            const textEl = document.getElementById('statusText');
            statusEl.classList.remove('disconnected');
            if (state === 'disconnected') {
                statusEl.classList.add('disconnected');
            }
            textEl.textContent = text;
        }
        
        async function generateToken() {
            const header = { alg: 'HS256', typ: 'JWT' };
            const payload = {
                iss: '<?php echo $api_key; ?>',
                sub: 'patient_' + CONSULTATION_ID,
                room: ROOM_NAME,
                name: PATIENT_NAME,
                exp: Math.floor(Date.now() / 1000) + 7200,
                video: {
                    canPublish: true,
                    canSubscribe: true,
                    canPublishData: true
                }
            };
            
            const headerB64 = btoa(JSON.stringify(header)).replace(/\+/g, '-').replace(/\//g, '_').replace(/=/g, '');
            const payloadB64 = btoa(JSON.stringify(payload)).replace(/\+/g, '-').replace(/\//g, '_').replace(/=/g, '');
            const message = headerB64 + '.' + payloadB64;
            
            const encoder = new TextEncoder();
            const keyData = encoder.encode('<?php echo $api_secret; ?>');
            const messageData = encoder.encode(message);
            
            const cryptoKey = await crypto.subtle.importKey('raw', keyData, { name: 'HMAC', hash: 'SHA-256' }, false, ['sign']);
            const signature = await crypto.subtle.sign('HMAC', cryptoKey, messageData);
            const signatureB64 = btoa(String.fromCharCode(...new Uint8Array(signature))).replace(/\+/g, '-').replace(/\//g, '_').replace(/=/g, '');
            
            return message + '.' + signatureB64;
        }
        
        async function connectToRoom() {
            try {
                updateConnectionStatus('connecting', 'Conectando...');
                
                localStream = await navigator.mediaDevices.getUserMedia({ 
                    video: { width: { ideal: 1280 }, height: { ideal: 720 }, facingMode: 'user' }, 
                    audio: true 
                });
                document.getElementById('localVideo').srcObject = localStream;
                document.getElementById('localPlaceholder').style.display = 'none';
                
                const token = await generateToken();
                
                room = new Room({ 
                    adaptiveStream: true, 
                    dynacast: true,
                    videoCaptureDefaults: {
                        resolution: { width: 1280, height: 720 }
                    }
                });
                
                room.on(RoomEvent.Connected, () => {
                    document.getElementById('waitingScreen').classList.add('hidden');
                    updateConnectionStatus('connected', 'Conectado');
                });
                
                room.on(RoomEvent.Disconnected, () => {
                    updateConnectionStatus('disconnected', 'Desconectado');
                });
                
                room.on(RoomEvent.Reconnecting, () => {
                    updateConnectionStatus('connecting', 'Reconectando...');
                });
                
                room.on(RoomEvent.TrackSubscribed, (track, publication, participant) => {
                    if (track.kind === Track.Kind.Video) {
                        const remoteVideo = document.getElementById('remoteVideo');
                        remoteVideo.srcObject = track.attach();
                        remoteVideo.style.transform = 'none';
                        document.getElementById('remotePlaceholder').style.display = 'none';
                        document.getElementById('remoteName').textContent = participant.name || participant.identity || 'Médico';
                        document.getElementById('remoteName').style.display = 'block';
                    }
                    if (track.kind === Track.Kind.Audio) {
                        track.attach();
                    }
                });
                
                room.on(RoomEvent.TrackUnsubscribed, (track) => {
                    if (track.kind === Track.Kind.Video) {
                        document.getElementById('remoteVideo').srcObject = null;
                        document.getElementById('remotePlaceholder').style.display = 'flex';
                        document.getElementById('remoteName').textContent = 'Médico desconectado';
                    }
                });
                
                room.on(RoomEvent.ActiveSpeakersChanged, (speakers) => {
                    const networkBtn = document.getElementById('networkBtn');
                    if (speakers.length > 0) {
                        networkBtn.style.color = '#28a745';
                    }
                });
                
                await room.connect(SERVER_URL, token);
                await room.localParticipant.setMicrophoneEnabled(true);
                await room.localParticipant.setCameraEnabled(true);
                
            } catch (error) {
                console.error('Error:', error);
                document.getElementById('waitingScreen').classList.add('hidden');
                document.getElementById('errorScreen').classList.remove('hidden');
                document.getElementById('errorMessage').textContent = error.message || 'No se pudo conectar al servidor de video';
                updateConnectionStatus('disconnected', 'Error');
            }
        }
        
        window.toggleMute = function() {
            if (room && localStream) {
                isMuted = !isMuted;
                room.localParticipant.setMicrophoneEnabled(!isMuted);
                const btn = document.getElementById('muteBtn');
                btn.classList.toggle('muted', isMuted);
                btn.textContent = isMuted ? '🔇' : '🔊';
            }
        };
        
        window.toggleVideo = function() {
            if (room && localStream) {
                isVideoOff = !isVideoOff;
                room.localParticipant.setCameraEnabled(!isVideoOff);
                const btn = document.getElementById('videoBtn');
                btn.classList.toggle('off', isVideoOff);
                btn.textContent = isVideoOff ? '📷' : '📹';
                document.getElementById('localPlaceholder').style.display = isVideoOff ? 'flex' : 'none';
            }
        };
        
        window.endCall = function() {
            if (room) room.disconnect();
            if (localStream) localStream.getTracks().forEach(t => t.stop());
            window.location.href = 'patient_join.php';
        };
        
        // Auto cleanup on page unload
        window.addEventListener('beforeunload', () => {
            if (room) room.disconnect();
            if (localStream) localStream.getTracks().forEach(t => t.stop());
        });
        
        // Start connection
        connectToRoom();
    </script>
</body>
</html>

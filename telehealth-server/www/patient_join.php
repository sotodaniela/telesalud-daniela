<?php
session_start();

$error = '';
$consultation = null;

// Database connection
$conn = new mysqli('telehealth-db', 'telehealth', 'telehealth123', 'telehealth');

// Check if accessing via token
if (isset($_GET['token']) && !empty($_GET['token'])) {
    $token = $conn->real_escape_string($_GET['token']);
    $consultation = $conn->query("
        SELECT c.*, CONCAT(p.first_name, ' ', p.last_name) as patient_name, p.id_number,
               CONCAT(d.full_name, ' - ', d.specialty) as doctor_name
        FROM consultations c
        JOIN patients p ON c.patient_id = p.id
        JOIN doctors d ON c.doctor_id = d.id
        WHERE c.access_token = '$token' AND c.status IN ('scheduled', 'in_progress')
    ")->fetch_assoc();
    
    if ($consultation) {
        $_SESSION['patient_consultation_id'] = $consultation['id'];
        $_SESSION['patient_name'] = $consultation['patient_name'];
        $_SESSION['patient_room'] = $consultation['room_name'];
        $_SESSION['patient_token'] = $token;
        header('Location: patient_room.php');
        exit;
    } else {
        $error = 'El enlace de acceso no es válido o ha expirado';
    }
}

// Form submission (legacy method)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $consultation_id = intval($_POST['consultation_id']);
    $patient_name = $conn->real_escape_string($_POST['patient_name']);
    
    $consultation = $conn->query("
        SELECT c.*, CONCAT(p.first_name, ' ', p.last_name) as patient_name, p.id_number,
               CONCAT(d.full_name, ' - ', d.specialty) as doctor_name
        FROM consultations c
        JOIN patients p ON c.patient_id = p.id
        JOIN doctors d ON c.doctor_id = d.id
        WHERE c.id = $consultation_id AND c.status IN ('scheduled', 'in_progress')
    ")->fetch_assoc();
    
    if (!$consultation) {
        $error = 'No se encontró la consulta o ya no está disponible';
    } else {
        $_SESSION['patient_consultation_id'] = $consultation_id;
        $_SESSION['patient_name'] = $patient_name;
        $_SESSION['patient_room'] = $consultation['room_name'];
        header('Location: patient_room.php');
        exit;
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Acceso Paciente - Telesalud</title>
    <style>
        * { 
            box-sizing: border-box; 
            margin: 0; 
            padding: 0;
            -webkit-tap-highlight-color: transparent;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
            background: linear-gradient(135deg, #1a5276 0%, #2874a6 50%, #3498db 100%);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .container {
            background: white;
            padding: 30px 25px;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            max-width: 400px;
            width: 100%;
            text-align: center;
        }
        .logo {
            margin-bottom: 25px;
        }
        .logo-icon {
            width: 70px;
            height: 70px;
            background: linear-gradient(135deg, #1a5276, #2874a6);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
        }
        .logo-icon svg {
            width: 40px;
            height: 40px;
            fill: white;
        }
        .logo h1 {
            color: #1a5276;
            font-size: 22px;
            font-weight: 700;
            margin-bottom: 5px;
        }
        .logo p {
            color: #666;
            font-size: 14px;
        }
        .error {
            background: linear-gradient(135deg, #ff6b6b, #ee5a5a);
            color: white;
            padding: 12px 15px;
            border-radius: 12px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        .info-box {
            background: linear-gradient(135deg, #e8f4fd, #d4e9f7);
            padding: 15px;
            border-radius: 12px;
            margin-bottom: 20px;
            text-align: left;
        }
        .info-box h3 {
            color: #1a5276;
            font-size: 14px;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .info-box h3 svg {
            width: 18px;
            height: 18px;
            fill: #1a5276;
        }
        .info-box p {
            color: #444;
            font-size: 13px;
            line-height: 1.5;
        }
        .divider {
            display: flex;
            align-items: center;
            margin: 25px 0;
            color: #999;
            font-size: 12px;
        }
        .divider::before, .divider::after {
            content: '';
            flex: 1;
            height: 1px;
            background: #ddd;
        }
        .divider span {
            padding: 0 15px;
        }
        .form-group {
            margin-bottom: 15px;
            text-align: left;
        }
        .form-group label {
            display: block;
            margin-bottom: 6px;
            color: #333;
            font-weight: 600;
            font-size: 14px;
        }
        .form-group input {
            width: 100%;
            padding: 14px 16px;
            border: 2px solid #e0e0e0;
            border-radius: 12px;
            font-size: 16px;
            transition: border-color 0.3s, box-shadow 0.3s;
        }
        .form-group input:focus {
            outline: none;
            border-color: #3498db;
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.2);
        }
        .btn {
            width: 100%;
            background: linear-gradient(135deg, #1a5276, #2874a6);
            color: white;
            padding: 16px;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .btn:active {
            transform: scale(0.98);
        }
        .btn:hover {
            box-shadow: 0 5px 20px rgba(26, 82, 118, 0.4);
        }
        .footer {
            margin-top: 25px;
            color: rgba(255,255,255,0.7);
            font-size: 12px;
            text-align: center;
        }
        .footer a {
            color: white;
            text-decoration: none;
        }
        
        /* Demo mode indicator */
        .demo-hint {
            background: #fff3cd;
            border: 1px solid #ffc107;
            padding: 10px;
            border-radius: 8px;
            margin-bottom: 15px;
            font-size: 12px;
            color: #856404;
        }
        
        /* Responsive */
        @media (max-width: 380px) {
            .container {
                padding: 25px 20px;
            }
            .logo h1 {
                font-size: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="logo">
            <div class="logo-icon">
                <svg viewBox="0 0 24 24"><path d="M17 10.5V7c0-.55-.45-1-1-1H4c-.55 0-1 .45-1 1v10c0 .55.45 1 1 1h12c.55 0 1-.45 1-1v-3.5l4 4v-11l-4 4zM14 13h-3v3H9v-3H6v-2h3V8h2v3h3v2z"/></svg>
            </div>
            <h1>Videoconsulta</h1>
            <p>Acceso para pacientes</p>
        </div>
        
        <?php if ($error): ?>
        <div class="error">⚠️ <?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <div class="demo-hint">
            <strong>Modo prueba:</strong> Si tiene un código de acceso, úselo a continuación.
        </div>
        
        <form method="POST" action="">
            <div class="form-group">
                <label>Código de Consulta</label>
                <input type="text" name="consultation_id" required placeholder="Ej: 1, 2, 3..." inputmode="numeric">
            </div>
            
            <div class="form-group">
                <label>Su Nombre Completo</label>
                <input type="text" name="patient_name" required placeholder="Ingrese su nombre" autocomplete="name">
            </div>
            
            <button type="submit" class="btn">Ingresar a la Videoconsulta</button>
        </form>
    </div>
    
    <div class="footer">
        <p>Sistema de Telesalud</p>
    </div>
</body>
</html>

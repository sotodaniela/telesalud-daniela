<?php
session_start();

if (!isset($_SESSION['doctor_id'])) {
    header('Location: login.php');
    exit;
}

$conn = new mysqli('telehealth-db', 'telehealth', 'telehealth123', 'telehealth');

$consultation_id = $_GET['id'] ?? '';
$doctor_id = $_SESSION['doctor_id'];

$stmt = $conn->prepare('
    SELECT c.*, CONCAT(p.first_name, " ", p.last_name) as patient_name, p.id_number as document_id, p.phone, p.email as patient_email, p.birth_date
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
$conn->close();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detalle Consulta - PAHO Telesalud</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Segoe UI', Tahoma, sans-serif;
            background: #f5f5f5;
        }
        .header {
            background: #0066cc;
            color: white;
            padding: 15px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .header h1 { font-size: 20px; }
        .back-link {
            color: white;
            text-decoration: none;
            padding: 8px 15px;
            background: rgba(255,255,255,0.2);
            border-radius: 5px;
        }
        
        .container {
            max-width: 900px;
            margin: 30px auto;
            padding: 0 20px;
        }
        
        .card {
            background: white;
            border-radius: 10px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .card h2 {
            color: #333;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #0066cc;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
        }
        .info-item {
            padding: 15px;
            background: #f9f9f9;
            border-radius: 8px;
        }
        .info-item label {
            display: block;
            font-weight: 600;
            color: #666;
            font-size: 12px;
            margin-bottom: 5px;
        }
        .info-item span {
            color: #333;
            font-size: 16px;
        }
        
        .status {
            display: inline-block;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 14px;
        }
        .status.scheduled { background: #fff3cd; color: #856404; }
        .status.in_progress { background: #cce5ff; color: #004085; }
        .status.completed { background: #d4edda; color: #155724; }
        .status.cancelled { background: #f8d7da; color: #721c24; }
        
        .actions {
            display: flex;
            gap: 15px;
        }
        .btn {
            padding: 12px 25px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
        }
        .btn-success { background: #28a745; color: white; }
        .btn-primary { background: #0066cc; color: white; }
        .btn-secondary { background: #6c757d; color: white; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Detalle de Videoconsulta</h1>
        <a href="dashboard.php" class="back-link">← Volver al Dashboard</a>
    </div>
    
    <div class="container">
        <div class="card">
            <h2>Información de la Consulta</h2>
            <div class="info-grid">
                <div class="info-item">
                    <label>ID CONSULTA</label>
                    <span>#<?php echo $consultation['id']; ?></span>
                </div>
                <div class="info-item">
                    <label>SALA</label>
                    <span><?php echo htmlspecialchars($consultation['room_name']); ?></span>
                </div>
                <div class="info-item">
                    <label>FECHA/HORA</label>
                    <span><?php echo date('d/m/Y H:i', strtotime($consultation['scheduled_date'])); ?></span>
                </div>
                <div class="info-item">
                    <label>DURACIÓN</label>
                    <span><?php echo $consultation['duration_minutes']; ?> minutos</span>
                </div>
                <div class="info-item">
                    <label>ESTADO</label>
                    <span class="status <?php echo $consultation['status']; ?>"><?php echo ucfirst(str_replace('_', ' ', $consultation['status'])); ?></span>
                </div>
                <div class="info-item">
                    <label>FECHA CREACIÓN</label>
                    <span><?php echo date('d/m/Y H:i', strtotime($consultation['created_at'])); ?></span>
                </div>
            </div>
            
            <?php if ($consultation['notes']): ?>
            <div style="margin-top: 20px;">
                <label style="font-weight: 600; color: #666; font-size: 12px;">NOTAS</label>
                <p style="margin-top: 5px; color: #333;"><?php echo htmlspecialchars($consultation['notes']); ?></p>
            </div>
            <?php endif; ?>
        </div>
        
        <div class="card">
            <h2>Información del Paciente</h2>
            <div class="info-grid">
                <div class="info-item">
                    <label>NOMBRE COMPLETO</label>
                    <span><?php echo htmlspecialchars($consultation['patient_name']); ?></span>
                </div>
                <div class="info-item">
                    <label>DOCUMENTO</label>
                    <span><?php echo htmlspecialchars($consultation['document_id']); ?></span>
                </div>
                <div class="info-item">
                    <label>TELÉFONO</label>
                    <span><?php echo htmlspecialchars($consultation['phone'] ?? 'No disponible'); ?></span>
                </div>
                <div class="info-item">
                    <label>EMAIL</label>
                    <span><?php echo htmlspecialchars($consultation['patient_email'] ?? 'No disponible'); ?></span>
                </div>
            </div>
        </div>
        
        <div class="actions">
            <?php if ($consultation['status'] === 'scheduled'): ?>
            <a href="join_consultation.php?id=<?php echo $consultation['id']; ?>" class="btn btn-success">Iniciar Videoconsulta</a>
            <?php elseif ($consultation['status'] === 'completed' && $consultation['recording_path']): ?>
            <a href="watch_recording.php?id=<?php echo $consultation['id']; ?>" class="btn btn-primary">Ver Grabación</a>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>

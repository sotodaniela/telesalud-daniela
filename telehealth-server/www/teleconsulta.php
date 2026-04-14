<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$conn = new mysqli('telehealth-db', 'telehealth', 'telehealth123', 'telehealth');
$user_id = $_SESSION['user_id'];
$message = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $patient_id = intval($_POST['patient_id']);
    $consultation_id = intval($_POST['consultation_id'] ?? 0);
    $video_mode = $_POST['video_mode'] ?? '1';
    $skip_video = ($video_mode === '0') ? '&skip_video=1' : '';
    
    if ($consultation_id > 0) {
        header("Location: teleconsulta_session.php?id=$consultation_id$skip_video");
    } else {
        header("Location: teleconsulta_session.php?patient_id=$patient_id$skip_video");
    }
    exit;
}

// Get patients for dropdown
$patients = $conn->query("SELECT id, CONCAT(first_name, ' ', last_name) as full_name, id_number FROM patients WHERE is_active = 1 ORDER BY last_name, first_name");

// Get upcoming consultations
$upcoming = $conn->query("
    SELECT c.id, c.patient_id, c.scheduled_date, CONCAT(p.first_name, ' ', p.last_name) as patient_name, c.status
    FROM consultations c
    JOIN patients p ON c.patient_id = p.id
    WHERE c.doctor_id = $user_id 
    AND c.status = 'scheduled'
    AND c.scheduled_date >= NOW()
    ORDER BY c.scheduled_date ASC
    LIMIT 10
");
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teleconsulta - PAHO Telesalud</title>
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
        }
        .nav a:hover { background: rgba(255,255,255,0.3); }
        
        .container {
            max-width: 900px;
            margin: 30px auto;
            padding: 0 20px;
        }
        
        .card {
            background: white;
            border-radius: 10px;
            padding: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .card h2 {
            color: #333;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #0066cc;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            color: #555;
            font-weight: 500;
        }
        .form-group select, .form-group input {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
        }
        .form-group select:focus, .form-group input:focus {
            outline: none;
            border-color: #0066cc;
        }
        
        .btn {
            background: #6f42c1;
            color: white;
            padding: 12px 25px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            cursor: pointer;
            width: 100%;
        }
        .btn:hover { background: #5a32a3; }
        
        .consultation-list {
            margin-top: 20px;
        }
        .consultation-item {
            background: #f9f9f9;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .consultation-item:hover {
            background: #e9e9ff;
        }
        .consultation-item .info {
            flex: 1;
        }
        .consultation-item .patient {
            font-weight: bold;
            color: #333;
        }
        .consultation-item .date {
            color: #666;
            font-size: 13px;
        }
        .consultation-item .btn {
            width: auto;
            padding: 8px 15px;
            background: #28a745;
        }
        .consultation-item .btn:hover { background: #218838; }
        
        .empty-state {
            text-align: center;
            padding: 40px;
            color: #999;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Teleconsulta - PAHO Telesalud</h1>
        <div class="nav">
            <a href="dashboard.php">Dashboard</a>
            <a href="schedule.php">Agendamiento</a>
            <a href="patients.php">Pacientes</a>
        </div>
    </div>
    
    <div class="container">
        <div class="card">
            <h2>Iniciar Nueva Teleconsulta</h2>
            
            <form method="POST" action="">
                <div class="form-group">
                    <label>Seleccionar Paciente</label>
                    <select name="patient_id" required>
                        <option value="">-- Seleccionar paciente --</option>
                        <?php while ($patient = $patients->fetch_assoc()): ?>
                        <option value="<?php echo $patient['id']; ?>">
                            <?php echo htmlspecialchars($patient['full_name'] . ' - ' . $patient['id_number']); ?>
                        </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div style="display: flex; gap: 10px;">
                    <button type="submit" class="btn" name="video_mode" value="1" style="flex: 1;">📹 Con Video</button>
                    <button type="submit" class="btn" name="video_mode" value="0" style="flex: 1; background: #6c757d;">📋 Solo HC (Sin Video)</button>
                </div>
            </form>
        </div>
        
        <div class="card">
            <h2>Citas Programadas</h2>
            
            <?php if ($upcoming->num_rows > 0): ?>
            <div class="consultation-list">
                <?php while ($c = $upcoming->fetch_assoc()): ?>
                <div class="consultation-item">
                    <div class="info">
                        <div class="patient"><?php echo htmlspecialchars($c['patient_name']); ?></div>
                        <div class="date"><?php echo date('d/m/Y H:i', strtotime($c['scheduled_date'])); ?></div>
                    </div>
                    <form method="POST" action="" style="display: inline;">
                        <input type="hidden" name="consultation_id" value="<?php echo $c['id']; ?>">
                        <input type="hidden" name="patient_id" value="<?php echo $c['patient_id']; ?>">
                        <input type="hidden" name="video_mode" value="0">
                        <button type="submit" class="btn" style="background: #6c757d;">Solo HC</button>
                    </form>
                </div>
                <?php endwhile; ?>
            </div>
            <?php else: ?>
            <div class="empty-state">
                <p>No hay citas programadas</p>
                <p style="margin-top: 10px;"><a href="schedule.php" style="color: #0066cc;">Agendar nueva cita</a></p>
            </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
<?php $conn->close(); ?>

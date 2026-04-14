<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$conn = new mysqli('telehealth-db', 'telehealth', 'telehealth123', 'telehealth');
$error = '';
$success = '';

// Get patients
$patients = $conn->query('SELECT id, CONCAT(first_name, " ", last_name) as full_name, id_number as document_id FROM patients WHERE is_active = 1 ORDER BY full_name');

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $patient_id = $_POST['patient_id'] ?? '';
    $scheduled_date = $_POST['scheduled_date'] ?? '';
    $scheduled_time = $_POST['scheduled_time'] ?? '';
    $notes = $_POST['notes'] ?? '';
    
    if (empty($patient_id) || empty($scheduled_date) || empty($scheduled_time)) {
        $error = 'Por favor complete todos los campos obligatorios';
    } else {
        // Generate room name
        $room_name = 'vc_' . date('YmdHis') . '_' . rand(1000, 9999);
        $scheduled_datetime = $scheduled_date . ' ' . $scheduled_time . ':00';
        
        $stmt = $conn->prepare('INSERT INTO consultations (room_name, doctor_id, patient_id, scheduled_date, notes, status) VALUES (?, ?, ?, ?, ?, ?)');
        $status = 'scheduled';
        $stmt->bind_param('siisss', $room_name, $_SESSION['user_id'], $patient_id, $scheduled_datetime, $notes, $status);
        
        if ($stmt->execute()) {
            $success = 'Videoconsulta creada exitosamente. ID: ' . $stmt->insert_id;
        } else {
            $error = 'Error al crear la videoconsulta';
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nueva Videoconsulta - PAHO Telesalud</title>
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
        .back-link:hover { background: rgba(255,255,255,0.3); }
        
        .container {
            max-width: 800px;
            margin: 30px auto;
            padding: 0 20px;
        }
        
        .card {
            background: white;
            border-radius: 10px;
            padding: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .card h2 {
            color: #333;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid #0066cc;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 500;
        }
        .form-group label .required {
            color: #dc3545;
        }
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
            font-family: inherit;
        }
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #0066cc;
        }
        .form-group textarea {
            min-height: 100px;
            resize: vertical;
        }
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        .btn {
            background: #0066cc;
            color: white;
            padding: 14px 30px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            cursor: pointer;
            transition: background 0.3s;
        }
        .btn:hover { background: #0056b3; }
        
        .error {
            background: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .success {
            background: #d4edda;
            color: #155724;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Nueva Videoconsulta</h1>
        <a href="dashboard.php" class="back-link">← Volver al Dashboard</a>
    </div>
    
    <div class="container">
        <div class="card">
            <h2>Crear Nueva Videoconsulta</h2>
            
            <?php if ($error): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
            <div class="success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="form-group">
                    <label for="patient_id">Paciente <span class="required">*</span></label>
                    <select id="patient_id" name="patient_id" required>
                        <option value="">Seleccione un paciente</option>
                        <?php while ($patient = $patients->fetch_assoc()): ?>
                        <option value="<?php echo $patient['id']; ?>">
                            <?php echo htmlspecialchars($patient['full_name'] . ' (' . $patient['document_id'] . ')'); ?>
                        </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="scheduled_date">Fecha <span class="required">*</span></label>
                        <input type="date" id="scheduled_date" name="scheduled_date" required value="<?php echo date('Y-m-d'); ?>">
                    </div>
                    <div class="form-group">
                        <label for="scheduled_time">Hora <span class="required">*</span></label>
                        <input type="time" id="scheduled_time" name="scheduled_time" required value="<?php echo date('H:i'); ?>">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="notes">Notas / Motivo de la Consulta</label>
                    <textarea id="notes" name="notes" placeholder="Ingrese el motivo de la consulta o notas relevantes..."></textarea>
                </div>
                
                <button type="submit" class="btn">Crear Videoconsulta</button>
            </form>
        </div>
    </div>
</body>
</html>
<?php $conn->close(); ?>

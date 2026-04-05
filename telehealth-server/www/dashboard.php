<?php
session_start();

if (!isset($_SESSION['doctor_id'])) {
    header('Location: login.php');
    exit;
}

// Database connection
$conn = new mysqli('telehealth-db', 'telehealth', 'telehealth123', 'telehealth');

// Get consultations
$doctor_id = $_SESSION['doctor_id'];
$consultations = $conn->query("
    SELECT c.*, CONCAT(p.first_name, ' ', p.last_name) as patient_name, p.id_number as document_id, p.phone, p.email as patient_email
    FROM consultations c
    JOIN patients p ON c.patient_id = p.id
    WHERE c.doctor_id = $doctor_id
    ORDER BY c.scheduled_date DESC
");

// Get stats
$total = $conn->query("SELECT COUNT(*) as count FROM consultations WHERE doctor_id = $doctor_id")->fetch_assoc()['count'];
$completed = $conn->query("SELECT COUNT(*) as count FROM consultations WHERE doctor_id = $doctor_id AND status = 'completed'")->fetch_assoc()['count'];
$today = $conn->query("SELECT COUNT(*) as count FROM consultations WHERE doctor_id = $doctor_id AND DATE(scheduled_date) = CURDATE()")->fetch_assoc()['count'];
$pending = $conn->query("SELECT COUNT(*) as count FROM consultations WHERE doctor_id = $doctor_id AND status = 'scheduled'")->fetch_assoc()['count'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - PAHO Telesalud</title>
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
            transition: background 0.3s;
        }
        .nav a:hover, .nav a.active { background: rgba(255,255,255,0.3); }
        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .logout {
            background: rgba(255,255,255,0.2);
            color: white;
            padding: 8px 15px;
            border-radius: 5px;
            text-decoration: none;
            transition: background 0.3s;
        }
        .logout:hover { background: rgba(255,255,255,0.3); }
        
        .container {
            max-width: 1400px;
            margin: 30px auto;
            padding: 0 20px;
        }
        
        .stats {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .stat-card h3 { color: #666; font-size: 14px; margin-bottom: 10px; }
        .stat-card .value { color: #0066cc; font-size: 32px; font-weight: bold; }
        
        .actions {
            margin-bottom: 30px;
            display: flex;
            gap: 10px;
        }
        .btn {
            background: #28a745;
            color: white;
            padding: 12px 25px;
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
        
        .card {
            background: white;
            border-radius: 10px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .card h2 {
            color: #333;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #0066cc;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        th {
            background: #f9f9f9;
            color: #666;
            font-weight: 600;
        }
        tr:hover { background: #f5f5f5; }
        
        .status {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        .status.scheduled { background: #fff3cd; color: #856404; }
        .status.in_progress { background: #cce5ff; color: #004085; }
        .status.completed { background: #d4edda; color: #155724; }
        .status.cancelled { background: #f8d7da; color: #721c24; }
        
        .actions-cell {
            display: flex;
            gap: 10px;
        }
        .btn-small {
            padding: 6px 12px;
            border-radius: 5px;
            font-size: 12px;
            cursor: pointer;
            border: none;
            text-decoration: none;
        }
        .btn-primary { background: #0066cc; color: white; }
        .btn-success { background: #28a745; color: white; }
        .btn-danger { background: #dc3545; color: white; }
        .btn-secondary { background: #6c757d; color: white; }
        
        .empty-state {
            text-align: center;
            padding: 50px;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>PAHO Telesalud - Dashboard</h1>
        <div class="nav">
            <a href="dashboard.php" class="active">Dashboard</a>
            <a href="schedule.php">Agendamiento</a>
            <a href="patients.php">Pacientes</a>
            <a href="logout.php" class="logout">Cerrar Sesión</a>
        </div>
    </div>
    
    <div class="container">
        <div class="stats">
            <div class="stat-card">
                <h3>Total Consultas</h3>
                <div class="value"><?php echo $total; ?></div>
            </div>
            <div class="stat-card">
                <h3>Completadas</h3>
                <div class="value"><?php echo $completed; ?></div>
            </div>
            <div class="stat-card">
                <h3>Hoy</h3>
                <div class="value"><?php echo $today; ?></div>
            </div>
            <div class="stat-card">
                <h3>Pendientes</h3>
                <div class="value"><?php echo $pending; ?></div>
            </div>
        </div>
        
        <div class="actions">
            <a href="schedule.php" class="btn btn-primary">📅 Agendar Cita</a>
            <a href="new_consultation.php" class="btn">+ Nueva Videoconsulta</a>
        </div>
        
        <div class="card">
            <h2>Historial de Videoconsultas</h2>
            
            <?php if ($consultations->num_rows > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>Paciente</th>
                        <th>Documento</th>
                        <th>Fecha/Hora</th>
                        <th>Duración</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = $consultations->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['patient_name']); ?></td>
                        <td><?php echo htmlspecialchars($row['document_id']); ?></td>
                        <td><?php echo date('d/m/Y H:i', strtotime($row['scheduled_date'])); ?></td>
                        <td><?php echo $row['duration_minutes']; ?> min</td>
                        <td><span class="status <?php echo $row['status']; ?>"><?php echo ucfirst(str_replace('_', ' ', $row['status'])); ?></span></td>
                        <td class="actions-cell">
                            <?php if ($row['status'] === 'scheduled'): ?>
                            <a href="join_consultation.php?id=<?php echo $row['id']; ?>" class="btn-small btn-success">Iniciar</a>
                            <?php elseif ($row['status'] === 'completed'): ?>
                            <?php if ($row['recording_path']): ?>
                            <a href="watch_recording.php?id=<?php echo $row['id']; ?>" class="btn-small btn-primary">Ver Grabación</a>
                            <?php else: ?>
                            <span style="color: #999; font-size: 12px;">Sin grabación</span>
                            <?php endif; ?>
                            <?php endif; ?>
                            <a href="consultation_detail.php?id=<?php echo $row['id']; ?>" class="btn-small btn-secondary">Ver Detalle</a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
            <?php else: ?>
            <div class="empty-state">
                <p>No hay videoconsultas registradas.</p>
                <p style="margin-top: 10px;"><a href="new_consultation.php" class="btn">Crear primera videoconsulta</a></p>
            </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
<?php $conn->close(); ?>

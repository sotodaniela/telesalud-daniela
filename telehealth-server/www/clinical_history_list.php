<?php
session_start();

if (!isset($_SESSION['user_id']) && !isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$conn = new mysqli('telehealth-db', 'telehealth', 'telehealth123', 'telehealth');
$user_id = $_SESSION['user_id'] ?? $_SESSION['user_id'] ?? null;
$user_role = $_SESSION['user_role'] ?? 'assistant';

$search = $_GET['search'] ?? '';
$patient_data = null;
$history_records = [];

if ($search) {
    $search_esc = $conn->real_escape_string($search);
    $patient_data = $conn->query("SELECT * FROM patients WHERE id_number LIKE '%$search_esc%' OR first_name LIKE '%$search_esc%' OR last_name LIKE '%$search_esc%' LIMIT 1")->fetch_assoc();
    
    if ($patient_data) {
        $patient_id = $patient_data['id'];
        $history_records = $conn->query("
            SELECT * FROM clinical_history 
            WHERE patient_id = $patient_id 
            ORDER BY consultation_date DESC
        ");
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historial de Historia Clínica - Red de Salud Ladera ESE</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Segoe UI', Tahoma, sans-serif;
            background: #f5f5f5;
            padding: 20px;
        }
        .header {
            background: linear-gradient(135deg, #3498db, #2980b9);
            color: white;
            padding: 15px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-radius: 10px;
            margin-bottom: 20px;
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
        .header h1 { font-size: 20px; }
        .header a { color: white; text-decoration: none; }
        
        .container {
            max-width: 900px;
            margin: 0 auto;
        }
        
        .card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        
        .search-box {
            position: relative;
        }
        .search-box input {
            width: 100%;
            padding: 14px 15px;
            border: 2px solid #3498db;
            border-radius: 8px;
            font-size: 15px;
        }
        .search-box input:focus {
            outline: none;
            border-color: #2980b9;
        }
        .search-hint {
            font-size: 13px;
            color: #666;
            margin-top: 8px;
        }
        
        .patient-info {
            background: linear-gradient(135deg, #ebf5fb, #d4e6f1);
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .patient-info h3 { color: #2980b9; margin-bottom: 10px; }
        .patient-info p { margin: 5px 0; font-size: 14px; }
        
        .history-item {
            background: #f9f9f9;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
        }
        .history-item h4 {
            color: #333;
            margin-bottom: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .history-item .date {
            color: #666;
            font-size: 13px;
        }
        .history-item .type {
            background: #3498db;
            color: white;
            padding: 3px 10px;
            border-radius: 15px;
            font-size: 12px;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            text-decoration: none;
            display: inline-block;
            background: #3498db;
            color: white;
        }
        .btn:hover { opacity: 0.9; }
        
        .empty {
            text-align: center;
            padding: 40px;
            color: #999;
        }
        
        .no-results {
            text-align: center;
            padding: 40px;
            background: #fff3cd;
            border-radius: 8px;
            color: #856404;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <img src="images/Logo Ladera ESE.png" alt="Logo" style="height: 40px; border-radius: 5px;">
            <h1>Red de Salud Ladera ESE - Historia Clínica</h1>
        </div>
        <a href="dashboard.php">← Volver al Dashboard</a>
    </div>
    
    <div class="container">
        <div class="card">
            <form method="GET">
                <div class="form-group">
                    <label style="display: block; margin-bottom: 8px; font-weight: 500; color: #333;">Buscar Paciente</label>
                    <div class="search-box">
                        <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Ingrese nombre o número de identificación..." autocomplete="off">
                    </div>
                    <p class="search-hint">🔍 Puede buscar por: Nombre, Apellido o Número de identificación</p>
                </div>
                <button type="submit" class="btn">Buscar</button>
            </form>
        </div>
        
        <?php if ($search && !$patient_data): ?>
        <div class="no-results">
            <h3>⚠️ No se encontró el paciente</h3>
            <p>No se encontró ningún paciente con el criterio de búsqueda: <strong><?php echo htmlspecialchars($search); ?></strong></p>
            <p>Verifique el nombre o número de identificación e intente nuevamente.</p>
        </div>
        <?php endif; ?>
        
        <?php if ($patient_data): ?>
        <div class="patient-info">
            <h3>📋 Datos del Paciente</h3>
            <p><strong>Nombre:</strong> <?php echo htmlspecialchars($patient_data['first_name'] . ' ' . $patient_data['last_name']); ?></p>
            <p><strong>Identificación:</strong> <?php echo htmlspecialchars($patient_data['id_type'] . ' ' . $patient_data['id_number']); ?></p>
            <p><strong>Fecha de Nacimiento:</strong> <?php echo date('d/m/Y', strtotime($patient_data['birth_date'])); ?></p>
            <p><strong>Teléfono:</strong> <?php echo htmlspecialchars($patient_data['phone'] ?? $patient_data['mobile'] ?? 'No registrado'); ?></p>
        </div>
        
        <?php if ($history_records && $history_records->num_rows > 0): ?>
        <div class="card">
            <h3 style="color: #2980b9; margin-bottom: 15px;">📝 Historial de Consultas</h3>
            <?php while ($history = $history_records->fetch_assoc()): ?>
            <div class="history-item">
                <h4>
                    <span><?php echo htmlspecialchars($history['consultation_type'] ?? 'Consulta'); ?></span>
                    <span class="date"><?php echo date('d/m/Y H:i', strtotime($history['consultation_date'])); ?></span>
                </h4>
                <p><strong>Motivo:</strong> <?php echo htmlspecialchars($history['reason_consultation'] ?? 'N/A'); ?></p>
                <?php if ($history['diagnoses']): ?>
                <p><strong>Diagnóstico:</strong> <?php echo htmlspecialchars($history['diagnoses']); ?></p>
                <?php endif; ?>
                <div style="margin-top: 10px; display: flex; gap: 10px;">
                    <a href="clinical_history.php?patient_id=<?php echo $patient_data['id']; ?>&history_id=<?php echo $history['id']; ?>" class="btn" style="font-size: 12px;">Ver Detalle</a>
                    <a href="teleconsulta_pdf.php?id=<?php echo $patient_data['id']; ?>&consultation_id=<?php echo $history['id']; ?>" target="_blank" class="btn" style="background: #dc3545; font-size: 12px;">📄 Generar PDF</a>
                </div>
            </div>
            <?php endwhile; ?>
        </div>
        <?php else: ?>
        <div class="card">
            <div class="empty">
                <h3>📭 No hay historial clínico</h3>
                <p>Este paciente no tiene consultas registradas en la historia clínica.</p>
            </div>
        </div>
        <?php endif; ?>
        <?php endif; ?>
    </div>
</body>
</html>

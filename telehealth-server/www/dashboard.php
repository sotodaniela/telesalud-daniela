<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];
$user_role = $_SESSION['user_role'];
$user_specialty = $_SESSION['user_specialty'] ?? '';

$conn = new mysqli('telehealth-db', 'telehealth', 'telehealth123', 'telehealth');

$user_permissions = [];
$perm_result = $conn->query("SELECT module_key, is_enabled FROM user_module_permissions WHERE user_id = $user_id");
while ($perm = $perm_result->fetch_assoc()) {
    $user_permissions[$perm['module_key']] = $perm['is_enabled'];
}

$consultations = null;
$total = 0;
$completed = 0;
$today = 0;
$pending = 0;

if ($user_role === 'doctor') {
    $consultations = $conn->query("
        SELECT c.*, CONCAT(p.first_name, ' ', p.last_name) as patient_name, p.id_number as document_id, p.phone, p.email as patient_email
        FROM consultations c
        JOIN patients p ON c.patient_id = p.id
        WHERE c.doctor_id = $user_id
        ORDER BY c.scheduled_date DESC
    ");
    
    $total = $conn->query("SELECT COUNT(*) as count FROM consultations WHERE doctor_id = $user_id")->fetch_assoc()['count'];
    $completed = $conn->query("SELECT COUNT(*) as count FROM consultations WHERE doctor_id = $user_id AND status = 'completed'")->fetch_assoc()['count'];
    $today = $conn->query("SELECT COUNT(*) as count FROM consultations WHERE doctor_id = $user_id AND DATE(scheduled_date) = CURDATE()")->fetch_assoc()['count'];
    $pending = $conn->query("SELECT COUNT(*) as count FROM consultations WHERE doctor_id = $user_id AND status = 'scheduled'")->fetch_assoc()['count'];
} elseif ($user_role === 'admin') {
    $total = $conn->query("SELECT COUNT(*) as count FROM consultations")->fetch_assoc()['count'];
    $completed = $conn->query("SELECT COUNT(*) as count FROM consultations WHERE status = 'completed'")->fetch_assoc()['count'];
    $today = $conn->query("SELECT COUNT(*) as count FROM consultations WHERE DATE(scheduled_date) = CURDATE()")->fetch_assoc()['count'];
    $pending = $conn->query("SELECT COUNT(*) as count FROM consultations WHERE status = 'scheduled'")->fetch_assoc()['count'];
    
    $consultations = $conn->query("
        SELECT c.*, CONCAT(p.first_name, ' ', p.last_name) as patient_name, p.id_number as document_id, u.full_name as doctor_name
        FROM consultations c
        JOIN patients p ON c.patient_id = p.id
        LEFT JOIN users u ON c.doctor_id = u.id
        ORDER BY c.scheduled_date DESC
        LIMIT 50
    ");
} else {
    $total = $conn->query("SELECT COUNT(*) as count FROM consultations")->fetch_assoc()['count'];
    $completed = $conn->query("SELECT COUNT(*) as count FROM consultations WHERE status = 'completed'")->fetch_assoc()['count'];
    $today = $conn->query("SELECT COUNT(*) as count FROM consultations WHERE DATE(scheduled_date) = CURDATE()")->fetch_assoc()['count'];
    $pending = $conn->query("SELECT COUNT(*) as count FROM consultations WHERE status = 'scheduled'")->fetch_assoc()['count'];
}

function getServerMetrics() {
    $metrics = [];
    
    if (function_exists('sys_getloadavg')) {
        $load = sys_getloadavg();
        $metrics['cpu_load'] = round($load[0], 2);
    } else {
        $metrics['cpu_load'] = 'N/A';
    }
    
    if (function_exists('memory_get_usage')) {
        $metrics['php_memory'] = round(memory_get_usage() / 1024 / 1024, 2);
        $metrics['php_memory_peak'] = round(memory_get_peak_usage() / 1024 / 1024, 2);
    }
    
    if (is_readable('/proc/meminfo')) {
        $meminfo = file_get_contents('/proc/meminfo');
        preg_match('/MemTotal:\s+(\d+)/', $meminfo, $total);
        preg_match('/MemAvailable:\s+(\d+)/', $meminfo, $available);
        
        if (isset($total[1]) && isset($available[1])) {
            $totalMB = (int) round($total[1] / 1024, 0);
            $availableMB = (int) round($available[1] / 1024, 0);
            $usedMB = $totalMB - $availableMB;
            $metrics['sys_memory_total'] = $totalMB;
            $metrics['sys_memory_used'] = $usedMB;
            $metrics['sys_memory_available'] = $availableMB;
            $metrics['sys_memory_percent'] = round(($usedMB / $totalMB) * 100, 1);
        }
    } else {
        $metrics['sys_memory_total'] = 'N/A';
        $metrics['sys_memory_used'] = 'N/A';
        $metrics['sys_memory_available'] = 'N/A';
        $metrics['sys_memory_percent'] = 'N/A';
    }
    
    if (is_readable('/proc/net/dev')) {
        $netdev = file_get_contents('/proc/net/dev');
        $lines = explode("\n", $netdev);
        $totalRx = 0;
        $totalTx = 0;
        
        foreach ($lines as $line) {
            if (strpos($line, ':') !== false && strpos($line, 'lo:') === false) {
                $parts = explode(':', $line);
                $data = preg_split('/\s+/', trim($parts[1]));
                if (isset($data[0]) && isset($data[8])) {
                    $totalRx += (int) $data[0];
                    $totalTx += (int) $data[8];
                }
            }
        }
        
        $metrics['network_rx_bytes'] = $totalRx;
        $metrics['network_tx_bytes'] = $totalTx;
        $metrics['network_rx_mb'] = round($totalRx / 1024 / 1024, 2);
        $metrics['network_tx_mb'] = round($totalTx / 1024 / 1024, 2);
    } else {
        $metrics['network_rx_bytes'] = 0;
        $metrics['network_tx_bytes'] = 0;
        $metrics['network_rx_mb'] = 'N/A';
        $metrics['network_tx_mb'] = 'N/A';
    }
    
    if (function_exists('disk_total_space')) {
        $metrics['disk_total'] = round(disk_total_space('/') / 1024 / 1024 / 1024, 2);
        $metrics['disk_free'] = round(disk_free_space('/') / 1024 / 1024 / 1024, 2);
        $metrics['disk_used'] = round($metrics['disk_total'] - $metrics['disk_free'], 2);
        $metrics['disk_percent'] = round(($metrics['disk_used'] / $metrics['disk_total']) * 100, 1);
    }
    
    if (is_readable('/proc/uptime')) {
        $uptime = (float) explode(' ', file_get_contents('/proc/uptime'))[0];
        $days = (int) floor($uptime / 86400);
        $hours = (int) floor(fmod($uptime, 86400) / 3600);
        $minutes = (int) floor(fmod(fmod($uptime, 86400), 3600) / 60);
        $metrics['uptime'] = "{$days}d {$hours}h {$minutes}m";
    } else {
        $metrics['uptime'] = 'N/A';
    }
    
    return $metrics;
}

function formatBytes($bytes) {
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } else {
        return $bytes . ' bytes';
    }
}

$metrics = getServerMetrics();

$role_labels = [
    'admin' => 'Administrador',
    'doctor' => 'Médico',
    'nurse' => 'Enfermería',
    'assistant' => 'Auxiliar'
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Telesalud</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Segoe UI', Tahoma, sans-serif;
            background: #f5f5f5;
        }
        .header {
            background: linear-gradient(135deg, #3498db, #2980b9);
            color: white;
            padding: 15px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .header h1 { font-size: 20px; }
        .header .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .header .user-role {
            font-size: 12px;
            background: rgba(255,255,255,0.2);
            padding: 4px 12px;
            border-radius: 15px;
        }
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
        .stat-card .value { color: #1a5276; font-size: 32px; font-weight: bold; }
        
        .actions {
            margin-bottom: 30px;
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
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
        .btn-primary { background: #1a5276; }
        .btn-primary:hover { background: #154360; }
        
        .card {
            background: white;
            border-radius: 10px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        .card h2 {
            color: #333;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #1a5276;
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
        
        .metrics-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-bottom: 20px;
        }
        .metric-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
        }
        .metric-card h4 {
            color: #666;
            font-size: 13px;
            margin-bottom: 8px;
            text-transform: uppercase;
        }
        .metric-card .metric-value {
            font-size: 28px;
            font-weight: bold;
            margin-bottom: 5px;
        }
        .metric-card .metric-detail {
            font-size: 11px;
            color: #999;
        }
        .metric-card.cpu .metric-value { color: #dc3545; }
        .metric-card.memory .metric-value { color: #17a2b8; }
        .metric-card.network .metric-value { color: #28a745; }
        .metric-card.disk .metric-value { color: #6f42c1; }
        
        .progress-bar {
            width: 100%;
            height: 8px;
            background: #e9ecef;
            border-radius: 4px;
            overflow: hidden;
            margin-top: 10px;
        }
        .progress-bar-fill {
            height: 100%;
            border-radius: 4px;
            transition: width 0.3s;
        }
        .progress-bar-fill.cpu { background: linear-gradient(90deg, #28a745, #dc3545); }
        .progress-bar-fill.memory { background: linear-gradient(90deg, #17a2b8, #6610f2); }
        .progress-bar-fill.disk { background: linear-gradient(90deg, #6f42c1, #e83e8c); }
        
        .tab-content { display: none; }
        .tab-content.active { display: block; }
        .tabs {
            display: flex;
            gap: 5px;
            margin-bottom: 20px;
        }
        .tab-btn {
            padding: 10px 20px;
            border: none;
            background: #e9ecef;
            color: #666;
            border-radius: 5px 5px 0 0;
            cursor: pointer;
            font-size: 14px;
        }
        .tab-btn.active {
            background: #1a5276;
            color: white;
        }
        
        .role-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
        }
        .role-badge.admin { background: #6f42c1; color: white; }
        .role-badge.doctor { background: #dc3545; color: white; }
        .role-badge.nurse { background: #17a2b8; color: white; }
        .role-badge.assistant { background: #28a745; color: white; }
        
        @media (max-width: 768px) {
            .stats { grid-template-columns: repeat(2, 1fr); }
            .metrics-grid { grid-template-columns: 1fr; }
            .header { flex-direction: column; gap: 15px; }
            .actions { flex-direction: column; }
        }
    </style>
</head>
<body>
    <div class="header">
        <div style="display: flex; align-items: center; gap: 15px;">
            <img src="images/Logo Ladera ESE.png" alt="Logo" style="height: 40px; width: auto; border-radius: 5px;">
            <h1>Red de Salud Ladera ESE</h1>
        </div>
        <div class="user-info">
            <span class="user-role"><?php echo htmlspecialchars($role_labels[$user_role] ?? $user_role); ?></span>
            <span><?php echo htmlspecialchars($user_name); ?></span>
        </div>
        <div class="nav">
            <a href="dashboard.php" class="active">Dashboard</a>
            <?php if (isset($user_permissions['users']) && $user_permissions['users']): ?>
            <a href="users.php">Usuarios</a>
            <?php endif; ?>
            <?php if (isset($user_permissions['schedule']) && $user_permissions['schedule']): ?>
            <a href="schedule.php">Agendamiento</a>
            <?php endif; ?>
            <?php if (isset($user_permissions['patients']) && $user_permissions['patients']): ?>
            <a href="patients.php">Pacientes</a>
            <?php endif; ?>
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
            <?php if (isset($user_permissions['schedule']) && $user_permissions['schedule']): ?>
            <a href="schedule.php" class="btn btn-primary">📅 Agendar Cita</a>
            <?php endif; ?>
            <?php if (isset($user_permissions['patients']) && $user_permissions['patients']): ?>
            <a href="patients.php" class="btn" style="background: #17a2b8;">👥 Pacientes</a>
            <?php endif; ?>
            <?php if (isset($user_permissions['teleconsulta']) && $user_permissions['teleconsulta']): ?>
            <a href="teleconsulta.php" class="btn" style="background: #6f42c1;">💻 Teleconsulta</a>
            <?php endif; ?>
            <?php if (isset($user_permissions['clinical_history']) && $user_permissions['clinical_history']): ?>
            <a href="clinical_history_list.php" class="btn" style="background: #e83e8c;">📋 Historia Clínica</a>
            <?php endif; ?>
            <?php if ($user_role === 'admin'): ?>
            <button onclick="toggleReports()" class="btn" style="background: #ffc107; color: #000;">📊 Informes del Sistema</button>
            <?php endif; ?>
        </div>
        
        <div id="reportsSection" style="display: none; margin-bottom: 30px;">
            <div class="tabs">
                <button class="tab-btn active" onclick="showTab('overview')">Resumen General</button>
                <button class="tab-btn" onclick="showTab('cpu')">CPU</button>
                <button class="tab-btn" onclick="showTab('memory')">Memoria</button>
                <button class="tab-btn" onclick="showTab('network')">Red</button>
                <button class="tab-btn" onclick="showTab('disk')">Almacenamiento</button>
            </div>
            
            <div id="tab-overview" class="tab-content active">
                <div class="metrics-grid">
                    <div class="metric-card cpu">
                        <h4>CPU Load Average</h4>
                        <div class="metric-value"><?php echo $metrics['cpu_load']; ?></div>
                        <div class="metric-detail">Load promedio del sistema</div>
                        <div class="progress-bar">
                            <div class="progress-bar-fill cpu" style="width: <?php echo min(($metrics['cpu_load'] / 4) * 100, 100); ?>%"></div>
                        </div>
                    </div>
                    <div class="metric-card memory">
                        <h4>Memoria del Sistema</h4>
                        <div class="metric-value"><?php echo $metrics['sys_memory_percent']; ?>%</div>
                        <div class="metric-detail"><?php echo $metrics['sys_memory_used']; ?> / <?php echo $metrics['sys_memory_total']; ?> MB</div>
                        <div class="progress-bar">
                            <div class="progress-bar-fill memory" style="width: <?php echo $metrics['sys_memory_percent']; ?>%"></div>
                        </div>
                    </div>
                    <div class="metric-card disk">
                        <h4>Disco</h4>
                        <div class="metric-value"><?php echo $metrics['disk_percent']; ?>%</div>
                        <div class="metric-detail"><?php echo $metrics['disk_used']; ?> / <?php echo $metrics['disk_total']; ?> GB</div>
                        <div class="progress-bar">
                            <div class="progress-bar-fill disk" style="width: <?php echo $metrics['disk_percent']; ?>%"></div>
                        </div>
                    </div>
                </div>
                <div class="metrics-grid">
                    <div class="metric-card network">
                        <h4>RX (Recibido)</h4>
                        <div class="metric-value"><?php echo $metrics['network_rx_mb']; ?> MB</div>
                        <div class="metric-detail">Datos totales recibidos</div>
                    </div>
                    <div class="metric-card network">
                        <h4>TX (Enviado)</h4>
                        <div class="metric-value"><?php echo $metrics['network_tx_mb']; ?> MB</div>
                        <div class="metric-detail">Datos totales enviados</div>
                    </div>
                    <div class="metric-card">
                        <h4>Uptime</h4>
                        <div class="metric-value" style="font-size: 22px;"><?php echo $metrics['uptime']; ?></div>
                        <div class="metric-detail">Tiempo activo del servidor</div>
                    </div>
                </div>
            </div>
            
            <div id="tab-cpu" class="tab-content">
                <div class="card">
                    <h2>Métricas de CPU</h2>
                    <table>
                        <tr><th>Métrica</th><th>Valor</th><th>Descripción</th></tr>
                        <tr><td>Load Average (1 min)</td><td><strong><?php echo $metrics['cpu_load']; ?></strong></td><td>Carga promedio del sistema</td></tr>
                    </table>
                </div>
            </div>
            
            <div id="tab-memory" class="tab-content">
                <div class="card">
                    <h2>Métricas de Memoria</h2>
                    <table>
                        <tr><th>Métrica</th><th>Valor</th><th>Descripción</th></tr>
                        <tr><td>Memoria Total</td><td><strong><?php echo $metrics['sys_memory_total']; ?> MB</strong></td><td>RAM total del servidor</td></tr>
                        <tr><td>Memoria Usada</td><td><strong><?php echo $metrics['sys_memory_used']; ?> MB</strong></td><td>RAM en uso</td></tr>
                    </table>
                </div>
            </div>
            
            <div id="tab-network" class="tab-content">
                <div class="card">
                    <h2>Métricas de Red</h2>
                    <table>
                        <tr><th>Métrica</th><th>Valor</th><th>Descripción</th></tr>
                        <tr><td>Datos Recibidos</td><td><strong><?php echo $metrics['network_rx_mb']; ?> MB</strong></td><td>Total recibido</td></tr>
                        <tr><td>Datos Enviados</td><td><strong><?php echo $metrics['network_tx_mb']; ?> MB</strong></td><td>Total enviado</td></tr>
                    </table>
                </div>
            </div>
            
            <div id="tab-disk" class="tab-content">
                <div class="card">
                    <h2>Métricas de Almacenamiento</h2>
                    <table>
                        <tr><th>Métrica</th><th>Valor</th><th>Descripción</th></tr>
                        <tr><td>Espacio Total</td><td><strong><?php echo $metrics['disk_total']; ?> GB</strong></td><td>Capacidad total</td></tr>
                        <tr><td>Espacio Usado</td><td><strong><?php echo $metrics['disk_used']; ?> GB</strong></td><td>Espacio ocupado</td></tr>
                    </table>
                </div>
            </div>
        </div>
        
        <div class="card">
            <h2>Historial de Videoconsultas</h2>
            
            <?php if ($consultations && $consultations->num_rows > 0): ?>
            <table>
                <thead>
                    <tr>
                        <?php if ($user_role === 'admin'): ?>
                        <th>Médico</th>
                        <?php endif; ?>
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
                        <?php if ($user_role === 'admin'): ?>
                        <td><?php echo htmlspecialchars($row['doctor_name'] ?? '-'); ?></td>
                        <?php endif; ?>
                        <td><?php echo htmlspecialchars($row['patient_name']); ?></td>
                        <td><?php echo htmlspecialchars($row['document_id'] ?? '-'); ?></td>
                        <td><?php echo date('d/m/Y H:i', strtotime($row['scheduled_date'])); ?></td>
                        <td><?php echo $row['duration_minutes'] ?? '-'; ?> min</td>
                        <td><span class="status <?php echo $row['status']; ?>"><?php echo ucfirst(str_replace('_', ' ', $row['status'])); ?></span></td>
                        <td class="actions-cell">
                            <?php if ($row['status'] === 'scheduled' && $user_role === 'doctor'): ?>
                            <a href="join_consultation.php?id=<?php echo $row['id']; ?>" class="btn-small btn-success">Iniciar</a>
                            <?php elseif ($row['status'] === 'completed'): ?>
                            <a href="teleconsulta_pdf.php?id=<?php echo $row['patient_id']; ?>&consultation_id=<?php echo $row['id']; ?>" target="_blank" class="btn-small" style="background: #dc3545; color: white;">📄 PDF</a>
                            <?php endif; ?>
                            <a href="consultation_detail.php?id=<?php echo $row['id']; ?>" class="btn-small btn-secondary">Ver</a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
            <?php else: ?>
            <div class="empty-state">
                <p>No hay videoconsultas registradas.</p>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        function toggleReports() {
            var section = document.getElementById('reportsSection');
            if (section.style.display === 'none') {
                section.style.display = 'block';
            } else {
                section.style.display = 'none';
            }
        }
        
        function showTab(tabName) {
            document.querySelectorAll('.tab-content').forEach(function(el) {
                el.classList.remove('active');
            });
            document.querySelectorAll('.tab-btn').forEach(function(el) {
                el.classList.remove('active');
            });
            document.getElementById('tab-' + tabName).classList.add('active');
            event.target.classList.add('active');
        }
        
        setInterval(function() {
            var section = document.getElementById('reportsSection');
            if (section.style.display !== 'none') {
                location.reload();
            }
        }, 30000);
    </script>
</body>
</html>
<?php $conn->close(); ?>

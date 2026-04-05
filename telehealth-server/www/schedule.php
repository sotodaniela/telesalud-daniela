<?php
session_start();

if (!isset($_SESSION['doctor_id'])) {
    header('Location: login.php');
    exit;
}

$conn = new mysqli('telehealth-db', 'telehealth', 'telehealth123', 'telehealth');
$doctor_id = $_SESSION['doctor_id'];

// Handle form submission for new appointment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'create') {
        $patient_id = intval($_POST['patient_id']);
        $date = $conn->real_escape_string($_POST['appointment_date']);
        $time = $conn->real_escape_string($_POST['appointment_time']);
        $notes = $conn->real_escape_string($_POST['notes']);
        $scheduled_datetime = $date . ' ' . $time . ':00';
        $room_name = 'room_' . time() . '_' . rand(1000, 9999);
        
        $sql = "INSERT INTO consultations (room_name, doctor_id, patient_id, scheduled_date, notes, status) 
                VALUES ('$room_name', $doctor_id, $patient_id, '$scheduled_datetime', '$notes', 'scheduled')";
        $conn->query($sql);
        header('Location: schedule.php?success=1');
        exit;
    }
    
    if ($_POST['action'] === 'update') {
        $id = intval($_POST['id']);
        $status = $conn->real_escape_string($_POST['status']);
        $notes = $conn->real_escape_string($_POST['notes']);
        $conn->query("UPDATE consultations SET status='$status', notes='$notes' WHERE id=$id AND doctor_id=$doctor_id");
        header('Location: schedule.php');
        exit;
    }
    
    if ($_POST['action'] === 'delete') {
        $id = intval($_POST['id']);
        $conn->query("DELETE FROM consultations WHERE id=$id AND doctor_id=$doctor_id");
        header('Location: schedule.php');
        exit;
    }
}

// Get patients for dropdown
$patients = $conn->query("SELECT id, CONCAT(first_name, ' ', last_name) as full_name FROM patients WHERE is_active = 1 ORDER BY full_name");

// Get current month/year
$month = isset($_GET['month']) ? intval($_GET['month']) : date('n');
$year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');

// Get appointments for current month
$start_date = date('Y-m-01', strtotime("$year-$month-01"));
$end_date = date('Y-m-t', strtotime("$year-$month-01"));
$appointments = $conn->query("
    SELECT c.*, CONCAT(p.first_name, ' ', p.last_name) as patient_name 
    FROM consultations c 
    JOIN patients p ON c.patient_id = p.id 
    WHERE c.doctor_id = $doctor_id 
    AND DATE(c.scheduled_date) BETWEEN '$start_date' AND '$end_date'
    ORDER BY c.scheduled_date
");

// Create appointments array by date
$appointments_by_date = [];
while ($apt = $appointments->fetch_assoc()) {
    $date_key = date('Y-m-d', strtotime($apt['scheduled_date']));
    if (!isset($appointments_by_date[$date_key])) {
        $appointments_by_date[$date_key] = [];
    }
    $appointments_by_date[$date_key][] = $apt;
}

// Get success message
$success = isset($_GET['success']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agendamiento - PAHO Telesalud</title>
    <link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.js"></script>
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
        .logout { background: rgba(255,255,255,0.2) !important; }
        
        .container {
            max-width: 1400px;
            margin: 20px auto;
            padding: 0 20px;
        }
        
        .top-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
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
        .btn-danger { background: #dc3545; }
        .btn-danger:hover { background: #c82333; }
        
        .main-content {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 20px;
        }
        
        .card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .card h2 {
            color: #333;
            margin-bottom: 15px;
            font-size: 18px;
            border-bottom: 2px solid #0066cc;
            padding-bottom: 10px;
        }
        
        #calendar {
            max-width: 100%;
        }
        .fc-event {
            background: #0066cc !important;
            border: none !important;
            cursor: pointer;
        }
        .fc-event:hover { background: #0052a3 !important; }
        .fc-day-today { background: #e3f2fd !important; }
        
        .appointments-list {
            max-height: 600px;
            overflow-y: auto;
        }
        .appointment-item {
            padding: 15px;
            border: 1px solid #eee;
            border-radius: 8px;
            margin-bottom: 10px;
            transition: all 0.3s;
        }
        .appointment-item:hover {
            background: #f9f9f9;
            border-color: #0066cc;
        }
        .appointment-item .time {
            color: #0066cc;
            font-weight: bold;
            font-size: 16px;
        }
        .appointment-item .patient {
            color: #333;
            font-size: 14px;
            margin: 5px 0;
        }
        .appointment-item .status {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 15px;
            font-size: 11px;
            font-weight: 600;
            margin-top: 5px;
        }
        .status.scheduled { background: #fff3cd; color: #856404; }
        .status.completed { background: #d4edda; color: #155724; }
        .status.cancelled { background: #f8d7da; color: #721c24; }
        
        .appointment-actions {
            margin-top: 10px;
            display: flex;
            gap: 5px;
        }
        .btn-small {
            padding: 5px 10px;
            border-radius: 5px;
            font-size: 12px;
            cursor: pointer;
            border: none;
            text-decoration: none;
            color: white;
        }
        .btn-small:hover { opacity: 0.8; }
        
        /* Modal */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }
        .modal.show { display: flex; }
        .modal-content {
            background: white;
            padding: 30px;
            border-radius: 10px;
            max-width: 500px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
        }
        .modal h2 {
            margin-bottom: 20px;
            color: #333;
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            color: #666;
            font-weight: 500;
        }
        .form-group input, .form-group select, .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
        }
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus {
            outline: none;
            border-color: #0066cc;
        }
        .form-actions {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }
        .close {
            float: right;
            font-size: 24px;
            cursor: pointer;
            color: #999;
        }
        .close:hover { color: #333; }
        
        .success-message {
            background: #d4edda;
            color: #155724;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 1px solid #c3e6cb;
        }
        
        .no-appointments {
            text-align: center;
            padding: 40px;
            color: #999;
        }
        
        .fc-toolbar-title {
            font-size: 18px !important;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>PAHO Telesalud - Agendamiento</h1>
        <div class="nav">
            <a href="dashboard.php">Dashboard</a>
            <a href="schedule.php" class="active">Agendamiento</a>
            <a href="logout.php" class="logout">Cerrar Sesión</a>
        </div>
    </div>
    
    <div class="container">
        <?php if ($success): ?>
        <div class="success-message">
            ✓ Cita agendada exitosamente
        </div>
        <?php endif; ?>
        
        <div class="top-bar">
            <h2 style="color: #333;">Calendario de Citas</h2>
            <button class="btn" onclick="openModal()">+ Nueva Cita</button>
        </div>
        
        <div class="main-content">
            <div class="card">
                <div id="calendar"></div>
            </div>
            
            <div class="card appointments-list">
                <h2>Próximas Citas</h2>
                <?php 
                $upcoming = $conn->query("
                    SELECT c.*, CONCAT(p.first_name, ' ', p.last_name) as patient_name 
                    FROM consultations c 
                    JOIN patients p ON c.patient_id = p.id 
                    WHERE c.doctor_id = $doctor_id 
                    AND c.scheduled_date >= NOW()
                    AND c.status = 'scheduled'
                    ORDER BY c.scheduled_date ASC
                    LIMIT 10
                ");
                ?>
                
                <?php if ($upcoming->num_rows > 0): ?>
                    <?php while ($apt = $upcoming->fetch_assoc()): ?>
                    <div class="appointment-item">
                        <div class="time"><?php echo date('H:i', strtotime($apt['scheduled_date'])); ?></div>
                        <div class="patient"><?php echo htmlspecialchars($apt['patient_name']); ?></div>
                        <div><?php echo date('d/m/Y', strtotime($apt['scheduled_date'])); ?></div>
                        <span class="status <?php echo $apt['status']; ?>"><?php echo ucfirst($apt['status']); ?></span>
                        <div class="appointment-actions">
                            <a href="join_consultation.php?id=<?php echo $apt['id']; ?>" class="btn-small" style="background: #28a745;">Iniciar Videoconsulta</a>
                        </div>
                    </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="no-appointments">
                        <p>No hay citas próximas</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- New Appointment Modal -->
    <div id="appointmentModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal()">&times;</span>
            <h2>Nueva Cita</h2>
            <form method="POST" action="">
                <input type="hidden" name="action" value="create">
                
                <div class="form-group">
                    <label>Paciente *</label>
                    <select name="patient_id" required>
                        <option value="">Seleccionar paciente...</option>
                        <?php while ($patient = $patients->fetch_assoc()): ?>
                        <option value="<?php echo $patient['id']; ?>"><?php echo htmlspecialchars($patient['full_name']); ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Fecha *</label>
                    <input type="date" name="appointment_date" required min="<?php echo date('Y-m-d'); ?>">
                </div>
                
                <div class="form-group">
                    <label>Hora *</label>
                    <input type="time" name="appointment_time" required>
                </div>
                
                <div class="form-group">
                    <label>Notas</label>
                    <textarea name="notes" rows="3" placeholder="Observaciones o motivo de la consulta..."></textarea>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn">Agendar Cita</button>
                    <button type="button" class="btn" style="background: #6c757d;" onclick="closeModal()">Cancelar</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Edit Appointment Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeEditModal()">&times;</span>
            <h2>Editar Cita</h2>
            <form method="POST" action="">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="id" id="edit_id">
                
                <div class="form-group">
                    <label>Estado</label>
                    <select name="status" id="edit_status">
                        <option value="scheduled">Programada</option>
                        <option value="completed">Completada</option>
                        <option value="cancelled">Cancelada</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Notas</label>
                    <textarea name="notes" id="edit_notes" rows="3"></textarea>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn">Guardar Cambios</button>
                    <button type="button" class="btn" style="background: #6c757d;" onclick="closeEditModal()">Cancelar</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Calendar events data
        const appointments = <?php echo json_encode($appointments_by_date); ?>;
        
        // Convert appointments to FullCalendar events
        const events = [];
        for (const [date, appts] of Object.entries(appointments)) {
            appts.forEach(apt => {
                const time = apt.scheduled_date.split(' ')[1].substring(0, 5);
                events.push({
                    id: apt.id,
                    title: apt.patient_name,
                    start: apt.scheduled_date,
                    backgroundColor: apt.status === 'scheduled' ? '#0066cc' : 
                                   apt.status === 'completed' ? '#28a745' : '#dc3545',
                    extendedProps: {
                        status: apt.status,
                        room_name: apt.room_name,
                        notes: apt.notes || ''
                    }
                });
            });
        }
        
        // Initialize calendar
        document.addEventListener('DOMContentLoaded', function() {
            var calendarEl = document.getElementById('calendar');
            var calendar = new FullCalendar.Calendar(calendarEl, {
                initialView: 'dayGridMonth',
                headerToolbar: {
                    left: 'prev,next today',
                    center: 'title',
                    right: 'dayGridMonth,timeGridWeek,listWeek'
                },
                events: events,
                eventClick: function(info) {
                    openEditModal(info.event);
                },
                locale: 'es',
                height: 'auto',
                eventDisplay: 'block',
                eventTextColor: '#fff',
                dayMaxEvents: 3
            });
            calendar.render();
        });
        
        // Modal functions
        function openModal(date) {
            if (date) {
                document.querySelector('input[name="appointment_date"]').value = date;
            }
            document.getElementById('appointmentModal').classList.add('show');
        }
        
        function closeModal() {
            document.getElementById('appointmentModal').classList.remove('show');
        }
        
        function openEditModal(event) {
            document.getElementById('edit_id').value = event.id;
            document.getElementById('edit_status').value = event.extendedProps.status;
            document.getElementById('edit_notes').value = event.extendedProps.notes || '';
            document.getElementById('editModal').classList.add('show');
        }
        
        function closeEditModal() {
            document.getElementById('editModal').classList.remove('show');
        }
        
        // Close modal on outside click
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                closeModal();
                closeEditModal();
            }
        }
    </script>
</body>
</html>
<?php $conn->close(); ?>

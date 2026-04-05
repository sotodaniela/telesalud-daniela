<?php
session_start();

if (!isset($_SESSION['doctor_id'])) {
    header('Location: login.php');
    exit;
}

$conn = new mysqli('telehealth-db', 'telehealth', 'telehealth123', 'telehealth');
$doctor_id = $_SESSION['doctor_id'];
$message = '';
$message_type = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        
        // Crear/Actualizar paciente
        if ($_POST['action'] === 'save_patient') {
            $id_type = $conn->real_escape_string($_POST['id_type']);
            $id_number = $conn->real_escape_string($_POST['id_number']);
            $first_name = $conn->real_escape_string($_POST['first_name']);
            $second_name = $conn->real_escape_string($_POST['second_name'] ?? '');
            $last_name = $conn->real_escape_string($_POST['last_name']);
            $second_last_name = $conn->real_escape_string($_POST['second_last_name'] ?? '');
            $birth_date = $conn->real_escape_string($_POST['birth_date']);
            $gender = $conn->real_escape_string($_POST['gender']);
            $email = $conn->real_escape_string($_POST['email'] ?? '');
            $phone = $conn->real_escape_string($_POST['phone'] ?? '');
            $mobile = $conn->real_escape_string($_POST['mobile'] ?? '');
            $address = $conn->real_escape_string($_POST['address'] ?? '');
            $city = $conn->real_escape_string($_POST['city'] ?? '');
            $department = $conn->real_escape_string($_POST['department'] ?? '');
            $emergency_contact_name = $conn->real_escape_string($_POST['emergency_contact_name'] ?? '');
            $emergency_contact_phone = $conn->real_escape_string($_POST['emergency_contact_phone'] ?? '');
            $emergency_contact_relation = $conn->real_escape_string($_POST['emergency_contact_relation'] ?? '');
            $eps_name = $conn->real_escape_string($_POST['eps_name'] ?? '');
            $eps_afiliation_type = $conn->real_escape_string($_POST['eps_afiliation_type'] ?? 'Contributivo');
            $occupation = $conn->real_escape_string($_POST['occupation'] ?? '');
            $education_level = $conn->real_escape_string($_POST['education_level'] ?? '');
            $marital_status = $conn->real_escape_string($_POST['marital_status'] ?? 'Soltero');
            
            if (isset($_POST['patient_id']) && $_POST['patient_id'] != '') {
                // Update
                $patient_id = intval($_POST['patient_id']);
                $sql = "UPDATE patients SET 
                    id_type='$id_type', id_number='$id_number', first_name='$first_name', 
                    second_name='$second_name', last_name='$last_name', second_last_name='$second_last_name',
                    birth_date='$birth_date', gender='$gender', email='$email', phone='$phone',
                    mobile='$mobile', address='$address', city='$city', department='$department',
                    emergency_contact_name='$emergency_contact_name', emergency_contact_phone='$emergency_contact_phone',
                    emergency_contact_relation='$emergency_contact_relation', eps_name='$eps_name',
                    eps_afiliation_type='$eps_afiliation_type', occupation='$occupation',
                    education_level='$education_level', marital_status='$marital_status'
                    WHERE id=$patient_id";
                $conn->query($sql);
                $message = 'Paciente actualizado exitosamente';
            } else {
                // Create
                $sql = "INSERT INTO patients (id_type, id_number, first_name, second_name, last_name, second_last_name,
                        birth_date, gender, email, phone, mobile, address, city, department,
                        emergency_contact_name, emergency_contact_phone, emergency_contact_relation,
                        eps_name, eps_afiliation_type, occupation, education_level, marital_status)
                        VALUES ('$id_type', '$id_number', '$first_name', '$second_name', '$last_name', '$second_last_name',
                        '$birth_date', '$gender', '$email', '$phone', '$mobile', '$address', '$city', '$department',
                        '$emergency_contact_name', '$emergency_contact_phone', '$emergency_contact_relation',
                        '$eps_name', '$eps_afiliation_type', '$occupation', '$education_level', '$marital_status')";
                $conn->query($sql);
                $message = 'Paciente creado exitosamente';
            }
            $message_type = 'success';
        }
        
        // Eliminar paciente
        if ($_POST['action'] === 'delete_patient') {
            $patient_id = intval($_POST['patient_id']);
            $conn->query("UPDATE patients SET is_active = 0 WHERE id=$patient_id");
            $message = 'Paciente eliminado exitosamente';
            $message_type = 'success';
        }
    }
}

// Get patients
$search = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';
$where = "WHERE is_active = 1";
if ($search) {
    $where .= " AND (first_name LIKE '%$search%' OR last_name LIKE '%$search%' OR id_number LIKE '%$search%')";
}
$patients = $conn->query("SELECT * FROM patients $where ORDER BY last_name, first_name");

// Get patient for edit
$edit_patient = null;
if (isset($_GET['edit'])) {
    $edit_id = intval($_GET['edit']);
    $edit_result = $conn->query("SELECT * FROM patients WHERE id=$edit_id");
    $edit_patient = $edit_result->fetch_assoc();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pacientes - PAHO Telesalud</title>
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
            flex-wrap: wrap;
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
        .btn-warning { background: #ffc107; color: #000; }
        .btn-warning:hover { background: #e0a800; }
        .btn-danger { background: #dc3545; }
        .btn-danger:hover { background: #c82333; }
        
        .search-box {
            display: flex;
            gap: 10px;
        }
        .search-box input {
            padding: 10px 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            width: 300px;
        }
        
        .card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .card h2 {
            color: #333;
            margin-bottom: 15px;
            font-size: 18px;
            border-bottom: 2px solid #0066cc;
            padding-bottom: 10px;
        }
        
        .message {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .message.success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .message.error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        
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
        
        .actions-cell {
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
            align-items: flex-start;
            padding: 20px;
            overflow-y: auto;
        }
        .modal.show { display: flex; }
        .modal-content {
            background: white;
            padding: 30px;
            border-radius: 10px;
            max-width: 800px;
            width: 100%;
        }
        .modal h2 {
            margin-bottom: 20px;
            color: #333;
            border-bottom: 2px solid #0066cc;
            padding-bottom: 10px;
        }
        .close {
            float: right;
            font-size: 24px;
            cursor: pointer;
            color: #999;
        }
        .close:hover { color: #333; }
        
        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
        }
        .form-grid-3 {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group.full-width {
            grid-column: span 2;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            color: #666;
            font-weight: 500;
            font-size: 13px;
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
        .form-section {
            background: #f9f9f9;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .form-section h3 {
            color: #0066cc;
            margin-bottom: 15px;
            font-size: 14px;
        }
        .form-actions {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }
        
        .empty-state {
            text-align: center;
            padding: 50px;
            color: #999;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>PAHO Telesalud - Gestión de Pacientes</h1>
        <div class="nav">
            <a href="dashboard.php">Dashboard</a>
            <a href="schedule.php">Agendamiento</a>
            <a href="patients.php" class="active">Pacientes</a>
            <a href="logout.php" class="logout">Cerrar Sesión</a>
        </div>
    </div>
    
    <div class="container">
        <?php if ($message): ?>
        <div class="message <?php echo $message_type; ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
        <?php endif; ?>
        
        <div class="top-bar">
            <h2 style="color: #333;">Lista de Pacientes</h2>
            <div style="display: flex; gap: 10px; align-items: center;">
                <form method="GET" class="search-box">
                    <input type="text" name="search" placeholder="Buscar por nombre o documento..." value="<?php echo htmlspecialchars($search); ?>">
                    <button type="submit" class="btn btn-primary">Buscar</button>
                </form>
                <button class="btn" onclick="openModal()">+ Nuevo Paciente</button>
            </div>
        </div>
        
        <div class="card">
            <?php if ($patients->num_rows > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>Tipo Doc</th>
                        <th>Número</th>
                        <th>Nombre Completo</th>
                        <th>Fecha Nac.</th>
                        <th>Teléfono</th>
                        <th>EPS</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = $patients->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo $row['id_type']; ?></td>
                        <td><?php echo htmlspecialchars($row['id_number']); ?></td>
                        <td><?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?></td>
                        <td><?php echo date('d/m/Y', strtotime($row['birth_date'])); ?></td>
                        <td><?php echo htmlspecialchars($row['mobile'] ?: $row['phone']); ?></td>
                        <td><?php echo htmlspecialchars($row['eps_name'] ?: '-'); ?></td>
                        <td class="actions-cell">
                            <a href="clinical_history.php?patient_id=<?php echo $row['id']; ?>" class="btn-small" style="background: #17a2b8;">Historia Clínica</a>
                            <a href="edit_patient.php?id=<?php echo $row['id']; ?>" class="btn-small btn-warning">Editar</a>
                            <form method="POST" style="display: inline;" onsubmit="return confirm('¿Eliminar este paciente?');">
                                <input type="hidden" name="action" value="delete_patient">
                                <input type="hidden" name="patient_id" value="<?php echo $row['id']; ?>">
                                <button type="submit" class="btn-small btn-danger">Eliminar</button>
                            </form>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
            <?php else: ?>
            <div class="empty-state">
                <p>No se encontraron pacientes</p>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Patient Modal -->
    <div id="patientModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal()">&times;</span>
            <h2 id="modalTitle">Nuevo Paciente</h2>
            
            <form method="POST" action="" id="patientForm">
                <input type="hidden" name="action" value="save_patient">
                <input type="hidden" name="patient_id" id="patient_id" value="">
                
                <div class="form-section">
                    <h3>📋 Identificación</h3>
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Tipo de Documento *</label>
                            <select name="id_type" id="id_type" required>
                                <option value="CC">Cédula de Ciudadanía (CC)</option>
                                <option value="CE">Cédula de Extranjería (CE)</option>
                                <option value="TI">Tarjeta de Identidad (TI)</option>
                                <option value="RC">Registro Civil (RC)</option>
                                <option value="PA">Pasaporte (PA)</option>
                                <option value="NIT">NIT</option>
                                <option value="PEP">PEP</option>
                                <option value="MSP">Menor sin Identificación (MSP)</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Número de Documento *</label>
                            <input type="text" name="id_number" id="id_number" required>
                        </div>
                    </div>
                </div>
                
                <div class="form-section">
                    <h3>👤 Datos Personales</h3>
                    <div class="form-grid-3">
                        <div class="form-group">
                            <label>Primer Nombre *</label>
                            <input type="text" name="first_name" id="first_name" required>
                        </div>
                        <div class="form-group">
                            <label>Segundo Nombre</label>
                            <input type="text" name="second_name" id="second_name">
                        </div>
                        <div class="form-group">
                            <label>Primer Apellido *</label>
                            <input type="text" name="last_name" id="last_name" required>
                        </div>
                        <div class="form-group">
                            <label>Segundo Apellido</label>
                            <input type="text" name="second_last_name" id="second_last_name">
                        </div>
                        <div class="form-group">
                            <label>Fecha de Nacimiento *</label>
                            <input type="date" name="birth_date" id="birth_date" required>
                        </div>
                        <div class="form-group">
                            <label>Género *</label>
                            <select name="gender" id="gender" required>
                                <option value="M">Masculino</option>
                                <option value="F">Femenino</option>
                                <option value="O">Otro</option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <div class="form-section">
                    <h3>📞 Información de Contacto</h3>
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Email</label>
                            <input type="email" name="email" id="email">
                        </div>
                        <div class="form-group">
                            <label>Teléfono Fijo</label>
                            <input type="text" name="phone" id="phone">
                        </div>
                        <div class="form-group">
                            <label>Celular</label>
                            <input type="text" name="mobile" id="mobile">
                        </div>
                        <div class="form-group">
                            <label>Ciudad</label>
                            <input type="text" name="city" id="city">
                        </div>
                        <div class="form-group full-width">
                            <label>Dirección</label>
                            <input type="text" name="address" id="address">
                        </div>
                    </div>
                </div>
                
                <div class="form-section">
                    <h3>⚠️ Contacto de Emergencia</h3>
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Nombre</label>
                            <input type="text" name="emergency_contact_name" id="emergency_contact_name">
                        </div>
                        <div class="form-group">
                            <label>Teléfono</label>
                            <input type="text" name="emergency_contact_phone" id="emergency_contact_phone">
                        </div>
                        <div class="form-group">
                            <label>Parentesco</label>
                            <select name="emergency_contact_relation" id="emergency_contact_relation">
                                <option value="">Seleccionar...</option>
                                <option value="Cónyuge">Cónyuge</option>
                                <option value="Hijo/a">Hijo/a</option>
                                <option value="Padre">Padre</option>
                                <option value="Madre">Madre</option>
                                <option value="Hermano/a">Hermano/a</option>
                                <option value="Otro">Otro</option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <div class="form-section">
                    <h3>🏥 Información de Afiliación</h3>
                    <div class="form-grid">
                        <div class="form-group">
                            <label>EPS</label>
                            <input type="text" name="eps_name" id="eps_name" placeholder="Nombre de la EPS">
                        </div>
                        <div class="form-group">
                            <label>Tipo de Afiliación</label>
                            <select name="eps_afiliation_type" id="eps_afiliation_type">
                                <option value="Contributivo">Contributivo</option>
                                <option value="Subsidiado">Subsidiado</option>
                                <option value="Vinculado">Vinculado</option>
                                <option value="Especial">Especial</option>
                                <option value="Particular">Particular</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Ocupación</label>
                            <input type="text" name="occupation" id="occupation">
                        </div>
                        <div class="form-group">
                            <label>Estado Civil</label>
                            <select name="marital_status" id="marital_status">
                                <option value="Soltero">Soltero(a)</option>
                                <option value="Casado">Casado(a)</option>
                                <option value="Unión Libre">Unión Libre</option>
                                <option value="Viudo">Viudo(a)</option>
                                <option value="Separado">Separado(a)</option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn">Guardar Paciente</button>
                    <button type="button" class="btn" style="background: #6c757d;" onclick="closeModal()">Cancelar</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openModal() {
            document.getElementById('modalTitle').textContent = 'Nuevo Paciente';
            document.getElementById('patientForm').reset();
            document.getElementById('patient_id').value = '';
            document.getElementById('patientModal').classList.add('show');
        }
        
        function closeModal() {
            document.getElementById('patientModal').classList.remove('show');
        }
        
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                closeModal();
            }
        }
        
        // Auto-open modal if editing
        <?php if ($edit_patient): ?>
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('modalTitle').textContent = 'Editar Paciente';
            document.getElementById('patient_id').value = '<?php echo $edit_patient['id']; ?>';
            document.getElementById('id_type').value = '<?php echo $edit_patient['id_type']; ?>';
            document.getElementById('id_number').value = '<?php echo htmlspecialchars($edit_patient['id_number'] ?? ''); ?>';
            document.getElementById('first_name').value = '<?php echo htmlspecialchars($edit_patient['first_name'] ?? ''); ?>';
            document.getElementById('second_name').value = '<?php echo htmlspecialchars($edit_patient['second_name'] ?? ''); ?>';
            document.getElementById('last_name').value = '<?php echo htmlspecialchars($edit_patient['last_name'] ?? ''); ?>';
            document.getElementById('second_last_name').value = '<?php echo htmlspecialchars($edit_patient['second_last_name'] ?? ''); ?>';
            document.getElementById('birth_date').value = '<?php echo $edit_patient['birth_date'] ?? ''; ?>';
            document.getElementById('gender').value = '<?php echo $edit_patient['gender'] ?? 'M'; ?>';
            document.getElementById('email').value = '<?php echo htmlspecialchars($edit_patient['email'] ?? ''); ?>';
            document.getElementById('phone').value = '<?php echo htmlspecialchars($edit_patient['phone'] ?? ''); ?>';
            document.getElementById('mobile').value = '<?php echo htmlspecialchars($edit_patient['mobile'] ?? ''); ?>';
            document.getElementById('address').value = '<?php echo htmlspecialchars($edit_patient['address'] ?? ''); ?>';
            document.getElementById('city').value = '<?php echo htmlspecialchars($edit_patient['city'] ?? ''); ?>';
            document.getElementById('department').value = '<?php echo htmlspecialchars($edit_patient['department'] ?? ''); ?>';
            document.getElementById('emergency_contact_name').value = '<?php echo htmlspecialchars($edit_patient['emergency_contact_name'] ?? ''); ?>';
            document.getElementById('emergency_contact_phone').value = '<?php echo htmlspecialchars($edit_patient['emergency_contact_phone'] ?? ''); ?>';
            document.getElementById('emergency_contact_relation').value = '<?php echo htmlspecialchars($edit_patient['emergency_contact_relation'] ?? ''); ?>';
            document.getElementById('eps_name').value = '<?php echo htmlspecialchars($edit_patient['eps_name'] ?? ''); ?>';
            document.getElementById('eps_afiliation_type').value = '<?php echo $edit_patient['eps_afiliation_type'] ?? 'Contributivo'; ?>';
            document.getElementById('occupation').value = '<?php echo htmlspecialchars($edit_patient['occupation'] ?? ''); ?>';
            document.getElementById('marital_status').value = '<?php echo $edit_patient['marital_status'] ?? 'Soltero'; ?>';
            document.getElementById('patientModal').classList.add('show');
        });
        <?php endif; ?>
    </script>
</body>
</html>
<?php $conn->close(); ?>

<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$conn = new mysqli('telehealth-db', 'telehealth', 'telehealth123', 'telehealth');
$doctor_id = $_SESSION['user_id'];
$message = '';
$error = '';

$patient_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Get patient
$result = $conn->query("SELECT * FROM patients WHERE id = $patient_id AND is_active = 1");
$patient = $result->fetch_assoc();

if (!$patient) {
    header('Location: patients.php');
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
    
    if ($conn->query($sql)) {
        $message = 'Paciente actualizado exitosamente';
        // Refresh patient data
        $result = $conn->query("SELECT * FROM patients WHERE id = $patient_id");
        $patient = $result->fetch_assoc();
    } else {
        $error = 'Error al actualizar: ' . $conn->error;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Paciente - PAHO Telesalud</title>
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
        }
        .card h2 {
            color: #333;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #0066cc;
        }
        
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
            color: #555;
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
        .form-group input:focus, .form-group select:focus {
            outline: none;
            border-color: #0066cc;
        }
        
        .form-section {
            background: #f9f9f9;
            padding: 20px;
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
        .btn {
            background: #28a745;
            color: white;
            padding: 12px 25px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            cursor: pointer;
            text-decoration: none;
        }
        .btn:hover { background: #218838; }
        .btn-secondary { background: #6c757d; }
        .btn-secondary:hover { background: #5a6268; }
        
        .message {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .message.success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .message.error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Editar Paciente - PAHO Telesalud</h1>
        <div class="nav">
            <a href="patients.php">Volver a Pacientes</a>
        </div>
    </div>
    
    <div class="container">
        <?php if ($message): ?>
        <div class="message success"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
        <div class="message error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <div class="card">
            <h2>Editar Datos del Paciente</h2>
            
            <form method="POST" action="">
                <div class="form-section">
                    <h3>Identificacion</h3>
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Tipo de Documento *</label>
                            <select name="id_type" required>
                                <option value="CC" <?php echo $patient['id_type'] === 'CC' ? 'selected' : ''; ?>>Cedula de Ciudadania (CC)</option>
                                <option value="CE" <?php echo $patient['id_type'] === 'CE' ? 'selected' : ''; ?>>Cedula de Extranjeria (CE)</option>
                                <option value="TI" <?php echo $patient['id_type'] === 'TI' ? 'selected' : ''; ?>>Tarjeta de Identidad (TI)</option>
                                <option value="RC" <?php echo $patient['id_type'] === 'RC' ? 'selected' : ''; ?>>Registro Civil (RC)</option>
                                <option value="PA" <?php echo $patient['id_type'] === 'PA' ? 'selected' : ''; ?>>Pasaporte (PA)</option>
                                <option value="NIT" <?php echo $patient['id_type'] === 'NIT' ? 'selected' : ''; ?>>NIT</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Numero de Documento *</label>
                            <input type="text" name="id_number" value="<?php echo htmlspecialchars($patient['id_number'] ?? ''); ?>" required>
                        </div>
                    </div>
                </div>
                
                <div class="form-section">
                    <h3>Datos Personales</h3>
                    <div class="form-grid-3">
                        <div class="form-group">
                            <label>Primer Nombre *</label>
                            <input type="text" name="first_name" value="<?php echo htmlspecialchars($patient['first_name'] ?? ''); ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Segundo Nombre</label>
                            <input type="text" name="second_name" value="<?php echo htmlspecialchars($patient['second_name'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label>Primer Apellido *</label>
                            <input type="text" name="last_name" value="<?php echo htmlspecialchars($patient['last_name'] ?? ''); ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Segundo Apellido</label>
                            <input type="text" name="second_last_name" value="<?php echo htmlspecialchars($patient['second_last_name'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label>Fecha de Nacimiento *</label>
                            <input type="date" name="birth_date" value="<?php echo $patient['birth_date'] ?? ''; ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Genero *</label>
                            <select name="gender" required>
                                <option value="M" <?php echo ($patient['gender'] ?? '') === 'M' ? 'selected' : ''; ?>>Masculino</option>
                                <option value="F" <?php echo ($patient['gender'] ?? '') === 'F' ? 'selected' : ''; ?>>Femenino</option>
                                <option value="O" <?php echo ($patient['gender'] ?? '') === 'O' ? 'selected' : ''; ?>>Otro</option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <div class="form-section">
                    <h3>Informacion de Contacto</h3>
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Email</label>
                            <input type="email" name="email" value="<?php echo htmlspecialchars($patient['email'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label>Telefono Fijo</label>
                            <input type="text" name="phone" value="<?php echo htmlspecialchars($patient['phone'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label>Celular</label>
                            <input type="text" name="mobile" value="<?php echo htmlspecialchars($patient['mobile'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label>Ciudad</label>
                            <input type="text" name="city" value="<?php echo htmlspecialchars($patient['city'] ?? ''); ?>">
                        </div>
                        <div class="form-group full-width">
                            <label>Direccion</label>
                            <input type="text" name="address" value="<?php echo htmlspecialchars($patient['address'] ?? ''); ?>">
                        </div>
                    </div>
                </div>
                
                <div class="form-section">
                    <h3>Contacto de Emergencia</h3>
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Nombre</label>
                            <input type="text" name="emergency_contact_name" value="<?php echo htmlspecialchars($patient['emergency_contact_name'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label>Telefono</label>
                            <input type="text" name="emergency_contact_phone" value="<?php echo htmlspecialchars($patient['emergency_contact_phone'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label>Parentesco</label>
                            <select name="emergency_contact_relation">
                                <option value="">Seleccionar...</option>
                                <option value="Conyuge" <?php echo ($patient['emergency_contact_relation'] ?? '') === 'Conyuge' ? 'selected' : ''; ?>>Conyuge</option>
                                <option value="Hijo/a" <?php echo ($patient['emergency_contact_relation'] ?? '') === 'Hijo/a' ? 'selected' : ''; ?>>Hijo/a</option>
                                <option value="Padre" <?php echo ($patient['emergency_contact_relation'] ?? '') === 'Padre' ? 'selected' : ''; ?>>Padre</option>
                                <option value="Madre" <?php echo ($patient['emergency_contact_relation'] ?? '') === 'Madre' ? 'selected' : ''; ?>>Madre</option>
                                <option value="Hermano/a" <?php echo ($patient['emergency_contact_relation'] ?? '') === 'Hermano/a' ? 'selected' : ''; ?>>Hermano/a</option>
                                <option value="Otro" <?php echo ($patient['emergency_contact_relation'] ?? '') === 'Otro' ? 'selected' : ''; ?>>Otro</option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <div class="form-section">
                    <h3>Informacion de Afiliacion</h3>
                    <div class="form-grid">
                        <div class="form-group">
                            <label>EPS</label>
                            <input type="text" name="eps_name" value="<?php echo htmlspecialchars($patient['eps_name'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label>Tipo de Afiliacion</label>
                            <select name="eps_afiliation_type">
                                <option value="Contributivo" <?php echo ($patient['eps_afiliation_type'] ?? '') === 'Contributivo' ? 'selected' : ''; ?>>Contributivo</option>
                                <option value="Subsidiado" <?php echo ($patient['eps_afiliation_type'] ?? '') === 'Subsidiado' ? 'selected' : ''; ?>>Subsidiado</option>
                                <option value="Vinculado" <?php echo ($patient['eps_afiliation_type'] ?? '') === 'Vinculado' ? 'selected' : ''; ?>>Vinculado</option>
                                <option value="Especial" <?php echo ($patient['eps_afiliation_type'] ?? '') === 'Especial' ? 'selected' : ''; ?>>Especial</option>
                                <option value="Particular" <?php echo ($patient['eps_afiliation_type'] ?? '') === 'Particular' ? 'selected' : ''; ?>>Particular</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Ocupacion</label>
                            <input type="text" name="occupation" value="<?php echo htmlspecialchars($patient['occupation'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label>Estado Civil</label>
                            <select name="marital_status">
                                <option value="Soltero" <?php echo ($patient['marital_status'] ?? '') === 'Soltero' ? 'selected' : ''; ?>>Soltero(a)</option>
                                <option value="Casado" <?php echo ($patient['marital_status'] ?? '') === 'Casado' ? 'selected' : ''; ?>>Casado(a)</option>
                                <option value="Union Libre" <?php echo ($patient['marital_status'] ?? '') === 'Union Libre' ? 'selected' : ''; ?>>Union Libre</option>
                                <option value="Viudo" <?php echo ($patient['marital_status'] ?? '') === 'Viudo' ? 'selected' : ''; ?>>Viudo(a)</option>
                                <option value="Separado" <?php echo ($patient['marital_status'] ?? '') === 'Separado' ? 'selected' : ''; ?>>Separado(a)</option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn">Guardar Cambios</button>
                    <a href="patients.php" class="btn btn-secondary">Cancelar</a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
<?php $conn->close(); ?>

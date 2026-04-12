<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

$conn = new mysqli('telehealth-db', 'telehealth', 'telehealth123', 'telehealth');

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];
        
        if ($action === 'create') {
            $username = $conn->real_escape_string($_POST['username']);
            $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
            $role = $conn->real_escape_string($_POST['role']);
            $full_name = $conn->real_escape_string($_POST['full_name']);
            $email = $conn->real_escape_string($_POST['email']);
            $specialty = $conn->real_escape_string($_POST['specialty']);
            
            $check = $conn->query("SELECT id FROM users WHERE username = '$username'");
            if ($check->num_rows > 0) {
                $error = 'El usuario ya existe';
            } else {
                $conn->query("INSERT INTO users (username, password, role, full_name, email, specialty) 
                              VALUES ('$username', '$password', '$role', '$full_name', '$email', '$specialty')");
                $success = 'Usuario creado exitosamente';
            }
        }
        
        if ($action === 'update') {
            $id = intval($_POST['id']);
            $full_name = $conn->real_escape_string($_POST['full_name']);
            $email = $conn->real_escape_string($_POST['email']);
            $specialty = $conn->real_escape_string($_POST['specialty']);
            $role = $conn->real_escape_string($_POST['role']);
            $is_active = isset($_POST['is_active']) ? 1 : 0;
            
            if (!empty($_POST['new_password'])) {
                $new_password = password_hash($_POST['new_password'], PASSWORD_DEFAULT);
                $conn->query("UPDATE users SET full_name='$full_name', email='$email', specialty='$specialty', role='$role', is_active=$is_active, password='$new_password' WHERE id=$id");
            } else {
                $conn->query("UPDATE users SET full_name='$full_name', email='$email', specialty='$specialty', role='$role', is_active=$is_active WHERE id=$id");
            }
            $success = 'Usuario actualizado exitosamente';
        }
        
        if ($action === 'update_permissions') {
            $user_id = intval($_POST['user_id']);
            $modules = isset($_POST['modules']) ? $_POST['modules'] : [];
            
            $all_modules = [
                'dashboard' => 'Dashboard',
                'patients' => 'Pacientes',
                'schedule' => 'Agenda/Citas',
                'teleconsulta' => 'Teleconsulta',
                'clinical_history' => 'Historia Clínica',
                'users' => 'Gestión de Usuarios'
            ];
            
            foreach ($all_modules as $key => $name) {
                $is_enabled = isset($modules[$key]) ? 1 : 0;
                $conn->query("INSERT INTO user_module_permissions (user_id, module_key, module_name, is_enabled) 
                             VALUES ($user_id, '$key', '$name', $is_enabled)
                             ON DUPLICATE KEY UPDATE is_enabled = $is_enabled");
            }
            $success = 'Permisos actualizados exitosamente';
        }
        
        if ($action === 'delete') {
            $id = intval($_POST['id']);
            if ($id !== $_SESSION['user_id']) {
                $conn->query("DELETE FROM users WHERE id=$id");
                $success = 'Usuario eliminado exitosamente';
            } else {
                $error = 'No puedes eliminar tu propio usuario';
            }
        }
    }
}

$users = $conn->query("SELECT * FROM users ORDER BY role, full_name");

$user_permissions = [];
$permissions_result = $conn->query("SELECT * FROM user_module_permissions");
while ($perm = $permissions_result->fetch_assoc()) {
    $user_permissions[$perm['user_id']][$perm['module_key']] = $perm['is_enabled'];
}

$conn->close();

$all_modules = [
    'dashboard' => ['name' => 'Dashboard', 'icon' => '📊'],
    'patients' => ['name' => 'Pacientes', 'icon' => '👥'],
    'schedule' => ['name' => 'Agenda/Citas', 'icon' => '📅'],
    'teleconsulta' => ['name' => 'Teleconsulta', 'icon' => '📹'],
    'clinical_history' => ['name' => 'Historia Clínica', 'icon' => '📋'],
    'users' => ['name' => 'Gestión de Usuarios', 'icon' => '⚙️']
];

$role_labels = [
    'admin' => 'Administrador',
    'doctor' => 'Médico',
    'nurse' => 'Enfermería',
    'assistant' => 'Auxiliar'
];

$role_colors = [
    'admin' => '#6f42c1',
    'doctor' => '#dc3545',
    'nurse' => '#17a2b8',
    'assistant' => '#28a745'
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Usuarios - Telesalud</title>
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
        }
        
        .container {
            max-width: 1200px;
            margin: 30px auto;
            padding: 0 20px;
        }
        
        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
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
            transition: background 0.3s;
        }
        .btn:hover { background: #218838; }
        .btn-primary { background: #1a5276; }
        .btn-danger { background: #dc3545; }
        
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
        
        .role-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 15px;
            font-size: 12px;
            font-weight: 600;
            color: white;
        }
        
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 15px;
            font-size: 12px;
            font-weight: 600;
        }
        .status-badge.active { background: #d4edda; color: #155724; }
        .status-badge.inactive { background: #f8d7da; color: #721c24; }
        
        .btn-small {
            padding: 6px 12px;
            border-radius: 5px;
            font-size: 12px;
            cursor: pointer;
            border: none;
            text-decoration: none;
            color: white;
            margin-right: 5px;
        }
        .btn-edit { background: #17a2b8; }
        .btn-delete { background: #dc3545; }
        
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
            border-radius: 15px;
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
        .form-group input, .form-group select {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
        }
        .form-group input:focus, .form-group select:focus {
            outline: none;
            border-color: #1a5276;
        }
        .form-actions {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }
        .close {
            float: right;
            font-size: 28px;
            cursor: pointer;
            color: #999;
        }
        .close:hover { color: #333; }
        
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .checkbox-group input {
            width: auto;
        }
        
        .toggle-switch input:checked + .toggle-slider {
            background-color: #28a745;
        }
        .toggle-switch input:checked + .toggle-slider:before {
            transform: translateX(24px);
        }
        .toggle-slider:before {
            position: absolute;
            content: "";
            height: 20px;
            width: 20px;
            left: 3px;
            bottom: 3px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }
        .toggle-switch input:checked + .toggle-slider {
            background-color: #28a745;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Telesalud - Gestión de Usuarios</h1>
        <div class="nav">
            <a href="dashboard.php">Dashboard</a>
            <a href="users.php" class="active">Usuarios</a>
            <a href="logout.php" class="logout">Cerrar Sesión</a>
        </div>
    </div>
    
    <div class="container">
        <?php if ($success): ?>
        <div class="alert alert-success">✓ <?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
        <div class="alert alert-error">⚠️ <?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <div class="top-bar">
            <h2 style="color: #333;">Usuarios del Sistema</h2>
            <button class="btn" onclick="openModal()">+ Nuevo Usuario</button>
        </div>
        
        <div class="card">
            <table>
                <thead>
                    <tr>
                        <th>Usuario</th>
                        <th>Nombre Completo</th>
                        <th>Rol</th>
                        <th>Email</th>
                        <th>Especialidad</th>
                        <th>Estado</th>
                        <th>Último Acceso</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($user = $users->fetch_assoc()): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($user['username']); ?></strong></td>
                        <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                        <td><span class="role-badge" style="background: <?php echo $role_colors[$user['role']]; ?>;"><?php echo $role_labels[$user['role']]; ?></span></td>
                        <td><?php echo htmlspecialchars($user['email'] ?? '-'); ?></td>
                        <td><?php echo htmlspecialchars($user['specialty'] ?? '-'); ?></td>
                        <td><span class="status-badge <?php echo $user['is_active'] ? 'active' : 'inactive'; ?>"><?php echo $user['is_active'] ? 'Activo' : 'Inactivo'; ?></span></td>
                        <td><?php echo $user['last_login'] ? date('d/m/Y H:i', strtotime($user['last_login'])) : 'Nunca'; ?></td>
                        <td>
                            <button class="btn-small btn-edit" onclick="editUser(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>', '<?php echo htmlspecialchars($user['full_name']); ?>', '<?php echo htmlspecialchars($user['email'] ?? ''); ?>', '<?php echo htmlspecialchars($user['specialty'] ?? ''); ?>', '<?php echo $user['role']; ?>', <?php echo $user['is_active']; ?>)">Editar</button>
                            <button class="btn-small" style="background: #6f42c1;" onclick="openPermissionsModal(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['full_name']); ?>')">Permisos</button>
                            <?php if ($user['id'] != $_SESSION['user_id']): ?>
                            <button class="btn-small btn-delete" onclick="deleteUser(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['full_name']); ?>')">Eliminar</button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- Create User Modal -->
    <div id="userModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal()">&times;</span>
            <h2>Nuevo Usuario</h2>
            <form method="POST" action="">
                <input type="hidden" name="action" value="create">
                
                <div class="form-group">
                    <label>Usuario *</label>
                    <input type="text" name="username" required placeholder="Nombre de usuario">
                </div>
                
                <div class="form-group">
                    <label>Contraseña *</label>
                    <input type="password" name="password" required placeholder="Contraseña">
                </div>
                
                <div class="form-group">
                    <label>Nombre Completo *</label>
                    <input type="text" name="full_name" required placeholder="Nombre completo">
                </div>
                
                <div class="form-group">
                    <label>Rol *</label>
                    <select name="role" required>
                        <option value="admin">Administrador</option>
                        <option value="doctor">Médico</option>
                        <option value="nurse">Enfermería</option>
                        <option value="assistant">Auxiliar</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" placeholder="correo@ejemplo.com">
                </div>
                
                <div class="form-group">
                    <label>Especialidad</label>
                    <input type="text" name="specialty" placeholder="Especialidad o área">
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn">Crear Usuario</button>
                    <button type="button" class="btn" style="background: #6c757d;" onclick="closeModal()">Cancelar</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Edit User Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeEditModal()">&times;</span>
            <h2>Editar Usuario</h2>
            <form method="POST" action="">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="id" id="edit_id">
                
                <div class="form-group">
                    <label>Usuario</label>
                    <input type="text" id="edit_username" disabled style="background: #f5f5f5;">
                </div>
                
                <div class="form-group">
                    <label>Nueva Contraseña (dejar vacío para mantener)</label>
                    <input type="password" name="new_password" placeholder="Nueva contraseña">
                </div>
                
                <div class="form-group">
                    <label>Nombre Completo *</label>
                    <input type="text" name="full_name" id="edit_full_name" required>
                </div>
                
                <div class="form-group">
                    <label>Rol *</label>
                    <select name="role" id="edit_role" required>
                        <option value="admin">Administrador</option>
                        <option value="doctor">Médico</option>
                        <option value="nurse">Enfermería</option>
                        <option value="assistant">Auxiliar</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" id="edit_email">
                </div>
                
                <div class="form-group">
                    <label>Especialidad</label>
                    <input type="text" name="specialty" id="edit_specialty">
                </div>
                
                <div class="form-group checkbox-group">
                    <input type="checkbox" name="is_active" id="edit_is_active" value="1">
                    <label for="edit_is_active">Usuario activo</label>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn">Guardar Cambios</button>
                    <button type="button" class="btn" style="background: #6c757d;" onclick="closeEditModal()">Cancelar</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="modal">
        <div class="modal-content" style="max-width: 400px;">
            <h2>Confirmar Eliminación</h2>
            <p style="margin: 20px 0;">¿Está seguro de eliminar al usuario <strong id="delete_user_name"></strong>?</p>
            <form method="POST" action="">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" id="delete_id">
                <div class="form-actions">
                    <button type="submit" class="btn btn-danger">Eliminar</button>
                    <button type="button" class="btn" style="background: #6c757d;" onclick="closeDeleteModal()">Cancelar</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Permissions Modal -->
    <div id="permissionsModal" class="modal">
        <div class="modal-content" style="max-width: 500px;">
            <span class="close" onclick="closePermissionsModal()">&times;</span>
            <h2>Permisos de Módulos</h2>
            <p style="margin: 10px 0 20px; color: #666;">Usuario: <strong id="perm_user_name"></strong></p>
            <form method="POST" action="">
                <input type="hidden" name="action" value="update_permissions">
                <input type="hidden" name="user_id" id="perm_user_id">
                <div style="display: grid; gap: 12px;">
                    <?php foreach ($all_modules as $key => $module): ?>
                    <div class="module-permission-item" style="display: flex; align-items: center; justify-content: space-between; padding: 12px 15px; background: #f8f9fa; border-radius: 8px; border: 1px solid #e9ecef;">
                        <div style="display: flex; align-items: center; gap: 10px;">
                            <span style="font-size: 20px;"><?php echo $module['icon']; ?></span>
                            <span style="font-weight: 500; color: #333;"><?php echo $module['name']; ?></span>
                        </div>
                        <label class="toggle-switch" style="position: relative; display: inline-block; width: 50px; height: 26px;">
                            <input type="checkbox" name="modules[<?php echo $key; ?>]" id="perm_<?php echo $key; ?>" value="1" class="perm-toggle" style="opacity: 0; width: 0; height: 0;">
                            <span class="toggle-slider" style="position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #ccc; transition: .4s; border-radius: 34px; before: none;"></span>
                        </label>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div class="form-actions" style="margin-top: 25px;">
                    <button type="submit" class="btn">Guardar Permisos</button>
                    <button type="button" class="btn" style="background: #6c757d;" onclick="closePermissionsModal()">Cancelar</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        function openModal() {
            document.getElementById('userModal').classList.add('show');
        }
        
        function closeModal() {
            document.getElementById('userModal').classList.remove('show');
        }
        
        function editUser(id, username, full_name, email, specialty, role, is_active) {
            document.getElementById('edit_id').value = id;
            document.getElementById('edit_username').value = username;
            document.getElementById('edit_full_name').value = full_name;
            document.getElementById('edit_email').value = email;
            document.getElementById('edit_specialty').value = specialty;
            document.getElementById('edit_role').value = role;
            document.getElementById('edit_is_active').checked = is_active;
            document.getElementById('editModal').classList.add('show');
        }
        
        function closeEditModal() {
            document.getElementById('editModal').classList.remove('show');
        }
        
        function deleteUser(id, name) {
            document.getElementById('delete_id').value = id;
            document.getElementById('delete_user_name').textContent = name;
            document.getElementById('deleteModal').classList.add('show');
        }
        
        function closeDeleteModal() {
            document.getElementById('deleteModal').classList.remove('show');
        }
        
        function openPermissionsModal(userId, userName) {
            document.getElementById('perm_user_id').value = userId;
            document.getElementById('perm_user_name').textContent = userName;
            
            // Reset all toggles
            document.querySelectorAll('.perm-toggle').forEach(toggle => {
                toggle.checked = false;
            });
            
            // Get current permissions via AJAX
            fetch('get_user_permissions.php?user_id=' + userId)
                .then(response => response.json())
                .then(data => {
                    Object.keys(data).forEach(key => {
                        const toggle = document.getElementById('perm_' + key);
                        if (toggle && data[key] == 1) {
                            toggle.checked = true;
                        }
                    });
                })
                .catch(() => {
                    console.log('Using default permissions');
                });
            
            document.getElementById('permissionsModal').classList.add('show');
        }
        
        function closePermissionsModal() {
            document.getElementById('permissionsModal').classList.remove('show');
        }
        
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                closeModal();
                closeEditModal();
                closeDeleteModal();
                closePermissionsModal();
            }
        }
    </script>
</body>
</html>

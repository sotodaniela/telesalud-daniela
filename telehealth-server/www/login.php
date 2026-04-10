<?php
session_start();

if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    $conn = new mysqli('telehealth-db', 'telehealth', 'telehealth123', 'telehealth');
    
    if ($conn->connect_error) {
        $error = 'Error de conexión a la base de datos';
    } else {
        $stmt = $conn->prepare('SELECT id, username, password, full_name, role, specialty, is_active FROM users WHERE username = ?');
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            if ($user['is_active'] == 0) {
                $error = 'Usuario inactivo. Contacte al administrador.';
            } elseif (password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['full_name'];
                $_SESSION['user_role'] = $user['role'];
                $_SESSION['user_specialty'] = $user['specialty'];
                
                $conn->query("UPDATE users SET last_login = NOW() WHERE id = " . $user['id']);
                
                header('Location: dashboard.php');
                exit;
            } else {
                $error = 'Credenciales incorrectas';
            }
        } else {
            $error = 'Credenciales incorrectas';
        }
        $stmt->close();
        $conn->close();
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Telesalud</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #5dade2 0%, #3498db 50%, #2980b9 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .login-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            max-width: 400px;
            width: 100%;
            padding: 40px;
        }
        .logo {
            text-align: center;
            margin-bottom: 30px;
        }
        .logo-icon {
            width: 120px;
            height: auto;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
        }
        .logo-icon img {
            width: 100%;
            height: auto;
            border-radius: 10px;
        }
        .logo h1 {
            color: #1a5276;
            font-size: 24px;
            font-weight: 700;
        }
        .logo p {
            color: #666;
            font-size: 14px;
            margin-top: 5px;
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
        .form-group input {
            width: 100%;
            padding: 14px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 15px;
            transition: border-color 0.3s;
        }
        .form-group input:focus {
            outline: none;
            border-color: #2874a6;
        }
        .btn {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #1a5276, #2874a6);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(26, 82, 118, 0.4);
        }
        .error {
            background: #f8d7da;
            color: #721c24;
            padding: 14px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-size: 14px;
            border: 1px solid #f5c6cb;
        }
        .credits {
            margin-top: 20px;
            text-align: center;
            font-size: 12px;
            color: #999;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="logo">
            <div class="logo-icon">
                <img src="images/Logo Ladera ESE.png" alt="Logo" style="width: 80px; height: auto; border-radius: 10px;">
            </div>
            <h1>Red de Salud Ladera ESE</h1>
            <p>Sistema de Telesalud</p>
        </div>
        
        <?php if ($error): ?>
        <div class="error">⚠️ <?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <div class="form-group">
                <label for="username">Usuario</label>
                <input type="text" id="username" name="username" required autocomplete="username" placeholder="Ingrese su usuario">
            </div>
            <div class="form-group">
                <label for="password">Contraseña</label>
                <input type="password" id="password" name="password" required autocomplete="current-password" placeholder="Ingrese su contraseña">
            </div>
            <button type="submit" class="btn">Ingresar</button>
        </form>
        
        <div class="credits">
            <p>Usuarios de prueba: admin, medico, enfermera, auxiliar</p>
            <p>Contraseña: pass</p>
        </div>
    </div>
</body>
</html>

<?php
session_start();

// ============================================================================
// HEADERS DE SEGURIDAD - DESPUÉS DE SESSION_START
// ============================================================================
require_once __DIR__ . '/../config/security_headers.php';

include 'includes/conexion.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $correo = $_POST['correo'];
    $contrasena = $_POST['contrasena'];

    $sql = "SELECT * FROM usuarios WHERE correo = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $correo);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $usuario = $result->fetch_assoc();

        if (password_verify($contrasena, $usuario['contrasena'])) {
            // Verificar si el email está confirmado, excepto para admins
            if (!$usuario['email_verificado'] && $usuario['rol'] !== 'admin') {
                // Email no verificado - redirigir con error
                $_SESSION['email_no_verificado'] = true;
                $_SESSION['email_pendiente'] = $correo;
                safe_redirect("resend_verification.php?email=" . urlencode($correo));
            }
            
            $_SESSION['id'] = $usuario['id'];
            $_SESSION['nombre'] = $usuario['nombre'];
            $_SESSION['rol'] = $usuario['rol'];

            if ($usuario['rol'] === 'admin') {
                safe_redirect("admin/panel.php");
            } else {
                safe_redirect("usuario/panel.php");
            }
        } else {
            $error = "⚠ Contraseña incorrecta.";
        }
    } else {
        $error = "⚠ Usuario no encontrado.";
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Iniciar sesión</title>
    <link rel="stylesheet" href="Estilos/login.css">
    <style>
        /* Estilos para Google Sign-In Button */
        .login-container {
            display: flex;
            flex-direction: column;
            gap: 20px;
            max-width: 400px;
            margin: auto;
        }
        
        .divider {
            display: flex;
            align-items: center;
            margin: 20px 0;
            gap: 10px;
        }
        
        .divider::before,
        .divider::after {
            content: '';
            flex: 1;
            height: 1px;
            background: #ddd;
        }
        
        .divider-text {
            color: #666;
            font-size: 14px;
        }
        
        .google-login-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            padding: 12px 20px;
            background: white;
            border: 1px solid #ddd;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 500;
            color: #333;
            transition: all 0.3s ease;
            text-decoration: none;
        }
        
        .google-login-btn:hover {
            background: #f8f8f8;
            border-color: #999;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .google-login-btn img {
            width: 20px;
            height: 20px;
        }
        
        .error-alert {
            background: #fee;
            color: #c33;
            padding: 12px;
            border-radius: 4px;
            margin-bottom: 15px;
            border-left: 4px solid #c33;
        }
        
        .success-alert {
            background: #efe;
            color: #3c3;
            padding: 12px;
            border-radius: 4px;
            margin-bottom: 15px;
            border-left: 4px solid #3c3;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <h2>Iniciar sesión</h2>

        <?php if (isset($error)): ?>
            <div class="error-alert"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if (isset($_GET['registro']) && $_GET['registro'] == 'exitoso'): ?>
            <div class="success-alert">✓ Usuario registrado con éxito. Ahora puedes iniciar sesión.</div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
            <div class="error-alert"><?php echo isset($_SESSION['error']) ? $_SESSION['error'] : ''; ?></div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <!-- Google Login Button -->
        <a href="api/google_login_start.php" class="google-login-btn">
            <img src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24'%3E%3Ctext x='50%25' y='50%25' dominant-baseline='middle' text-anchor='middle' font-size='14' fill='%234285F4'%3EG%3C/text%3E%3C/svg%3E" alt="Google Logo">
            Continuar con Google
        </a>

        <div class="divider">
            <span class="divider-text">o usa tu email</span>
        </div>

        <form method="POST" action="login.php">
            <label for="correo">Correo:</label><br>
            <input type="email" name="correo" required><br><br>

            <label for="contrasena">Contraseña:</label><br>
            <input type="password" name="contrasena" required><br><br>

            <button type="submit">Entrar</button>
            <br>
        </form>
        
        <p>¿No tienes cuenta? <a href="registro.php">Regístrate aquí</a></p>
    </div>
</body>
</html>
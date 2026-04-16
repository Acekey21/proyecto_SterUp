<?php
session_start();

// ============================================================================
// HEADERS DE SEGURIDAD
// ============================================================================
require_once __DIR__ . '/../config/security_headers.php';

include 'includes/conexion.php';
require_once 'includes/email_service.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $nombre = trim($_POST['nombre']);
    $correo = trim($_POST['correo']);
    $contrasena = $_POST['contrasena'];
    $confirmar = $_POST['confirmar'];
    $telefono = $_POST['telefono'] ?? null;
    $fecha_nacimiento = $_POST['fecha_nacimiento'] ?? null;

    // Validar que las contraseñas coincidan
    if ($contrasena !== $confirmar) {
        $error = "Las contraseñas no coinciden.";
    } else if (strlen($contrasena) < 6) {
        $error = "La contraseña debe tener al menos 6 caracteres.";
    } else {
        // Validar si correo ya existe
        $sqlExiste = "SELECT id FROM usuarios WHERE correo = ?";
        $stmtExiste = $conn->prepare($sqlExiste);
        $stmtExiste->bind_param("s", $correo);
        $stmtExiste->execute();
        $resExiste = $stmtExiste->get_result();
        if ($resExiste && $resExiste->num_rows > 0) {
            $error = "El correo ya está registrado. Por favor inicia sesión.";
        } else {
            $contrasena_segura = password_hash($contrasena, PASSWORD_DEFAULT);

            // Insertar usuario con email NO verificado
            $sql = "INSERT INTO usuarios (nombre, correo, contrasena, rol, email_verificado) VALUES (?, ?, ?, 'usuario', 0)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sss", $nombre, $correo, $contrasena_segura);

            if ($stmt->execute()) {
                $usuario_id = $conn->insert_id;
                
                // Generar token único de verificación (válido por 24 horas)
                $verification_token = bin2hex(random_bytes(32));
                $expires_at = date('Y-m-d H:i:s', time() + 86400); // 24 horas
                
                // Guardar token en BD
                $insert_token = $conn->prepare(
                    "INSERT INTO email_verification_tokens (usuario_id, token, email, expires_at) 
                     VALUES (?, ?, ?, ?)"
                );
                $insert_token->bind_param("isss", $usuario_id, $verification_token, $correo, $expires_at);
                
                if ($insert_token->execute()) {
                    // Construir enlace de verificación
                    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
                    $host = $_SERVER['HTTP_HOST'];
                    $verification_link = $protocol . "://" . $host . "/StepUp/nova/api/verify_email.php?token=" . $verification_token;
                    
                    // Enviar correo de verificación
                    $email_result = send_email_verification(
                        $correo,
                        $nombre,
                        $verification_token,
                        $verification_link,
                        1440  // 24 horas
                    );
                    
                    if ($email_result['success']) {
                        $success = "✓ Registro exitoso. Te hemos enviado un correo de confirmación. Por favor verifica tu email para activar tu cuenta.";
                        $_SESSION['registro_pendiente'] = true;
                        $_SESSION['registro_email'] = $correo;
                    } else {
                        // El usuario se registró pero falló el email - mostrar advertencia
                        $warning = "Registro completado, pero hay un problema enviando el email de verificación. Intenta con el botón 'Reenviar' después.";
                    }
                } else {
                    $error = "Error al generar token de verificación: " . $conn->error;
                }
                
                $insert_token->close();
            } else {
                $error = "Error al registrar: " . $stmt->error;
            }
            $stmt->close();
        }
        $stmtExiste->close();
    }
}

?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Registro de Usuario</title>
    <link rel="stylesheet" href="Estilos/registro.css">
    <style>
        .alert { padding: 15px; margin: 20px 0; border-radius: 4px; }
        .alert-success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .alert-warning { background-color: #fff3cd; color: #856404; border: 1px solid #ffeaa7; }
    </style>
</head>
<body>
    <h2>Registro de Usuario</h2>

    <?php if (isset($success)): ?>
        <div class="alert alert-success">
            <?php echo $success; ?>
        </div>
        <div style="text-align: center; margin-top: 30px;">
            <p>No ves el email? <a href="resend_verification.php">Reenviar correo</a></p>
            <p><a href="login.php">Ir al login</a></p>
        </div>
    <?php elseif (isset($warning)): ?>
        <div class="alert alert-warning"><?php echo $warning; ?></div>
        <div style="text-align: center; margin-top: 30px;">
            <p>No ves el email? <a href="resend_verification.php">Reenviar correo</a></p>
            <p><a href="login.php">Ir al login</a></p>
        </div>
    <?php elseif (isset($error)): ?>
        <div class="alert alert-error"><?php echo $error; ?></div>
        <form method="POST">
            <label>Nombre completo:</label><br>
            <input type="text" name="nombre" value="<?php echo htmlspecialchars($nombre ?? ''); ?>" required><br>

            <label>Correo electrónico:</label><br>
            <input type="email" name="correo" value="<?php echo htmlspecialchars($correo ?? ''); ?>" required><br>

            <label>Contraseña:</label><br>
            <input type="password" name="contrasena" required><br>

            <label>Confirmar contraseña:</label><br>
            <input type="password" name="confirmar" required><br>

            <button type="submit">Registrarme</button>
        </form>

        <br><a href="login.php">Volver al login</a>
    <?php elseif (isset($warning)): ?>
        <div class="alert alert-warning"><?php echo $warning; ?></div>
        <div style="text-align: center; margin-top: 30px;">
            <p><a href="resend_verification.php">Reenviar correo de verificación</a></p>
            <p><a href="login.php">Ir al login</a></p>
        </div>
    <?php else: ?>
        <form method="POST">
            <label>Nombre completo:</label><br>
            <input type="text" name="nombre" required><br>

            <label>Correo electrónico:</label><br>
            <input type="email" name="correo" required><br>

            <label>Contraseña:</label><br>
            <input type="password" name="contrasena" required><br>

            <label>Confirmar contraseña:</label><br>
            <input type="password" name="confirmar" required><br>

            <button type="submit">Registrarme</button>
        </form>

        <br><a href="login.php">Volver al login</a>
    <?php endif; ?>
</body>
</html>
<?php
/**
 * VERIFY_EMAIL.PHP
 * Endpoint para verificar email con token
 * Se accede desde el enlace enviado al email de confirmación
 */

require_once __DIR__ . '/init.php';
require_once __DIR__ . '/../includes/conexion.php';

// Obtener token del query string
$token = $_GET['token'] ?? null;

if (!$token) {
    $error = "Token no proporcionado";
}

if (!isset($error)) {
    // Buscar el token en la BD
    $sql = "SELECT * FROM email_verification_tokens 
            WHERE token = ? AND verificado = 0 AND expires_at > NOW()";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        $error = "Token inválido o expirado";
    } else {
        $token_data = $result->fetch_assoc();
        $usuario_id = $token_data['usuario_id'];
        
        // Marcar como verificado en la BD
        $update_token = $conn->prepare(
            "UPDATE email_verification_tokens 
             SET verificado = 1, verificado_at = NOW() 
             WHERE id = ?"
        );
        $update_token->bind_param("i", $token_data['id']);
        
        $update_usuario = $conn->prepare(
            "UPDATE usuarios 
             SET email_verificado = 1, email_verificado_at = NOW() 
             WHERE id = ?"
        );
        $update_usuario->bind_param("i", $usuario_id);
        
        if ($update_token->execute() && $update_usuario->execute()) {
            $success = "¡Email verificado exitosamente! Tu cuenta está activa. Ahora puedes iniciar sesión.";
        } else {
            $error = "Error al verificar email: " . $conn->error;
        }
        
        $update_token->close();
        $update_usuario->close();
    }
    
    $stmt->close();
}

?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Verificación de Email - StepUp</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .container {
            background: white;
            padding: 40px;
            border-radius: 8px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
            max-width: 500px;
            width: 90%;
            text-align: center;
        }
        h1 { color: #333; margin-bottom: 20px; }
        .success {
            background: #d4edda;
            color: #155724;
            padding: 20px;
            border-radius: 4px;
            margin-bottom: 20px;
            border-left: 4px solid #28a745;
        }
        .error {
            background: #f8d7da;
            color: #721c24;
            padding: 20px;
            border-radius: 4px;
            margin-bottom: 20px;
            border-left: 4px solid #dc3545;
        }
        .icon {
            font-size: 60px;
            margin-bottom: 20px;
        }
        p { color: #666; margin-bottom: 15px; line-height: 1.6; }
        a {
            display: inline-block;
            margin-top: 20px;
            padding: 12px 30px;
            background: #667eea;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            transition: background 0.3s;
        }
        a:hover { background: #5568d3; }
        .secondary-link {
            display: block;
            margin-top: 15px;
            color: #667eea;
            text-decoration: none;
        }
        .secondary-link:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class="container">
        <?php if (isset($success)): ?>
            <div class="icon">✅</div>
            <h1>Email Verificado</h1>
            <div class="success">
                <p><?php echo $success; ?></p>
            </div>
            <p>Tu email ha sido confirmado exitosamente. Ahora puedes acceder a todas las funciones de StepUp.</p>
            <a href="<?php echo '/StepUp/nova/login.php'; ?>">Ir al Login</a>
            
        <?php elseif (isset($error)): ?>
            <div class="icon">❌</div>
            <h1>Error de Verificación</h1>
            <div class="error">
                <p><?php echo $error; ?></p>
            </div>
            <p>Este enlace puede haber expirado o ya fue utilizado. Intenta registrarte nuevamente o solicita un correo de verificación.</p>
            <a href="<?php echo '/StepUp/nova/registro.php'; ?>">Registrarse de Nuevo</a>
            <a href="<?php echo '/StepUp/nova/resend_verification.php'; ?>" class="secondary-link">Reenviar Correo</a>
        <?php endif; ?>
    </div>
</body>
</html>

<?php
/**
 * RESEND_VERIFICATION.PHP
 * Permite a usuarios que no recibieron el correo de verificación
 * solicitar que se reenvíe el correo
 */

session_start();
require_once __DIR__ . '/includes/conexion.php';
require_once __DIR__ . '/includes/email_service.php';

$success = null;
$error = null;
$email_no_verificado = isset($_SESSION['email_no_verificado']) && $_SESSION['email_no_verificado'];
$email_initial = $_GET['email'] ?? ($_SESSION['email_pendiente'] ?? '');

// Limpiar sesión
if ($email_no_verificado) {
    unset($_SESSION['email_no_verificado']);
    unset($_SESSION['email_pendiente']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Por favor ingresa un email válido";
    } else {
        // Buscar usuario no verificado
        $sql = "SELECT id, nombre, correo, email_verificado FROM usuarios WHERE correo = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            $error = "Este email no está registrado";
        } else {
            $usuario = $result->fetch_assoc();
            
            // Si ya está verificado
            if ($usuario['email_verificado']) {
                $error = "Este email ya está verificado. Puedes <a href='login.php'>iniciar sesión</a>";
            } else {
                // Generar nuevo token (invalidar los anteriores)
                $delete_old = $conn->prepare(
                    "DELETE FROM email_verification_tokens WHERE usuario_id = ?"
                );
                $delete_old->bind_param("i", $usuario['id']);
                $delete_old->execute();
                
                // Crear nuevo token
                $verification_token = bin2hex(random_bytes(32));
                $expires_at = date('Y-m-d H:i:s', time() + 86400); // 24 horas
                
                $insert_token = $conn->prepare(
                    "INSERT INTO email_verification_tokens (usuario_id, token, email, expires_at) 
                     VALUES (?, ?, ?, ?)"
                );
                $insert_token->bind_param("isss", $usuario['id'], $verification_token, $email, $expires_at);
                
                if ($insert_token->execute()) {
                    // Construir enlace de verificación
                    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
                    $host = $_SERVER['HTTP_HOST'];
                    $verification_link = $protocol . "://" . $host . "/StepUp/nova/api/verify_email.php?token=" . $verification_token;
                    
                    // Enviar correo
                    $email_result = send_email_verification(
                        $email,
                        $usuario['nombre'],
                        $verification_token,
                        $verification_link,
                        1440
                    );
                    
                    if ($email_result['success']) {
                        $success = "✓ Correo de verificación reenviado exitosamente. Por favor revisa tu email (incluyendo la carpeta de SPAM).";
                    } else {
                        $error = "Error al enviar el correo: " . $email_result['message'];
                    }
                } else {
                    $error = "Error al generar token: " . $conn->error;
                }
                
                $delete_old->close();
                $insert_token->close();
            }
        }
        
        $stmt->close();
    }
}

?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reenviar Correo de Verificación - StepUp</title>
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
            max-width: 400px;
            width: 90%;
        }
        h1 { color: #333; margin-bottom: 10px; font-size: 24px; }
        .subtitle { color: #666; margin-bottom: 30px; font-size: 14px; }
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
            border-left: 4px solid;
        }
        .alert-success {
            background: #d4edda;
            color: #155724;
            border-color: #28a745;
        }
        .alert-success a { color: #155724; font-weight: bold; }
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border-color: #dc3545;
        }
        form {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        label {
            color: #333;
            font-weight: 600;
            margin-bottom: 5px;
        }
        input[type="email"] {
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
            font-family: inherit;
        }
        input[type="email"]:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        button {
            padding: 12px;
            background: #667eea;
            color: white;
            border: none;
            border-radius: 4px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.3s;
        }
        button:hover { background: #5568d3; }
        button:active { background: #4657b8; }
        .links {
            display: flex;
            justify-content: space-between;
            margin-top: 20px;
            font-size: 14px;
        }
        a {
            color: #667eea;
            text-decoration: none;
        }
        a:hover { text-decoration: underline; }
        .info-box {
            background: #f0f4ff;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
            font-size: 13px;
            color: #555;
            border-left: 4px solid #667eea;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Verificar Email</h1>
        <p class="subtitle">
            <?php 
            if ($email_no_verificado) {
                echo "📧 Tu email aún no está verificado";
            } else {
                echo "Reenviar correo de confirmación";
            }
            ?>
        </p>
        
        <?php if ($success): ?>
            <div class="alert alert-success">
                <?php echo $success; ?>
            </div>
            <div class="links">
                <a href="login.php">← Volver al login</a>
            </div>
        <?php else: ?>
            <?php if ($email_no_verificado): ?>
                <div class="alert alert-error">
                    ⚠️ Para completar tu acceso a StepUp, debes verificar tu email primero.
                </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-error">
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <div class="info-box">
                📧 Ingresa el email con el que te registraste. Te enviaremos un nuevo enlace de confirmación.
            </div>
            
            <form method="POST">
                <div>
                    <label for="email">Email de registro:</label>
                    <input type="email" id="email" name="email" placeholder="tu_email@example.com" value="<?php echo htmlspecialchars($email_initial); ?>" required>
                </div>
                <button type="submit">Reenviar Correo</button>
            </form>
            
            <div class="links">
                <a href="login.php">Ir al login</a>
                <a href="registro.php">Registrarse de nuevo</a>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>

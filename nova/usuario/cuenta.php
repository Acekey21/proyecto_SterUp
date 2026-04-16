<?php
// Panel de cuenta de usuario con SSO
session_start();
if (!isset($_SESSION['id'])) {
    header("Location: ../login.php");
    exit();
}

require_once '../includes/conexion.php';
require_once '../auth/middleware.php';

// Verificar autenticación JWT si existe
$user_jwt = null;
if (isset($_COOKIE['jwt_token'])) {
    $user_jwt = auth_verify_token();
}

// Obtener información del usuario
$user_id = $_SESSION['id'];
$stmt = $conn->prepare("SELECT id, nombre, correo, rol_id, fecha_registro FROM usuarios WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Obtener sesiones activas
$sessions = [];
$stmt = $conn->prepare("SELECT token, app_origen, created_at, expires_at FROM sesiones WHERE usuario_id = ? AND estado = 'activa' ORDER BY created_at DESC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $sessions[] = $row;
}
$stmt->close();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mi Cuenta - StepUp</title>
    <link rel="stylesheet" href="../Estilos/usuarios.css">
    <style>
        .account-container {
            max-width: 1000px;
            margin: 20px auto;
            padding: 20px;
        }
        .account-section {
            background: white;
            margin: 20px 0;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .section-header {
            border-bottom: 2px solid #3498db;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }
        .user-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }
        .info-item {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            border-left: 4px solid #3498db;
        }
        .sso-section {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        .sso-apps {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        .sso-app {
            background: rgba(255,255,255,0.1);
            padding: 20px;
            border-radius: 10px;
            text-align: center;
            backdrop-filter: blur(10px);
        }
        .sso-app h3 {
            margin-top: 0;
            color: white;
        }
        .btn-sso {
            background: #27ae60;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            margin: 10px 5px;
            text-decoration: none;
            display: inline-block;
        }
        .btn-sso:hover {
            background: #229954;
        }
        .btn-sso.secondary {
            background: #e74c3c;
        }
        .btn-sso.secondary:hover {
            background: #c0392b;
        }
        .token-display {
            background: #2c3e50;
            color: #ecf0f1;
            padding: 15px;
            border-radius: 5px;
            margin: 10px 0;
            font-family: monospace;
            word-break: break-all;
        }
        .sessions-list {
            margin-top: 20px;
        }
        .session-item {
            background: #f8f9fa;
            padding: 15px;
            margin: 10px 0;
            border-radius: 5px;
            border-left: 4px solid #f39c12;
        }
        .session-item strong {
            color: #2c3e50;
        }
        .copy-btn {
            background: #3498db;
            color: white;
            border: none;
            padding: 5px 10px;
            border-radius: 3px;
            cursor: pointer;
            font-size: 12px;
            margin-left: 10px;
        }
        .copy-btn:hover {
            background: #2980b9;
        }
    </style>
</head>
<body>

<div class="top-nav">
    Bienvenido, <?php echo htmlspecialchars($user['nombre']); ?> |
    <a href="panel.php">🏠 Tienda</a> |
    <a href="../logout.php">🚪 Cerrar sesión</a>
</div>

<div class="account-container">
    <h1>👤 Mi Cuenta - StepUp</h1>

    <!-- Información del Usuario -->
    <div class="account-section">
        <div class="section-header">
            <h2>📋 Información de la Cuenta</h2>
        </div>
        <div class="user-info">
            <div class="info-item">
                <strong>Nombre:</strong><br>
                <?php echo htmlspecialchars($user['nombre']); ?>
            </div>
            <div class="info-item">
                <strong>Email:</strong><br>
                <?php echo htmlspecialchars($user['correo']); ?>
            </div>
            <div class="info-item">
                <strong>Rol:</strong><br>
                <?php echo $user['rol_id'] == 100 ? 'Administrador' : ($user['rol_id'] == 50 ? 'Editor' : 'Usuario'); ?>
            </div>
            <div class="info-item">
                <strong>Fecha de Registro:</strong><br>
                <?php echo date('d/m/Y H:i', strtotime($user['fecha_registro'])); ?>
            </div>
        </div>
    </div>

    <!-- SSO Section -->
    <div class="account-section sso-section">
        <div class="section-header">
            <h2>🔗 Single Sign-On (SSO) - Aplicaciones Conectadas</h2>
            <p style="color: rgba(255,255,255,0.8); margin-top: 5px;">
                Accede a otras aplicaciones sin volver a iniciar sesión
            </p>
        </div>

        <div class="sso-apps">
            <div class="sso-app">
                <h3>🚀 App B</h3>
                <p>Aplicación de demostración que muestra cómo funciona el SSO</p>
                <button class="btn-sso" onclick="generateSSOToken('app_b')">🔑 Generar Token SSO</button>
                <a href="../app_b/" target="_blank" class="btn-sso secondary">🌐 Ir a App B</a>
            </div>
        </div>

        <div id="sso-token-display" style="display: none;">
            <h3>🎫 Token SSO Generado</h3>
            <p>Este token expira en 5 minutos. Cópialo y pégalo en App B:</p>
            <div class="token-display" id="sso-token-text"></div>
            <button class="copy-btn" onclick="copyToken()">📋 Copiar Token</button>
        </div>
    </div>

    <!-- Sesiones Activas -->
    <div class="account-section">
        <div class="section-header">
            <h2>🖥️ Sesiones Activas</h2>
        </div>
        <div class="sessions-list">
            <?php if (empty($sessions)): ?>
                <p>No hay sesiones activas.</p>
            <?php else: ?>
                <?php foreach ($sessions as $session): ?>
                    <div class="session-item">
                        <strong>Aplicación:</strong> <?php echo htmlspecialchars($session['app_origen']); ?><br>
                        <strong>Creada:</strong> <?php echo date('d/m/Y H:i', strtotime($session['created_at'])); ?><br>
                        <strong>Expira:</strong> <?php echo date('d/m/Y H:i', strtotime($session['expires_at'])); ?><br>
                        <strong>Token:</strong> <code><?php echo substr($session['token'], 0, 20) . '...'; ?></code>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
let currentSSOToken = null;

async function generateSSOToken(targetApp) {
    try {
        const response = await fetch('../api/sso_generate.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': 'Bearer <?php echo $_COOKIE['jwt_token'] ?? ''; ?>'
            },
            body: JSON.stringify({
                target_app: targetApp
            })
        });

        const data = await response.json();

        if (data.success) {
            currentSSOToken = data.sso_token;
            document.getElementById('sso-token-text').textContent = data.sso_token;
            document.getElementById('sso-token-display').style.display = 'block';

            // Auto copiar al portapapeles
            navigator.clipboard.writeText(data.sso_token).then(() => {
                alert('Token SSO copiado al portapapeles automáticamente. Pégalo en App B para autenticarte.');
            });
        } else {
            alert('Error generando token SSO: ' + (data.error || 'Error desconocido'));
        }
    } catch (error) {
        alert('Error de conexión: ' + error.message);
    }
}

function copyToken() {
    if (currentSSOToken) {
        navigator.clipboard.writeText(currentSSOToken).then(() => {
            alert('Token copiado al portapapeles');
        });
    }
}

// Verificar si hay parámetro SSO en la URL
const urlParams = new URLSearchParams(window.location.search);
if (urlParams.get('sso') === 'app_b') {
    // Auto-generar token para App B
    setTimeout(() => {
        generateSSOToken('app_b');
    }, 1000);
}
</script>

</body>
</html>
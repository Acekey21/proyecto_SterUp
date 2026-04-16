<?php
require_once __DIR__ . '/init.php';
require_once __DIR__ . '/../includes/conexion.php';
require_once __DIR__ . '/../auth/middleware.php';

$payload = auth_protect();

// Determinar si es admin viendo todas las sesiones o usuario viendo las suyas
$action = $_GET['action'] ?? 'own';

switch ($action) {
    case 'all':
        // Solo admins pueden ver todas las sesiones del sistema
        auth_has_permission($payload, 'sesiones.ver_todas');

        $stmt = $conn->prepare('
            SELECT us.id, us.user_agent, us.ip, us.created_at, us.expires_at, us.revoked,
                   u.nombre, u.correo
            FROM user_sessions us
            JOIN usuarios u ON us.usuario_id = u.id
            ORDER BY us.created_at DESC
        ');
        $stmt->execute();
        $result = $stmt->get_result();
        break;

    case 'own':
    default:
        // Usuarios pueden ver sus propias sesiones
        $usuarioId = $payload['sub'];
        $stmt = $conn->prepare('SELECT id, user_agent, ip, created_at, expires_at, revoked FROM user_sessions WHERE usuario_id = ? ORDER BY created_at DESC');
        $stmt->bind_param('i', $usuarioId);
        $stmt->execute();
        $result = $stmt->get_result();
        break;
}

$sessions = [];
while ($row = $result->fetch_assoc()) {
    $sessions[] = $row;
}

send_json_response(200, ['sessions' => $sessions]);

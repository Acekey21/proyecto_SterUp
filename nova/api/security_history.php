<?php
/**
 * API PARA CONSULTAR HISTORIAL DE SEGURIDAD DEL USUARIO
 * Muestra cambios de contraseña, intentos de recuperación, etc.
 */

require_once __DIR__ . '/init.php';
require_once __DIR__ . '/../auth/middleware.php';
require_once __DIR__ . '/../includes/conexion.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    send_json_response(405, ['error' => 'Método no permitido']);
}

// Verificar autenticación
$payload = auth_protect();

// Determinar la acción
$action = $_GET['action'] ?? 'summary';

switch ($action) {
    case 'password_history':
        // Verificar permiso para ver historial de contraseñas
        auth_has_permission($payload, 'usuarios.ver'); // Solo admins pueden ver historial completo

        $userId = intval($_GET['user_id'] ?? $payload['sub']);

        // Obtener historial de contraseñas (últimas 10)
        $sql = "SELECT ph.created_at, pcl.tipo_cambio, pcl.ip, pcl.exito
                FROM password_history ph
                LEFT JOIN password_change_logs pcl ON ph.usuario_id = pcl.usuario_id
                    AND DATE(ph.created_at) = DATE(pcl.created_at)
                WHERE ph.usuario_id = ?
                ORDER BY ph.created_at DESC LIMIT 10";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $result = $stmt->get_result();

        $history = [];
        while ($row = $result->fetch_assoc()) {
            $history[] = [
                'fecha' => $row['created_at'],
                'tipo' => $row['tipo_cambio'] ?? 'manual',
                'ip' => $row['ip'] ?? 'N/A',
                'exito' => (bool)$row['exito']
            ];
        }

        send_json_response(200, [
            'usuario_id' => $userId,
            'historial_contrasenas' => $history,
            'total_cambios' => count($history)
        ]);
        break;

    case 'reset_attempts':
        // Verificar que sea el propio usuario o admin
        $userId = intval($_GET['user_id'] ?? $payload['sub']);
        if ($userId !== $payload['sub']) {
            auth_has_permission($payload, 'usuarios.ver'); // Solo admins
        }

        // Obtener intentos de recuperación
        $sql = "SELECT prl.created_at, prl.ip, prl.email_sent, prt.expires_at, prt.used
                FROM password_reset_logs prl
                LEFT JOIN password_reset_tokens prt ON prl.token_id = prt.id
                WHERE prl.usuario_id = ?
                ORDER BY prl.created_at DESC LIMIT 20";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $result = $stmt->get_result();

        $attempts = [];
        while ($row = $result->fetch_assoc()) {
            $attempts[] = [
                'fecha' => $row['created_at'],
                'ip' => $row['ip'],
                'email_enviado' => (bool)$row['email_sent'],
                'expiracion_token' => $row['expires_at'],
                'token_usado' => (bool)$row['used']
            ];
        }

        send_json_response(200, [
            'usuario_id' => $userId,
            'intentos_recuperacion' => $attempts,
            'total_intentos' => count($attempts)
        ]);
        break;

    case 'security_summary':
        // Resumen de seguridad para el usuario actual
        $userId = $payload['sub'];

        // Estadísticas de seguridad
        $stats = [];

        // Cambios de contraseña en los últimos 30 días
        $passwordChanges = $conn->prepare("
            SELECT COUNT(*) as total
            FROM password_change_logs
            WHERE usuario_id = ? AND created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)
        ");
        $passwordChanges->bind_param('i', $userId);
        $passwordChanges->execute();
        $stats['cambios_contrasena_30_dias'] = $passwordChanges->get_result()->fetch_assoc()['total'];

        // Intentos de recuperación en los últimos 30 días
        $resetAttempts = $conn->prepare("
            SELECT COUNT(*) as total
            FROM password_reset_logs
            WHERE usuario_id = ? AND created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)
        ");
        $resetAttempts->bind_param('i', $userId);
        $resetAttempts->execute();
        $stats['intentos_recuperacion_30_dias'] = $resetAttempts->get_result()->fetch_assoc()['total'];

        // Último cambio de contraseña
        $lastChange = $conn->prepare("
            SELECT created_at
            FROM password_change_logs
            WHERE usuario_id = ? AND exito = 1
            ORDER BY created_at DESC LIMIT 1
        ");
        $lastChange->bind_param('i', $userId);
        $lastChange->execute();
        $result = $lastChange->get_result();
        $stats['ultimo_cambio_contrasena'] = $result->num_rows > 0 ?
            $result->fetch_assoc()['created_at'] : null;

        // IPs únicas que han solicitado recuperación
        $uniqueIPs = $conn->prepare("
            SELECT COUNT(DISTINCT ip) as total
            FROM password_reset_logs
            WHERE usuario_id = ? AND created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)
        ");
        $uniqueIPs->bind_param('i', $userId);
        $uniqueIPs->execute();
        $stats['ips_unicas_recuperacion'] = $uniqueIPs->get_result()->fetch_assoc()['total'];

        // Estado de tokens activos
        $activeTokens = $conn->prepare("
            SELECT COUNT(*) as total
            FROM password_reset_tokens
            WHERE usuario_id = ? AND used = 0 AND expires_at > NOW()
        ");
        $activeTokens->bind_param('i', $userId);
        $activeTokens->execute();
        $stats['tokens_activos'] = $activeTokens->get_result()->fetch_assoc()['total'];

        send_json_response(200, [
            'usuario_id' => $userId,
            'resumen_seguridad' => $stats,
            'recomendaciones' => [
                'cambiar_contrasena' => $stats['cambios_contrasena_30_dias'] === 0,
                'revisar_intentos' => $stats['intentos_recuperacion_30_dias'] > 2,
                'tokens_activos' => $stats['tokens_activos'] > 0
            ]
        ]);
        break;

    default:
        send_json_response(400, ['error' => 'Acción no válida']);
}
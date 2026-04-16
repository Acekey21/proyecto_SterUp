<?php
/**
 * API PARA RECUPERACIÓN POR LLAMADA TELEFÓNICA
 * Simula llamadas automáticas con códigos de voz
 */

require_once __DIR__ . '/init.php';
require_once __DIR__ . '/../includes/conexion.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_json_response(405, ['error' => 'Método no permitido']);
}

// Validar CSRF para POST
validate_csrf_if_needed();

$data = get_request_data();

$action = $data['action'] ?? 'request';

switch ($action) {
    case 'request':
        // Solicitar llamada automática
        $correo = trim($data['correo'] ?? '');
        $telefono = trim($data['telefono'] ?? '');

        if (!$correo || !$telefono) {
            send_json_response(400, ['error' => 'Correo y teléfono son requeridos']);
        }

        // Validar formato de teléfono
        if (!preg_match('/^[0-9+\-\s()]{10,15}$/', $telefono)) {
            send_json_response(400, ['error' => 'Formato de teléfono inválido']);
        }

        // Buscar usuario por correo
        $userSql = "SELECT id, nombre FROM usuarios WHERE correo = ? AND activo = 1";
        $userStmt = $conn->prepare($userSql);
        $userStmt->bind_param('s', $correo);
        $userStmt->execute();
        $userResult = $userStmt->get_result();

        if ($userResult->num_rows === 0) {
            send_json_response(404, ['error' => 'Usuario no encontrado']);
        }

        $user = $userResult->fetch_assoc();

        // RATE LIMITING: Solo 1 llamada por hora por usuario
        $checkRateLimit = $conn->prepare("
            SELECT COUNT(*) as attempts
            FROM call_recovery_attempts
            WHERE usuario_id = ? AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
        ");
        $checkRateLimit->bind_param('i', $user['id']);
        $checkRateLimit->execute();
        $rateLimitResult = $checkRateLimit->get_result()->fetch_assoc();

        if ($rateLimitResult['attempts'] >= 1) {
            send_json_response(429, ['error' => 'Solo se permite una llamada por hora. Intenta nuevamente más tarde.']);
        }

        // Generar código de voz de 4 dígitos (más fácil de recordar por teléfono)
        $codigoVoz = str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);
        $expires = date('Y-m-d H:i:s', strtotime('+15 minutes'));

        // Guardar intento de llamada en BD
        $insertSql = "INSERT INTO call_recovery_attempts (usuario_id, telefono, codigo_voz, expires_at) VALUES (?, ?, ?, ?)";
        $insertStmt = $conn->prepare($insertSql);
        $insertStmt->bind_param('isss', $user['id'], $telefono, $codigoVoz, $expires);
        $insertStmt->execute();

        // SIMULAR llamada automática (en producción usarías Twilio, Nexmo, etc.)
        $mensajeLlamada = "Hola {$user['nombre']}. Tu código de recuperación de StepUp es: " .
                         implode(' ', str_split($codigoVoz)) . ". Repito: " .
                         implode(' ', str_split($codigoVoz)) . ". Este código expira en 15 minutos.";

        // Guardar en logs para desarrollo (simula llamada)
        $logDir = __DIR__ . '/../logs/calls/';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }

        $logFile = $logDir . date('Y-m-d') . '_calls.log';
        $logEntry = sprintf(
            "[%s] LLAMADA SIMULADA a %s (+%s): %s\n",
            date('Y-m-d H:i:s'),
            $user['nombre'],
            $telefono,
            $mensajeLlamada
        );
        file_put_contents($logFile, $logEntry, FILE_APPEND);

        send_json_response(200, [
            'message' => 'Llamada automática iniciada',
            'telefono' => '+XXX-XXX-' . substr($telefono, -4), // Ocultar número real
            'estimated_wait' => '30-60 segundos', // Tiempo simulado de llamada
            'expires_in' => 900, // 15 minutos en segundos
            'simulado' => true // Indicar que es simulado
        ]);
        break;

    case 'verify':
        // Verificar código de voz
        $correo = trim($data['correo'] ?? '');
        $codigoVoz = trim($data['codigo_voz'] ?? '');

        if (!$correo || !$codigoVoz) {
            send_json_response(400, ['error' => 'Correo y código de voz son requeridos']);
        }

        // Validar formato del código (4 dígitos)
        if (!preg_match('/^\d{4}$/', $codigoVoz)) {
            send_json_response(400, ['error' => 'Código de voz debe ser 4 dígitos']);
        }

        // Buscar usuario
        $userSql = "SELECT id FROM usuarios WHERE correo = ? AND activo = 1";
        $userStmt = $conn->prepare($userSql);
        $userStmt->bind_param('s', $correo);
        $userStmt->execute();
        $userResult = $userStmt->get_result();

        if ($userResult->num_rows === 0) {
            send_json_response(404, ['error' => 'Usuario no encontrado']);
        }

        $userId = $userResult->fetch_assoc()['id'];

        // Buscar código válido
        $codeSql = "SELECT id FROM call_recovery_attempts
                   WHERE usuario_id = ? AND codigo_voz = ? AND usado = 0 AND expires_at > NOW()
                   ORDER BY created_at DESC LIMIT 1";
        $codeStmt = $conn->prepare($codeSql);
        $codeStmt->bind_param('is', $userId, $codigoVoz);
        $codeStmt->execute();
        $codeResult = $codeStmt->get_result();

        if ($codeResult->num_rows === 0) {
            send_json_response(400, ['error' => 'Código de voz inválido o expirado']);
        }

        $codeData = $codeResult->fetch_assoc();

        // Marcar código como usado
        $updateSql = "UPDATE call_recovery_attempts SET usado = 1 WHERE id = ?";
        $updateStmt = $conn->prepare($updateSql);
        $updateStmt->bind_param('i', $codeData['id']);
        $updateStmt->execute();

        // Generar token de reset
        $token = bin2hex(random_bytes(32));
        $tokenExpires = date('Y-m-d H:i:s', strtotime('+30 minutes'));

        $tokenSql = "INSERT INTO password_reset_tokens (usuario_id, token, expires_at, tipo) VALUES (?, ?, ?, 'call')";
        $tokenStmt = $conn->prepare($tokenSql);
        $tokenStmt->bind_param('iss', $userId, $token, $tokenExpires);
        $tokenStmt->execute();

        send_json_response(200, [
            'message' => 'Código de voz verificado exitosamente',
            'reset_token' => $token,
            'expires_in' => 1800 // 30 minutos
        ]);
        break;

    default:
        send_json_response(400, ['error' => 'Acción no válida']);
}
<?php
/**
 * API PARA RECUPERACIÓN POR SMS
 * Simula envío de códigos por SMS para recuperación de contraseña
 */

require_once __DIR__ . '/init.php';
require_once __DIR__ . '/../includes/conexion.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_json_response(405, ['error' => 'Método no permitido']);
}

validate_csrf_if_needed();
$data = get_request_data();

$action = $data['action'] ?? 'request';

switch ($action) {
    case 'request':
        // Solicitar código SMS
        $correo = trim($data['correo'] ?? '');
        $telefono = trim($data['telefono'] ?? '');

        if (!$correo || !$telefono) {
            send_json_response(400, ['error' => 'Correo y teléfono son requeridos']);
        }

        // Validar formato de teléfono (básico)
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

        // RATE LIMITING: Verificar intentos recientes (máximo 2 por hora por usuario)
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $checkRateLimit = $conn->prepare("
            SELECT COUNT(*) as attempts
            FROM sms_recovery_codes
            WHERE usuario_id = ? AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
        ");
        $checkRateLimit->bind_param('i', $user['id']);
        $checkRateLimit->execute();
        $rateLimitResult = $checkRateLimit->get_result()->fetch_assoc();

        if ($rateLimitResult['attempts'] >= 2) {
            send_json_response(429, ['error' => 'Demasiados intentos SMS. Intenta nuevamente en 1 hora.']);
        }

        // Generar código de 6 dígitos
        $codigo = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
        $expires = date('Y-m-d H:i:s', strtotime('+10 minutes'));

        // Guardar código en BD
        $insertSql = "INSERT INTO sms_recovery_codes (usuario_id, telefono, codigo, expires_at) VALUES (?, ?, ?, ?)";
        $insertStmt = $conn->prepare($insertSql);
        $insertStmt->bind_param('isss', $user['id'], $telefono, $codigo, $expires);
        $insertStmt->execute();

        // SIMULAR envío de SMS (en producción usarías un servicio real como Twilio)
        $mensajeSMS = "Tu código de recuperación StepUp es: $codigo\nVálido por 10 minutos.";

        // Guardar en logs para desarrollo (simula envío)
        $logDir = __DIR__ . '/../logs/sms/';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }

        $logFile = $logDir . date('Y-m-d') . '_sms.log';
        $logEntry = sprintf(
            "[%s] SMS enviado a %s (+%s): %s\n",
            date('Y-m-d H:i:s'),
            $user['nombre'],
            $telefono,
            $mensajeSMS
        );
        file_put_contents($logFile, $logEntry, FILE_APPEND);

        send_json_response(200, [
            'message' => 'Código SMS enviado exitosamente',
            'telefono' => '+XXX-XXX-' . substr($telefono, -4), // Ocultar número real
            'expires_in' => 600, // 10 minutos en segundos
            'simulado' => true // Indicar que es simulado
        ]);
        break;

    case 'verify':
        // Verificar código SMS
        $correo = trim($data['correo'] ?? '');
        $codigo = trim($data['codigo'] ?? '');

        if (!$correo || !$codigo) {
            send_json_response(400, ['error' => 'Correo y código son requeridos']);
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
        $codeSql = "SELECT id FROM sms_recovery_codes
                   WHERE usuario_id = ? AND codigo = ? AND usado = 0 AND expires_at > NOW()
                   ORDER BY created_at DESC LIMIT 1";
        $codeStmt = $conn->prepare($codeSql);
        $codeStmt->bind_param('is', $userId, $codigo);
        $codeStmt->execute();
        $codeResult = $codeStmt->get_result();

        if ($codeResult->num_rows === 0) {
            send_json_response(400, ['error' => 'Código inválido o expirado']);
        }

        $codeData = $codeResult->fetch_assoc();

        // Marcar código como usado
        $updateSql = "UPDATE sms_recovery_codes SET usado = 1 WHERE id = ?";
        $updateStmt = $conn->prepare($updateSql);
        $updateStmt->bind_param('i', $codeData['id']);
        $updateStmt->execute();

        // Generar token de reset
        $token = bin2hex(random_bytes(32));
        $tokenExpires = date('Y-m-d H:i:s', strtotime('+30 minutes'));

        $tokenSql = "INSERT INTO password_reset_tokens (usuario_id, token, expires_at, tipo) VALUES (?, ?, ?, 'sms')";
        $tokenStmt = $conn->prepare($tokenSql);
        $tokenStmt->bind_param('iss', $userId, $token, $tokenExpires);
        $tokenStmt->execute();

        send_json_response(200, [
            'message' => 'Código verificado exitosamente',
            'reset_token' => $token,
            'expires_in' => 1800 // 30 minutos
        ]);
        break;

    default:
        send_json_response(400, ['error' => 'Acción no válida']);
}
<?php
require_once __DIR__ . '/init.php';
require_once __DIR__ . '/../includes/conexion.php';
require_once __DIR__ . '/../auth/jwt.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_json_response(405, ['error' => 'Método no permitido']);
}

// Validar CSRF token para POST
validate_csrf_if_needed();

$data = get_request_data();

$correo = trim($data['correo'] ?? '');
$method = trim($data['method'] ?? 'email'); // email, questions, sms, call

if (!$correo) {
    send_json_response(400, ['error' => 'Correo electrónico es requerido']);
}

// Validar formato de email
if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
    send_json_response(400, ['error' => 'Formato de correo electrónico inválido']);
}

// Buscar usuario
$sql = 'SELECT id, nombre, rol_id FROM usuarios WHERE correo = ? AND activo = 1';
$stmt = $conn->prepare($sql);
$stmt->bind_param('s', $correo);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    send_json_response(404, ['error' => 'Usuario no encontrado']);
}

$user = $result->fetch_assoc();

// RATE LIMITING: Verificar intentos recientes (máximo 3 por hora por IP)
$ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$checkRateLimit = $conn->prepare("
    SELECT COUNT(*) as attempts
    FROM password_reset_attempts
    WHERE ip = ? AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
");
$checkRateLimit->bind_param('s', $ip);
$checkRateLimit->execute();
$rateLimitResult = $checkRateLimit->get_result()->fetch_assoc();

if ($rateLimitResult['attempts'] >= 3) {
    send_json_response(429, ['error' => 'Demasiados intentos. Intenta nuevamente en 1 hora.']);
}

// Registrar intento
$insertAttempt = $conn->prepare("INSERT INTO password_reset_attempts (correo, ip) VALUES (?, ?)");
$insertAttempt->bind_param('ss', $correo, $ip);
$insertAttempt->execute();

switch ($method) {
    case 'email':
        // Método original por email
        $token = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

        $insertToken = $conn->prepare("INSERT INTO password_reset_tokens (usuario_id, token, expires_at, tipo, created_ip, user_agent) VALUES (?, ?, ?, 'email', ?, ?)");
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $insertToken->bind_param('issss', $user['id'], $token, $expires, $ip, $userAgent);
        $insertToken->execute();

        // Enviar email (usando el servicio existente)
        require_once '../includes/email_service.php';
        $resetLink = "http://localhost/StepUp/nova/reset_password.php?token=$token";
        send_password_reset_email($correo, $user['nombre'], $resetLink);

        send_json_response(200, [
            'message' => 'Enlace de recuperación enviado al correo electrónico',
            'method' => 'email',
            'expires_in' => 3600
        ]);
        break;

    case 'questions':
        // Verificar que el usuario tiene preguntas secretas
        $questionsCheck = $conn->prepare("SELECT COUNT(*) as total FROM security_questions WHERE usuario_id = ?");
        $questionsCheck->bind_param('i', $user['id']);
        $questionsCheck->execute();
        $questionsCount = $questionsCheck->get_result()->fetch_assoc()['total'];

        if ($questionsCount === 0) {
            send_json_response(400, ['error' => 'No tienes preguntas secretas configuradas. Usa el método email.']);
        }

        // Generar token para preguntas
        $token = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', strtotime('+30 minutes'));

        $insertToken = $conn->prepare("INSERT INTO password_reset_tokens (usuario_id, token, expires_at, tipo, created_ip, user_agent) VALUES (?, ?, ?, 'questions', ?, ?)");
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $insertToken->bind_param('issss', $user['id'], $token, $expires, $ip, $userAgent);
        $insertToken->execute();

        send_json_response(200, [
            'message' => 'Token generado para recuperación con preguntas secretas',
            'method' => 'questions',
            'token' => $token,
            'questions_count' => $questionsCount,
            'expires_in' => 1800
        ]);
        break;

    case 'sms':
        // Método SMS requiere teléfono adicional
        $telefono = trim($data['telefono'] ?? '');
        if (!$telefono) {
            send_json_response(400, ['error' => 'Teléfono es requerido para recuperación por SMS']);
        }

        // Redirigir a API de SMS
        $_POST['correo'] = $correo;
        $_POST['telefono'] = $telefono;
        $_POST['action'] = 'request';
        require 'sms_recovery.php';
        exit;

    case 'call':
        // Método llamada requiere teléfono adicional
        $telefono = trim($data['telefono'] ?? '');
        if (!$telefono) {
            send_json_response(400, ['error' => 'Teléfono es requerido para recuperación por llamada']);
        }

        // Redirigir a API de llamada
        $_POST['correo'] = $correo;
        $_POST['telefono'] = $telefono;
        $_POST['action'] = 'request';
        require 'call_recovery.php';
        exit;

    default:
        send_json_response(400, ['error' => 'Método de recuperación no válido']);
}

if ($result->num_rows !== 1) {
    // No revelar si el correo existe por seguridad
    // Pero sí registrar el intento fallido
    send_json_response(200, [
        'message' => 'Si el correo existe y está activo, se enviaron instrucciones de recuperación.',
        'info' => 'Revisa tu bandeja de entrada y carpeta de spam.'
    ]);
}

$user = $result->fetch_assoc();

// Verificar si ya existe un token activo para este usuario
$checkExisting = $conn->prepare("
    SELECT id FROM password_reset_tokens
    WHERE usuario_id = ? AND used = 0 AND expires_at > NOW()
    ORDER BY created_at DESC LIMIT 1
");
$checkExisting->bind_param('i', $user['id']);
$checkExisting->execute();

if ($checkExisting->get_result()->num_rows > 0) {
    // Ya existe un token activo, no crear otro
    send_json_response(200, [
        'message' => 'Ya se enviaron instrucciones recientemente. Revisa tu email.',
        'info' => 'Si no encuentras el email, espera unos minutos o revisa la carpeta de spam.'
    ]);
}

// Generar token seguro único
do {
    $token = bin2hex(random_bytes(32));
    $checkToken = $conn->prepare("SELECT id FROM password_reset_tokens WHERE token = ?");
    $checkToken->bind_param('s', $token);
    $checkToken->execute();
} while ($checkToken->get_result()->num_rows > 0);

$expires = date('Y-m-d H:i:s', time() + 3600); // 1 hora
$created_ip = $ip;

// Insertar token con información de seguridad
$insert = $conn->prepare('
    INSERT INTO password_reset_tokens
    (usuario_id, token, expires_at, created_ip, user_agent)
    VALUES (?, ?, ?, ?, ?)
');
$userAgent = substr($_SERVER['HTTP_USER_AGENT'] ?? 'desconocido', 0, 255);
$insert->bind_param('issss', $user['id'], $token, $expires, $created_ip, $userAgent);

if (!$insert->execute()) {
    send_json_response(500, ['error' => 'Error al crear token de recuperación']);
}

// Preparar enlace de reset seguro
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$base_url = $protocol . $host;
$reset_link = $base_url . '/StepUp/nova/app/password-reset.php?token=' . urlencode($token);

// Enviar email con enlace de reset
require_once __DIR__ . '/../includes/email_service.php';

$email_result = send_password_reset_email(
    $correo,
    $user['nombre'],
    $token,
    $reset_link,
    60 // 60 minutos de expiración
);

// Log de actividad de recuperación
$logActivity = $conn->prepare("
    INSERT INTO password_reset_logs
    (usuario_id, correo, ip, user_agent, email_sent, token_id)
    VALUES (?, ?, ?, ?, ?, ?)
");
$emailSent = $email_result['success'] ? 1 : 0;
$tokenId = $conn->insert_id;
$logActivity->bind_param('issiii', $user['id'], $correo, $ip, $userAgent, $emailSent, $tokenId);
$logActivity->execute();

// Respuesta (no revelar detalles por seguridad)
send_json_response(200, [
    'message' => 'Si el correo existe y está activo, se enviaron instrucciones de recuperación.',
    'email_sent' => $email_result['success'],
    'email_message' => $email_result['message'],
    'info' => 'Revisa tu bandeja de entrada y carpeta de spam.',
    'support_contact' => 'Si no recibes el email, contacta a soporte.',
    // Solo en desarrollo - remover en producción
    'debug_token' => $token,
    'debug_link' => $reset_link
]);
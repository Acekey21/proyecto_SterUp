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
$contrasena = $data['contrasena'] ?? '';

if (!$correo || !$contrasena) {
    send_json_response(400, ['error' => 'Correo y contraseña son requeridos']);
}

$sql = "SELECT * FROM usuarios WHERE correo = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param('s', $correo);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows !== 1) {
    send_json_response(401, ['error' => 'Credenciales inválidas']);
}

$usuario = $result->fetch_assoc();
if (!password_verify($contrasena, $usuario['contrasena'])) {
    send_json_response(401, ['error' => 'Credenciales inválidas']);
}

// MFA: generar código y guardar
$mfaCode = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
$expiresAtCode = date('Y-m-d H:i:s', time() + 300); // 5 minutos
$insertMfa = $conn->prepare('INSERT INTO mfa_codes (usuario_id, codigo, expires_at) VALUES (?, ?, ?)');
$insertMfa->bind_param('iss', $usuario['id'], $mfaCode, $expiresAtCode);
if (!$insertMfa->execute()) {
    send_json_response(500, ['error' => 'Error al generar código MFA']);
}

// Enviar código MFA por email
require_once __DIR__ . '/../includes/email_service.php';

$email_result = send_mfa_code_email(
    $usuario['correo'],
    $usuario['nombre'],
    $mfaCode,
    5 // 5 minutos de expiración
);

// Respuesta (incluye código simulado en desarrollo)
send_json_response(200, [
    'mfa_required' => true,
    'usuario_id' => $usuario['id'],
    'mensaje' => 'Código MFA enviado a tu email. Verifica antes de completar el acceso.',
    'email_sent' => $email_result['success'],
    'email_message' => $email_result['message'],
    'codigo_simulado' => $mfaCode // Solo en desarrollo, en producción no incluir
]);


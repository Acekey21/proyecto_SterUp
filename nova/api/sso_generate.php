<?php
// StepUp - API para generar tokens SSO para App B
// Permite a usuarios autenticados generar tokens para acceder a App B

require_once __DIR__ . '/init.php';
require_once __DIR__ . '/../includes/conexion.php';
require_once __DIR__ . '/../../auth/jwt.php';
require_once __DIR__ . '/../auth/middleware.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_json_response(405, ['error' => 'Método no permitido']);
}

validate_csrf_if_needed();

// Verificar autenticación
$user = auth_verify_token();
if (!$user) {
    send_json_response(401, ['error' => 'Token de autenticación requerido']);
}

$data = get_request_data();

if (!$data || !isset($data['target_app'])) {
    send_json_response(400, ['error' => 'Aplicación destino requerida']);
}

$target_app = $data['target_app'];

if ($target_app !== 'app_b') {
    send_json_response(400, ['error' => 'Aplicación destino no soportada']);
}

// Generar token SSO con información del usuario
$sso_payload = [
    'iss' => 'StepUp', // Emisor
    'aud' => 'App B',  // Audiencia
    'iat' => time(),   // Emitido en
    'exp' => time() + 300, // Expira en 5 minutos
    'user_id' => $user['id'],
    'email' => $user['correo'],
    'nombre' => $user['nombre'],
    'rol_id' => $user['rol_id'],
    'sso' => true,     // Indica que es un token SSO
    'target_app' => $target_app
];

$sso_token = JWT::encode($sso_payload, JWT_SECRET, JWT_ALGO);

// Log del token SSO generado
$log_dir = '../logs';
if (!is_dir($log_dir)) {
    mkdir($log_dir, 0755, true);
}

$sso_log = date('Y-m-d H:i:s') . " - SSO Token generado: Usuario {$user['correo']} para {$target_app}\n";
file_put_contents("$log_dir/sso_tokens.log", $sso_log, FILE_APPEND);

// Respuesta exitosa
http_response_code(200);
echo json_encode([
    'success' => true,
    'sso_token' => $sso_token,
    'target_app' => $target_app,
    'expires_in' => 300, // 5 minutos
    'instructions' => 'Usa este token para autenticarte en App B via SSO'
]);

?>
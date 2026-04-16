<?php
// App B - API de SSO (Single Sign-On)
// Recibe tokens de StepUp y crea sesiones locales

require_once __DIR__ . '/init.php';
require_once __DIR__ . '/../includes/conexion.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_json_response(405, ['error' => 'Método no permitido']);
}

$data = get_request_data();

if (!$data || !isset($data['sso_token'])) {
    send_json_response(400, ['error' => 'Token SSO requerido']);
}

// Verificar el token SSO de StepUp
$verification = app_b_verify_sso_token($sso_token);

if (!$verification['valid']) {
    send_json_response(401, [
        'error' => 'Token SSO inválido',
        'details' => $verification['error']
    ]);
}

// Token válido - crear sesión en App B
$user = $verification['user'];
$session_result = app_b_create_session($user['id'], 'App B SSO');

if (!$session_result['success']) {
    send_json_response(500, ['error' => 'Error creando sesión en App B']);
}

// Log de SSO exitoso
$app_b_log_dir = '../../logs/app_b';
if (!is_dir($app_b_log_dir)) {
    mkdir($app_b_log_dir, 0755, true);
}

$sso_log = date('Y-m-d H:i:s') . " - SSO exitoso: Usuario {$user['email']} autenticado via StepUp\n";
file_put_contents("$app_b_log_dir/sso.log", $sso_log, FILE_APPEND);

// Respuesta exitosa
send_json_response(200, [
    'success' => true,
    'message' => 'SSO exitoso - Bienvenido a App B',
    'user' => [
        'id' => $user['id'],
        'email' => $user['email'],
        'nombre' => $user['nombre'],
        'rol' => $user['rol_id']
    ],
    'session' => [
        'token' => $session_result['session_token'],
        'app' => 'App B',
        'created_via' => 'SSO desde StepUp'
    ]
]);
<?php
require_once __DIR__ . '/init.php';
require_once __DIR__ . '/../includes/conexion.php';
require_once __DIR__ . '/../auth/middleware.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_json_response(405, ['error' => 'Método no permitido']);
}

validate_csrf_if_needed();

$payload = auth_protect();
$data = get_request_data();

$sessionId = intval($data['session_id'] ?? 0);
if (!$sessionId) {
    send_json_response(400, ['error' => 'session_id es requerido']);
}

// Verificar si el usuario puede revocar esta sesión
$usuarioId = $payload['sub'];
$check = $conn->prepare('SELECT refresh_token_id, usuario_id FROM user_sessions WHERE id = ? AND revoked = 0');
$check->bind_param('i', $sessionId);
$check->execute();
$result = $check->get_result();

if ($result->num_rows !== 1) {
    send_json_response(404, ['error' => 'Sesión no encontrada o ya cerrada']);
}

$session = $result->fetch_assoc();

// Verificar permisos: usuarios pueden revocar sus propias sesiones, admins pueden revocar cualquier sesión
if ($session['usuario_id'] !== $usuarioId) {
    auth_has_permission($payload, 'sesiones.revocar');
}

$updateSession = $conn->prepare('UPDATE user_sessions SET revoked = 1 WHERE id = ?');
$updateSession->bind_param('i', $sessionId);
$updateSession->execute();

$updateToken = $conn->prepare('UPDATE refresh_tokens SET revoked = 1 WHERE id = ?');
$updateToken->bind_param('i', $session['refresh_token_id']);
$updateToken->execute();

send_json_response(200, ['message' => 'Sesión cerrada exitosamente']);

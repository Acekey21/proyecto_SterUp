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

$refreshToken = trim($data['refresh_token'] ?? '');
if (!$refreshToken) {
    send_json_response(400, ['error' => 'refresh_token es requerido']);
}

// Revocar refresh token
$stmt = $conn->prepare('UPDATE refresh_tokens SET revoked = 1 WHERE refresh_token = ?');
$stmt->bind_param('s', $refreshToken);
$stmt->execute();

// Marcar sesión como revocada
$selectToken = $conn->prepare('SELECT id FROM refresh_tokens WHERE refresh_token = ? LIMIT 1');
$selectToken->bind_param('s', $refreshToken);
$selectToken->execute();
$tokenResult = $selectToken->get_result();
if ($tokenResult && $tokenResult->num_rows === 1) {
    $tokenRow = $tokenResult->fetch_assoc();
    $sessionUpdate = $conn->prepare('UPDATE user_sessions SET revoked = 1 WHERE refresh_token_id = ?');
    $sessionUpdate->bind_param('i', $tokenRow['id']);
    $sessionUpdate->execute();
}

send_json_response(200, ['message' => 'Sesión cerrada correctamente']);

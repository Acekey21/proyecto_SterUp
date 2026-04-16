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

$sql = "SELECT rt.*, u.rol FROM refresh_tokens rt JOIN usuarios u ON rt.usuario_id = u.id WHERE rt.refresh_token = ? AND rt.revoked = 0 AND rt.expires_at > NOW()";
$stmt = $conn->prepare($sql);
$stmt->bind_param('s', $refreshToken);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows !== 1) {
    send_json_response(401, ['error' => 'Refresh token inválido o expirado']);
}

$tokenRow = $result->fetch_assoc();

// Revocar token viejo y crear nuevo token de refresco
$revoca = $conn->prepare('UPDATE refresh_tokens SET revoked = 1 WHERE id = ?');
$revoca->bind_param('i', $tokenRow['id']);
$revoca->execute();

// Actualizar la sesión asociada
$sessionSelect = $conn->prepare('SELECT id FROM user_sessions WHERE refresh_token_id = ? AND revoked = 0 LIMIT 1');
$sessionSelect->bind_param('i', $tokenRow['id']);
$sessionSelect->execute();
$sessionResult = $sessionSelect->get_result();

$newAccessToken = jwt_create_access_token($tokenRow['usuario_id'], $tokenRow['rol']);
$newRefreshToken = jwt_create_refresh_token();
$newExpiresAt = date('Y-m-d H:i:s', time() + JWT_REFRESH_EXPIRE);

$guardar = $conn->prepare('INSERT INTO refresh_tokens (usuario_id, refresh_token, expires_at) VALUES (?, ?, ?)');
$guardar->bind_param('iss', $tokenRow['usuario_id'], $newRefreshToken, $newExpiresAt);
if (!$guardar->execute()) {
    send_json_response(500, ['error' => 'Error interno al generar un nuevo refresh token']);
}
$newRefreshTokenId = $conn->insert_id;

if ($sessionResult && $sessionResult->num_rows === 1) {
    $sessionRow = $sessionResult->fetch_assoc();
    $updateSession = $conn->prepare('UPDATE user_sessions SET refresh_token_id = ?, expires_at = ? WHERE id = ?');
    $updateSession->bind_param('isi', $newRefreshTokenId, $newExpiresAt, $sessionRow['id']);
    $updateSession->execute();
} else {
    $userAgent = substr($_SERVER['HTTP_USER_AGENT'] ?? 'desconocido', 0, 255);
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $insertSession = $conn->prepare('INSERT INTO user_sessions (usuario_id, refresh_token_id, user_agent, ip, expires_at) VALUES (?, ?, ?, ?, ?)');
    $insertSession->bind_param('iisss', $tokenRow['usuario_id'], $newRefreshTokenId, $userAgent, $ip, $newExpiresAt);
    $insertSession->execute();
}

send_json_response(200, [
    'access_token' => $newAccessToken,
    'token_type' => 'Bearer',
    'expires_in' => JWT_ACCESS_EXPIRE,
    'refresh_token' => $newRefreshToken
]);

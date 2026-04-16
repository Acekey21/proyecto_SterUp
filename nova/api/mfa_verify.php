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

$usuarioId = intval($data['usuario_id'] ?? 0);
$codigo = trim($data['codigo'] ?? '');

if (!$usuarioId || !$codigo) {
    send_json_response(400, ['error' => 'usuario_id y codigo son requeridos']);
}

$sql = "SELECT * FROM mfa_codes WHERE usuario_id = ? AND codigo = ? AND usado = 0 AND expires_at > NOW() ORDER BY created_at DESC LIMIT 1";
$stmt = $conn->prepare($sql);
$stmt->bind_param('is', $usuarioId, $codigo);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows !== 1) {
    send_json_response(401, ['error' => 'Código MFA inválido o expirado']);
}

$mfa = $result->fetch_assoc();

$update = $conn->prepare('UPDATE mfa_codes SET usado = 1 WHERE id = ?');
$update->bind_param('i', $mfa['id']);
$update->execute();

// Generar tokens JWT + refresh
$userSql = 'SELECT u.id, u.rol, r.nombre as rol_nombre FROM usuarios u LEFT JOIN roles r ON u.rol_id = r.id WHERE u.id = ?';
$userStmt = $conn->prepare($userSql);
$userStmt->bind_param('i', $usuarioId);
$userStmt->execute();
$userResult = $userStmt->get_result();
if ($userResult->num_rows !== 1) {
    send_json_response(404, ['error' => 'Usuario no encontrado']);
}
$user = $userResult->fetch_assoc();

// Usar rol_id si está disponible, sino usar el campo rol legacy
$rolId = isset($user['rol_id']) ? $user['rol_id'] : null;
$rolNombre = $user['rol_nombre'] ?? $user['rol'];

$accessToken = jwt_create_access_token($user['id'], $rolNombre, $rolId);
$refreshToken = jwt_create_refresh_token();
$expiresAt = date('Y-m-d H:i:s', time() + JWT_REFRESH_EXPIRE);

$insertRefresh = $conn->prepare('INSERT INTO refresh_tokens (usuario_id, refresh_token, expires_at) VALUES (?, ?, ?)');
$insertRefresh->bind_param('iss', $user['id'], $refreshToken, $expiresAt);
if (!$insertRefresh->execute()) {
    send_json_response(500, ['error' => 'Error al crear refresh token']);
}
$refreshTokenId = $conn->insert_id;

// Registrar sesión activa (multi-sesión)
$userAgent = substr($_SERVER['HTTP_USER_AGENT'] ?? 'desconocido', 0, 255);
$ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

$insertSession = $conn->prepare('INSERT INTO user_sessions (usuario_id, refresh_token_id, user_agent, ip, expires_at) VALUES (?, ?, ?, ?, ?)');
$insertSession->bind_param('iisss', $user['id'], $refreshTokenId, $userAgent, $ip, $expiresAt);
$insertSession->execute();

send_json_response(200, [
    'access_token' => $accessToken,
    'token_type' => 'Bearer',
    'expires_in' => JWT_ACCESS_EXPIRE,
    'refresh_token' => $refreshToken
]);
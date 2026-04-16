<?php
// Librería simple JWT para PHP sin dependencias externas

// Cambia esta clave por una aún más segura y no la subas a Git.
const JWT_SECRET_KEY = 'MI_CLAVE_SUPER_SEGURA_CAMBIAR';
const JWT_ALGO = 'HS256';
const ACCESS_TOKEN_EXPIRY = 900; // 15 minutos
const REFRESH_TOKEN_EXPIRY = 604800; // 7 días

// Aliases de constantes para compatibilidad con APIs
const JWT_ACCESS_EXPIRE = ACCESS_TOKEN_EXPIRY;
const JWT_REFRESH_EXPIRE = REFRESH_TOKEN_EXPIRY;

function base64url_encode($data) {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function base64url_decode($data) {
    $remainder = strlen($data) % 4;
    if ($remainder) {
        $data .= str_repeat('=', 4 - $remainder);
    }
    return base64_decode(strtr($data, '-_', '+/'));
}

function jwt_sign($header, $payload, $secret = JWT_SECRET_KEY) {
    $header_encoded = base64url_encode(json_encode($header));
    $payload_encoded = base64url_encode(json_encode($payload));
    $signature = hash_hmac('sha256', "$header_encoded.$payload_encoded", $secret, true);
    $signature_encoded = base64url_encode($signature);

    return "$header_encoded.$payload_encoded.$signature_encoded";
}

function generate_access_token($userId, $role, $roleId = null) {
    $now = time();
    $payload = [
        'iss' => 'StepUp',
        'iat' => $now,
        'exp' => $now + ACCESS_TOKEN_EXPIRY,
        'sub' => $userId,
        'rol' => $role
    ];
    
    // Agregar rol_id si está disponible (para RBAC)
    if ($roleId !== null) {
        $payload['rol_id'] = $roleId;
    }
    
    $header = ['typ' => 'JWT', 'alg' => JWT_ALGO];
    return jwt_sign($header, $payload);
}

function verify_access_token($token) {
    $parts = explode('.', $token);
    if (count($parts) !== 3) {
        return false;
    }
    [$header_b64, $payload_b64, $sig_b64] = $parts;
    $header = json_decode(base64url_decode($header_b64), true);
    $payload = json_decode(base64url_decode($payload_b64), true);
    if (!$header || !$payload || !isset($header['alg']) || $header['alg'] !== JWT_ALGO) {
        return false;
    }
    $validSig = base64url_encode(hash_hmac('sha256', "$header_b64.$payload_b64", JWT_SECRET_KEY, true));
    if (!hash_equals($validSig, $sig_b64)) {
        return false;
    }
    if (isset($payload['exp']) && time() > $payload['exp']) {
        return false;
    }
    return $payload;
}

function generate_refresh_token() {
    return base64url_encode(random_bytes(64));
}

function send_json($statusCode, $data) {
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// ============================================================================
// ALIASES PARA COMPATIBILIDAD CON APIs
// ============================================================================

/**
 * Alias de generate_access_token() para APIs
 * @param int $userId ID del usuario
 * @param string $role Rol del usuario
 * @param int|null $roleId ID del rol (opcional, para RBAC)
 * @return string Access Token firmado
 */
function jwt_create_access_token($userId, $role, $roleId = null) {
    return generate_access_token($userId, $role, $roleId);
}

/**
 * Alias de generate_refresh_token() para APIs
 * @return string Refresh Token base64url
 */
function jwt_create_refresh_token() {
    return generate_refresh_token();
}

/**
 * Función estándar para enviar respuestas JSON en APIs
 * Alias de send_json() con nombre más descriptivo
 * @param int $statusCode Código HTTP
 * @param array $data Datos a enviar en JSON
 */
function send_json_response($statusCode, $data) {
    send_json($statusCode, $data);
}

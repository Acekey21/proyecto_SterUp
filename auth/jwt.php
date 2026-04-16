<?php
require_once __DIR__ . '/../vendor/autoload.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

// Alias global para compatibilidad con código heredado que usa JWT::encode / JWT::decode
if (!class_exists('JWT')) {
    class_alias('Firebase\\JWT\\JWT', 'JWT');
}

const JWT_SECRET = 'CAMBIA_POR_ALGO_MUY_SEGURO_Y_CONFIDENCIAL';
const JWT_ALGO = 'HS256';
const JWT_ACCESS_EXPIRE = 900; // 15 min
const JWT_REFRESH_EXPIRE = 604800; // 7 dias

function jwt_create_access_token($userId, $role) {
    $payload = [
        'iss' => 'StepUp',
        'iat' => time(),
        'exp' => time() + JWT_ACCESS_EXPIRE,
        'sub' => $userId,
        'role' => $role,
    ];
    return JWT::encode($payload, JWT_SECRET, JWT_ALGO);
}

function jwt_verify_access_token($token) {
    try {
        $decoded = JWT::decode($token, new Key(JWT_SECRET, JWT_ALGO));
        return (array) $decoded;
    } catch (Exception $e) {
        return false;
    }
}

function jwt_create_refresh_token() {
    return bin2hex(random_bytes(64));
}

function send_json_response($code, $body) {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($body, JSON_UNESCAPED_UNICODE);
    exit;
}

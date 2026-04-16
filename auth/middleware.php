<?php
require_once __DIR__ . '/jwt.php';

function auth_get_bearer_token() {
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (!$authHeader || !preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
        return null;
    }
    return trim($matches[1]);
}

function auth_protect() {
    $token = auth_get_bearer_token();
    if (!$token) {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => 'Token de acceso faltante']);
        exit;
    }

    $payload = jwt_verify_access_token($token);
    if (!$payload) {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => 'Token inválido o expirado']);
        exit;
    }

    // En caso de necesitar info adicional en el request
    return $payload;
}

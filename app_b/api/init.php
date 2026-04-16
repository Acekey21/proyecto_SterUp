<?php
/**
 * App B - INIT.PHP (SSO CLIENT)
 * Proporciona headers de seguridad para comunicación segura con StepUp
 * CORS permitido SOLO para StepUp (no *)
 */

// Aplicar headers CORS - Solo permitir StepUp
function apply_cors_headers() {
    $allowed_origins = [
        'http://localhost',
        'http://localhost:80',
        'http://localhost:8080',
        'http://stepup.local',
        'https://stepup.local'
    ];
    
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    if (in_array($origin, $allowed_origins)) {
        header('Access-Control-Allow-Origin: ' . $origin);
    }
    
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-CSRF-Token');
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Max-Age: 3600');
}

// Manejar preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    apply_cors_headers();
    exit(0);
}

// Aplicar CORS headers a todas las requests
apply_cors_headers();

// Header de JSON automático
header('Content-Type: application/json; charset=utf-8');

/**
 * Enviar respuesta JSON estándar
 * 
 * @param int $httpCode Código HTTP (200, 400, 401, 403, 404, 500, etc.)
 * @param array $data Datos a retornar en JSON
 */
if (!function_exists('send_json_response')) {
    function send_json_response($httpCode, $data = []) {
        http_response_code($httpCode);
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }
}

/**
 * Obtener datos del request (JSON o form)
 * 
 * @return array Datos parseados
 */
function get_request_data() {
    $data = [];
    $rawInput = file_get_contents('php://input');
    
    if (!empty($rawInput)) {
        $data = json_decode($rawInput, true) ?? [];
    }
    
    if (empty($data)) {
        $data = $_POST ?? [];
    }
    
    return $data;
}

/**
 * Validar CSRF token para POST/PUT/DELETE
 * NOTA: App B es cliente SSO, CSRF validation aquí es básica
 */
function validate_csrf_if_needed() {
    // En App B, la validación CSRF principal ocurre en StepUp
    // Esta función es un placeholder para consistencia
    return true;
}

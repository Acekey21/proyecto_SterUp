<?php
/**
 * API: OBTENER CSRF TOKEN
 * 
 * Endpoint público para obtener un nuevo token CSRF
 * Útil para peticiones AJAX/fetch sin necesidad de recargar la página
 * 
 * GET /api/csrf_token.php
 * 
 * Response:
 * {
 *   "success": true,
 *   "token": "abc123...",
 *   "token_name": "_csrf_token",
 *   "lifetime": 3600,
 *   "timestamp": 1234567890
 * }
 */

require_once __DIR__ . '/init.php';

// Información del token
$response = [
    'success' => true,
    'token' => get_csrf_token(),
    'token_name' => CSRF_TOKEN_NAME,
    'lifetime' => CSRF_TOKEN_LIFETIME,
    'timestamp' => time()
];

send_json_response(200, $response);

?>

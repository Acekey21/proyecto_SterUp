<?php
/**
 * INICIALIZACIÓN CENTRAL PARA API
 * 
 * Este archivo debe incluirse al inicio de todos los APIs
 * para aplicar automáticamente:
 * - Security headers (CSP, X-Frame-Options, etc.)
 * - CORS headers
 * - CSRF protection
 * - JSON response handling
 * - Error handling
 * 
 * USAGE:
 *   require_once __DIR__ . '/init.php';
 *   require_once __DIR__ . '/../includes/conexion.php';
 *   // ... resto del código
 */

// ============================================================================
// 1. APLICAR TODOS LOS HEADERS DE SEGURIDAD
// ============================================================================

require_once __DIR__ . '/../../config/security_headers.php';

// ============================================================================
// 2. APLICAR CORS HEADERS
// ============================================================================

require_once __DIR__ . '/../../config/cors.php';
apply_cors_headers();

// ============================================================================
// 3. INICIALIZAR SESIÓN PARA CSRF
// ============================================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ============================================================================
// 4. CARGAR CONFIGURACIÓN CSRF
// ============================================================================

require_once __DIR__ . '/../../config/csrf.php';

// ============================================================================
// 5. ESTABLECER CONTENT-TYPE JSON
// ============================================================================

header('Content-Type: application/json; charset=utf-8');

// ============================================================================
// 5. FUNCIÓN DE RESPUESTA JSON UNIFICADA
// ============================================================================

if (!function_exists('send_json_response')) {
    function send_json_response($httpCode, $data = []) {
        http_response_code($httpCode);
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }
}

// ============================================================================
// 6. VALIDACIÓN AUTOMÁTICA DE CSRF PARA POST/PUT/DELETE
// ============================================================================

/**
 * Valida CSRF para operaciones modificadoras (POST, PUT, DELETE, PATCH)
 * Las operaciones GET y OPTIONS no necesitan validación
 * 
 * NOTA: Se puede desactivar para ciertos endpoints usando:
 *   - Parámetro skip_csrf=true
 *   - Header X-Skip-CSRF: true (solo para casos especiales)
 */
function validate_csrf_if_needed() {
    $method = $_SERVER['REQUEST_METHOD'];
    $isModifyingOperation = in_array($method, ['POST', 'PUT', 'DELETE', 'PATCH']);
    
    // Excepciones que no necesitan CSRF
    $skipCsrf = $_GET['skip_csrf'] ?? false;
    $skipCsrfHeader = $_SERVER['HTTP_X_SKIP_CSRF'] ?? null;
    
    if ($isModifyingOperation && !$skipCsrf && !$skipCsrfHeader) {
        if (!validate_csrf_token()) {
            send_json_response(403, [
                'error' => 'Token CSRF inválido',
                'message' => 'La solicitud fue rechazada por seguridad. Por favor, intenta de nuevo.'
            ]);
        }
    }
}

// ============================================================================
// 7. HELPER PARA ENTENDER DATOS DE ENTRADA
// ============================================================================

/**
 * Obtiene datos JSON del body o $_POST combinados
 */
function get_request_data() {
    $data = json_decode(file_get_contents('php://input'), true);
    if (!$data) {
        $data = $_POST;
    }
    return $data ?? [];
}

?>

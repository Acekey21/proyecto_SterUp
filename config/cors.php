<?php
/**
 * CONFIGURACIÓN DE CORS (Cross-Origin Resource Sharing)
 * 
 * Controla qué dominios externos pueden acceder a esta API
 * y qué métodos/headers están permitidos.
 */

// ============================================================================
// CONFIGURACIÓN DE DOMINIOS PERMITIDOS
// ============================================================================

// Dominios que pueden hacer peticiones a esta API
$ALLOWED_ORIGINS = [
    // Desarrollo local
    'http://localhost',
    'http://localhost:3000',
    'http://localhost:5000',
    'http://localhost:8000',
    'http://localhost:8080',
    'http://127.0.0.1',
    
    // Tu dominio principal (cambiar cuando tengas hosting)
    // 'https://tusitio.com',
    // 'https://www.tusitio.com',
    
    // Subdominios (si tienes múltiples apps)
    // 'https://app1.tusitio.com',
    // 'https://app2.tusitio.com',
    
    // Si necesitas permitir TODO en desarrollo (NO RECOMENDADO EN PRODUCCIÓN)
    // '*'
];

// ============================================================================
// FUNCIÓN PARA APLICAR CORS HEADERS
// ============================================================================

/**
 * Aplicar headers de CORS
 * 
 * Debe ser llamada al inicio de cada API/archivo.
 * 
 * Ejemplo:
 *   require_once __DIR__ . '/config/cors.php';
 *   apply_cors_headers();
 */
function apply_cors_headers() {
    // Obtener el origen que hace la petición
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    
    // Verificar si el origen está en la lista de permitidos
    if (in_array($origin, $GLOBALS['ALLOWED_ORIGINS']) || $GLOBALS['ALLOWED_ORIGINS'][0] === '*') {
        // Permitir acceso desde ese origen
        header("Access-Control-Allow-Origin: $origin");
    }
    
    // Métodos HTTP permitidos
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, PATCH, OPTIONS');
    
    // Headers que se pueden enviar en la petición
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-CSRF-Token');
    
    // Permitir envío de cookies/credenciales
    header('Access-Control-Allow-Credentials: true');
    
    // Tiempo que el navegador puede cachear los permisos CORS (1 día)
    header('Access-Control-Max-Age: 86400');
    
    // Si es una petición OPTIONS (preflight), responder inmediatamente
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        exit;
    }
}

/**
 * INSTRUCCIONES DE USO:
 * 
 * 1. En cada archivo API que needs CORS, agrega esto al inicio:
 *    
 *    <?php
 *    require_once __DIR__ . '/../../config/cors.php';
 *    apply_cors_headers();
 *    require_once __DIR__ . '/../includes/conexion.php';
 *    // ... resto del código
 * 
 * 2. O en un archivo incluido por TODOS los APIs:
 *    
 *    // En nova/api/index.php o similar
 *    require_once __DIR__ . '/../../config/cors.php';
 *    apply_cors_headers();
 * 
 * 3. Para AGREGAR MÁS DOMINIOS:
 *    
 *    Edita la variable $ALLOWED_ORIGINS arriba
 * 
 * 4. EN PRODUCCIÓN:
 *    
 *    NUNCA uses '*' (permite acceso desde TODO)
 *    Siempre especifica dominios exactos
 */

?>

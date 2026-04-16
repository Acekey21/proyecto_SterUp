<?php
/**
 * CONFIGURACIÓN DE CSRF (Cross-Site Request Forgery)
 * 
 * Protege formularios contra ataques de falsificación de solicitudes
 * intra-sitio generando tokens únicos y validándolos.
 */

// ============================================================================
// INICIALIZAR SESIÓN SI NO ESTÁ INICIADA
// ============================================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ============================================================================
// CONSTANTES CSRF
// ============================================================================

define('CSRF_TOKEN_NAME', '_csrf_token');
define('CSRF_TOKEN_LIFETIME', 3600); // 1 hora en segundos

// ============================================================================
// GENERAR CSRF TOKEN
// ============================================================================

/**
 * Genera un nuevo token CSRF si no existe,
 * o devuelve el existente si es válido.
 * 
 * @return string Token CSRF único
 * 
 * Ejemplo:
 *   $token = generate_csrf_token();
 *   echo '<input type="hidden" name="' . CSRF_TOKEN_NAME . '" value="' . $token . '">';
 */
function generate_csrf_token() {
    // Si no existe token o expiró, generar uno nuevo
    if (empty($_SESSION[CSRF_TOKEN_NAME]) || 
        time() - $_SESSION['csrf_timestamp'] > CSRF_TOKEN_LIFETIME) {
        
        // Generar token aleatorio seguro (32 bytes = 64 caracteres hex)
        $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
        $_SESSION['csrf_timestamp'] = time();
    }
    
    return $_SESSION[CSRF_TOKEN_NAME];
}

// ============================================================================
// VALIDAR CSRF TOKEN
// ============================================================================

/**
 * Valida que el token CSRF enviado sea válido y no haya expirado.
 * 
 * @param string|null $token Token a validar (si null, busca en $_POST/$_GET)
 * @return bool true si es válido, false si no
 * 
 * Ejemplo:
 *   if (!validate_csrf_token($_POST[CSRF_TOKEN_NAME])) {
 *       send_json_response(403, ['error' => 'Token CSRF inválido']);
 *   }
 */
function validate_csrf_token($token = null) {
    // Si no se proporciona token, intentar obtener de POST/GET/HEADER
    if ($token === null) {
        $token = $_POST[CSRF_TOKEN_NAME] ?? 
                 $_GET[CSRF_TOKEN_NAME] ?? 
                 (isset($_SERVER['HTTP_X_CSRF_TOKEN']) ? $_SERVER['HTTP_X_CSRF_TOKEN'] : null);
    }
    
    // Validaciones
    $isValid = !empty($token) && 
              !empty($_SESSION[CSRF_TOKEN_NAME]) && 
              hash_equals($_SESSION[CSRF_TOKEN_NAME], $token);
    
    // Verificar que no haya expirado
    $isExpired = isset($_SESSION['csrf_timestamp']) && 
                 time() - $_SESSION['csrf_timestamp'] > CSRF_TOKEN_LIFETIME;
    
    return $isValid && !$isExpired;
}

// ============================================================================
// VALIDAR CSRF Y RESPONDER CON ERROR SI ES INVÁLIDO
// ============================================================================

/**
 * Valida CSRF y termina la ejecución si falla
 * (útil para APIs que rechazan automáticamente)
 * 
 * Ejemplo:
 *   require_once __DIR__ . '/csrf.php';
 *   
 *   if ($_SERVER['REQUEST_METHOD'] === 'POST') {
 *       require_csrf_token_or_die();
 *       // ... resto del código
 *   }
 */
function require_csrf_token_or_die() {
    if (!validate_csrf_token()) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode([
            'error' => 'Token CSRF inválido',
            'message' => 'La solicitud fue rechazada por seguridad. Intenta de nuevo.'
        ]);
        exit;
    }
}

// ============================================================================
// FUNCIONES DE UTILIDAD
// ============================================================================

/**
 * Obtiene el token CSRF para pasar en headers/formularios
 * (alias de generate_csrf_token)
 */
function get_csrf_token() {
    return generate_csrf_token();
}

/**
 * Crea un campo hidden HTML con el token CSRF
 * 
 * Ejemplo:
 *   echo csrf_field();
 *   // Output: <input type="hidden" name="_csrf_token" value="abc123...">
 */
function csrf_field() {
    $token = generate_csrf_token();
    return sprintf(
        '<input type="hidden" name="%s" value="%s">',
        CSRF_TOKEN_NAME,
        htmlspecialchars($token, ENT_QUOTES, 'UTF-8')
    );
}

/**
 * Revoca el token CSRF actual (útil después de logout)
 */
function revoke_csrf_token() {
    unset($_SESSION[CSRF_TOKEN_NAME]);
    unset($_SESSION['csrf_timestamp']);
}

// ============================================================================
// INSTRUCCIONES DE USO
// ============================================================================

/**
 * USO EN FORMULARIOS HTML:
 * 
 * 1. En el formulario:
 *    <form method="POST" action="/api/alguna_accion.php">
 *        <?php echo csrf_field(); ?>
 *        <!-- resto de campos -->
 *        <button type="submit">Enviar</button>
 *    </form>
 * 
 * 2. En el API que recibe el formulario:
 *    <?php
 *    require_once __DIR__ . '/../../config/csrf.php';
 *    
 *    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
 *        require_csrf_token_or_die();
 *        // ... procesar formulario seguramente
 *    }
 * 
 * 
 * USO EN AJAX/APIs JSON:
 * 
 * 1. Obtener token:
 *    fetch('/api/csrf-token.php')
 *    .then(r => r.json())
 *    .then(data => {
 *        const token = data.token;
 *        // ... guardar en variable
 *    })
 * 
 * 2. Enviar con petición:
 *    fetch('/api/alguna_accion.php', {
 *        method: 'POST',
 *        headers: {
 *            'Content-Type': 'application/json',
 *            'X-CSRF-Token': token  // Enviar en header
 *        },
 *        body: JSON.stringify({...})
 *    })
 * 
 * 3. En el API validar:
 *    <?php
 *    require_once __DIR__ . '/../../config/csrf.php';
 *    
 *    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
 *        require_csrf_token_or_die();
 *        // ... procesar solicitud
 *    }
 * 
 * 
 * GENERAR UN ENDPOINT PARA OBTENER TOKENS:
 * 
 * Crear archivo nova/api/csrf-token.php:
 * 
 *    <?php
 *    require_once __DIR__ . '/../../config/cors.php';
 *    apply_cors_headers();
 *    require_once __DIR__ . '/../../config/csrf.php';
 *    
 *    header('Content-Type: application/json');
 *    
 *    send_json_response(200, [
 *        'token' => get_csrf_token(),
 *        'token_name' => CSRF_TOKEN_NAME,
 *        'lifetime' => CSRF_TOKEN_LIFETIME
 *    ]);
 */

?>

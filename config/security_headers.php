<?php
/**
 * CONFIGURACIÓN DE HEADERS DE SEGURIDAD
 * 
 * Este archivo configura todos los headers de seguridad necesarios para proteger
 * contra vulnerabilidades comunes como:
 * - Content Security Policy (CSP)
 * - Clickjacking
 * - MIME sniffing
 * - Exposición de información del servidor
 * - XSS attacks
 * - Insecure redirects
 * 
 * DEBE ser incluido al inicio de TODOS los archivos principales
 */

// ============================================================================
// REMOVER HEADERS QUE EXPONGAN INFORMACIÓN DEL SERVIDOR
// ============================================================================

// Remover o enmascarar el header X-Powered-By que expone PHP
header_remove('X-Powered-By');
header('X-Powered-By: Null');

// Remover información de Apache (si está disponible)
header_remove('Server');

// ============================================================================
// HEADERS DE SEGURIDAD ESTÁNDAR
// ============================================================================

/**
 * Content-Security-Policy (CSP)
 * 
 * Define fuentes permitidas para cargar contenido (scripts, estilos, etc.)
 * Previene inyección de scripts maliciosos
 * 
 * Directivas configuradas:
 * - default-src 'self' -> Solo permite recursos del mismo origen
 * - script-src 'self' 'unsafe-inline' -> Scripts propios + inline (necesario para algunas apps)
 * - style-src 'self' 'unsafe-inline' -> Estilos propios + inline
 * - img-src 'self' data: https: -> Imágenes locales, data URLs, y HTTPS externas
 * - font-src 'self' data: -> Fuentes propias y data URLs
 * - connect-src 'self' -> Conexiones AJAX/Fetch al mismo origen
 * - frame-ancestors 'none' -> No permite embeber en iframes
 * - base-uri 'self' -> Solo base tags del mismo origen
 * - form-action 'self' -> Formularios al mismo origen
 */
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data: https:; font-src 'self' data:; connect-src 'self'; frame-ancestors 'none'; base-uri 'self'; form-action 'self'; upgrade-insecure-requests");

// Alternativa más estricta para apis (descomenta si quieres)
// header("Content-Security-Policy: default-src 'none'; script-src 'self'; style-src 'self'; img-src 'self'; font-src 'self'; connect-src 'self'; form-action 'self'");

/**
 * X-Content-Type-Options: nosniff
 * 
 * Previene que el navegador infiera el tipo MIME de un archivo
 * Si envías un archivo como texto, el navegador no lo tratará como script/estilo
 * Previene MIME sniffing attacks
 */
header('X-Content-Type-Options: nosniff');

/**
 * X-Frame-Options: DENY
 * 
 * Previene que la página se embeba en un iframe en otro sitio
 * Protege contra clickjacking attacks
 * 
 * Opciones:
 * - DENY: No permitir embeber en ningún iframe
 * - SAMEORIGIN: Permitir solo en iframes del mismo origen
 * - ALLOW-FROM uri: Permitir solo en un URI específico (deprecado)
 */
header('X-Frame-Options: DENY');

/**
 * X-XSS-Protection: 1; mode=block
 * 
 * Activa la protección XSS del navegador
 * mode=block hace que el navegador bloquee la página si detecta XSS
 * (Navegadores modernos tienen mejores protecciones, pero es buena práctica)
 */
header('X-XSS-Protection: 1; mode=block');

/**
 * Strict-Transport-Security (HSTS)
 * 
 * Force HTTPS connections
 * max-age=31536000: 1 año
 * includeSubDomains: aplica a subdominios
 * preload: permite incluir en HSTS preload list de navegadores
 * 
 * NOTA: Solo activar si tienes HTTPS configurado
 * Descomenta cuando tengas certificado SSL instalado
 */
// header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');

/**
 * Referrer-Policy: strict-origin-when-cross-origin
 * 
 * Controla qué información del referrer se envía en requests
 * strict-origin-when-cross-origin: Envía URL completa en same-origin, 
 *                                  solo origen en cross-origin
 * Previene fuga de información sensible en URLs
 */
header('Referrer-Policy: strict-origin-when-cross-origin');

/**
 * Permissions-Policy (Feature-Policy)
 * 
 * Controla qué features/APIs del navegador pueden usar iframes y embeds
 * Limita acceso a micrófono, cámara, ubicación, etc.
 */
header('Permissions-Policy: geolocation=(), microphone=(), camera=(), payment=(), usb=()');

/**
 * X-Content-Type-Options: nosniff
 * Ya está arriba, pero es importante

 * Algunos servidores también agregan:
 */
header('X-Content-Security-Policy: default-src \'self\''); // Para navegadores antiguos

/**
 * Cache-Control para proteger datos sensibles
 * Asegurar que datos sensibles no se cacheen
 */
$request_uri = $_SERVER['REQUEST_URI'] ?? '';
if (strpos($request_uri, '/api/') === 0 || 
    strpos($request_uri, '/admin/') === 0 ||
    strpos($request_uri, '/usuario/') === 0) {
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: ' . gmdate('D, d M Y H:i:s', time() - 3600) . ' GMT');
}

// ============================================================================
// FUNCIONES DE UTILIDAD
// ============================================================================

/**
 * Función para validar que las redirecciones sean seguras
 * Previene open redirect vulnerabilities
 * 
 * @param string $url URL a validar
 * @return bool true si es una redirección segura
 * 
 * Ejemplo:
 *   if (is_safe_redirect($url)) {
 *       header("Location: $url");
 *   }
 */
if (!function_exists('is_safe_redirect')) {
    function is_safe_redirect($url) {
        // No permitir redirecciones externas (solo rutas relativas o mismo dominio)
        
        // Si la URL empieza con //, es una redirección al protocolo actual pero otro dominio
        if (strpos($url, '//') === 0) {
            return false;
        }
        
        // Si tiene protocolo (http://, https://, ftp://, etc.) checkear que sea del mismo dominio
        if (preg_match('~^[a-z][a-z0-9+.-]*:~i', $url)) {
            // Tiene protocolo, parsear
            $parsed = parse_url($url);
            $current_host = $_SERVER['HTTP_HOST'] ?? '';
            
            if (isset($parsed['host']) && $parsed['host'] !== $current_host) {
                return false; // Dominio diferente
            }
        }
        
        // Redirección relativa o al mismo dominio (segura)
        return true;
    }
}

/**
 * Función para redirigir de forma segura
 * 
 * Ejemplo:
 *   safe_redirect('admin/panel.php');
 */
if (!function_exists('safe_redirect')) {
    function safe_redirect($url) {
        if (!is_safe_redirect($url)) {
            // Si no es segura, redirigir al home
            header("Location: /");
            exit;
        }
        
        header("Location: $url");
        exit;
    }
}

// ============================================================================
// VALIDACIÓN ADICIONAL DE ENTRADA
// ============================================================================

/**
 * Validar que las redirecciones no contengan caracteres peligrosos
 */
function sanitize_redirect_url($url) {
    // Remover caracteres nulos y espacios en blanco
    $url = trim($url);
    $url = str_replace("\0", '', $url);
    $url = str_replace("\n", '', $url);
    $url = str_replace("\r", '', $url);
    
    // Validar que sea una redirección segura
    if (!is_safe_redirect($url)) {
        return '/'; // Redirigir al home por defecto
    }
    
    return $url;
}

?>

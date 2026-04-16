<?php
/**
 * Google OAuth Configuration
 * 
 * Para obtener credentials:
 * 1. Ir a https://console.cloud.google.com/
 * 2. Crear nuevo proyecto
 * 3. Habilitar Google+ API
 * 4. Crear OAuth 2.0 credential (Application Type: Web)
 * 5. Copiar Client ID, Client Secret y configurar Authorized redirect URIs
 * 
 * Authorized redirect URI debe ser:
 * http://localhost/StepUp/nova/api/google_callback.php (desarrollo)
 * https://tudominio.com/nova/api/google_callback.php (producción)
 */

// ============ CONFIGURACIÓN GOOGLE OAUTH ============
// ⚠️ IMPORTANTE: Guardar estas credenciales en variables de entorno en producción

define('GOOGLE_CLIENT_ID', '');
define('GOOGLE_CLIENT_SECRET', '');
define('GOOGLE_REDIRECT_URI', 'http://localhost/StepUp/nova/api/google_callback.php');

// Información de endpoint de Google
define('GOOGLE_OAUTH_URL', 'https://accounts.google.com/o/oauth2/v2/auth');
define('GOOGLE_TOKEN_URL', 'https://oauth2.googleapis.com/token');
define('GOOGLE_USERINFO_URL', 'https://openidconnect.googleapis.com/v1/userinfo');

// Scope de permisos solicitados
define('GOOGLE_SCOPES', 'openid profile email');

/**
 * Generar URL de autorización de Google
 */
function get_google_auth_url($state = null) {
    if (!$state) {
        $state = bin2hex(random_bytes(16));
    }
    
    $_SESSION['google_oauth_state'] = $state;
    
    $params = [
        'client_id' => GOOGLE_CLIENT_ID,
        'redirect_uri' => GOOGLE_REDIRECT_URI,
        'response_type' => 'code',
        'scope' => GOOGLE_SCOPES,
        'state' => $state,
        'access_type' => 'online',
        'prompt' => 'consent'
    ];
    
    return GOOGLE_OAUTH_URL . '?' . http_build_query($params);
}

/**
 * Intercambiar código por token de acceso
 */
function exchange_google_code($code) {
    $params = [
        'client_id' => GOOGLE_CLIENT_ID,
        'client_secret' => GOOGLE_CLIENT_SECRET,
        'code' => $code,
        'grant_type' => 'authorization_code',
        'redirect_uri' => GOOGLE_REDIRECT_URI
    ];
    
    $ch = curl_init(GOOGLE_TOKEN_URL);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code !== 200) {
        return [
            'success' => false,
            'error' => 'Error al obtener token de Google'
        ];
    }
    
    $data = json_decode($response, true);
    
    if (!isset($data['access_token'])) {
        return [
            'success' => false,
            'error' => 'No access token recibido'
        ];
    }
    
    return [
        'success' => true,
        'access_token' => $data['access_token'],
        'token_type' => $data['token_type'] ?? 'Bearer',
        'expires_in' => $data['expires_in'] ?? 3600,
        'id_token' => $data['id_token'] ?? null
    ];
}

/**
 * Obtener información del usuario de Google
 */
function get_google_userinfo($access_token) {
    $headers = [
        'Authorization: Bearer ' . $access_token,
        'Accept: application/json'
    ];
    
    $ch = curl_init(GOOGLE_USERINFO_URL);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code !== 200) {
        return [
            'success' => false,
            'error' => 'Error al obtener información del usuario'
        ];
    }
    
    $data = json_decode($response, true);
    
    return [
        'success' => true,
        'data' => [
            'id' => $data['sub'] ?? null,
            'email' => $data['email'] ?? null,
            'name' => $data['name'] ?? null,
            'picture' => $data['picture'] ?? null,
            'verified_email' => $data['email_verified'] ?? false
        ]
    ];
}
?>

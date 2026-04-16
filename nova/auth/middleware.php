<?php
/**
 * MIDDLEWARE DE AUTENTICACIÓN JWT
 * Valida y protege las rutas que requieren autenticación
 */

require_once __DIR__ . '/../../auth/jwt.php';
require_once __DIR__ . '/../includes/conexion.php';

/**
 * Protege una ruta verificando que el token JWT sea válido
 * Extrae el token del header Authorization: Bearer <token>
 * 
 * @return array|false Payload del token si es válido, false si no
 */
function auth_protect() {
    // Obtener header Authorization
    $headers = getallheaders();
    $authHeader = $headers['Authorization'] ?? $_SERVER['HTTP_AUTHORIZATION'] ?? null;
    
    if (!$authHeader) {
        send_json_response(401, ['error' => 'Token no proporcionado']);
    }
    
    // Extraer token de "Bearer <token>"
    if (preg_match('/Bearer\s+(.+)/i', $authHeader, $matches)) {
        $token = $matches[1];
    } else {
        send_json_response(401, ['error' => 'Formato de token inválido. Use: Authorization: Bearer <token>']);
    }
    
    // Verificar el token
    $payload = jwt_verify_access_token($token);
    
    if (!$payload) {
        send_json_response(401, ['error' => 'Token inválido o expirado']);
    }
    
    return $payload;
}

/**
 * Obtiene los permisos de un rol desde la base de datos
 * 
 * @param int $rolId ID del rol
 * @return array Array de nombres de permisos
 */
function get_role_permissions($rolId) {
    global $conn;
    
    $permisos = [];
    $query = "SELECT p.nombre 
              FROM permisos p 
              INNER JOIN role_permisos rp ON p.id = rp.permiso_id 
              WHERE rp.rol_id = ? AND p.activo = 1";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $rolId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $permisos[] = $row['nombre'];
    }
    
    return $permisos;
}

/**
 * Verifica si un usuario tiene un permiso específico
 * 
 * @param array $payload Payload del token JWT
 * @param string $requiredPermission Permiso requerido
 * @return bool true si tiene el permiso
 */
function auth_has_permission($payload, $requiredPermission) {
    if (!isset($payload['rol_id'])) {
        send_json_response(403, ['error' => 'Información de rol no disponible en el token']);
    }
    
    $userPermissions = get_role_permissions($payload['rol_id']);
    
    if (!in_array($requiredPermission, $userPermissions)) {
        send_json_response(403, ['error' => "Acceso denegado. Se requiere permiso: $requiredPermission"]);
    }
    
    return true;
}

/**
 * Verifica si un usuario tiene al menos uno de los permisos especificados
 * 
 * @param array $payload Payload del token JWT
 * @param array $requiredPermissions Array de permisos requeridos
 * @return bool true si tiene al menos uno de los permisos
 */
function auth_has_any_permission($payload, $requiredPermissions) {
    if (!isset($payload['rol_id'])) {
        send_json_response(403, ['error' => 'Información de rol no disponible en el token']);
    }
    
    $userPermissions = get_role_permissions($payload['rol_id']);
    
    foreach ($requiredPermissions as $permission) {
        if (in_array($permission, $userPermissions)) {
            return true;
        }
    }
    
    send_json_response(403, ['error' => 'Acceso denegado. No tiene los permisos requeridos']);
}

/**
 * Verifica si un usuario tiene todos los permisos especificados
 * 
 * @param array $payload Payload del token JWT
 * @param array $requiredPermissions Array de permisos requeridos
 * @return bool true si tiene todos los permisos
 */
function auth_has_all_permissions($payload, $requiredPermissions) {
    if (!isset($payload['rol_id'])) {
        send_json_response(403, ['error' => 'Información de rol no disponible en el token']);
    }
    
    $userPermissions = get_role_permissions($payload['rol_id']);
    
    foreach ($requiredPermissions as $permission) {
        if (!in_array($permission, $userPermissions)) {
            send_json_response(403, ['error' => "Acceso denegado. Falta permiso: $permission"]);
        }
    }
    
    return true;
}

/**
 * Middleware para verificar rol específico (LEGACY - usar permisos en su lugar)
 * 
 * @param array $payload El payload del token (obtenido de auth_protect)
 * @param string $requiredRole Rol requerido
 * @return bool true si el rol es correcto
 */
function auth_require_role($payload, $requiredRole) {
    if (!isset($payload['rol']) || $payload['rol'] !== $requiredRole) {
        send_json_response(403, ['error' => "Acceso denegado. Se requiere rol: $requiredRole"]);
    }
    return true;
}

/**
 * Middleware para verificar múltiples roles (LEGACY - usar permisos en su lugar)
 * 
 * @param array $payload El payload del token
 * @param array $allowedRoles Array de roles permitidos
 * @return bool true si el rol está en la lista
 */
function auth_require_roles($payload, $allowedRoles = []) {
    if (!isset($payload['rol']) || !in_array($payload['rol'], $allowedRoles)) {
        send_json_response(403, ['error' => 'Acceso denegado. Rol insuficiente.']);
    }
    return true;
}

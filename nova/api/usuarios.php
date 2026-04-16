<?php
/**
 * API PARA GESTIÓN DE USUARIOS CON RBAC
 * Demuestra el uso de diferentes permisos para operaciones CRUD
 */

require_once __DIR__ . '/init.php';
require_once __DIR__ . '/../auth/middleware.php';
require_once __DIR__ . '/../includes/conexion.php';

$method = $_SERVER['REQUEST_METHOD'];

// Verificar autenticación
$payload = auth_protect();

// Validar CSRF para operaciones modificadoras
if (in_array($method, ['POST', 'PUT', 'DELETE'])) {
    validate_csrf_if_needed();
}

// Determinar la acción basada en parámetros
$action = $_GET['action'] ?? 'list';

switch ($action) {
    case 'list':
        if ($method !== 'GET') {
            send_json_response(405, ['error' => 'Método no permitido']);
        }
        // Verificar permiso para ver usuarios
        auth_has_permission($payload, 'usuarios.ver');

        // Obtener lista de usuarios (sin contraseñas)
        $sql = "SELECT u.id, u.nombre, u.correo, u.created_at, r.nombre as rol
                FROM usuarios u
                LEFT JOIN roles r ON u.rol_id = r.id
                ORDER BY u.created_at DESC";

        $result = $conn->query($sql);
        $usuarios = [];

        while ($row = $result->fetch_assoc()) {
            $usuarios[] = $row;
        }

        send_json_response(200, [
            'usuarios' => $usuarios,
            'total' => count($usuarios)
        ]);
        break;

    case 'create':
        if ($method !== 'POST') {
            send_json_response(405, ['error' => 'Método no permitido']);
        }
        // Verificar permiso para crear usuarios
        auth_has_permission($payload, 'usuarios.crear');

        // Solo admins pueden crear otros admins
        $data = get_request_data();

        $nombre = trim($data['nombre'] ?? '');
        $correo = trim($data['correo'] ?? '');
        $contrasena = $data['contrasena'] ?? '';
        $rolNombre = $data['rol'] ?? 'usuario';

        if (!$nombre || !$correo || !$contrasena) {
            send_json_response(400, ['error' => 'Nombre, correo y contraseña son requeridos']);
        }

        // Verificar si el rol solicitado es válido
        $rolResult = $conn->prepare("SELECT id, nombre FROM roles WHERE nombre = ? AND activo = 1");
        $rolResult->bind_param('s', $rolNombre);
        $rolResult->execute();
        $rolData = $rolResult->get_result()->fetch_assoc();

        if (!$rolData) {
            send_json_response(400, ['error' => 'Rol inválido']);
        }

        // Si se intenta crear un admin, verificar que el usuario actual sea admin
        if ($rolNombre === 'admin') {
            auth_has_permission($payload, 'usuarios.cambiar_rol');
        }

        // Verificar que el correo no exista
        $checkEmail = $conn->prepare("SELECT id FROM usuarios WHERE correo = ?");
        $checkEmail->bind_param('s', $correo);
        $checkEmail->execute();
        if ($checkEmail->get_result()->num_rows > 0) {
            send_json_response(409, ['error' => 'El correo ya está registrado']);
        }

        // Crear usuario
        $hashed = password_hash($contrasena, PASSWORD_DEFAULT);
        $insertUser = $conn->prepare("INSERT INTO usuarios (nombre, correo, contrasena, rol_id) VALUES (?, ?, ?, ?)");
        $insertUser->bind_param('sssi', $nombre, $correo, $hashed, $rolData['id']);

        if ($insertUser->execute()) {
            send_json_response(201, [
                'message' => 'Usuario creado exitosamente',
                'usuario_id' => $conn->insert_id
            ]);
        } else {
            send_json_response(500, ['error' => 'Error al crear usuario']);
        }
        break;

    case 'update':
        if (!in_array($method, ['PUT', 'POST'])) {
            send_json_response(405, ['error' => 'Método no permitido']);
        }
        // Verificar permiso para editar usuarios
        auth_has_permission($payload, 'usuarios.editar');

        $userId = intval($_GET['id'] ?? 0);
        if (!$userId) {
            send_json_response(400, ['error' => 'ID de usuario requerido']);
        }

        $data = get_request_data();

        $nombre = trim($data['nombre'] ?? '');
        $correo = trim($data['correo'] ?? '');

        if (!$nombre || !$correo) {
            send_json_response(400, ['error' => 'Nombre y correo son requeridos']);
        }

        // Verificar que el usuario existe
        $checkUser = $conn->prepare("SELECT id FROM usuarios WHERE id = ?");
        $checkUser->bind_param('i', $userId);
        $checkUser->execute();
        if ($checkUser->get_result()->num_rows === 0) {
            send_json_response(404, ['error' => 'Usuario no encontrado']);
        }

        // Actualizar usuario
        $updateUser = $conn->prepare("UPDATE usuarios SET nombre = ?, correo = ? WHERE id = ?");
        $updateUser->bind_param('ssi', $nombre, $correo, $userId);

        if ($updateUser->execute()) {
            send_json_response(200, ['message' => 'Usuario actualizado exitosamente']);
        } else {
            send_json_response(500, ['error' => 'Error al actualizar usuario']);
        }
        break;

    case 'delete':
        if ($method !== 'DELETE' && $method !== 'POST') {
            send_json_response(405, ['error' => 'Método no permitido']);
        }
        // Verificar permiso para eliminar usuarios
        auth_has_permission($payload, 'usuarios.eliminar');

        $userId = intval($_GET['id'] ?? 0);
        if (!$userId) {
            send_json_response(400, ['error' => 'ID de usuario requerido']);
        }

        // No permitir eliminar al propio usuario
        if ($userId == $payload['sub']) {
            send_json_response(403, ['error' => 'No puedes eliminar tu propio usuario']);
        }

        // Verificar que el usuario existe
        $checkUser = $conn->prepare("SELECT id FROM usuarios WHERE id = ?");
        $checkUser->bind_param('i', $userId);
        $checkUser->execute();
        if ($checkUser->get_result()->num_rows === 0) {
            send_json_response(404, ['error' => 'Usuario no encontrado']);
        }

        // Eliminar usuario
        $deleteUser = $conn->prepare("DELETE FROM usuarios WHERE id = ?");
        $deleteUser->bind_param('i', $userId);

        if ($deleteUser->execute()) {
            send_json_response(200, ['message' => 'Usuario eliminado exitosamente']);
        } else {
            send_json_response(500, ['error' => 'Error al eliminar usuario']);
        }
        break;

    default:
        send_json_response(400, ['error' => 'Acción no válida']);
}
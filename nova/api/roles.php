<?php
/**
 * API PARA GESTIÓN DE ROLES Y PERMISOS (RBAC)
 * Solo accesible para administradores
 */

require_once __DIR__ . '/init.php';
require_once __DIR__ . '/../auth/middleware.php';
require_once __DIR__ . '/../includes/conexion.php';

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? 'list';

// Verificar autenticación
$payload = auth_protect();

// Todas las operaciones requieren ser admin
auth_has_permission($payload, 'usuarios.cambiar_rol');

// Validar CSRF para operaciones modificadoras
if (in_array($method, ['POST', 'PUT', 'DELETE'])) {
    validate_csrf_if_needed();
}

switch ($method) {
    case 'GET':
        switch ($action) {
            case 'roles':
                // Listar todos los roles
                $sql = "SELECT id, nombre, descripcion, nivel, activo, created_at FROM roles WHERE activo = 1 ORDER BY nivel DESC";
                $result = $conn->query($sql);
                $roles = [];

                while ($row = $result->fetch_assoc()) {
                    $roles[] = $row;
                }

                send_json_response(200, ['roles' => $roles]);
                break;

            case 'permisos':
                // Listar todos los permisos
                $sql = "SELECT id, nombre, descripcion, modulo, activo FROM permisos WHERE activo = 1 ORDER BY modulo, nombre";
                $result = $conn->query($sql);
                $permisos = [];

                while ($row = $result->fetch_assoc()) {
                    $permisos[] = $row;
                }

                send_json_response(200, ['permisos' => $permisos]);
                break;

            case 'role_permisos':
                // Ver permisos de un rol específico
                $rolId = intval($_GET['rol_id'] ?? 0);
                if (!$rolId) {
                    send_json_response(400, ['error' => 'rol_id es requerido']);
                }

                $sql = "SELECT p.id, p.nombre, p.descripcion, p.modulo
                        FROM permisos p
                        INNER JOIN role_permisos rp ON p.id = rp.permiso_id
                        WHERE rp.rol_id = ? AND p.activo = 1
                        ORDER BY p.modulo, p.nombre";

                $stmt = $conn->prepare($sql);
                $stmt->bind_param('i', $rolId);
                $stmt->execute();
                $result = $stmt->get_result();

                $permisos = [];
                while ($row = $result->fetch_assoc()) {
                    $permisos[] = $row;
                }

                send_json_response(200, ['permisos' => $permisos]);
                break;

            default:
                send_json_response(400, ['error' => 'Acción no válida']);
        }
        break;

    case 'POST':
        switch ($action) {
            case 'asignar_rol':
                // Asignar rol a usuario
                $data = get_request_data();

                $userId = intval($data['user_id'] ?? 0);
                $rolId = intval($data['rol_id'] ?? 0);

                if (!$userId || !$rolId) {
                    send_json_response(400, ['error' => 'user_id y rol_id son requeridos']);
                }

                // Verificar que el rol existe
                $checkRol = $conn->prepare("SELECT id FROM roles WHERE id = ? AND activo = 1");
                $checkRol->bind_param('i', $rolId);
                $checkRol->execute();
                if ($checkRol->get_result()->num_rows === 0) {
                    send_json_response(404, ['error' => 'Rol no encontrado']);
                }

                // Actualizar rol del usuario
                $update = $conn->prepare("UPDATE usuarios SET rol_id = ? WHERE id = ?");
                $update->bind_param('ii', $rolId, $userId);
                $update->execute();

                send_json_response(200, ['message' => 'Rol asignado exitosamente']);
                break;

            case 'actualizar_permisos':
                // Actualizar permisos de un rol
                $data = get_request_data();

                $rolId = intval($data['rol_id'] ?? 0);
                $permisos = $data['permisos'] ?? [];

                if (!$rolId) {
                    send_json_response(400, ['error' => 'rol_id es requerido']);
                }

                // Verificar que el rol existe
                $checkRol = $conn->prepare("SELECT id FROM roles WHERE id = ? AND activo = 1");
                $checkRol->bind_param('i', $rolId);
                $checkRol->execute();
                if ($checkRol->get_result()->num_rows === 0) {
                    send_json_response(404, ['error' => 'Rol no encontrado']);
                }

                // Eliminar permisos actuales del rol
                $delete = $conn->prepare("DELETE FROM role_permisos WHERE rol_id = ?");
                $delete->bind_param('i', $rolId);
                $delete->execute();

                // Asignar nuevos permisos
                if (!empty($permisos)) {
                    $insert = $conn->prepare("INSERT INTO role_permisos (rol_id, permiso_id) VALUES (?, ?)");
                    foreach ($permisos as $permisoId) {
                        $insert->bind_param('ii', $rolId, $permisoId);
                        $insert->execute();
                    }
                }

                send_json_response(200, ['message' => 'Permisos actualizados exitosamente']);
                break;

            default:
                send_json_response(400, ['error' => 'Acción no válida']);
        }
        break;

    default:
        send_json_response(405, ['error' => 'Método no permitido']);
}
<?php
// App B - Conexión a Base de Datos
// Simula una aplicación separada que comparte BD con StepUp

require_once '../../nova/includes/conexion.php';
require_once '../../auth/jwt.php';

// App B usa la misma conexión de BD que StepUp
// Esto simula el escenario real donde múltiples apps comparten BD

function app_b_get_connection() {
    global $conn;
    return $conn;
}

function app_b_verify_sso_token($token) {
    try {
        $decoded = JWT::decode($token, new Key(JWT_SECRET, JWT_ALGO));

        // Verificar que el token sea válido y no expirado
        if ($decoded->exp < time()) {
            return ['valid' => false, 'error' => 'Token expirado'];
        }

        // Verificar que el usuario existe en la BD
        $conn = app_b_get_connection();
        $stmt = $conn->prepare("SELECT id, nombre, correo, rol_id, fecha_registro FROM usuarios WHERE id = ? AND estado != 'inactivo'");
        $stmt->bind_param("i", $decoded->user_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            return ['valid' => false, 'error' => 'Usuario no encontrado o inactivo'];
        }

        $user = $result->fetch_assoc();
        $stmt->close();

        return [
            'valid' => true,
            'user' => $user,
            'token_data' => $decoded
        ];

    } catch (Exception $e) {
        return ['valid' => false, 'error' => 'Token inválido: ' . $e->getMessage()];
    }
}

function app_b_create_session($user_id, $app_name = 'App B') {
    $conn = app_b_get_connection();
    $session_token = bin2hex(random_bytes(32));
    $created_at = date('Y-m-d H:i:s');
    $expires_at = date('Y-m-d H:i:s', strtotime('+2 hours'));

    $stmt = $conn->prepare("INSERT INTO sesiones (usuario_id, token, app_origen, created_at, expires_at, estado) VALUES (?, ?, ?, ?, ?, 'activa')");
    $stmt->bind_param("issss", $user_id, $session_token, $app_name, $created_at, $expires_at);

    if ($stmt->execute()) {
        $stmt->close();
        return ['success' => true, 'session_token' => $session_token];
    } else {
        $stmt->close();
        return ['success' => false, 'error' => 'Error creando sesión'];
    }
}

?>
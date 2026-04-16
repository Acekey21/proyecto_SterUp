<?php
/**
 * Google OAuth Callback
 * Este archivo maneja la redirección desde Google con el código de autorización
 */

session_start();

// ============================================================================
// HEADERS DE SEGURIDAD
// ============================================================================
require_once __DIR__ . '/../../config/security_headers.php';
require_once __DIR__ . '/init.php';
require_once __DIR__ . '/../includes/conexion.php';
require_once __DIR__ . '/../../config/google.php';

// Verificar que CURL esté disponible
if (!extension_loaded('curl')) {
    die('CURL no está habilitado. Por favor, habilitar curl en php.ini');
}

// Verificar credenciales de Google configuradas
if (GOOGLE_CLIENT_ID === 'TU_CLIENT_ID_AQUI.apps.googleusercontent.com') {
    $_SESSION['error'] = '❌ Google OAuth no está configurado. Por favor, completar config/google.php';
    safe_redirect('../login.php');
}

// Verificar parámetros
if (!isset($_GET['code'])) {
    $_SESSION['error'] = '❌ Error: No se recibió código de autorización';
    safe_redirect('../login.php');
}

if (!isset($_GET['state'])) {
    $_SESSION['error'] = '❌ Error: No se recibió parámetro de estado';
    safe_redirect('../login.php');
}

// Validar el estado para prevenir CSRF
if (!isset($_SESSION['google_oauth_state']) || $_SESSION['google_oauth_state'] !== $_GET['state']) {
    $_SESSION['error'] = '❌ Error de validación de seguridad';
    safe_redirect('../login.php');
}

$code = $_GET['code'];

// Intercambiar código por token
$token_result = exchange_google_code($code);

if (!$token_result['success']) {
    $_SESSION['error'] = 'Error al obtener token: ' . $token_result['error'];
    safe_redirect('../login.php');
}

// Obtener información del usuario
$userinfo_result = get_google_userinfo($token_result['access_token']);

if (!$userinfo_result['success']) {
    $_SESSION['error'] = 'Error al obtener información del usuario: ' . $userinfo_result['error'];
    safe_redirect('../login.php');
}

$google_user = $userinfo_result['data'];

// Validar que tenemos email
if (!$google_user['email']) {
    $_SESSION['error'] = '❌ No se pudo obtener el email de Google';
    safe_redirect('../login.php');
}

// Buscar si el usuario ya existe en la BD
$sql = "SELECT * FROM usuarios WHERE correo = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $google_user['email']);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 1) {
    // Usuario existente - hacer login
    $usuario = $result->fetch_assoc();
    
    // Actualizar la información de Google si fue actualizada
    $update_sql = "UPDATE usuarios SET nombre = ?, google_id = ?, google_picture = ?, ultimo_login_google = NOW() WHERE id = ?";
    $update_stmt = $conn->prepare($update_sql);
    $update_stmt->bind_param("sssi", $google_user['name'], $google_user['id'], $google_user['picture'], $usuario['id']);
    $update_stmt->execute();
    
    // Crear sesión
    $_SESSION['id'] = $usuario['id'];
    $_SESSION['nombre'] = $google_user['name'];
    $_SESSION['correo'] = $google_user['email'];
    $_SESSION['rol'] = $usuario['rol'];
    $_SESSION['login_method'] = 'google';
    
    // Registrar en log de seguridad
    $log_sql = "INSERT INTO security_history (usuario_id, action, ip_address, user_agent, created_at) VALUES (?, ?, ?, ?, NOW())";
    $log_stmt = $conn->prepare($log_sql);
    $action = 'LOGIN_GOOGLE_SUCCESS';
    $ip = $_SERVER['REMOTE_ADDR'];
    $user_agent = $_SERVER['HTTP_USER_AGENT'];
    $log_stmt->bind_param("isss", $usuario['id'], $action, $ip, $user_agent);
    $log_stmt->execute();
    
    // Redirigir al panel correspondiente
    if ($usuario['rol'] === 'admin') {
        safe_redirect('../admin/panel.php');
    } else {
        safe_redirect('../usuario/panel.php');
    }
} else {
    // Usuario nuevo - crear cuenta automáticamente y hacer login
    
    // Generar nombre de usuario si no lo proporciona Google
    $nombre = $google_user['name'] ?? 'Usuario Google';
    $email = $google_user['email'];
    $google_id = $google_user['id'];
    $picture = $google_user['picture'] ?? '';
    
    // Crear contraseña aleatoria (no se usará pues el login es por Google)
    $contrasena = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
    $rol = 'usuario'; // Nuevo usuario siempre es 'usuario'
    
    // Insertar nuevo usuario
    $insert_sql = "INSERT INTO usuarios (nombre, correo, contrasena, rol, google_id, google_picture, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())";
    $insert_stmt = $conn->prepare($insert_sql);
    
    if (!$insert_stmt) {
        $_SESSION['error'] = 'Error en la base de datos: ' . $conn->error;
        safe_redirect('../login.php');
    }
    
    $insert_stmt->bind_param("ssssss", $nombre, $email, $contrasena, $rol, $google_id, $picture);
    
    if (!$insert_stmt->execute()) {
        $_SESSION['error'] = 'Error al crear usuario: ' . $insert_stmt->error;
        safe_redirect('../login.php');
    }
    
    $nuevo_usuario_id = $conn->insert_id;
    
    // Crear sesión para el nuevo usuario
    $_SESSION['id'] = $nuevo_usuario_id;
    $_SESSION['nombre'] = $nombre;
    $_SESSION['correo'] = $email;
    $_SESSION['rol'] = $rol;
    $_SESSION['login_method'] = 'google';
    $_SESSION['nuevo_usuario'] = true;
    
    // Registrar en log de seguridad
    $log_sql = "INSERT INTO security_history (usuario_id, action, ip_address, user_agent, created_at) VALUES (?, ?, ?, ?, NOW())";
    $log_stmt = $conn->prepare($log_sql);
    $action = 'REGISTRATION_GOOGLE';
    $ip = $_SERVER['REMOTE_ADDR'];
    $user_agent = $_SERVER['HTTP_USER_AGENT'];
    $log_stmt->bind_param("isss", $nuevo_usuario_id, $action, $ip, $user_agent);
    $log_stmt->execute();
    
    // Redirigir al panel de usuario
    header('Location: ../usuario/panel.php?nuevo=1');
    exit();
}
?>

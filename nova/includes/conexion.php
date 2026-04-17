<?php
$host = 'mysql';
$port = 5432;
$user = 'your_username'; // Change this accordingly
$pass = 'dWlkdXgm4Dlm2NNanKE7EtuGTvNgqbpQ';
$dbname = 'stepupdb_6u4l'; // Change this accordingly

$conn = new mysqli($host, $user, $pass, $db, $port);

if ($conn->connect_error) {
    die('Error en la conexión: ' . $conn->connect_error);
}

// Asegurar tabla de usuarios para login/registro
$createUsers = "CREATE TABLE IF NOT EXISTS usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    correo VARCHAR(100) NOT NULL UNIQUE,
    contrasena VARCHAR(255) NOT NULL,
    rol VARCHAR(20) NOT NULL DEFAULT 'usuario',
    email_verificado TINYINT(1) DEFAULT 0,
    email_verificado_at DATETIME DEFAULT NULL,
    google_id VARCHAR(255) DEFAULT NULL,
    google_picture VARCHAR(500) DEFAULT NULL,
    ultimo_login_google DATETIME DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_google_id (google_id),
    INDEX idx_email_verificado (email_verificado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
$conn->query($createUsers);

// Agregar columnas si no existen
$conn->query("ALTER TABLE usuarios ADD COLUMN IF NOT EXISTS email_verificado TINYINT(1) DEFAULT 0");
$conn->query("ALTER TABLE usuarios ADD COLUMN IF NOT EXISTS email_verificado_at DATETIME DEFAULT NULL");
$conn->query("ALTER TABLE usuarios ADD COLUMN IF NOT EXISTS google_id VARCHAR(255) DEFAULT NULL");
$conn->query("ALTER TABLE usuarios ADD COLUMN IF NOT EXISTS google_picture VARCHAR(500) DEFAULT NULL");
$conn->query("ALTER TABLE usuarios ADD COLUMN IF NOT EXISTS ultimo_login_google DATETIME DEFAULT NULL");
$conn->query("ALTER TABLE usuarios ADD COLUMN IF NOT EXISTS created_at DATETIME DEFAULT CURRENT_TIMESTAMP");
$conn->query("ALTER TABLE usuarios ADD INDEX IF NOT EXISTS idx_google_id (google_id)");
$conn->query("ALTER TABLE usuarios ADD INDEX IF NOT EXISTS idx_email_verificado (email_verificado)");

$createRefreshTokens = "CREATE TABLE IF NOT EXISTS refresh_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    refresh_token VARCHAR(255) NOT NULL UNIQUE,
    expires_at DATETIME NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    revoked TINYINT(1) NOT NULL DEFAULT 0,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
$conn->query($createRefreshTokens);

$createMfaCodes = "CREATE TABLE IF NOT EXISTS mfa_codes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    codigo VARCHAR(10) NOT NULL,
    expires_at DATETIME NOT NULL,
    usado TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
$conn->query($createMfaCodes);

// ============================================================================
// TABLA PARA TOKENS DE VERIFICACIÓN DE EMAIL
// ============================================================================
$createEmailVerificationTokens = "CREATE TABLE IF NOT EXISTS email_verification_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    token VARCHAR(255) NOT NULL UNIQUE,
    email VARCHAR(255) NOT NULL,
    expires_at DATETIME NOT NULL,
    verificado TINYINT(1) DEFAULT 0,
    verificado_at DATETIME DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    INDEX idx_token (token),
    INDEX idx_usuario_expires (usuario_id, expires_at),
    INDEX idx_verificado (verificado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
$conn->query($createEmailVerificationTokens);

$createPasswordResets = "CREATE TABLE IF NOT EXISTS password_reset_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    token VARCHAR(255) NOT NULL UNIQUE,
    expires_at DATETIME NOT NULL,
    used TINYINT(1) DEFAULT 0,
    tipo ENUM('email', 'questions', 'sms', 'call') DEFAULT 'email',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_ip VARCHAR(45) DEFAULT NULL,
    user_agent VARCHAR(255) DEFAULT NULL,
    used_at TIMESTAMP NULL DEFAULT NULL,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    INDEX idx_token (token),
    INDEX idx_usuario_expires (usuario_id, expires_at),
    INDEX idx_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

// Agregar columnas faltantes si no existen
$conn->query("ALTER TABLE password_reset_tokens ADD COLUMN IF NOT EXISTS tipo ENUM('email', 'questions', 'sms', 'call') DEFAULT 'email' AFTER used");
$conn->query("ALTER TABLE password_reset_tokens ADD COLUMN IF NOT EXISTS created_ip VARCHAR(45) DEFAULT NULL AFTER created_at");
$conn->query("ALTER TABLE password_reset_tokens ADD COLUMN IF NOT EXISTS user_agent VARCHAR(255) DEFAULT NULL AFTER created_ip");
$conn->query("ALTER TABLE password_reset_tokens ADD COLUMN IF NOT EXISTS used_at TIMESTAMP NULL DEFAULT NULL AFTER user_agent");

$conn->query($createPasswordResets);

// ============================================================================
// TABLAS PARA SISTEMA AVANZADO DE RECUPERACIÓN DE CONTRASEÑA
// ============================================================================

$createPasswordResetAttempts = "CREATE TABLE IF NOT EXISTS password_reset_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    correo VARCHAR(255) NOT NULL,
    ip VARCHAR(45) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_correo_ip_time (correo, ip, created_at),
    INDEX idx_ip_time (ip, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
$conn->query($createPasswordResetAttempts);

$createPasswordResetLogs = "CREATE TABLE IF NOT EXISTS password_reset_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT DEFAULT NULL,
    correo VARCHAR(255) NOT NULL,
    ip VARCHAR(45) NOT NULL,
    user_agent VARCHAR(255) DEFAULT NULL,
    email_sent TINYINT(1) DEFAULT 0,
    token_id INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL,
    FOREIGN KEY (token_id) REFERENCES password_reset_tokens(id) ON DELETE SET NULL,
    INDEX idx_usuario_time (usuario_id, created_at),
    INDEX idx_correo_time (correo, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
$conn->query($createPasswordResetLogs);

// ============================================================================
// TABLAS PARA HISTORIAL Y SEGURIDAD DE CONTRASEÑAS
// ============================================================================

$createPasswordHistory = "CREATE TABLE IF NOT EXISTS password_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    contrasena VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    INDEX idx_usuario_time (usuario_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
$conn->query($createPasswordHistory);

$createPasswordChangeLogs = "CREATE TABLE IF NOT EXISTS password_change_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    tipo_cambio ENUM('manual', 'reset', 'admin') NOT NULL DEFAULT 'manual',
    ip VARCHAR(45) DEFAULT NULL,
    user_agent VARCHAR(255) DEFAULT NULL,
    exito TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    INDEX idx_usuario_time (usuario_id, created_at),
    INDEX idx_tipo_time (tipo_cambio, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
$conn->query($createPasswordChangeLogs);

// ============================================================================
// TABLAS PARA PREGUNTAS SECRETAS Y RECUPERACIÓN AVANZADA
// ============================================================================

$createSecurityQuestions = "CREATE TABLE IF NOT EXISTS security_questions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    pregunta VARCHAR(255) NOT NULL,
    respuesta_encriptada VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    INDEX idx_usuario (usuario_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
$conn->query($createSecurityQuestions);

$createSmsRecovery = "CREATE TABLE IF NOT EXISTS sms_recovery_codes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    telefono VARCHAR(20) NOT NULL,
    codigo VARCHAR(10) NOT NULL,
    usado TINYINT(1) DEFAULT 0,
    expires_at DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    INDEX idx_usuario_expires (usuario_id, expires_at),
    INDEX idx_codigo (codigo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
$conn->query($createSmsRecovery);

$createCallRecovery = "CREATE TABLE IF NOT EXISTS call_recovery_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    telefono VARCHAR(20) NOT NULL,
    codigo_voz VARCHAR(10) NOT NULL,
    usado TINYINT(1) DEFAULT 0,
    expires_at DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    INDEX idx_usuario_expires (usuario_id, expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
$conn->query($createCallRecovery);

$createUserSessions = "CREATE TABLE IF NOT EXISTS user_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    refresh_token_id INT NOT NULL,
    user_agent VARCHAR(255) NOT NULL,
    ip VARCHAR(45) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME NOT NULL,
    revoked TINYINT(1) DEFAULT 0,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (refresh_token_id) REFERENCES refresh_tokens(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
$conn->query($createUserSessions);

// ============================================================================
// TABLAS PARA SISTEMA RBAC (ROLE-BASED ACCESS CONTROL)
// ============================================================================

$createRoles = "CREATE TABLE IF NOT EXISTS roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(50) NOT NULL UNIQUE,
    descripcion VARCHAR(255),
    nivel INT NOT NULL DEFAULT 1,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
$conn->query($createRoles);

$createPermisos = "CREATE TABLE IF NOT EXISTS permisos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL UNIQUE,
    descripcion VARCHAR(255),
    modulo VARCHAR(50) NOT NULL,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
$conn->query($createPermisos);

$createRolePermisos = "CREATE TABLE IF NOT EXISTS role_permisos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    rol_id INT NOT NULL,
    permiso_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (rol_id) REFERENCES roles(id) ON DELETE CASCADE,
    FOREIGN KEY (permiso_id) REFERENCES permisos(id) ON DELETE CASCADE,
    UNIQUE KEY unique_rol_permiso (rol_id, permiso_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
$conn->query($createRolePermisos);

// ============================================================================
// DATOS INICIALES PARA RBAC
// ============================================================================

// Insertar roles básicos si no existen
$insertRoles = [
    ['superadmin', 'Superadministrador con acceso total y sin restricciones', 200],
    ['admin', 'Administrador con acceso total al sistema', 100],
    ['editor', 'Editor con permisos para modificar contenido', 50],
    ['usuario', 'Usuario estándar con acceso limitado', 10]
];

foreach ($insertRoles as $rol) {
    $checkRol = $conn->prepare("SELECT id FROM roles WHERE nombre = ?");
    $checkRol->bind_param('s', $rol[0]);
    $checkRol->execute();
    if ($checkRol->get_result()->num_rows === 0) {
        $insertRol = $conn->prepare("INSERT INTO roles (nombre, descripcion, nivel) VALUES (?, ?, ?)");
        $insertRol->bind_param('ssi', $rol[0], $rol[1], $rol[2]);
        $insertRol->execute();
    }
}

// Insertar permisos básicos si no existen
$insertPermisos = [
    // Usuarios
    ['usuarios.ver', 'Ver lista de usuarios', 'usuarios'],
    ['usuarios.crear', 'Crear nuevos usuarios', 'usuarios'],
    ['usuarios.editar', 'Editar usuarios existentes', 'usuarios'],
    ['usuarios.eliminar', 'Eliminar usuarios', 'usuarios'],
    ['usuarios.cambiar_rol', 'Cambiar rol de usuarios', 'usuarios'],
    
    // Sesiones
    ['sesiones.ver', 'Ver sesiones activas', 'sesiones'],
    ['sesiones.revocar', 'Revocar sesiones de usuarios', 'sesiones'],
    ['sesiones.ver_todas', 'Ver todas las sesiones del sistema', 'sesiones'],
    
    // Sistema
    ['sistema.configurar', 'Configurar parámetros del sistema', 'sistema'],
    ['sistema.logs', 'Ver logs del sistema', 'sistema'],
    ['sistema.backup', 'Realizar backups', 'sistema'],
    
    // Contenido (para el sistema de productos/tienda)
    ['productos.ver', 'Ver productos', 'productos'],
    ['productos.crear', 'Crear productos', 'productos'],
    ['productos.editar', 'Editar productos', 'productos'],
    ['productos.eliminar', 'Eliminar productos', 'productos'],
    
    // Pedidos/Carrito
    ['pedidos.ver', 'Ver pedidos', 'pedidos'],
    ['pedidos.procesar', 'Procesar pedidos', 'pedidos'],
    ['pedidos.cancelar', 'Cancelar pedidos', 'pedidos']
];

foreach ($insertPermisos as $permiso) {
    $checkPermiso = $conn->prepare("SELECT id FROM permisos WHERE nombre = ?");
    $checkPermiso->bind_param('s', $permiso[0]);
    $checkPermiso->execute();
    if ($checkPermiso->get_result()->num_rows === 0) {
        $insertPermiso = $conn->prepare("INSERT INTO permisos (nombre, descripcion, modulo) VALUES (?, ?, ?)");
        $insertPermiso->bind_param('sss', $permiso[0], $permiso[1], $permiso[2]);
        $insertPermiso->execute();
    }
}

// Asignar permisos a roles
// Superadmin: TODOS los permisos (tiene prioridad de admin)
$superadminId = $conn->query("SELECT id FROM roles WHERE nombre = 'superadmin'")->fetch_assoc()['id'];
$allPermisos = $conn->query("SELECT id FROM permisos");
while ($permiso = $allPermisos->fetch_assoc()) {
    $checkRolePermiso = $conn->prepare("SELECT id FROM role_permisos WHERE rol_id = ? AND permiso_id = ?");
    $checkRolePermiso->bind_param('ii', $superadminId, $permiso['id']);
    $checkRolePermiso->execute();
    if ($checkRolePermiso->get_result()->num_rows === 0) {
        $insertRolePermiso = $conn->prepare("INSERT INTO role_permisos (rol_id, permiso_id) VALUES (?, ?)");
        $insertRolePermiso->bind_param('ii', $superadminId, $permiso['id']);
        $insertRolePermiso->execute();
    }
}

// Admin: todos los permisos
$adminId = $conn->query("SELECT id FROM roles WHERE nombre = 'admin'")->fetch_assoc()['id'];
$allPermisos = $conn->query("SELECT id FROM permisos");
while ($permiso = $allPermisos->fetch_assoc()) {
    $checkRolePermiso = $conn->prepare("SELECT id FROM role_permisos WHERE rol_id = ? AND permiso_id = ?");
    $checkRolePermiso->bind_param('ii', $adminId, $permiso['id']);
    $checkRolePermiso->execute();
    if ($checkRolePermiso->get_result()->num_rows === 0) {
        $insertRolePermiso = $conn->prepare("INSERT INTO role_permisos (rol_id, permiso_id) VALUES (?, ?)");
        $insertRolePermiso->bind_param('ii', $adminId, $permiso['id']);
        $insertRolePermiso->execute();
    }
}

// Editor: permisos de contenido y algunos de usuarios
$editorId = $conn->query("SELECT id FROM roles WHERE nombre = 'editor'")->fetch_assoc()['id'];
$editorPermisos = ['productos.ver', 'productos.crear', 'productos.editar', 'pedidos.ver', 'pedidos.procesar'];
foreach ($editorPermisos as $permisoNombre) {
    $permisoId = $conn->query("SELECT id FROM permisos WHERE nombre = '$permisoNombre'")->fetch_assoc()['id'];
    $checkRolePermiso = $conn->prepare("SELECT id FROM role_permisos WHERE rol_id = ? AND permiso_id = ?");
    $checkRolePermiso->bind_param('ii', $editorId, $permisoId);
    $checkRolePermiso->execute();
    if ($checkRolePermiso->get_result()->num_rows === 0) {
        $insertRolePermiso = $conn->prepare("INSERT INTO role_permisos (rol_id, permiso_id) VALUES (?, ?)");
        $insertRolePermiso->bind_param('ii', $editorId, $permisoId);
        $insertRolePermiso->execute();
    }
}

// Usuario: permisos básicos
$usuarioId = $conn->query("SELECT id FROM roles WHERE nombre = 'usuario'")->fetch_assoc()['id'];
$usuarioPermisos = ['productos.ver', 'pedidos.ver'];
foreach ($usuarioPermisos as $permisoNombre) {
    $permisoId = $conn->query("SELECT id FROM permisos WHERE nombre = '$permisoNombre'")->fetch_assoc()['id'];
    $checkRolePermiso = $conn->prepare("SELECT id FROM role_permisos WHERE rol_id = ? AND permiso_id = ?");
    $checkRolePermiso->bind_param('ii', $usuarioId, $permisoId);
    $checkRolePermiso->execute();
    if ($checkRolePermiso->get_result()->num_rows === 0) {
        $insertRolePermiso = $conn->prepare("INSERT INTO role_permisos (rol_id, permiso_id) VALUES (?, ?)");
        $insertRolePermiso->bind_param('ii', $usuarioId, $permisoId);
        $insertRolePermiso->execute();
    }
}

// ============================================================================
// MODIFICACIONES A TABLA USUARIOS PARA RBAC
// ============================================================================

// Agregar columna rol_id si no existe
$checkColumn = $conn->query("SHOW COLUMNS FROM usuarios LIKE 'rol_id'");
if ($checkColumn->num_rows === 0) {
    $alterUsuarios = "ALTER TABLE usuarios ADD COLUMN rol_id INT DEFAULT NULL AFTER contrasena,
                      ADD CONSTRAINT fk_usuarios_rol_id FOREIGN KEY (rol_id) REFERENCES roles(id) ON DELETE SET NULL";
    $conn->query($alterUsuarios);
}

// Si no existe admin, crearlo con credenciales iniciales
$adminEmail = 'admin@gmail.com';
$adminPass = 'admin12345';
$checkAdmin = $conn->prepare("SELECT id FROM usuarios WHERE correo = ?");
$checkAdmin->bind_param('s', $adminEmail);
$checkAdmin->execute();
$resultAdmin = $checkAdmin->get_result();
if ($resultAdmin && $resultAdmin->num_rows === 0) {
    $hashed = password_hash($adminPass, PASSWORD_DEFAULT);
    $adminRolId = $conn->query("SELECT id FROM roles WHERE nombre = 'admin'")->fetch_assoc()['id'];
    $insertAdmin = $conn->prepare("INSERT INTO usuarios (nombre, correo, contrasena, rol, rol_id, email_verificado, email_verificado_at) VALUES (?, ?, ?, 'admin', ?, 1, NOW())");
    $adminName = 'Administrador';
    $insertAdmin->bind_param('sssi', $adminName, $adminEmail, $hashed, $adminRolId);
    $insertAdmin->execute();
    $insertAdmin->close();
}
// ============================================================================
// TABLA PARA SESIONES SSO (SINGLE SIGN-ON)
// ============================================================================

$createSSOSessions = "CREATE TABLE IF NOT EXISTS sesiones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    token VARCHAR(255) NOT NULL UNIQUE,
    app_origen VARCHAR(100) NOT NULL DEFAULT 'StepUp',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME NOT NULL,
    estado ENUM('activa', 'expirada', 'revocada') NOT NULL DEFAULT 'activa',
    ip VARCHAR(45) DEFAULT NULL,
    user_agent VARCHAR(255) DEFAULT NULL,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    INDEX idx_usuario_estado (usuario_id, estado),
    INDEX idx_token (token),
    INDEX idx_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
$conn->query($createSSOSessions);

// Agregar índices adicionales si no existen
$conn->query("ALTER TABLE sesiones ADD INDEX IF NOT EXISTS idx_usuario_estado (usuario_id, estado)");
$conn->query("ALTER TABLE sesiones ADD INDEX IF NOT EXISTS idx_token (token)");
$conn->query("ALTER TABLE sesiones ADD INDEX IF NOT EXISTS idx_expires (expires_at)");

// ============================================================================
// TABLA PARA HISTORIAL DE SEGURIDAD
// ============================================================================

$createSecurityHistory = "CREATE TABLE IF NOT EXISTS security_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    action VARCHAR(100) NOT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    user_agent VARCHAR(500) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    INDEX idx_usuario_time (usuario_id, created_at),
    INDEX idx_action_time (action, created_at),
    INDEX idx_ip_time (ip_address, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
$conn->query($createSecurityHistory);

?>

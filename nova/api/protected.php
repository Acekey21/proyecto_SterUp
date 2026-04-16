<?php
require_once __DIR__ . '/init.php';
require_once __DIR__ . '/../auth/middleware.php';

// Verificar autenticación básica
$payload = auth_protect();

// Verificar permisos específicos usando RBAC
// Ejemplo: solo usuarios con permiso 'usuarios.ver' pueden acceder
auth_has_permission($payload, 'usuarios.ver');

echo json_encode([
    'message' => 'Acceso autorizado con permisos verificados',
    'usuario_id' => $payload['sub'],
    'rol' => $payload['rol'] ?? null,
    'rol_id' => $payload['rol_id'] ?? null,
    'permisos_verificados' => ['usuarios.ver'],
    'exp' => $payload['exp'] ?? null
]);


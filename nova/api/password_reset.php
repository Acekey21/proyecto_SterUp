<?php
require_once __DIR__ . '/init.php';
require_once __DIR__ . '/../includes/conexion.php';
require_once __DIR__ . '/../auth/jwt.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_json_response(405, ['error' => 'Método no permitido']);
}

// Validar CSRF token para POST
validate_csrf_if_needed();

$data = get_request_data();

$token = trim($data['token'] ?? '');
$nuevaContrasena = $data['nueva_contrasena'] ?? '';
$confirmarContrasena = $data['confirmar_contrasena'] ?? '';
$questionAnswers = $data['question_answers'] ?? null; // Para tokens de tipo 'questions'

if (!$token || !$nuevaContrasena || !$confirmarContrasena) {
    send_json_response(400, ['error' => 'Token y nuevas contraseñas son requeridos']);
}

if ($nuevaContrasena !== $confirmarContrasena) {
    send_json_response(400, ['error' => 'Las contraseñas no coinciden']);
}

// Validar fortaleza de contraseña
if (strlen($nuevaContrasena) < 8) {
    send_json_response(400, ['error' => 'La contraseña debe tener al menos 8 caracteres']);
}

if (!preg_match('/[A-Z]/', $nuevaContrasena)) {
    send_json_response(400, ['error' => 'La contraseña debe contener al menos una letra mayúscula']);
}

if (!preg_match('/[a-z]/', $nuevaContrasena)) {
    send_json_response(400, ['error' => 'La contraseña debe contener al menos una letra minúscula']);
}

if (!preg_match('/[0-9]/', $nuevaContrasena)) {
    send_json_response(400, ['error' => 'La contraseña debe contener al menos un número']);
}

// Verificar que no sea una contraseña común
$commonPasswords = ['123456', 'password', '123456789', 'qwerty', 'abc123', 'password123', 'admin', 'letmein'];
if (in_array(strtolower($nuevaContrasena), $commonPasswords)) {
    send_json_response(400, ['error' => 'Esta contraseña es muy común. Elige una más segura.']);
}

$sql = 'SELECT prt.*, u.correo FROM password_reset_tokens prt 
        INNER JOIN usuarios u ON prt.usuario_id = u.id 
        WHERE prt.token = ? AND prt.used = 0 AND prt.expires_at > NOW() 
        ORDER BY prt.created_at DESC LIMIT 1';
$stmt = $conn->prepare($sql);
$stmt->bind_param('s', $token);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows !== 1) {
    send_json_response(400, ['error' => 'Token inválido, expirado o ya utilizado']);
}

$reset = $result->fetch_assoc();
$usuarioId = $reset['usuario_id'];
$userEmail = $reset['correo'];
$tokenType = $reset['tipo'];

// Validación adicional para tokens de preguntas secretas
if ($tokenType === 'questions') {
    if (!$questionAnswers || !is_array($questionAnswers)) {
        send_json_response(400, ['error' => 'Debes responder las preguntas secretas para este tipo de token']);
    }

    // Verificar respuestas a preguntas secretas
    $questionsSql = "SELECT id, respuesta_encriptada FROM security_questions WHERE usuario_id = ? ORDER BY created_at";
    $questionsStmt = $conn->prepare($questionsSql);
    $questionsStmt->bind_param('i', $usuarioId);
    $questionsStmt->execute();
    $questionsResult = $questionsStmt->get_result();

    $questions = [];
    while ($q = $questionsResult->fetch_assoc()) {
        $questions[] = $q;
    }

    if (count($questionAnswers) !== count($questions)) {
        send_json_response(400, ['error' => 'Debes responder todas las preguntas secretas']);
    }

    // Validar cada respuesta
    foreach ($questions as $index => $question) {
        $userAnswer = trim($questionAnswers[$index] ?? '');
        if (!$userAnswer) {
            send_json_response(400, ['error' => 'Todas las preguntas deben tener respuesta']);
        }

        if (!password_verify(strtolower($userAnswer), $question['respuesta_encriptada'])) {
            send_json_response(400, ['error' => 'Una o más respuestas a preguntas secretas son incorrectas']);
        }
    }
}

// Verificar que la nueva contraseña no sea igual a la actual (si existe)
$checkCurrentPassword = $conn->prepare("SELECT contrasena FROM usuarios WHERE id = ?");
$checkCurrentPassword->bind_param('i', $usuarioId);
$checkCurrentPassword->execute();
$currentPasswordHash = $checkCurrentPassword->get_result()->fetch_assoc()['contrasena'];

if (password_verify($nuevaContrasena, $currentPasswordHash)) {
    send_json_response(400, ['error' => 'La nueva contraseña no puede ser igual a la contraseña actual']);
}

// Verificar que no haya usado esta contraseña recientemente (últimas 5)
$checkRecentPasswords = $conn->prepare("
    SELECT contrasena FROM password_history 
    WHERE usuario_id = ? 
    ORDER BY created_at DESC LIMIT 5
");
$checkRecentPasswords->bind_param('i', $usuarioId);
$checkRecentPasswords->execute();
$recentPasswords = $checkRecentPasswords->get_result();

while ($row = $recentPasswords->fetch_assoc()) {
    if (password_verify($nuevaContrasena, $row['contrasena'])) {
        send_json_response(400, ['error' => 'No puedes reutilizar una contraseña reciente']);
    }
}

// Actualizar contraseña
$hash = password_hash($nuevaContrasena, PASSWORD_DEFAULT);
$updateUser = $conn->prepare('UPDATE usuarios SET contrasena = ?, updated_at = NOW() WHERE id = ?');
$updateUser->bind_param('si', $hash, $usuarioId);
if (!$updateUser->execute()) {
    send_json_response(500, ['error' => 'Error al actualizar contraseña']);
}

// Guardar contraseña en historial
$insertHistory = $conn->prepare("INSERT INTO password_history (usuario_id, contrasena) VALUES (?, ?)");
$insertHistory->bind_param('is', $usuarioId, $hash);
$insertHistory->execute();

// Marcar token como usado
$updateToken = $conn->prepare('UPDATE password_reset_tokens SET used = 1, used_at = NOW() WHERE id = ?');
$updateToken->bind_param('i', $reset['id']);
$updateToken->execute();

// Invalidar todas las sesiones activas del usuario (seguridad)
$invalidateSessions = $conn->prepare("
    UPDATE refresh_tokens 
    SET expires_at = NOW() 
    WHERE usuario_id = ? AND expires_at > NOW()
");
$invalidateSessions->bind_param('i', $usuarioId);
$invalidateSessions->execute();

// Log de cambio de contraseña
$logPasswordChange = $conn->prepare("
    INSERT INTO password_change_logs 
    (usuario_id, tipo_cambio, ip, user_agent, exito)
    VALUES (?, 'reset', ?, ?, 1)
");
$ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$userAgent = substr($_SERVER['HTTP_USER_AGENT'] ?? 'desconocido', 0, 255);
$logPasswordChange->bind_param('iss', $usuarioId, $ip, $userAgent);
$logPasswordChange->execute();

// Enviar email de confirmación
require_once __DIR__ . '/../includes/email_service.php';
$userNameQuery = $conn->prepare("SELECT nombre FROM usuarios WHERE id = ?");
$userNameQuery->bind_param('i', $usuarioId);
$userNameQuery->execute();
$userName = $userNameQuery->get_result()->fetch_assoc()['nombre'];

$confirmationResult = send_password_change_confirmation_email(
    $userEmail,
    $userName,
    $ip,
    date('d/m/Y H:i:s')
);

send_json_response(200, [
    'message' => 'Contraseña actualizada correctamente',
    'info' => 'Todas tus sesiones anteriores han sido invalidadas por seguridad',
    'recommendations' => [
        'Cambia tu contraseña en todos los dispositivos donde hayas iniciado sesión',
        'Revisa tu actividad reciente en el panel de usuario',
        'Si no reconoces alguna actividad, contacta a soporte inmediatamente'
    ],
    'confirmation_email_sent' => $confirmationResult['success']
]);

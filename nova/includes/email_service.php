<?php
/**
 * EMAIL SERVICE
 * Encapsula lógica de envío de emails para diferentes tipos
 * (MFA, Password Recovery, Notificaciones, etc)
 */

require_once __DIR__ . '/../../config/email.php';

/**
 * Enviar código MFA por email
 * @param string $email Email del usuario
 * @param string $nombre Nombre del usuario
 * @param string $codigo Código OTP de 6 dígitos
 * @param int $expiry_minutes Minutos de expiración del código
 * @return array ['success' => bool, 'message' => string]
 */
function send_mfa_code_email($email, $nombre, $codigo, $expiry_minutes = 5) {
    $subject = 'Código de Verificación - StepUp';
    
    // Cargar template HTML
    $body = get_email_template('mfa_code', [
        'nombre' => htmlspecialchars($nombre),
        'codigo' => $codigo,
        'expiry_minutes' => $expiry_minutes,
        'timestamp' => date('d/m/Y H:i:s')
    ]);
    
    // Versión de texto plano
    $text_body = <<<TEXT
Hola $nombre,

Tu código de verificación para acceder a StepUp es:

$codigo

Este código expirará en $expiry_minutes minutos.

Si no solicitaste este código, ignora este email.

Saludos,
StepUp Team
TEXT;
    
    return send_email($email, $subject, $body, [
        'text_body' => $text_body
    ]);
}

/**
 * Enviar correo de verificación de email para nuevo registro
 * @param string $email Email del usuario
 * @param string $nombre Nombre del usuario
 * @param string $token Token único de verificación
 * @param string $verification_link URL completa del enlace de verificación
 * @param int $expiry_minutes Minutos de expiración del enlace
 * @return array ['success' => bool, 'message' => string]
 */
function send_email_verification($email, $nombre, $token, $verification_link, $expiry_minutes = 1440) {
    $subject = 'Confirma tu Email - StepUp';
    
    // Cargar template HTML
    $body = get_email_template('email_verification', [
        'nombre' => htmlspecialchars($nombre),
        'verification_link' => htmlspecialchars($verification_link),
        'expiry_minutes' => $expiry_minutes,
        'expiry_hours' => ceil($expiry_minutes / 60),
        'timestamp' => date('d/m/Y H:i:s'),
        'token' => substr($token, 0, 15) . '...'
    ]);
    
    // Versión de texto plano
    $text_body = <<<TEXT
Hola $nombre,

¡Bienvenido a StepUp! 

Para completar tu registro, necesitas verificar tu email. Haz clic en el siguiente enlace:

$verification_link

Este enlace expirará en {$expiry_minutes} minutos.

Si no creaste esta cuenta, ignora este email.

Saludos,
StepUp Team
TEXT;
    
    return send_email($email, $subject, $body, [
        'text_body' => $text_body
    ]);
}

/**
 * Enviar enlace de recuperación de contraseña
 * @param string $email Email del usuario
 * @param string $nombre Nombre del usuario
 * @param string $token Token de recuperación
 * @param string $reset_link URL completa del enlace de reset
 * @param int $expiry_minutes Minutos de expiración del enlace
 * @return array ['success' => bool, 'message' => string]
 */
function send_password_reset_email($email, $nombre, $token, $reset_link, $expiry_minutes = 60) {
    $subject = 'Recuperar tu Contraseña - StepUp';
    
    // Cargar template HTML
    $body = get_email_template('password_reset', [
        'nombre' => htmlspecialchars($nombre),
        'reset_link' => htmlspecialchars($reset_link),
        'expiry_minutes' => $expiry_minutes,
        'timestamp' => date('d/m/Y H:i:s'),
        'token' => substr($token, 0, 20) . '...'
    ]);
    
    // Versión de texto plano
    $text_body = <<<TEXT
Hola $nombre,

Recibimos una solicitud para recuperar tu contraseña en StepUp.

Haz clic en el siguiente enlace para cambiarla:
$reset_link

Este enlace expirará en $expiry_minutes minutos.

Si no solicitaste esto, ignora este email.

Saludos,
StepUp Team
TEXT;
    
    return send_email($email, $subject, $body, [
        'text_body' => $text_body,
        'replyTo' => MAIL_ADMIN_EMAIL
    ]);
}

/**
 * Enviar confirmación de cambio de contraseña
 * @param string $email Email del usuario
 * @param string $nombre Nombre del usuario
 * @param string $ip IP desde donde se cambió
 * @param string $timestamp Fecha y hora del cambio
 * @return array ['success' => bool, 'message' => string]
 */
function send_password_change_confirmation_email($email, $nombre, $ip, $timestamp) {
    $subject = 'Contraseña Cambiada - StepUp';
    
    // Cargar template HTML
    $body = get_email_template('password_change_confirmation', [
        'nombre' => htmlspecialchars($nombre),
        'ip' => htmlspecialchars($ip),
        'timestamp' => htmlspecialchars($timestamp)
    ]);
    
    // Versión de texto plano
    $text_body = <<<TEXT
Hola $nombre,

Tu contraseña en StepUp ha sido cambiada exitosamente.

Detalles del cambio:
- IP: $ip
- Fecha y hora: $timestamp

Si no realizaste este cambio, contacta inmediatamente a soporte.

Saludos,
StepUp Team
TEXT;
    
    return send_email($email, $subject, $body, [
        'text_body' => $text_body,
        'replyTo' => MAIL_ADMIN_EMAIL
    ]);
}

/**
 * Enviar confirmación de acceso exitoso
 * @param string $email Email del usuario
 * @param string $nombre Nombre del usuario
 * @param array $session_info Info de sesión (IP, User-Agent, fecha)
 * @return array ['success' => bool, 'message' => string]
 */
function send_login_confirmation_email($email, $nombre, $session_info = []) {
    $subject = 'Nuevo Acceso a tu Cuenta - StepUp';
    
    // Cargar template HTML
    $body = get_email_template('login_confirmation', [
        'nombre' => htmlspecialchars($nombre),
        'ip' => $session_info['ip'] ?? 'Desconocida',
        'user_agent' => $session_info['user_agent'] ?? 'Desconocido',
        'timestamp' => $session_info['timestamp'] ?? date('d/m/Y H:i:s')
    ]);
    
    $text_body = <<<TEXT
Hola $nombre,

Accedimos a tu cuenta en StepUp.

Detalles:
- IP: {$session_info['ip']}
- Hora: {$session_info['timestamp']}

Si no fuiste tú, cambia tu contraseña inmediatamente.

Saludos,
StepUp Team
TEXT;
    
    return send_email($email, $subject, $body, [
        'text_body' => $text_body
    ]);
}

/**
 * Obtener template de email y reemplazar variables
 * @param string $template Nombre del template (sin .html)
 * @param array $variables Array de variables a reemplazar
 * @return string HTML del email
 */
function get_email_template($template, $variables = []) {
    $template_file = __DIR__ . '/templates/' . $template . '.html';
    
    if (!file_exists($template_file)) {
        error_log("TEMPLATE NOT FOUND: $template_file");
        return default_email_template($variables);
    }
    
    $html = file_get_contents($template_file);
    
    // Reemplazar variables {{nombre}} -> valor
    foreach ($variables as $key => $value) {
        $html = str_replace('{{' . $key . '}}', $value, $html);
    }
    
    return $html;
}

/**
 * Template por defecto si no existe archivo
 */
function default_email_template($variables = []) {
    $nombre = $variables['nombre'] ?? 'Usuario';
    
    return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background-color: #007bff; color: white; padding: 20px; text-align: center; }
        .content { padding: 20px; background-color: #f9f9f9; }
        .footer { text-align: center; padding: 10px; font-size: 12px; color: #666; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>StepUp</h1>
        </div>
        <div class="content">
            <p>Hola $nombre,</p>
            <p>Email de StepUp.</p>
        </div>
        <div class="footer">
            <p>&copy; 2026 StepUp. Todos los derechos reservados.</p>
        </div>
    </div>
</body>
</html>
HTML;
}

/**
 * FUNCIÓN PRINCIPAL DE ENVÍO DE EMAIL
 * Maneja envío con PHPMailer en modo SMTP o guarda en logs si está en modo demo
 * 
 * @param string $to Email del destinatario
 * @param string $subject Asunto del email
 * @param string $html_body Cuerpo HTML del email
 * @param array $options Opciones adicionales:
 *        - text_body: Versión de texto plano (para clientes sin HTML)
 *        - replyTo: Email de respuesta
 *        - cc: Email para copiar
 *        - bcc: Email para copia oculta
 *        - attachments: Array de archivos a adjuntar
 * @return array ['success' => bool, 'message' => string, 'log_file' => string (solo si demo)]
 */
function send_email($to, $subject, $html_body, $options = []) {
    // Modo DEMO - Guardar en archivo para debugging/desarrollo
    if (EMAIL_MODE === 'demo') {
        return save_email_demo($to, $subject, $html_body, $options);
    }
    
    // Modo SMTP - Usar PHPMailer
    if (EMAIL_MODE === 'smtp') {
        return send_email_phpmailer($to, $subject, $html_body, $options);
    }
    
    return [
        'success' => false,
        'message' => 'Modo de email no configurado: ' . EMAIL_MODE
    ];
}

/**
 * Enviar email con PHPMailer via SMTP
 */
function send_email_phpmailer($to, $subject, $html_body, $options = []) {
    try {
        // Preferir Composer autoload si está disponible
        $composerAutoload = __DIR__ . '/../../vendor/autoload.php';
        if (file_exists($composerAutoload)) {
            require_once $composerAutoload;
        } else {
            require_once PHPMAILER_PATH . 'Exception.php';
            require_once PHPMAILER_PATH . 'PHPMailer.php';
            require_once PHPMAILER_PATH . 'SMTP.php';
        }
        
        if (!SMTP_USER || !SMTP_PASS) {
            $error_msg = 'Error SMTP: faltan credenciales SMTP_USER y/o SMTP_PASS. Revisa tu archivo .env.';
            error_log($error_msg);
            return [
                'success' => false,
                'message' => $error_msg,
                'mode' => 'smtp'
            ];
        }

        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        
        // Configuración SMTP
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->Port = SMTP_PORT;
        $mail->SMTPAuth = SMTP_AUTH;
        $mail->Username = SMTP_USER;
        $mail->Password = SMTP_PASS;
        $mail->AuthType = 'LOGIN';
        
        // TLS/SSL
        if (!empty(SMTP_SECURE)) {
            $mail->SMTPSecure = SMTP_SECURE;
        } elseif (SMTP_PORT == 465) {
            $mail->SMTPSecure = 'ssl';
        } elseif (SMTP_PORT == 587) {
            $mail->SMTPSecure = 'tls';
        }
        $mail->SMTPAutoTLS = SMTP_AUTO_TLS;
        
        // Opciones de SSL para entornos Windows o certificados autogenerados
        $mail->SMTPOptions = [
            'ssl' => [
                'verify_peer' => SMTP_VERIFY_PEER,
                'verify_peer_name' => SMTP_VERIFY_PEER,
                'allow_self_signed' => SMTP_ALLOW_SELF_SIGNED,
            ],
        ];
        
        // Configuración de debug opcional
        $mail->SMTPDebug = defined('SMTP_DEBUG') ? SMTP_DEBUG : 0;
        if ($mail->SMTPDebug > 0) {
            $mail->Debugoutput = 'error_log';
        }

        $mail->CharSet = MAIL_CHARSET;
        
        // Remitente
        $mail->setFrom(MAIL_FROM_ADDRESS, MAIL_FROM_NAME);
        
        // Destinatario
        $mail->addAddress($to);
        
        // Reply-To (opcional)
        if (!empty($options['replyTo'])) {
            $mail->addReplyTo($options['replyTo'], MAIL_FROM_NAME);
        }
        
        // CC (opcional)
        if (!empty($options['cc'])) {
            if (is_array($options['cc'])) {
                foreach ($options['cc'] as $cc_email) {
                    $mail->addCC($cc_email);
                }
            } else {
                $mail->addCC($options['cc']);
            }
        }
        
        // BCC (opcional)
        if (!empty($options['bcc'])) {
            if (is_array($options['bcc'])) {
                foreach ($options['bcc'] as $bcc_email) {
                    $mail->addBCC($bcc_email);
                }
            } else {
                $mail->addBCC($options['bcc']);
            }
        }
        
        // Asunto
        $mail->Subject = $subject;
        
        // Cuerpo HTML
        $mail->isHTML(true);
        $mail->Body = $html_body;
        
        // Cuerpo de texto plano (alternativa)
        if (!empty($options['text_body'])) {
            $mail->AltBody = $options['text_body'];
        } else {
            $mail->AltBody = strip_tags($html_body);
        }
        
        // Adjuntos (opcional)
        if (!empty($options['attachments'])) {
            foreach ($options['attachments'] as $file_path) {
                if (file_exists($file_path)) {
                    $mail->addAttachment($file_path);
                }
            }
        }
        
        // Enviar
        $mail->send();
        
        error_log("✓ Email enviado exitosamente a: $to (SMTP)");
        
        return [
            'success' => true,
            'message' => 'Email enviado exitosamente',
            'mode' => 'smtp'
        ];
        
    } catch (\PHPMailer\PHPMailer\Exception $e) {
        $error_msg = "Error PHPMailer: " . $e->errorMessage();
        error_log("✗ $error_msg");
        
        return [
            'success' => false,
            'message' => $error_msg,
            'mode' => 'smtp'
        ];
    } catch (Exception $e) {
        $error_msg = "Error general: " . $e->getMessage();
        error_log("✗ $error_msg");
        
        return [
            'success' => false,
            'message' => $error_msg,
            'mode' => 'smtp'
        ];
    }
}

/**
 * Guardar email en archivo (modo demo/desarrollo)
 */
function save_email_demo($to, $subject, $html_body, $options = []) {
    try {
        $log_file = EMAIL_LOG_DIR . date('Y-m-d_H-i-s_') . uniqid() . '.log';
        $text_body = isset($options['text_body']) ? $options['text_body'] : 'No especificado';
        $options_log = var_export($options, true);
        
        $content = <<<LOG
================================================================================
EMAIL CAPTURADO EN MODO DEMO
================================================================================
Fecha: {$_SERVER['REQUEST_TIME_FLOAT']} | Timestamp: {$_SERVER['REQUEST_TIME']}
Hora legible: {date('Y-m-d H:i:s')}

DE: {MAIL_FROM_ADDRESS} ({MAIL_FROM_NAME})
PARA: $to
ASUNTO: $subject

OPCIONES:
{$options_log}

================================================================================
CUERPO HTML:
================================================================================
{$html_body}

================================================================================
CUERPO TEXTO PLANO:
================================================================================
{$text_body}

================================================================================
LOG;
        
        file_put_contents($log_file, $content, FILE_APPEND);
        error_log("✓ Email guardado en modo DEMO: $log_file");
        
        return [
            'success' => true,
            'message' => 'Email capturado en modo desarrollo',
            'mode' => 'demo',
            'log_file' => $log_file
        ];
        
    } catch (Exception $e) {
        error_log("✗ Error guardando email: " . $e->getMessage());
        
        return [
            'success' => false,
            'message' => 'Error al guardar email en logs',
            'mode' => 'demo'
        ];
    }
}

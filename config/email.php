<?php
/**
 * CONFIGURACIÓN DE EMAIL CON PHPMailer
 * 
 * Modos disponibles:
 * - 'demo': Guarda emails en logs (desarrollo local)
 * - 'smtp': Usa servidor SMTP real con PHPMailer
 */

// ============================================================================
// CARGA DE VARIABLES DE ENTORNO (.env)
// ============================================================================
function load_dotenv($path) {
    if (!file_exists($path)) {
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) {
            continue;
        }

        if (!strpos($line, '=')) {
            continue;
        }

        list($key, $value) = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);

        if ($key === '' || $value === '') {
            continue;
        }

        // Eliminar comillas alrededor del valor si existen
        if ((substr($value, 0, 1) === '"' && substr($value, -1) === '"') ||
            (substr($value, 0, 1) === "'" && substr($value, -1) === "'")) {
            $value = substr($value, 1, -1);
        }

        putenv("$key=$value");
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    }
}

// Intentar cargar .env desde la raíz del proyecto
load_dotenv(dirname(__DIR__) . '/.env');

// ============================================================================
// CONFIGURACIÓN GENERAL
// ============================================================================
define('EMAIL_MODE', getenv('EMAIL_MODE') ?: 'demo'); // demo o smtp
define('MAIL_FROM_ADDRESS', getenv('MAIL_FROM_ADDRESS') ?: 'noreply@stepup.local');
define('MAIL_FROM_NAME', getenv('MAIL_FROM_NAME') ?: 'StepUp');
define('MAIL_ADMIN_EMAIL', getenv('MAIL_ADMIN_EMAIL') ?: 'admin@stepup.local');

// ============================================================================
// MODO DEMO - Desarrollo Local (guarda en archivo)
// ============================================================================
if (EMAIL_MODE === 'demo') {
    define('EMAIL_LOG_DIR', __DIR__ . '/../nova/logs/emails/');
    
    // Crear directorio si no existe
    if (!is_dir(EMAIL_LOG_DIR)) {
        @mkdir(EMAIL_LOG_DIR, 0755, true);
    }
}

// ============================================================================
// MODO SMTP - Producción con PHPMailer
// ============================================================================
if (EMAIL_MODE === 'smtp') {
    // OPCIÓN 1: Gmail (recomendado para desarrollo/pruebas)
    define('SMTP_HOST', getenv('SMTP_HOST') ?: 'smtp.gmail.com');
    define('SMTP_PORT', getenv('SMTP_PORT') ?: 587);
    define('SMTP_USER', getenv('SMTP_USER') ?: '');
    define('SMTP_PASS', getenv('SMTP_PASS') ?: '');
    define('SMTP_SECURE', getenv('SMTP_SECURE') ?: 'tls'); // 'tls' o 'ssl'
    define('SMTP_AUTH', true);
    define('SMTP_DEBUG', getenv('SMTP_DEBUG') !== false ? intval(getenv('SMTP_DEBUG')) : 0);
    define('SMTP_AUTO_TLS', getenv('SMTP_AUTO_TLS') !== false ? filter_var(getenv('SMTP_AUTO_TLS'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) : true);
    define('SMTP_VERIFY_PEER', getenv('SMTP_VERIFY_PEER') !== false ? filter_var(getenv('SMTP_VERIFY_PEER'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) : true);
    define('SMTP_ALLOW_SELF_SIGNED', getenv('SMTP_ALLOW_SELF_SIGNED') !== false ? filter_var(getenv('SMTP_ALLOW_SELF_SIGNED'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) : false);
    
    // Validar que hay credenciales configuradas
    if (!SMTP_USER || !SMTP_PASS) {
        error_log('ERROR: SMTP_USER y SMTP_PASS no están configurados en el .env');
    }

    // Detectar valores de ejemplo comunes
    $smtp_examples = [
        'tu_email@gmail.com',
        'example.com',
        'tmapgnicrwldaszj',
        'changeme',
        'smtp_user',
        'smtp_pass'
    ];
    $smtp_combined = strtolower(SMTP_USER . '|' . SMTP_PASS);
    $smtp_placeholders_found = false;
    foreach ($smtp_examples as $example) {
        if (strpos($smtp_combined, strtolower($example)) !== false) {
            $smtp_placeholders_found = true;
            error_log('ADVERTENCIA: SMTP_USER o SMTP_PASS parecen valores de ejemplo. Actualiza tu archivo .env.');
            break;
        }
    }
    define('SMTP_CREDENTIALS_PLACEHOLDER', $smtp_placeholders_found);
}

// ============================================================================
// PATH A PHPMAILER
// ============================================================================
define('PHPMAILER_PATH', __DIR__ . '/../PHPMailer-master/src/');

// Encoding
define('MAIL_CHARSET', 'UTF-8');

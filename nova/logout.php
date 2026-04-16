<?php
session_start();

// ============================================================================
// HEADERS DE SEGURIDAD
// ============================================================================
require_once __DIR__ . '/../config/security_headers.php';
session_destroy();
safe_redirect('login.php');
?>
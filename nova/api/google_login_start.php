<?php
/**
 * Iniciar flujo de Google OAuth
 * Este archivo redirige al usuario a Google para autorizar
 */

session_start();
require_once __DIR__ . '/init.php';
require_once __DIR__ . '/../../config/google.php';

// Redirigir a Google
header('Location: ' . get_google_auth_url());
exit();

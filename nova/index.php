<?php
require_once __DIR__ . '/../config/security_headers.php';
if (function_exists('safe_redirect')) {
    safe_redirect('login.php');
} else {
    header('Location: login.php');
}
?>
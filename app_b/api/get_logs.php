<?php
// App B - API para obtener logs de SSO

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$log_file = '../../logs/app_b/sso.log';

$logs = [];

if (file_exists($log_file)) {
    $log_content = file_get_contents($log_file);
    $log_lines = explode("\n", trim($log_content));

    // Obtener las últimas 10 entradas
    $logs = array_slice(array_reverse($log_lines), 0, 10);
}

echo json_encode(['logs' => $logs]);

?>
<?php
require_once 'includes/conexion.php';

echo "Estructura de password_reset_tokens:\n";
$result = $conn->query('DESCRIBE password_reset_tokens');
while ($row = $result->fetch_assoc()) {
    echo $row['Field'] . ' - ' . $row['Type'] . ' - ' . ($row['Null'] === 'YES' ? 'NULL' : 'NOT NULL') . '\n';
}

echo "\nEstructura de usuarios:\n";
$result = $conn->query('DESCRIBE usuarios');
while ($row = $result->fetch_assoc()) {
    echo $row['Field'] . ' - ' . $row['Type'] . ' - ' . ($row['Null'] === 'YES' ? 'NULL' : 'NOT NULL') . '\n';
}
?>
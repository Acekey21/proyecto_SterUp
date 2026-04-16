<?php
session_start();
require '../conexion.php';

// Verificar si el usuario es administrador
if (!isset($_SESSION['id']) || $_SESSION['rol'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>StepUp - Productos Disponibles</title>
    <style>
        body {
            background-color: #ffeef7;
            font-family: Arial, sans-serif;
        }

        h2 {
            color: #cb0cab;
            text-align: center;
            margin-top: 20px;
        }

        table {
            margin: 20px auto;
            border-collapse: collapse;
            width: 95%;
        }

        th, td {
            border: 1px solid #cb0cab;
            padding: 10px;
            text-align: center;
        }

        th {
            background-color: #cb0cab;
            color: white;
        }

        tr:nth-child(even) {
            background-color: #fce9f3;
        }
    </style>
</head>
<body>
    <h2>Productos Disponibles</h2>

    <table>
        <tr>
            <th>ID</th>
            <th>Nombre</th>
            <th>Marca</th>
            <th>Descripción</th>
            <th>Precio</th>
            <th>Imagen</th>
            <th>Fecha</th>
        </tr>
        <?php
        $sql = "SELECT * FROM productos ORDER BY id DESC";
        $resultado = $conn->query($sql);

        if ($resultado && $resultado->num_rows > 0) {
            while ($fila = $resultado->fetch_assoc()) {
                echo "<tr>";
                echo "<td>" . $fila['id'] . "</td>";
                echo "<td>" . htmlspecialchars($fila['nombre']) . "</td>";
                echo "<td>" . htmlspecialchars($fila['marca']) . "</td>";
                echo "<td>" . htmlspecialchars($fila['descripcion']) . "</td>";
                echo "<td>$" . number_format(floatval($fila['precio']), 2) . "</td>";
                echo "<td>" . (!empty($fila['imagen']) ? '<img src="../usuario/uploads/tenis/' . htmlspecialchars($fila['imagen']) . '" style="max-width:60px;">' : '-') . "</td>";
                echo "<td>" . (isset($fila['fecha']) ? htmlspecialchars($fila['fecha']) : '-') . "</td>";
                echo "</tr>";
            }
        } else {
            echo "<tr><td colspan='7'>No hay productos registrados.</td></tr>";
        }
        ?>
    </table>
</body>
</html>

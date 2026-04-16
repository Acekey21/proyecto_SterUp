<?php
session_start();
if (!isset($_SESSION['id']) || $_SESSION['rol'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

require '../conexion.php';

// Consulta para obtener las postulaciones con datos de usuario y vacante
$sql = "SELECT p.id, u.nombre AS nombre_usuario, u.correo, v.titulo AS vacante, v.empresa, p.fecha_postulacion
        FROM postulaciones p
        JOIN usuarios u ON p.usuario_id = u.id
        JOIN vacantes v ON p.vacante_id = v.id
        ORDER BY p.fecha_postulacion DESC";

$resultado = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Postulaciones</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #ffeef7;
            color: #333;
            padding: 20px;
        }

        h2 {
            color: #cb0cab;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        th, td {
            padding: 12px;
            border: 1px solid #ccc;
            text-align: left;
        }

        th {
            background-color: #cb0cab;
            color: white;
        }

        a {
            text-decoration: none;
            color: #cb0cab;
        }

        .volver {
            margin-top: 20px;
            display: inline-block;
            padding: 8px 16px;
            background-color: #cb0cab;
            color: white;
            border-radius: 4px;
        }

        .volver:hover {
            background-color: #a0088c;
        }
    </style>
</head>
<body>
    <h2>Lista de Postulaciones</h2>

    <?php if ($resultado && $resultado->num_rows > 0): ?>
        <table>
            <thead>
                <tr>
                    <th>Nombre del Usuario</th>
                    <th>Correo</th>
                    <th>Vacante</th>
                    <th>Empresa</th>
                    <th>Fecha de Postulación</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($fila = $resultado->fetch_assoc()): ?>
                    <tr>
                        <td><?= htmlspecialchars($fila['nombre_usuario']) ?></td>
                        <td><?= htmlspecialchars($fila['correo']) ?></td>
                        <td><?= htmlspecialchars($fila['vacante']) ?></td>
                        <td><?= htmlspecialchars($fila['empresa']) ?></td>
                        <td><?= htmlspecialchars($fila['fecha_postulacion']) ?></td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p>No hay postulaciones registradas.</p>
    <?php endif; ?>

    <a href="panel.php" class="volver">← Volver al Panel</a>
</body>
</html>

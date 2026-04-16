<?php
session_start();
require_once '../includes/conexion.php';

if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

// Eliminar usuario ( excepto admin )
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $deleteId = intval($_GET['delete']);
    if ($deleteId !== $_SESSION['id']) {
        $stmt = $conn->prepare('DELETE FROM usuarios WHERE id = ?');
        $stmt->bind_param('i', $deleteId);
        $stmt->execute();
        $stmt->close();
        header('Location: usuarios.php');
        exit();
    }
}

$users = $conn->query('SELECT id, nombre, correo, rol FROM usuarios ORDER BY id DESC');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Usuarios</title>
    <link rel="stylesheet" href="../Estilos/admin.css">
</head>
<body>
    <div class="wrapper">
        <div class="main-card">
            <h1>Usuarios registrados</h1>
            <p><a href="panel.php">← Volver al panel</a></p>

            <table style="width:100%; border-collapse:collapse; margin-top:12px;">
                <thead>
                    <tr style="background:#cb0cab; color:#fff;">
                        <th>ID</th>
                        <th>Nombre</th>
                        <th>Correo</th>
                        <th>Rol</th>
                        <th>Acción</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($users && $users->num_rows > 0): ?>
                        <?php while ($u = $users->fetch_assoc()): ?>
                            <tr style="border-bottom:1px solid #ddd;">
                                <td><?= (int)$u['id'] ?></td>
                                <td><?= htmlspecialchars($u['nombre']) ?></td>
                                <td><?= htmlspecialchars($u['correo']) ?></td>
                                <td><?= htmlspecialchars($u['rol']) ?></td>
                                <td>
                                    <?php if ($u['rol'] !== 'admin'): ?>
                                        <a href="usuarios.php?delete=<?= (int)$u['id'] ?>" onclick="return confirm('¿Eliminar este usuario?')">Eliminar</a>
                                    <?php else: ?>
                                        —
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="5">No hay usuarios registrados</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>

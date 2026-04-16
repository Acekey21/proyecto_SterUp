<?php
session_start();
include '../includes/conexion.php';

// Solo permitir acceso a administradores
if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'admin') {
    header("Location: ../index.php");
    exit();
}

$id = $_GET['id'] ?? null;

if ($id) {
    // eliminar archivos de imagen relacionados
    $uploadDir = __DIR__ . '/../usuario/uploads/tenis/';
    $imgStmt = $conn->prepare("SELECT archivo FROM imagenes WHERE vacante_id = ?");
    if ($imgStmt) {
        $imgStmt->bind_param("i", $id);
        $imgStmt->execute();
        $res = $imgStmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $path = $uploadDir . $row['archivo'];
            if (file_exists($path)) {
                @unlink($path);
            }
        }
        $imgStmt->close();
    }
    // borrar registros de imágenes (la FK con ON DELETE CASCADE también lo haría)
    $conn->query("DELETE FROM imagenes WHERE vacante_id = " . intval($id));

    $sql = "DELETE FROM vacantes WHERE id = ?";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();
    }

    // Eliminar producto equivalente en tabla productos (si existe)
    $sqlProd = "DELETE FROM productos WHERE id = ?";
    $stmtProd = $conn->prepare($sqlProd);
    if ($stmtProd) {
        $stmtProd->bind_param("i", $id);
        $stmtProd->execute();
        $stmtProd->close();
    }
}

header('Location: panel.php');
exit;

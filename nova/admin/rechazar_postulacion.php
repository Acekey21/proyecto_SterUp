<?php
session_start();
require_once '../includes/conexion.php';

// Verifica que sea admin
if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'admin') {
    header("Location: ../index.php");
    exit();
}

$id_postulacion = isset($_GET['id']) ? intval($_GET['id']) : null;

if ($id_postulacion) {
    $sql = "DELETE FROM postulaciones WHERE id = ?";
    $stmt = $conn->prepare($sql);

    if ($stmt) {
        $stmt->bind_param("i", $id_postulacion);
        if ($stmt->execute()) {
            $stmt->close();
            $conn->close();
            header("Location: panel.php?msg=Postulación eliminada");
            exit();
        } else {
            $stmt->close();
            $conn->close();
            header("Location: panel.php?msg=Error al eliminar");
            exit();
        }
    } else {
        $conn->close();
        header("Location: panel.php?msg=Error en la consulta");
        exit();
    }
} else {
    $conn->close();
    header("Location: panel.php?msg=ID inválido");
    exit();
}
?>

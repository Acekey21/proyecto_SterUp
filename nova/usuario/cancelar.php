<?php
session_start();
require '../includes/conexion.php';

if (!isset($_SESSION['id'])) {
    header("Location: ../login.php");
    exit();
}

if (isset($_GET['id'])) {
    $id_postulacion = $_GET['id'];
    $id_usuario = $_SESSION['id'];

    // Verificar que la postulación pertenezca al usuario logueado
    $sql = "DELETE FROM postulaciones WHERE id = ? AND id_usuario = ?";
    $stmt = $conn->prepare($sql);

    if ($stmt === false) {
        die("Error en la preparación: " . $conn->error);
    }

    $stmt->bind_param("ii", $id_postulacion, $id_usuario);

    if ($stmt->execute()) {
        // Redirigir de vuelta con éxito
        header("Location: mis_postulaciones.php?mensaje=cancelada");
        exit();
    } else {
        echo "Error al cancelar la postulación.";
    }
} else {
    echo "ID de postulación no proporcionado.";
}
?>

<?php
session_start();
require '../includes/conexion.php';

if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'usuario') {
    header('Location: ../login.php');
    exit();
}

$vacante_id = isset($_GET['vacante_id']) ? intval($_GET['vacante_id']) : 0;

if ($vacante_id <= 0) {
    header('Location: index.php');
    exit();
}

$stmt = $conn->prepare("SELECT * FROM vacantes WHERE id = ?");
$stmt->bind_param("i", $vacante_id);
$stmt->execute();
$result = $stmt->get_result();
$vacante = $result->fetch_assoc();
$stmt->close();

if (!$vacante) {
    header('Location: index.php');
    exit();
}

$usuarioNombre = $_SESSION['nombre'] ?? '';
$usuarioCorreo = $_SESSION['correo'] ?? '';

$mensaje_exito = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verificar si ya se postuló
    $stmt = $conn->prepare("SELECT COUNT(*) FROM postulaciones WHERE id_vacante = ? AND id_usuario = ?");
    $stmt->bind_param("ii", $vacante_id, $_SESSION['id']);
    $stmt->execute();
    $stmt->bind_result($yaPostulado);
    $stmt->fetch();
    $stmt->close();

    if ($yaPostulado > 0) {
        $error = "Ya te has postulado a esta vacante.";
    } else {
        // Subir archivo
        $uploadDir = __DIR__ . '/uploads/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $archivo_nombre = null;
        if (isset($_FILES['archivo']) && $_FILES['archivo']['error'] === UPLOAD_ERR_OK) {
            $fileTmpPath = $_FILES['archivo']['tmp_name'];
            $fileName = $_FILES['archivo']['name'];
            $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
            $allowedfileExtensions = ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx'];

            if (in_array($fileExtension, $allowedfileExtensions)) {
                $newFileName = md5(time() . $fileName) . '.' . $fileExtension;
                $dest_path = $uploadDir . $newFileName;

                if (move_uploaded_file($fileTmpPath, $dest_path)) {
                    $archivo_nombre = $newFileName;
                } else {
                    $error = "Error al subir el archivo.";
                }
            } else {
                $error = "Tipo de archivo no permitido. Solo imágenes, PDFs y Word.";
            }
        }

        if (!$error) {
            $nombre = trim($_POST['nombre']);
            $email = trim($_POST['email']);
            $educacion = trim($_POST['educacion']);
            $experiencia = trim($_POST['experiencia']);
            $habilidades = trim($_POST['habilidades']);
            $mensaje = trim($_POST['mensaje']);

            // Si no sube archivo, debe llenar el formulario de CV
            if (!$archivo_nombre && (!$educacion || !$experiencia || !$habilidades)) {
                $error = "Si no subes archivo, debes completar tu CV en el formulario.";
            } elseif (!$nombre || !$email) {
                $error = "Por favor completa nombre y email.";
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = "Email no válido.";
            } else {
                // Construir el CV con los datos del formulario
                if (!$archivo_nombre) {
                    $cv_texto = "Educación: $educacion\nExperiencia: $experiencia\nHabilidades: $habilidades\nMensaje adicional: $mensaje";
                } else {
                    $cv_texto = $mensaje;
                }

                // Insertar postulación en BD incluyendo archivo y CV generado
                $stmt = $conn->prepare("INSERT INTO postulaciones (id_vacante, id_usuario, archivo, mensaje, fecha_postulacion) VALUES (?, ?, ?, ?, NOW())");

                if ($stmt === false) {
                    $error = "Error al preparar la consulta: " . $conn->error;
                } else {
                    $stmt->bind_param("iiss", $vacante_id, $_SESSION['id'], $archivo_nombre, $cv_texto);

                    if ($stmt->execute()) {
                        $mensaje_exito = "¡Postulación enviada correctamente!";
                    } else {
                        $error = "Error al guardar la postulación: " . $stmt->error;
                    }

                    $stmt->close();
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <title>Postulación</title>
    <link rel="stylesheet" href="../Estilos/postular.css">
</head>
<body>
    <div class="volver-panel">
        <a href="../usuario/panel.php" class="btn-volver">Regresar</a>
    </div>
    <div class="container">
        <div class="card">
            <h2>Postúlate</h2>
            <?php if ($mensaje_exito): ?>
                <p class="mensaje-exito"><?= htmlspecialchars($mensaje_exito) ?></p>
            <?php elseif ($error): ?>
                <p class="mensaje-error"><?= htmlspecialchars($error) ?></p>
            <?php endif; ?>
            <form method="POST" enctype="multipart/form-data" novalidate>
                <input type="text" name="nombre" placeholder="Tu nombre" required value="<?= htmlspecialchars($usuarioNombre) ?>">
                <input type="email" name="email" placeholder="Tu email" required value="<?= htmlspecialchars($usuarioCorreo) ?>">
                <hr>
                <p><strong>Si no tienes archivo de CV, completa el siguiente formulario:</strong></p>
                <input type="text" name="educacion" placeholder="Educación (Ej: Universidad, carrera, año de egreso)">
                <input type="text" name="experiencia" placeholder="Experiencia laboral (Ej: Puesto, empresa, años)">
                <input type="text" name="habilidades" placeholder="Habilidades (Ej: Office, trabajo en equipo)">
                <textarea name="mensaje" placeholder="Mensaje adicional o CV completo" rows="5"></textarea>
                <hr>
                <p><strong>O sube tu CV como archivo:</strong></p>
                <input type="file" name="archivo" accept=".jpg,.jpeg,.png,.gif,.pdf,.doc,.docx">
                <small>Puedes subir tu CV como archivo o crear uno con el formulario.</small>
                <button type="submit">Enviar postulación</button>
            </form>

            <!-- Formulario para descargar el CV en PDF -->
           
            </form>
        </div>

        <div class="card info">
            <h2><?= htmlspecialchars($vacante['puesto']) ?></h2>
            <p><strong>Empresa:</strong> <?= htmlspecialchars($vacante['empresa']) ?></p>
            <p><strong>Remuneracion:</strong> <?= htmlspecialchars($vacante['remuneracion']) ?></p>
            <p><strong>Carrera:</strong> <?= htmlspecialchars($vacante['carrera']) ?></p>
            <p><strong>Duracion:</strong> <?= htmlspecialchars($vacante['duracion_contrato']) ?></p>
            <p><strong>Jornada:</strong> <?= htmlspecialchars($vacante['tipo_jornada']) ?></p>
            <p><strong>Ubicación:</strong> <?= htmlspecialchars($vacante['ubicacion']) ?></p>
            <p><strong>Descripción:</strong></p>
            <p><?= nl2br(htmlspecialchars($vacante['descripcion'])) ?></p>
        </div>
    </div>
</body>
</html>

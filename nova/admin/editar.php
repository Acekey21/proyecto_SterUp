<?php
session_start();
include '../includes/conexion.php';

if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'admin') {
    header("Location: ../index.php");
    exit();
}

$id = $_GET['id'] ?? null;
if (!$id) {
    exit('ID no válido');
}

// Obtener el producto desde productos (prioritario)
$sql = "SELECT id, nombre AS empresa, precio AS puesto, descripcion, marca AS remuneracion FROM productos WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$vacante = $result->fetch_assoc();
$stmt->close();

if (!$vacante) {
    // fallback a vacantes
    $sql = "SELECT * FROM vacantes WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $vacante = $result->fetch_assoc();
    $stmt->close();
}

if (!$vacante) {
    exit('Producto no encontrado');
}

// obtener imágenes asociadas
$images = [];
$stmtImgs = $conn->prepare("SELECT id, archivo FROM imagenes WHERE vacante_id = ?");
if ($stmtImgs) {
    $stmtImgs->bind_param("i", $id);
    $stmtImgs->execute();
    $resImgs = $stmtImgs->get_result();
    while ($r = $resImgs->fetch_assoc()) {
        $images[] = $r;
    }
    $stmtImgs->close();
}

$mensaje = '';

/* ------------------ ACTUALIZAR VACANTE ------------------ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // adaptar a producto (nombre, precio, descripcion, marca)
    $nombre      = trim($_POST['nombre'] ?? '');
    $precio      = trim($_POST['precio'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    $marca       = trim($_POST['marca'] ?? '');

    if ($nombre && $precio && $descripcion && $marca) {
        $precio = floatval(str_replace([',', '$', '€', ' '], ['.', '', '', ''], $precio));
        if ($precio <= 0) {
            $mensaje = "⚠ Precio inválido, debe ser mayor a 0.";
        } else {
            $updated = false;
            // actualizar vacantes (si existe)
            $sql_update_vac = "UPDATE vacantes SET empresa = ?, puesto = ?, descripcion = ?, remuneracion = ? WHERE id = ?";
            $stmtVac = $conn->prepare($sql_update_vac);
            if ($stmtVac) {
                $stmtVac->bind_param("ssssi", $nombre, $precio, $descripcion, $marca, $id);
                if ($stmtVac->execute()) {
                    $updated = true;
                }
                $stmtVac->close();
            }

            // actualizar productos
            $sql_update_prod = "UPDATE productos SET nombre = ?, marca = ?, precio = ?, descripcion = ? WHERE id = ?";
            $stmtProd = $conn->prepare($sql_update_prod);
            if ($stmtProd) {
                $stmtProd->bind_param("ssdsi", $nombre, $marca, $precio, $descripcion, $id);
                if ($stmtProd->execute()) {
                    $updated = true;
                }
                $stmtProd->close();
            }

            if ($updated) {
                $mensaje = "Producto actualizado correctamente.";
                $vacante['empresa']      = $nombre;
                $vacante['puesto']       = $precio;
                $vacante['descripcion']  = $descripcion;
                $vacante['remuneracion'] = $marca;
            } else {
                $mensaje = "⚠ Error al actualizar el producto.";
            }

            // procesar nuevas imágenes si las suben
            $uploadDir = __DIR__ . '/../usuario/uploads/tenis/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
            if (!empty($_FILES['imagenes'])) {
                $allowed = ['jpg','jpeg','png','gif'];
                foreach ($_FILES['imagenes']['tmp_name'] as $idx => $tmpPath) {
                    if ($_FILES['imagenes']['error'][$idx] !== UPLOAD_ERR_OK) continue;
                    $origName = $_FILES['imagenes']['name'][$idx];
                    $relativePath = str_replace('\\', '/', $origName);
                    $ext = strtolower(pathinfo($relativePath, PATHINFO_EXTENSION));
                    if (!in_array($ext, $allowed)) continue;

                    $subPath = pathinfo($relativePath, PATHINFO_DIRNAME);
                    if ($subPath === '.' || $subPath === '') {
                        $subPath = '';
                    } else {
                        $subPath = trim($subPath, '/\\') . '/';
                    }

                    $targetDir = $uploadDir . $subPath;
                    if (!is_dir($targetDir)) {
                        mkdir($targetDir, 0755, true);
                    }

                    $newName = md5(time() . $relativePath . $idx) . '.' . $ext;
                    $dest = $targetDir . $newName;
                    $dbPath = $subPath . $newName;

                    if (move_uploaded_file($tmpPath, $dest)) {
                        $stmtImg = $conn->prepare("INSERT INTO imagenes (vacante_id, archivo) VALUES (?, ?)");
                        if ($stmtImg) {
                            $stmtImg->bind_param("is", $id, $dbPath);
                            $stmtImg->execute();
                            $stmtImg->close();
                        }
                    }
                }
            }

            // recargar lista de imágenes
            $images = [];
            $stmtImgs = $conn->prepare("SELECT id, archivo FROM imagenes WHERE vacante_id = ?");
            if ($stmtImgs) {
                $stmtImgs->bind_param("i", $id);
                $stmtImgs->execute();
                $resImgs = $stmtImgs->get_result();
                while ($r = $resImgs->fetch_assoc()) { $images[] = $r; }
                $stmtImgs->close();
            }
        }
    } else {
        $mensaje = "⚠ Por favor completa todos los campos obligatorios.";
    }
}


// manejar eliminación de imagen individual
if (isset($_GET['delete_img'])) {
    $imgId = intval($_GET['delete_img']);
    $stmtD = $conn->prepare("SELECT archivo FROM imagenes WHERE id = ? AND vacante_id = ?");
    if ($stmtD) {
        $stmtD->bind_param("ii", $imgId, $id);
        $stmtD->execute();
        $resD = $stmtD->get_result();
        if ($rowD = $resD->fetch_assoc()) {
            $path = __DIR__ . '/../usuario/uploads/tenis/' . $rowD['archivo'];
            if (file_exists($path)) @unlink($path);
            $stmtDel = $conn->prepare("DELETE FROM imagenes WHERE id = ?");
            if ($stmtDel) { $stmtDel->bind_param("i", $imgId); $stmtDel->execute(); $stmtDel->close(); }
        }
        $stmtD->close();
    }
    header('Location: editar.php?id=' . intval($id));
    exit();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Editar Vacante</title>
    <link rel="stylesheet" href="../Estilos/editar.css">
</head>
<body>
    <div class="wrapper">
        <div class="main-card">
            <h1>Editar Vacante</h1>

            <?php if ($mensaje): ?>
                <p class="mensaje"><?= htmlspecialchars($mensaje) ?></p>
            <?php endif; ?>


            <form method="POST" class="formulario" enctype="multipart/form-data">
                <input type="text" name="nombre" value="<?= htmlspecialchars($vacante['empresa']) ?>" placeholder="Nombre del tenis" required>
                <input type="text" name="precio" value="<?= htmlspecialchars($vacante['puesto']) ?>" placeholder="Precio" required>
                <textarea name="descripcion" rows="4" placeholder="Descripción" required><?= htmlspecialchars($vacante['descripcion']) ?></textarea>
                <input type="text" name="marca" value="<?= htmlspecialchars($vacante['remuneracion']) ?>" placeholder="Marca" required>
                <label for="imagenes">Añadir imágenes (puedes seleccionar múltiples archivos o una carpeta entera)</label>
                <input type="file" name="imagenes[]" id="imagenes" accept="image/*" multiple webkitdirectory directory>
                <button type="submit">Guardar Cambios</button>
            </form>

            <?php if (!empty($images)): ?>
                <div class="galeria-admin" style="margin-top:18px;">
                    <div id="mainImage" style="text-align:center;margin-bottom:10px;">
                        <img src="../usuario/uploads/tenis/<?= htmlspecialchars($images[0]['archivo']) ?>" id="imgDisplay" style="max-width:300px;border-radius:8px;" />
                    </div>
                    <div style="display:flex;gap:8px;flex-wrap:wrap;justify-content:center;">
                        <?php foreach ($images as $img): ?>
                            <div style="text-align:center;">
                                <a href="editar.php?id=<?= (int)$id ?>&delete_img=<?= (int)$img['id'] ?>" 
                                   onclick="return confirm('¿Eliminar esta imagen?');" 
                                   style="display:block;color:#c00;margin-bottom:4px;">Eliminar</a>
                                <img src="../usuario/uploads/tenis/<?= htmlspecialchars($img['archivo']) ?>" class="thumb" style="width:80px;cursor:pointer;border-radius:6px;" data-src="../usuario/uploads/tenis/<?= htmlspecialchars($img['archivo']) ?>" />
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <script>
                    document.addEventListener('DOMContentLoaded', function(){
                        document.querySelectorAll('.thumb').forEach(function(t){
                            t.addEventListener('click', function(){
                                document.getElementById('imgDisplay').src = this.dataset.src;
                            });
                        });
                    });
                </script>
            <?php endif; ?>

            <div class="volver">
                <a href="panel.php">← Regresar</a>
            </div>
        </div>
    </div>
</body>
</html>

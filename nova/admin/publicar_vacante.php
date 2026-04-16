<?php
session_start();
if (!isset($_SESSION['id']) || $_SESSION['rol'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

require '../includes/conexion.php';

$mensaje = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim($_POST['nombre'] ?? '');
    $marca = trim($_POST['marca'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    $precioRaw = trim($_POST['precio'] ?? '');

    // Normalizar precio: quitar moneda, espacios y comas
    $precio = floatval(str_replace([',', '$', '€', ' '], ['.', '', '', ''], $precioRaw));

    if ($nombre && $marca && $descripcion && $precio > 0) {
        $stmt = $conn->prepare("INSERT INTO vacantes (empresa, puesto, descripcion, remuneracion) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("sdss", $nombre, $precio, $descripcion, $marca);

        if ($stmt->execute()) {
            $producto_id = $conn->insert_id;
            // procesar imágenes
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
                            $stmtImg->bind_param("is", $producto_id, $dbPath);
                            $stmtImg->execute();
                            $stmtImg->close();
                        }
                    }
                }
            }
            $mensaje = "Producto agregado correctamente.";
        } else {
            $mensaje = "Error al publicar la vacante: " . $stmt->error;
        }

        $stmt->close();
    } else {
        $mensaje = "Por favor completa todos los campos.";
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Publicar Producto - StepUp</title>
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

        form {
            background-color: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            max-width: 600px;
        }

        label {
            display: block;
            margin-top: 10px;
        }

        input, textarea, select {
            width: 100%;
            padding: 10px;
            margin-top: 5px;
            border-radius: 5px;
            border: 1px solid #ccc;
        }

        button {
            margin-top: 20px;
            background-color: #cb0cab;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
        }

        button:hover {
            background-color: #a0088c;
        }

        .mensaje {
            margin-top: 15px;
            color: green;
        }

        .volver {
            display: inline-block;
            margin-top: 20px;
            padding: 8px 16px;
            background-color: #cb0cab;
            color: white;
            text-decoration: none;
            border-radius: 4px;
        }

        .volver:hover {
            background-color: #a0088c;
        }
    </style>
</head>
<body>
    <h2>Agregar Nuevo Tenis</h2>

    <form method="post" action="" enctype="multipart/form-data">
        <label for="nombre">Nombre del Producto:</label>
        <input type="text" name="nombre" id="nombre" required>

        <label for="marca">Marca:</label>
        <input type="text" name="marca" id="marca" required>

        <label for="descripcion">Descripción:</label>
        <textarea name="descripcion" id="descripcion" rows="4" required></textarea>

        <label for="precio">Precio:</label>
        <input type="text" name="precio" id="precio" required>

        <label for="imagenes">Imágenes (puedes seleccionar múltiples archivos):</label>
        <input type="file" name="imagenes[]" id="imagenes" accept="image/*" multiple>

        <button type="submit">Agregar Producto</button>
    </form>

    <?php if ($mensaje): ?>
        <p class="mensaje"><?= htmlspecialchars($mensaje) ?></p>
    <?php endif; ?>

    <a href="panel.php" class="volver">← Volver al Panel</a>
</body>
</html>

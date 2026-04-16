<?php
session_start();

// ============================================================================
// HEADERS DE SEGURIDAD
// ============================================================================
require_once __DIR__ . '/../../config/security_headers.php';
require_once '../includes/conexion.php';

// aseguramos existencia de tabla para productos (esquema tenis.sql que estás usando)
$createProductos = "CREATE TABLE IF NOT EXISTS productos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) DEFAULT NULL,
    marca VARCHAR(50) DEFAULT NULL,
    precio DECIMAL(10,2) DEFAULT NULL,
    imagen VARCHAR(255) DEFAULT NULL,
    descripcion TEXT DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
$conn->query($createProductos);

// Aseguramos esquema viejo vacantes (compatibilidad con el código que ya tenía)
$createVacantes = "CREATE TABLE IF NOT EXISTS vacantes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    empresa VARCHAR(100) DEFAULT NULL,
    puesto VARCHAR(100) DEFAULT NULL,
    descripcion TEXT DEFAULT NULL,
    remuneracion VARCHAR(100) DEFAULT NULL,
    fecha_publicacion DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
$conn->query($createVacantes);

// aseguramos existencia de tabla para imágenes de productos
$createTbl = "CREATE TABLE IF NOT EXISTS imagenes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    vacante_id INT NOT NULL,
    archivo VARCHAR(255) NOT NULL,
    FOREIGN KEY (vacante_id) REFERENCES vacantes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
$conn->query($createTbl);

// Charset correcto
if (method_exists($conn, 'set_charset')) { 
    $conn->set_charset('utf8mb4'); 
}

// Verificación de sesión y rol
if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'admin') {
    safe_redirect('../index.php');
}

$mensaje = '';

/* ----------------------- CREAR PRODUCTO (TENIS) ----------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre      = trim($_POST['nombre'] ?? '');   // nombre del tenis
    $precio      = trim($_POST['precio'] ?? '');   // precio del tenis
    $descripcion = trim($_POST['descripcion'] ?? '');
    $marca       = trim($_POST['marca'] ?? '');    // marca del tenis

    if ($nombre && $precio && $descripcion && $marca) {
        // normalizar precio y asegurarnos de que sea decimal
        $precio = floatval(str_replace([',', '$', '€', ' '], ['.', '', '', ''], $precio));
        if ($precio <= 0) {
            $mensaje = "⚠ Precio inválido, debe ser mayor a 0.";
        } else {
            // fallback: insertar en vacantes para compatibilidad (viejo flujo)
            $sql = "INSERT INTO vacantes 
                    (empresa, puesto, descripcion, remuneracion)
                    VALUES (?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            if ($stmt) {
                $stmt->bind_param(
                    "ssss",
                    $nombre, $precio, $descripcion, $marca
                );
                $stmtVacantesId = null;
                if ($stmt->execute()) {
                    $stmtVacantesId = $conn->insert_id;
                }
                $stmt->close();
            }

            // Insertar en tabla productos (dump tennis.sql)
            $sqlProd = "INSERT INTO productos (nombre, marca, precio, descripcion) VALUES (?, ?, ?, ?)";
            $stmtProd = $conn->prepare($sqlProd);
            if ($stmtProd) {
                $stmtProd->bind_param("ssds", $nombre, $marca, $precio, $descripcion);
                if ($stmtProd->execute()) {
                    $mensaje = "✅ Tenis publicado correctamente en productos.";
                    $producto_id = $conn->insert_id;
                } else {
                    $mensaje = "⚠ Error al publicar el tenis en productos: " . $stmtProd->error;
                }
                $stmtProd->close();
            } else {
                $mensaje = "⚠ Error preparando producto: " . $conn->error;
            }

            // Procesar imágenes si existen (guardar en imagenes/vacantes y complemento img en productos)
            $uploadDir = __DIR__ . '/../usuario/uploads/tenis/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            $firstImage = null;
            if (!empty($_FILES['imagenes']['tmp_name'])) {
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
                        if ($firstImage === null) {
                            $firstImage = $dbPath;
                        }
                        if ($stmtVacantesId) {
                            $stmtImg = $conn->prepare("INSERT INTO imagenes (vacante_id, archivo) VALUES (?, ?)");
                            if ($stmtImg) {
                                $stmtImg->bind_param("is", $stmtVacantesId, $dbPath);
                                $stmtImg->execute();
                                $stmtImg->close();
                            }
                        }
                    }
                }
            }

            // Almacenar imagen principal en tabla productos (si existe)
            if ($firstImage !== null && isset($producto_id)) {
                $stmtProdImg = $conn->prepare("UPDATE productos SET imagen = ? WHERE id = ?");
                if ($stmtProdImg) {
                    $stmtProdImg->bind_param("si", $firstImage, $producto_id);
                    $stmtProdImg->execute();
                    $stmtProdImg->close();
                }
            }
        }
    } else {
        $mensaje = "⚠ Completa al menos Nombre, Precio, Descripción y Marca.";
    }
}

/* ----------------------- OBTENER PRODUCTOS (tenis) ----------------------- */
$vacantes = null; // ahora representan tenis

// filtros de búsqueda (desde GET)
$search = trim($_GET['search'] ?? '');
$filterMarca = trim($_GET['marca'] ?? '');

// obtener marcas disponibles (productos)
$marcas = [];
$resM = $conn->query("SELECT DISTINCT marca FROM productos ORDER BY marca");
if ($resM) { while ($r = $resM->fetch_assoc()) $marcas[] = $r['marca']; }

// construir consulta con filtros
$query = "SELECT id, nombre AS empresa, precio AS puesto, descripcion, marca AS remuneracion, NULL AS fecha_publicacion FROM productos WHERE 1=1";
$params = [];
$types = '';
if ($search !== '') {
    $query .= " AND (nombre LIKE ? OR marca LIKE ? OR precio LIKE ? OR descripcion LIKE ?)";
    $like = "%" . $search . "%";
    $params = array_merge($params, [$like, $like, $like, $like]);
    $types .= 'ssss';
}
if ($filterMarca !== '') {
    $query .= " AND marca = ?";
    $params[] = $filterMarca;
    $types .= 's';
}
$query .= " ORDER BY id DESC";

$stmtVac = $conn->prepare($query);
if ($stmtVac) {
    if (!empty($params)) {
        $stmtVac->bind_param($types, ...$params);
    }
    if ($stmtVac->execute()) {
        $vacantes = $stmtVac->get_result();
        if ($vacantes && $vacantes->num_rows === 0) {
            // fallback a vacantes si productos está vacío
            $query2 = "SELECT id, empresa, puesto, descripcion, remuneracion, fecha_publicacion FROM vacantes WHERE 1=1";
            if ($search !== '') {
                $query2 .= " AND (empresa LIKE ? OR remuneracion LIKE ? OR puesto LIKE ? OR descripcion LIKE ? )";
            }
            if ($filterMarca !== '') {
                $query2 .= " AND remuneracion = ?";
            }
            $query2 .= " ORDER BY fecha_publicacion DESC";

            $stmtFallback = $conn->prepare($query2);
            if ($stmtFallback) {
                $bindParams = [];
                $bindTypes = '';
                if ($search !== '') {
                    $v = "%" . $search . "%";
                    $bindParams = array_merge($bindParams, [$v, $v, $v, $v]);
                    $bindTypes .= 'ssss';
                }
                if ($filterMarca !== '') {
                    $bindParams[] = $filterMarca;
                    $bindTypes .= 's';
                }
                if (!empty($bindParams)) {
                    $stmtFallback->bind_param($bindTypes, ...$bindParams);
                }
                if ($stmtFallback->execute()) {
                    $vacantes = $stmtFallback->get_result();
                }
                $stmtFallback->close();
            }
        }
    } else {
        $mensaje = $mensaje ?: "⚠ No se pudieron cargar los tenis: " . $conn->error;
    }
} else {
    $vacantes = $conn->query($query);
    if (!$vacantes) $mensaje = $mensaje ?: "⚠ No se pudieron cargar los tenis: " . $conn->error;
}?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Panel de Administración - Tenis</title>
    <link rel="stylesheet" href="../Estilos/admin.css">
    <meta name="viewport" content="width=device-width, initial-scale=1">
</head>
<body>
<div class="wrapper">
    <div class="main-card">

        <!-- Botón de logout -->
        <div style="text-align:right;margin-bottom:20px;">
            <a href="../logout.php" class="logout-button">Cerrar sesión</a>
        </div>

        <h1>Panel de Administración de Tenis</h1>
        <p><a href="usuarios.php" style="color:#1b98e0;">Ver/gestionar usuarios</a></p>

        <!-- Mensajes -->
        <?php if (!empty($mensaje)): ?>
            <p class="mensaje"><?= htmlspecialchars($mensaje) ?></p>
        <?php endif; ?>

        <!-- Formulario publicación de tenis -->
        <form class="formulario" method="POST" action="panel.php" autocomplete="off" enctype="multipart/form-data">
            <input type="text" name="nombre" placeholder="Nombre del tenis" required>
            <input type="text" name="precio" placeholder="Precio" required>
            <textarea name="descripcion" placeholder="Descripción" rows="4" required></textarea>
            <input type="text" name="marca" placeholder="Marca" required>
            <label for="imagenes">Imágenes (puedes seleccionar múltiples archivos o una carpeta entera)</label>
            <input type="file" name="imagenes[]" id="imagenes" accept="image/*" multiple webkitdirectory directory>
            <button type="submit">Publicar</button>
        </form>

        <!-- Listado de tenis -->
        <form method="GET" style="margin:12px 0;display:flex;gap:8px;align-items:center;">
            <input type="text" name="search" placeholder="Buscar por nombre o marca" value="<?= htmlspecialchars($search) ?>" style="padding:8px;border-radius:6px;border:1px solid #ccc;" />
            <select name="marca" style="padding:8px;border-radius:6px;border:1px solid #ccc;">
                <option value="">Todas las marcas</option>
                <?php foreach ($marcas as $m): ?>
                    <option value="<?= htmlspecialchars($m) ?>" <?= $filterMarca === $m ? 'selected' : '' ?>><?= htmlspecialchars($m) ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" style="padding:8px 12px;border-radius:6px;background:#1b98e0;color:#fff;border:none;">Filtrar</button>
        </form>
        <div class="vacante">
            <h2>Tenis Publicados</h2>
            <ul>
                <?php if ($vacantes && $vacantes->num_rows > 0): ?>
                    <?php while ($v = $vacantes->fetch_assoc()):
                        // obtener imágenes asociadas
                        $images = [];
                        if ($stmtImg = $conn->prepare("SELECT archivo FROM imagenes WHERE vacante_id=?")) {
                            $stmtImg->bind_param("i", $v['id']);
                            $stmtImg->execute();
                            $resImg = $stmtImg->get_result();
                            while ($rowImg = $resImg->fetch_assoc()) {
                                $images[] = $rowImg['archivo'];
                            }
                            $stmtImg->close();
                        }
                    ?>
                        <li style="margin-bottom:18px;">
                            <p><strong><?= htmlspecialchars($v['empresa']) ?></strong></p>
                            <p>Precio: <?= htmlspecialchars($v['puesto']) ?></p>
                            <p>Marca: <?= htmlspecialchars($v['remuneracion']) ?></p>
                            <?php if (!empty($images)): ?>
                                <div class="galeria" style="margin:8px 0;text-align:center;">
                                    <div style="margin-bottom:8px;">
                                        <img id="mainImg-<?= (int)$v['id'] ?>" src="../usuario/uploads/tenis/<?= htmlspecialchars($images[0]) ?>" style="max-width:220px;border-radius:8px;box-shadow:0 8px 18px rgba(0,0,0,0.12);" />
                                    </div>
                                    <div style="display:flex;gap:8px;justify-content:center;flex-wrap:wrap;">
                                        <?php foreach ($images as $img): ?>
                                            <img src="../usuario/uploads/tenis/<?= htmlspecialchars($img) ?>" alt="thumb" style="width:80px;cursor:pointer;border-radius:6px;" onclick="document.getElementById('mainImg-<?= (int)$v['id'] ?>').src=this.src;" />
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                            <p><em><?= nl2br(htmlspecialchars($v['descripcion'])) ?></em></p>
                            <small>
                               <p> Publicada: <?= htmlspecialchars($v['fecha_publicacion']) ?></p>
                            </small>
                            <br>
                            <a href="editar.php?id=<?= (int)$v['id'] ?>">Editar</a> |
                            <a href="eliminar.php?id=<?= (int)$v['id'] ?>" onclick="return confirm('¿Eliminar este tenis?');">Eliminar</a>
                        </li>
                    <?php endwhile; ?>
                <?php else: ?>
                    <li>No hay tenis aún.</li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</div>
</body>
</html>

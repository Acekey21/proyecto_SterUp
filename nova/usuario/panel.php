<?php
session_start();
if (!isset($_SESSION['id'])) {
    header("Location: ../login.php");
    exit();
}

$correo_usuario = $_SESSION['correo'] ?? 'Invitado';

require '../includes/conexion.php'; // Asegúrate que la ruta sea correcta
// si aún no existe, crea la tabla de imágenes de tenis
$conn->query("CREATE TABLE IF NOT EXISTS imagenes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    vacante_id INT NOT NULL,
    archivo VARCHAR(255) NOT NULL,
    FOREIGN KEY (vacante_id) REFERENCES vacantes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Tienda de Tenis</title>
    <link rel="stylesheet" href="../Estilos/usuarios.css">
    <style>
        .carousel {
            position: relative;
            max-width: 300px;
            margin: 0 auto;
        }
        .carousel-images {
            display: flex;
            overflow: hidden;
        }
        .carousel-images img {
            width: 100%;
            display: none;
        }
        .carousel-images img.active {
            display: block;
        }
        .carousel-btn {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            background: rgba(0,0,0,0.5);
            color: white;
            border: none;
            padding: 10px;
            cursor: pointer;
            font-size: 18px;
        }
        .carousel-btn.prev {
            left: 0;
        }
        .carousel-btn.next {
            right: 0;
        }
    </style>
</head>
<body>

<div class="top-nav">
    Bienvenido, <?php echo htmlspecialchars($correo_usuario); ?> |
    <a href="mis_postulaciones.php">🛒 Mi Carrito</a> |
    <a href="../logout.php">Cerrar sesión</a>
</div>

<h1>Tenis Disponibles</h1>

<?php
// valores para filtros
$search = trim($_GET['search'] ?? '');
$filterMarca = trim($_GET['marca'] ?? '');

// obtener marcas disponibles para filtro
$marcas = [];
$resM = $conn->query("SELECT DISTINCT marca FROM productos ORDER BY marca");
if ($resM) { while ($r = $resM->fetch_assoc()) $marcas[] = $r['marca']; }

// Consulta principal productos
$query = "SELECT id, nombre AS empresa, precio AS puesto, descripcion, marca AS remuneracion FROM productos WHERE 1=1";
$params = [];
$types = '';
if ($search !== '') {
    $query .= " AND (nombre LIKE ? OR marca LIKE ? OR precio LIKE ? OR descripcion LIKE ? )";
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

$stmt = $conn->prepare($query);
if ($stmt) {
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $res = $stmt->get_result();
} else {
    $res = $conn->query($query);
}

// fallback a vacantes si productos está vacío
if ($res && $res->num_rows === 0) {
    $query = "SELECT id, empresa, puesto, descripcion, remuneracion, fecha_publicacion FROM vacantes WHERE 1=1";
    $params = [];
    $types = '';
    if ($search !== '') {
        $query .= " AND (empresa LIKE ? OR remuneracion LIKE ? OR puesto LIKE ? OR descripcion LIKE ?)";
        $like = "%" . $search . "%";
        $params = array_merge($params, [$like, $like, $like, $like]);
        $types .= 'ssss';
    }
    if ($filterMarca !== '') {
        $query .= " AND remuneracion = ?";
        $params[] = $filterMarca;
        $types .= 's';
    }
    $query .= " ORDER BY fecha_publicacion DESC";

    $stmt = $conn->prepare($query);
    if ($stmt) {
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $res = $stmt->get_result();
    } else {
        $res = $conn->query($query);
    }
}
?>

<form method="GET" style="margin-bottom:18px;display:flex;gap:8px;align-items:center;">
    <input type="text" name="search" placeholder="Buscar por nombre o marca" value="<?= htmlspecialchars($search) ?>" style="padding:12px;font-size:16px;width:400px;border-radius:6px;border:1px solid #ccc;" />
    <select name="marca" style="padding:12px;font-size:16px;border-radius:6px;border:1px solid #ccc;">
        <option value="">Todas las marcas</option>
        <?php foreach ($marcas as $m): ?>
            <option value="<?= htmlspecialchars($m) ?>" <?= $filterMarca === $m ? 'selected' : '' ?>><?= htmlspecialchars($m) ?></option>
        <?php endforeach; ?>
    </select>
    <button type="submit" style="padding:12px 16px;font-size:16px;border-radius:6px;background:#0d47a1;color:#fff;border:none;">Buscar</button>
</form>

<div class="contenedor">
    <?php
    // preparar consulta con filtros
    $query = "SELECT * FROM vacantes WHERE 1=1";
    $params = [];
    $types = '';
    if ($search !== '') {
        $query .= " AND (empresa LIKE ? OR remuneracion LIKE ? OR puesto LIKE ? OR descripcion LIKE ?)";
        $like = "%" . $search . "%";
        $params = array_merge($params, [$like, $like, $like, $like]);
        $types .= 'ssss';
    }
    if ($filterMarca !== '') {
        $query .= " AND remuneracion = ?";
        $params[] = $filterMarca;
        $types .= 's';
    }
    $query .= " ORDER BY fecha_publicacion DESC";

    $stmt = $conn->prepare($query);
    if ($stmt) {
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $res = $stmt->get_result();
    } else {
        $res = $conn->query($query);
    }

    if ($res) {
        while ($vacante = $res->fetch_assoc()) {
            // obtener imágenes
            $images = [];
            if ($stmtImg = $conn->prepare("SELECT archivo FROM imagenes WHERE vacante_id = ?")) {
                $stmtImg->bind_param("i", $vacante['id']);
                $stmtImg->execute();
                $resImg = $stmtImg->get_result();
                while ($ri = $resImg->fetch_assoc()) {
                    $images[] = $ri['archivo'];
                }
                $stmtImg->close();
            }

            echo '<div class="vacante">';
            if (!empty($images)) {
                echo '<div class="carousel">';
                echo '<div class="carousel-images" id="carousel-' . $vacante['id'] . '">';
                foreach ($images as $index => $img) {
                    $active = $index == 0 ? 'active' : '';
                    echo '<img src="uploads/tenis/' . htmlspecialchars($img) . '" class="' . $active . '" style="max-width:180px;border-radius:6px;" />';
                }
                echo '</div>';
                if (count($images) > 1) {
                    echo '<button class="carousel-btn prev" onclick="prevImage(' . $vacante['id'] . ')">&#10094;</button>';
                    echo '<button class="carousel-btn next" onclick="nextImage(' . $vacante['id'] . ')">&#10095;</button>';
                }
                echo '</div>';
            }
            $precio = floatval($vacante['puesto']);
            echo '<h2>' . htmlspecialchars($vacante['empresa']) . '</h2>'; // nombre
            echo '<p><strong>Precio:</strong> $' . number_format($precio,2) . '</p>';
            echo '<p><strong>Marca:</strong> ' . htmlspecialchars($vacante['remuneracion']) . '</p>';
            echo '<p>' . nl2br(htmlspecialchars($vacante['descripcion'])) . '</p>';
            // acciones: ver y agregar al carrito
            echo '<div style="margin-top:8px;">';
            echo '<a href="producto.php?id=' . (int)$vacante['id'] . '" style="margin-right:10px;color:#0d47a1;font-weight:bold;">Ver</a>';
            echo '<form method="POST" action="producto.php?id=' . (int)$vacante['id'] . '" style="display:inline-block;">
                    <input type="hidden" name="action" value="add">
                    <input type="hidden" name="qty" value="1">
                    <button type="submit" style="background:#0d47a1;color:#fff;border:none;padding:8px 12px;border-radius:6px;">Agregar al carrito</button>
                  </form>';
            echo '</div>';

            echo '</div>';
        }
        if ($stmt) $stmt->close();
    } else {
        echo "Error en la consulta: " . $conn->error;
    }
    ?>
</div>

<script>
function nextImage(id) {
    const carousel = document.getElementById('carousel-' + id);
    const images = carousel.querySelectorAll('img');
    let activeIndex = Array.from(images).findIndex(img => img.classList.contains('active'));
    images[activeIndex].classList.remove('active');
    activeIndex = (activeIndex + 1) % images.length;
    images[activeIndex].classList.add('active');
}

function prevImage(id) {
    const carousel = document.getElementById('carousel-' + id);
    const images = carousel.querySelectorAll('img');
    let activeIndex = Array.from(images).findIndex(img => img.classList.contains('active'));
    images[activeIndex].classList.remove('active');
    activeIndex = activeIndex === 0 ? images.length - 1 : activeIndex - 1;
    images[activeIndex].classList.add('active');
}
</script>

</body>
</html>

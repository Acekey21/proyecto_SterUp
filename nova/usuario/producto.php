<?php
session_start();
require_once '../includes/conexion.php';

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($id <= 0) {
    header('Location: panel.php');
    exit();
}

// obtener producto desde productos (tenis.sql), fallback a vacantes
$stmt = $conn->prepare("SELECT id, nombre AS empresa, precio AS puesto, descripcion, marca AS remuneracion FROM productos WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$res = $stmt->get_result();
$prod = $res->fetch_assoc();
$stmt->close();

if (!$prod) {
    $stmt = $conn->prepare("SELECT id, empresa, puesto, descripcion, remuneracion FROM vacantes WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $res = $stmt->get_result();
    $prod = $res->fetch_assoc();
    $stmt->close();
}

if (!$prod) {
    header('Location: panel.php');
    exit();
}

// obtener imágenes old-style (vacantes) + fallback imagen en productos
$images = [];
$stmtImg = $conn->prepare("SELECT archivo FROM imagenes WHERE vacante_id = ?");
if ($stmtImg) {
    $stmtImg->bind_param("i", $id);
    $stmtImg->execute();
    $r = $stmtImg->get_result();
    while ($row = $r->fetch_assoc()) $images[] = $row['archivo'];
    $stmtImg->close();
}

if (empty($images) && !empty($prod['imagen'])) {
    $images[] = $prod['imagen'];
}

// agregar al carrito
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
    $qty = max(1, intval($_POST['qty'] ?? 1));
    if (!isset($_SESSION['cart'])) $_SESSION['cart'] = [];

    // Obtener precio numérico y fallback si no se encuentra
    $price = floatval($prod['puesto']);
    if ($price <= 0) {
        $price = floatval($prod['remuneracion']);
    }

    if ($price <= 0) {
        $price = 0.00;
    }

    if (!isset($_SESSION['cart'][$id])) {
        // Nuevo producto
        $_SESSION['cart'][$id] = [
            'qty' => $qty,
            'name' => $prod['empresa'],
            'price' => $price
        ];
    } else {
        // Producto ya existe, sumar cantidad
        $_SESSION['cart'][$id]['qty'] += $qty;
    }
    header('Location: mis_postulaciones.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title><?= htmlspecialchars($prod['empresa']) ?> - Detalle</title>
    <link rel="stylesheet" href="../Estilos/usuarios.css">
    <meta name="viewport" content="width=device-width,initial-scale=1">
</head>
<body>
<div class="top-nav">
    <a href="panel.php" style="color:#fff;text-decoration:none;">← Volver a tienda</a>
    &nbsp;|&nbsp;
    <a href="mis_postulaciones.php" style="color:#fff;text-decoration:none;">🛒 Mi Carrito (<?= array_sum(array_column($_SESSION['cart'] ?? [], 'qty')) ?: 0 ?>)</a>
</div>

<div style="max-width:980px;margin:30px auto;padding:20px;background:#fff;border-radius:10px;">
    <div style="display:flex;gap:24px;flex-wrap:wrap;align-items:flex-start;">
        <div style="flex:1 1 420px;">
            <div style="text-align:center;margin-bottom:12px;">
                <img id="mainImg" src="<?= !empty($images) ? 'uploads/tenis/' . htmlspecialchars($images[0]) : '../Estilos/no-image.png' ?>" style="max-width:540px;width:100%;border-radius:10px;box-shadow:0 8px 24px rgba(0,0,0,0.12);" />
            </div>
            <?php if (!empty($images)): ?>
                <div class="galeria" style="justify-content:center;">
                    <?php foreach ($images as $im): ?>
                        <img src="uploads/tenis/<?= htmlspecialchars($im) ?>" class="thumb-prod" data-src="uploads/tenis/<?= htmlspecialchars($im) ?>" style="cursor:pointer;" />
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        <div style="flex:1 1 300px;">
            <?php
                $displayPrice = floatval($prod['puesto']);
                if ($displayPrice <= 0) {
                    $displayPrice = floatval($prod['remuneracion']);
                }
            ?>
            <h1 style="color:#0d47a1;"><?= htmlspecialchars($prod['empresa']) ?></h1>
            <h3>Precio: $<?= number_format($displayPrice, 2) ?></h3>
            <p><strong>Marca:</strong> <?= htmlspecialchars($prod['remuneracion']) ?></p>
            <p><?= nl2br(htmlspecialchars($prod['descripcion'])) ?></p>

            <form method="POST" style="margin-top:16px;">
                <input type="hidden" name="action" value="add">
                <label>Cantidad</label>
                <input type="number" name="qty" value="1" min="1" style="width:80px;margin-left:8px;">
                <button type="submit" style="margin-left:12px;padding:10px 18px;border-radius:8px;background:#0d47a1;color:#fff;border:none;">Agregar al carrito</button>
            </form>
        </div>
    </div>
</div>

<script>
document.querySelectorAll('.thumb-prod').forEach(function(t){
    t.addEventListener('click', function(){
        document.getElementById('mainImg').src = this.dataset.src;
    });
});
</script>
</body>
</html>
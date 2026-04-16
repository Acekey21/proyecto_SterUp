<?php
session_start();
require_once '../includes/conexion.php';

if (!isset($_SESSION['id'])) {
    header("Location: ../login.php");
    exit();
}

if (!isset($_SESSION['cart'])) $_SESSION['cart'] = [];

// Acciones: eliminar item, actualizar cantidad, vaciar carrito
if (isset($_GET['action'])) {
    $action = $_GET['action'];
    
    if ($action === 'remove' && isset($_GET['id'])) {
        $id = intval($_GET['id']);
        unset($_SESSION['cart'][$id]);
        header('Location: mis_postulaciones.php');
        exit();
    }
    
    if ($action === 'clear') {
        $_SESSION['cart'] = [];
        header('Location: mis_postulaciones.php');
        exit();
    }
}

// Actualizar cantidad si es POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_qty'])) {
    $id = intval($_POST['product_id']);
    $new_qty = max(1, intval($_POST['quantity']));
    
    if (isset($_SESSION['cart'][$id])) {
        $_SESSION['cart'][$id]['qty'] = $new_qty;
    }
    header('Location: mis_postulaciones.php');
    exit();
}

// Calcular total
$total = 0;
$cantidad_items = 0;
foreach ($_SESSION['cart'] as $item) {
    $total += floatval($item['price']) * intval($item['qty']);
    $cantidad_items += intval($item['qty']);
}

$cantidad_productos = count($_SESSION['cart']);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mi Carrito</title>
    <link rel="stylesheet" href="../Estilos/mis_postulaciones.css">
</head>
<body>
    <!-- Header -->
    <header class="header-top">
        <div class="container">
            <h1 class="logo">🛒 Mi Carrito</h1>
            <a href="panel.php" class="btn-back">← Volver a la Tienda</a>
        </div>
    </header>

    <!-- Main Content -->
    <main class="container">
        <?php if (empty($_SESSION['cart'])): ?>
            <!-- Carrito Vacío -->
            <div class="empty-cart">
                <div class="empty-icon">📦</div>
                <h2>¡Tu carrito está vacío!</h2>
                <p>Explora nuestros mejores tenis y agrega algunos a tu carrito</p>
                <a href="panel.php" class="btn-primary">Ir a la Tienda</a>
            </div>
        <?php else: ?>
            <!-- Carrito con Productos -->
            <div class="cart-grid">
                <!-- Tabla de Productos -->
                <div class="cart-items">
                    <h2 class="section-title">🛍️ Tus Productos</h2>
                    <div class="items-grid">
                        <?php foreach ($_SESSION['cart'] as $product_id => $item): ?>
                            <?php
                                $name = htmlspecialchars($item['name']);
                                $price = floatval($item['price']);
                                $qty = intval($item['qty']);
                                $subtotal = $price * $qty;
                            ?>
                            <div class="cart-product-card">
                                <div class="card-image-container">
                                    <img src="uploads/tenis/default.png" alt="<?= $name ?>" class="card-image">
                                </div>
                                <div class="card-info">
                                    <h3 class="card-title"><?= $name ?></h3>
                                    <p class="card-price">$<?= number_format($price, 2) ?></p>
                                </div>
                                <div class="card-controls">
                                    <form method="POST" class="qty-form">
                                        <input type="hidden" name="product_id" value="<?= $product_id ?>">
                                        <input type="hidden" name="update_qty" value="1">
                                        <label>Cantidad:</label>
                                        <div class="qty-control">
                                            <button type="button" class="qty-btn" onclick="decrementarQty(this)">−</button>
                                            <input type="number" name="quantity" value="<?= $qty ?>" min="1" onchange="this.form.submit()">
                                            <button type="button" class="qty-btn" onclick="incrementarQty(this)">+</button>
                                        </div>
                                    </form>
                                </div>
                                <div class="card-subtotal">
                                    <span class="subtotal-label">Subtotal:</span>
                                    <span class="subtotal-price">$<?= number_format($subtotal, 2) ?></span>
                                </div>
                                <a href="mis_postulaciones.php?action=remove&id=<?= $product_id ?>" class="btn-remove-card" onclick="return confirm('¿Eliminar este producto?');">🗑️ Eliminar</a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <a href="mis_postulaciones.php?action=clear" class="btn-clear" onclick="return confirm('¿Vaciar todo el carrito?');">Vaciar Carrito</a>
                </div>

                <!-- Resumen de Compra -->
                <div class="cart-summary">
                    <h2 class="section-title">📋 Resumen</h2>
                    <div class="summary-box">
                        <div class="summary-row">
                            <span>Productos:</span>
                            <span class="summary-value"><?= $cantidad_productos ?></span>
                        </div>
                        <div class="summary-row">
                            <span>Items:</span>
                            <span class="summary-value"><?= $cantidad_items ?></span>
                        </div>
                        <div class="summary-divider"></div>
                        <div class="summary-row">
                            <span>Subtotal:</span>
                            <span class="summary-value">$<?= number_format($total, 2) ?></span>
                        </div>
                        <div class="summary-row">
                            <span>Envío:</span>
                            <span class="summary-value free">Gratis</span>
                        </div>
                        <div class="summary-divider"></div>
                        <div class="summary-row total-row">
                            <span>Total:</span>
                            <span class="total-amount">$<?= number_format($total, 2) ?></span>
                        </div>
                        <form method="POST" action="checkout.php">
                            <button type="submit" class="btn-checkout">💳 Proceder al Pago</button>
                        </form>
                        <a href="panel.php" class="btn-continue">Seguir Comprando</a>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </main>

    <script>
        function incrementarQty(btn) {
            const input = btn.parentElement.querySelector('input[type="number"]');
            input.value = parseInt(input.value) + 1;
            btn.closest('form').submit();
        }

        function decrementarQty(btn) {
            const input = btn.parentElement.querySelector('input[type="number"]');
            if (parseInt(input.value) > 1) {
                input.value = parseInt(input.value) - 1;
                btn.closest('form').submit();
            }
        }
    </script>
</body>
</html>

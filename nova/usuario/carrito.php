<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Mi Carrito</title>
    <link rel="stylesheet" href="../Estilos/carrito.css">
</head>
<body>

<header class="cart-header">
    <div class="container">
        <h1> Mi Carrito</h1>
        <a href="panel.php" class="btn-secondary">← Seguir comprando</a>
    </div>
</header>

<div class="container cart-container">

<?php if (empty($_SESSION['cart'])): ?>

    <div class="empty-cart">
        <h2>Tu carrito está vacío</h2>
        <p>Agrega productos para comenzar tu compra.</p>
        <a href="panel.php" class="btn-primary">Ir a la tienda</a>
    </div>

<?php else: ?>

    <div class="cart-grid">

        <div class="cart-products">
            <table>
                <thead>
                    <tr>
                        <th>Producto</th>
                        <th>Cantidad</th>
                        <th>Precio</th>
                        <th>Subtotal</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($_SESSION['cart'] as $pid => $it): ?>
                        <tr>
                            <td class="product-name">
                                <?= htmlspecialchars($it['name']) ?>
                            </td>
                            <td><?= (int)$it['qty'] ?></td>
                            <td>$<?= number_format($it['price'],2) ?></td>
                            <td class="subtotal">
                                $<?= number_format(floatval($it['price']) * intval($it['qty']), 2) ?>
                            </td>
                            <td>
                                <a href="carrito.php?action=remove&id=<?= (int)$pid ?>" 
                                   class="btn-remove">Eliminar</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="cart-summary">
            <h3>Resumen</h3>

            <div class="summary-row">
                <span>Subtotal</span>
                <span>$<?= number_format($total,2) ?></span>
            </div>

            <div class="summary-row">
                <span>Envío</span>
                <span class="free">Gratis</span>
            </div>

            <div class="summary-total">
                <span>Total</span>
                <span>$<?= number_format($total,2) ?></span>
            </div>

            <button class="btn-primary btn-block" onclick="window.location.href='checkout.php'">Proceder al pago</button>

            <a href="carrito.php?action=clear" class="btn-clear">
                Vaciar carrito
            </a>
        </div>

    </div>

<?php endif; ?>

</div>
</body>
</html>
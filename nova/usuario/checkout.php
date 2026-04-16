<?php
session_start();
require_once '../includes/conexion.php';

if (!isset($_SESSION['id']) || empty($_SESSION['cart'])) {
    header("Location: mis_postulaciones.php");
    exit();
}

$user_id = $_SESSION['id'];
$user_email = $_SESSION['correo'] ?? '';

// Calcular total
$total = 0;
$items = [];
foreach ($_SESSION['cart'] as $product_id => $item) {
    $subtotal = floatval($item['price']) * intval($item['qty']);
    $total += $subtotal;
    $items[] = [
        'name' => $item['name'],
        'qty' => $item['qty'],
        'price' => $item['price'],
        'subtotal' => $subtotal
    ];
}

$cantidad_items = array_sum(array_column($_SESSION['cart'], 'qty'));

// Procesamiento del pago
$error = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $payment_method = $_POST['payment_method'] ?? '';
    $full_name = trim($_POST['full_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $postal = trim($_POST['postal'] ?? '');
    
    // Validaciones
    if (!$full_name || !$phone || !$address || !$city || !$postal) {
        $error = 'Por favor completa todos los datos de envío.';
    } elseif (!$payment_method) {
        $error = 'Selecciona un método de pago.';
    } else {
        // Aquí iría la lógica del pago según el método seleccionado
        // Por defecto simulamos que el pago fue exitoso
        
        // Guardar la orden en BD (opcional)
        $order_data = json_encode($items);
        $order_date = date('Y-m-d H:i:s');
        $payment_status = 'completed'; // En producción validarías con la pasarela
        
        // Limpiar el carrito después del pago exitoso
        $_SESSION['cart'] = [];
        
        // Mostrar éxito (más adelante guardarías en BD)
        $success = true;
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pago Seguro - StepUp</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f8f9fa;
            color: #333;
        }

        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px 0;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
            margin-bottom: 30px;
        }

        .header .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header h1 {
            font-size: 1.8em;
            margin: 0;
        }

        .header a {
            padding: 10px 20px;
            background: rgba(255, 255, 255, 0.2);
            color: white;
            text-decoration: none;
            border-radius: 6px;
            transition: all 0.3s;
            border: 2px solid rgba(255, 255, 255, 0.3);
        }

        .header a:hover {
            background: rgba(255, 255, 255, 0.3);
        }

        .container {
            max-width: 1000px;
            margin: 0 auto;
            padding: 0 20px;
        }

        .checkout-grid {
            display: grid;
            grid-template-columns: 1fr 380px;
            gap: 30px;
            margin-bottom: 40px;
        }

        /* FORMULARIO */
        .checkout-form {
            background: white;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        }

        .form-section {
            margin-bottom: 30px;
        }

        .form-section h3 {
            font-size: 1.2em;
            margin-bottom: 16px;
            color: #333;
            border-bottom: 2px solid #f0f0f0;
            padding-bottom: 10px;
        }

        .form-group {
            margin-bottom: 16px;
        }

        .form-group label {
            display: block;
            margin-bottom: 6px;
            font-weight: 600;
            color: #555;
            font-size: 0.95em;
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 12px;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 1em;
            font-family: inherit;
            transition: border-color 0.3s;
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #667eea;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }

        .form-row .form-group {
            margin-bottom: 0;
        }

        /* MÉTODOS DE PAGO */
        .payment-methods {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }

        .payment-option {
            position: relative;
        }

        .payment-option input[type="radio"] {
            display: none;
        }

        .payment-label {
            display: block;
            padding: 16px;
            border: 2px solid #ddd;
            border-radius: 8px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
            background: white;
        }

        .payment-option input[type="radio"]:checked + .payment-label {
            border-color: #667eea;
            background: #f5f7ff;
        }

        .payment-label .icon {
            font-size: 1.8em;
            margin-bottom: 6px;
        }

        .payment-label .name {
            font-weight: 600;
            color: #333;
            font-size: 0.95em;
        }

        /* RESUMEN */
        .order-summary {
            background: white;
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
            position: sticky;
            top: 20px;
            height: fit-content;
        }

        .order-summary h3 {
            font-size: 1.1em;
            margin-bottom: 16px;
            color: #333;
            border-bottom: 2px solid #f0f0f0;
            padding-bottom: 10px;
        }

        .order-item {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            font-size: 0.9em;
            color: #666;
            border-bottom: 1px solid #f5f5f5;
        }

        .order-item .name {
            flex: 1;
        }

        .order-item .qty {
            margin: 0 12px;
            font-weight: 600;
        }

        .order-item .price {
            font-weight: 600;
            color: #333;
        }

        .order-divider {
            height: 1px;
            background: #f0f0f0;
            margin: 12px 0;
        }

        .order-total {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 16px;
            border-radius: 8px;
            margin-top: 12px;
            font-weight: 700;
        }

        .order-total .amount {
            font-size: 1.3em;
        }

        /* BOTONES */
        .btn-submit {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 1em;
            cursor: pointer;
            transition: all 0.3s;
            margin-top: 16px;
        }

        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.4);
        }

        .btn-back {
            display: inline-block;
            padding: 10px 20px;
            background: white;
            color: #667eea;
            border: 2px solid #667eea;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s;
        }

        .btn-back:hover {
            background: #f5f7ff;
        }

        /* ALERTAS */
        .alert {
            padding: 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-weight: 600;
        }

        .alert-error {
            background: #ffe8e8;
            color: #d32f2f;
            border-left: 4px solid #d32f2f;
        }

        .alert-success {
            background: #e8f5e9;
            color: #27ae60;
            border-left: 4px solid #27ae60;
        }

        /* MODAL ÉXITO */
        .success-modal {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1000;
        }

        .modal-content {
            background: white;
            border-radius: 16px;
            padding: 40px;
            text-align: center;
            max-width: 450px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            animation: slideUp 0.4s ease;
        }

        @keyframes slideUp {
            from {
                transform: translateY(40px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .modal-icon {
            font-size: 3em;
            margin-bottom: 16px;
        }

        .modal-content h2 {
            font-size: 1.6em;
            color: #27ae60;
            margin-bottom: 12px;
        }

        .modal-content p {
            color: #666;
            margin-bottom: 20px;
            line-height: 1.6;
        }

        .modal-buttons {
            display: flex;
            gap: 12px;
            justify-content: center;
        }

        .modal-buttons a {
            padding: 12px 28px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s;
        }

        .modal-buttons .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .modal-buttons .btn-primary:hover {
            transform: translateY(-2px);
        }

        .modal-buttons .btn-secondary {
            background: #f0f0f0;
            color: #667eea;
            border: 2px solid #667eea;
        }

        .modal-buttons .btn-secondary:hover {
            background: #f5f7ff;
        }

        /* RESPONSIVE */
        @media (max-width: 768px) {
            .checkout-grid {
                grid-template-columns: 1fr;
                gap: 20px;
            }

            .order-summary {
                position: static;
            }

            .payment-methods {
                grid-template-columns: 1fr;
            }

            .form-row {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 480px) {
            .checkout-form {
                padding: 20px;
            }

            .order-summary {
                padding: 20px;
            }

            .modal-content {
                padding: 24px;
                margin: 20px;
            }

            .modal-content h2 {
                font-size: 1.3em;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="container">
            <h1>💳 Pago Seguro</h1>
            <a href="mis_postulaciones.php">← Volver al Carrito</a>
        </div>
    </header>

    <!-- Main Content -->
    <main class="container">
        <?php if ($success): ?>
            <!-- Modal de Éxito -->
            <div class="success-modal">
                <div class="modal-content">
                    <div class="modal-icon">✅</div>
                    <h2>¡Compra Realizada!</h2>
                    <p>Tu pedido fue procesado exitosamente. Recibirás un email de confirmación en <strong><?= htmlspecialchars($user_email) ?></strong></p>
                    <p>Tu número de orden es: <strong>#<?= date('YmdHis') ?></strong></p>
                    <div class="modal-buttons">
                        <a href="panel.php" class="btn-primary">Ir a la Tienda</a>
                        <a href="mis_postulaciones.php" class="btn-secondary">Mi Carrito</a>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="checkout-grid">
                <!-- Formulario de Pago -->
                <div class="checkout-form">
                    <?php if ($error): ?>
                        <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
                    <?php endif; ?>

                    <form method="POST">
                        <!-- Datos de Envío -->
                        <div class="form-section">
                            <h3>📍 Datos de Envío</h3>

                            <div class="form-group">
                                <label>Nombre Completo</label>
                                <input type="text" name="full_name" required placeholder="Ej: Juan Pérez">
                            </div>

                            <div class="form-group">
                                <label>Teléfono</label>
                                <input type="tel" name="phone" required placeholder="+1 (123) 456-7890">
                            </div>

                            <div class="form-group">
                                <label>Dirección</label>
                                <input type="text" name="address" required placeholder="Calle, número, apartamento">
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label>Ciudad</label>
                                    <input type="text" name="city" required placeholder="Ciudad">
                                </div>
                                <div class="form-group">
                                    <label>Código Postal</label>
                                    <input type="text" name="postal" required placeholder="12345">
                                </div>
                            </div>
                        </div>

                        <!-- Métodos de Pago -->
                        <div class="form-section">
                            <h3>💳 Método de Pago</h3>

                            <div class="payment-methods">
                                <div class="payment-option">
                                    <input type="radio" id="card" name="payment_method" value="card" required>
                                    <label for="card" class="payment-label">
                                        <div class="icon">💳</div>
                                        <div class="name">Tarjeta de Crédito</div>
                                    </label>
                                </div>

                                <div class="payment-option">
                                    <input type="radio" id="paypal" name="payment_method" value="paypal" required>
                                    <label for="paypal" class="payment-label">
                                        <div class="icon">🅿️</div>
                                        <div class="name">PayPal</div>
                                    </label>
                                </div>

                                <div class="payment-option">
                                    <input type="radio" id="transfer" name="payment_method" value="transfer" required>
                                    <label for="transfer" class="payment-label">
                                        <div class="icon">🏦</div>
                                        <div class="name">Transferencia</div>
                                    </label>
                                </div>

                                <div class="payment-option">
                                    <input type="radio" id="cash" name="payment_method" value="cash" required>
                                    <label for="cash" class="payment-label">
                                        <div class="icon">💵</div>
                                        <div class="name">Contra Entrega</div>
                                    </label>
                                </div>
                            </div>

                            <!-- Campos específicos por método de pago -->
                            <div id="card-fields" class="payment-fields" style="display: none;">
                                <h4 style="margin-top: 20px; margin-bottom: 15px; color: #333;">Datos de la Tarjeta</h4>
                                <div class="form-group">
                                    <label>Número de Tarjeta</label>
                                    <input type="text" name="card_number" placeholder="1234 5678 9012 3456" maxlength="19">
                                </div>
                                <div class="form-row">
                                    <div class="form-group">
                                        <label>Fecha de Expiración</label>
                                        <input type="text" name="card_expiry" placeholder="MM/YY" maxlength="5">
                                    </div>
                                    <div class="form-group">
                                        <label>CVV</label>
                                        <input type="text" name="card_cvv" placeholder="123" maxlength="4">
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label>Nombre en la Tarjeta</label>
                                    <input type="text" name="card_name" placeholder="Como aparece en la tarjeta">
                                </div>
                            </div>

                            <div id="paypal-fields" class="payment-fields" style="display: none;">
                                <h4 style="margin-top: 20px; margin-bottom: 15px; color: #333;">Datos de PayPal</h4>
                                <div class="form-group">
                                    <label>Email de PayPal</label>
                                    <input type="email" name="paypal_email" placeholder="tu@email.com">
                                </div>
                                <p style="font-size: 0.9em; color: #666; margin-top: 10px;">
                                    Serás redirigido a PayPal para completar el pago de forma segura.
                                </p>
                            </div>

                            <div id="transfer-fields" class="payment-fields" style="display: none;">
                                <h4 style="margin-top: 20px; margin-bottom: 15px; color: #333;">Datos para Transferencia</h4>
                                <div style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin-bottom: 15px;">
                                    <p><strong>Banco:</strong> Banco Nacional</p>
                                    <p><strong>Cuenta:</strong> 1234567890</p>
                                    <p><strong>Titular:</strong> StepUp Store</p>
                                    <p><strong>CLABE:</strong> 012345678901234567</p>
                                </div>
                                <div class="form-group">
                                    <label>Referencia de Pago</label>
                                    <input type="text" name="transfer_ref" placeholder="Número de referencia del banco">
                                </div>
                            </div>

                            <div id="cash-fields" class="payment-fields" style="display: none;">
                                <h4 style="margin-top: 20px; margin-bottom: 15px; color: #333;">Pago Contra Entrega</h4>
                                <p style="font-size: 0.9em; color: #666;">
                                    Paga en efectivo cuando recibas tu pedido. Se aceptan billetes de cualquier denominación.
                                </p>
                                <div class="form-group">
                                    <label>Monto Exacto a Preparar</label>
                                    <input type="text" name="cash_amount" value="$<?= number_format($total, 2) ?>" readonly style="background: #f5f5f5;">
                                </div>
                            </div>
                        </div>

                        <!-- Botón Enviar -->
                        <div style="margin-top: 24px;">
                            <a href="mis_postulaciones.php" class="btn-back">← Volver</a>
                            <button type="submit" class="btn-submit">Finalizar Compra - $<?= number_format($total, 2) ?></button>
                        </div>
                    </form>
                </div>

                <!-- Resumen de Orden -->
                <div class="order-summary">
                    <h3>📦 Resumen de Orden</h3>

                    <?php foreach ($items as $item): ?>
                        <div class="order-item">
                            <span class="name"><?= htmlspecialchars($item['name']) ?></span>
                            <span class="qty">×<?= $item['qty'] ?></span>
                            <span class="price">$<?= number_format($item['subtotal'], 2) ?></span>
                        </div>
                    <?php endforeach; ?>

                    <div class="order-divider"></div>

                    <div class="order-item">
                        <span>Subtotal (<?= $cantidad_items ?> items)</span>
                        <span style="font-weight: 700;">$<?= number_format($total, 2) ?></span>
                    </div>

                    <div class="order-item">
                        <span>Envío</span>
                        <span style="color: #27ae60; font-weight: 700;">Gratis</span>
                    </div>

                    <div class="order-total">
                        <span>Total a Pagar:</span>
                        <span class="amount">$<?= number_format($total, 2) ?></span>
                    </div>

                    <p style="font-size: 0.85em; color: #999; margin-top: 16px; text-align: center;">
                        ✅ Sitio seguro - Tus datos están protegidos
                    </p>
                </div>
            </div>
        <?php endif; ?>
    </main>

    <script>
        // Función para mostrar/ocultar campos de pago
        function togglePaymentFields() {
            const methods = ['card', 'paypal', 'transfer', 'cash'];
            const selected = document.querySelector('input[name="payment_method"]:checked');
            
            // Ocultar todos los campos
            methods.forEach(method => {
                const fields = document.getElementById(method + '-fields');
                if (fields) fields.style.display = 'none';
            });
            
            // Mostrar campos del método seleccionado
            if (selected) {
                const fields = document.getElementById(selected.value + '-fields');
                if (fields) fields.style.display = 'block';
            }
        }
        
        // Agregar event listeners a los radios
        document.querySelectorAll('input[name="payment_method"]').forEach(radio => {
            radio.addEventListener('change', togglePaymentFields);
        });
        
        // Formatear número de tarjeta
        document.querySelector('input[name="card_number"]')?.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\s+/g, '').replace(/[^0-9]/gi, '');
            let formatted = value.match(/.{1,4}/g)?.join(' ') || '';
            e.target.value = formatted;
        });
        
        // Formatear fecha de expiración
        document.querySelector('input[name="card_expiry"]')?.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length >= 2) {
                value = value.substring(0, 2) + '/' + value.substring(2, 4);
            }
            e.target.value = value;
        });
    </script>
</body>
</html>

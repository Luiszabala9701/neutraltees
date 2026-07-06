<?php

/**
 * Modulo: carrito de compras.
 * Responsabilidad: administrar cantidades, eliminaciones, cupones y subtotal.
 */

require_once __DIR__ . '/config/conexion_DB.php';
$conexion = obtener_conexion_db();

if (es_post()) {
    $accion = trim(strip_tags((string) ($_POST['accion'] ?? '')));
    $idVariante = (int) ($_POST['id_variante'] ?? 0);
    $cantidad = (int) ($_POST['cantidad'] ?? 1);
    $variante = $idVariante > 0 ? obtener_variante_por_id($conexion, $idVariante) : null;

    if (($accion === 'agregar' || $accion === 'actualizar') && (!$variante || $variante['estado'] !== 'activo' || (string) ($variante['estado_producto'] ?? '') !== 'disponible' || (int) $variante['stock'] <= 0)) {
        guardar_flash('mensaje_error', 'La variante seleccionada no tiene stock disponible.');
        redirigir('/carrito.php');
    }

    if ($accion === 'agregar' && $variante) {
        $cantidadDisponible = obtener_stock_disponible_variante($conexion, $idVariante);

        if ($cantidadDisponible <= 0) {
            if (!empty($_POST['origen']) && $_POST['origen'] === 'ajax') {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode([
                    'ok' => false,
                    'mensaje' => 'Ya alcanzaste el stock disponible para esa variante.',
                    'cantidad_carrito' => obtener_cantidad_carrito(),
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }

            guardar_flash('mensaje_error', 'Ya alcanzaste el stock disponible para esa variante.');
            redirigir('/carrito.php');
        }

        $cantidad = min(max(1, $cantidad), $cantidadDisponible);
    }

    if ($accion === 'actualizar' && $variante) {
        $cantidadActual = (int) ($_SESSION['carrito'][$idVariante] ?? 0);
        $cantidadOtrasVariantesProducto = obtener_cantidad_producto_en_carrito($conexion, (int) $variante['id_producto'], $idVariante);
        $maximoPorProducto = max(0, limite_unidades_por_producto() - $cantidadOtrasVariantesProducto);
        $maximoPermitido = min((int) $variante['stock'], $maximoPorProducto);

        if ($cantidad === $cantidadActual) {
            redirigir('/carrito.php');
        }

        $cantidad = min(max(0, $cantidad), $maximoPermitido);
    }

    if ($accion === 'agregar' && $idVariante > 0) {
        $cantidad = max(1, $cantidad);
        agregar_al_carrito($idVariante, $cantidad);

        if (!empty($_POST['origen']) && $_POST['origen'] === 'ajax') {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'ok' => true,
                'mensaje' => 'Producto agregado al carrito.',
                'cantidad_carrito' => obtener_cantidad_carrito(),
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        guardar_flash('mensaje_exito', 'Producto agregado al carrito.');
        redirigir('/carrito.php');
    }

    if ($accion === 'actualizar' && $idVariante > 0) {
        if ($cantidad <= 0) {
            eliminar_del_carrito($idVariante);
            guardar_flash('mensaje_exito', 'Producto quitado del carrito.');
            redirigir('/carrito.php');
        }

        actualizar_carrito($idVariante, $cantidad);
        guardar_flash('mensaje_exito', 'Carrito actualizado.');
        redirigir('/carrito.php');
    }

    if ($accion === 'eliminar' && $idVariante > 0) {
        eliminar_del_carrito($idVariante);
        guardar_flash('mensaje_exito', 'Producto quitado del carrito.');
        redirigir('/carrito.php');
    }

    if ($accion === 'vaciar') {
        vaciar_carrito();
        guardar_flash('mensaje_exito', 'Carrito vaciado.');
        redirigir('/carrito.php');
    }
}

$detallesCarrito = obtener_detalles_carrito($conexion);
$totalCarrito = 0.0;

foreach ($detallesCarrito as $detalle) {
    $totalCarrito += (float) $detalle['subtotal'];
}

require_once __DIR__ . '/includes/cabecera_publica.php';
?>

<section>
    <h1 class="titulo-seccion">Carrito de compras</h1>

    <?php if ($detallesCarrito === []): ?>
        <div class="mensaje-vacio">Tu carrito está vacío.</div>
        <div class="carrito-vacio__contenedor">
            <a class="boton-principal" href="/index.php">Seguir comprando</a>
        </div>
    <?php else: ?>
        <table class="tabla-carrito">
            <thead>
                <tr>
                    <th>Producto</th>
                    <th>Talle</th>
                    <th>Cantidad</th>
                    <th>Precio</th>
                    <th>Subtotal</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($detallesCarrito as $detalle): ?>
                    <?php $imagenProducto = obtener_ruta_imagen_producto($detalle['imagen'] ?? null); ?>
                    <?php
                    $cantidadOtrasVariantesProducto = obtener_cantidad_producto_en_carrito($conexion, (int) $detalle['id_producto'], (int) $detalle['id_variante']);
                    $maximoPermitidoProducto = max(0, limite_unidades_por_producto() - $cantidadOtrasVariantesProducto);
                    $maximoCantidadLinea = min((int) $detalle['stock'], $maximoPermitidoProducto);
                    ?>
                    <tr>
                        <td>
                            <div class="tabla-carrito__producto">
                                <img class="tabla-carrito__imagen" src="<?php echo $imagenProducto; ?>" alt="<?php echo sanear_texto($detalle['nombre_producto']); ?>">
                                <span><?php echo sanear_texto($detalle['nombre_producto']); ?></span>
                            </div>
                        </td>
                        <td><?php echo sanear_texto($detalle['talle']); ?></td>
                        <td>
                            <div class="acciones-carrito__cantidad">
                                <form method="post" action="/carrito.php" class="acciones-carrito__formulario acciones-carrito__formulario--compacto">
                                    <input type="hidden" name="accion" value="actualizar">
                                    <input type="hidden" name="id_variante" value="<?php echo (int) $detalle['id_variante']; ?>">
                                    <input type="hidden" name="cantidad" value="<?php echo max(0, (int) $detalle['cantidad'] - 1); ?>">
                                    <button class="boton-terciario acciones-carrito__boton" type="submit" aria-label="Restar una unidad">−</button>
                                </form>

                                <span class="acciones-carrito__cantidad-valor" aria-label="Cantidad actual"><?php echo (int) $detalle['cantidad']; ?></span>

                                <form method="post" action="/carrito.php" class="acciones-carrito__formulario acciones-carrito__formulario--compacto">
                                    <input type="hidden" name="accion" value="actualizar">
                                    <input type="hidden" name="id_variante" value="<?php echo (int) $detalle['id_variante']; ?>">
                                    <input type="hidden" name="cantidad" value="<?php echo min($maximoCantidadLinea, (int) $detalle['cantidad'] + 1); ?>">
                                    <button class="boton-terciario acciones-carrito__boton" type="submit" aria-label="Agregar una unidad" <?php echo (int) $detalle['cantidad'] >= $maximoCantidadLinea ? 'disabled' : ''; ?>>+</button>
                                </form>
                            </div>
                        </td>
                        <td><?php echo formatear_precio((float) $detalle['precio']); ?></td>
                        <td><span class="tabla-carrito__subtotal"><?php echo formatear_precio((float) $detalle['subtotal']); ?></span></td>
                        <td>
                            <form method="post" action="/carrito.php" data-confirmar="¿Querés quitar este producto del carrito?">
                                <input type="hidden" name="accion" value="eliminar">
                                <input type="hidden" name="id_variante" value="<?php echo (int) $detalle['id_variante']; ?>">
                                <button class="boton-terciario" type="submit">Eliminar</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="resumen-carrito resumen-carrito--checkout">
            <div>
                <h2 class="resumen-carrito__titulo">Total a pagar</h2>
            </div>
            <div class="resumen-carrito__bloque">
                <div class="resumen-carrito__importe"><?php echo formatear_precio($totalCarrito); ?></div>
                <a class="boton-principal" href="/checkout.php">Finalizar compra</a>
            </div>
        </div>
    <?php endif; ?>
</section>

<?php require_once __DIR__ . '/includes/pie_publico.php'; ?>

<?php

/**
 * Modulo: movimiento de stock.
 * Responsabilidad: registrar ingresos y egresos sobre variantes existentes.
 */

require_once __DIR__ . '/../config/conexion_DB.php';
$conexion = obtener_conexion_db();
requiere_admin();

$errores = [];
$idProductoSeleccionado = 0;

if (es_post()) {
    $idProductoSeleccionado = (int) ($_POST['id_producto'] ?? 0);
    $idVariante = (int) ($_POST['id_variante'] ?? 0);
    $tipoMovimiento = limpiar_entrada((string) ($_POST['tipo_movimiento'] ?? ''));
    $cantidad = (int) ($_POST['cantidad'] ?? 0);
    $observacion = trim((string) ($_POST['observacion'] ?? ''));

    if ($idVariante <= 0) {
        $errores[] = 'Seleccioná una variante válida.';
    }

    if ($idProductoSeleccionado <= 0) {
        $errores[] = 'Seleccioná un producto válido.';
    }

    if ($idProductoSeleccionado > 0 && $idVariante > 0) {
        $varianteSeleccionada = obtener_variante_por_id($conexion, $idVariante);
        if (!$varianteSeleccionada || (int) $varianteSeleccionada['id_producto'] !== $idProductoSeleccionado) {
            $errores[] = 'La variante seleccionada no pertenece al producto elegido.';
        }
    }

    if (!in_array($tipoMovimiento, ['ingreso', 'egreso'], true)) {
        $errores[] = 'Seleccioná un tipo de movimiento válido.';
    }

    if ($cantidad <= 0) {
        $errores[] = 'La cantidad debe ser mayor a cero.';
    }

    if ($observacion === '') {
        $errores[] = 'La observación es obligatoria.';
    }

    if ($errores === []) {
        try {
            registrar_movimiento_stock($conexion, $idVariante, $tipoMovimiento, $cantidad, $observacion);
            guardar_flash('mensaje_exito', 'Movimiento de stock registrado.');
            // Volvemos al mismo formulario para poder registrar otro movimiento sin duplicar el anterior al refrescar.
            redirigir('/admin/movimientos_stock.php');
        } catch (Throwable $ex) {
            registrar_error_sistema('Error al registrar movimiento de stock', $ex->getMessage());
            $errores[] = $ex->getMessage();
        }
    }
}

// Lista de variantes activas para poder elegir cuál ajustar.
$variantes = obtener_variantes_con_producto($conexion);
$productosConVariantes = [];

foreach ($variantes as $variante) {
    $idProductoVariante = (int) $variante['id_producto'];
    if (!isset($productosConVariantes[$idProductoVariante])) {
        $productosConVariantes[$idProductoVariante] = [
            'id_producto' => $idProductoVariante,
            'nombre_producto' => (string) $variante['nombre_producto'],
        ];
    }
}

require_once __DIR__ . '/../includes/cabecera_admin.php';
?>

<header class="encabezado-panel">
    <h1>Movimiento de stock</h1>
    <p>Registrar ingresos y egresos de inventario</p>
</header>

<section class="panel-seccion">
    <?php foreach ($errores as $error): ?>
        <div class="contenido-vacio-admin mensaje-vacio--error u-mb-18 u-text-left"><?php echo sanear_texto($error); ?></div>
    <?php endforeach; ?>

    <div class="tarjeta-resumen tarjeta-resumen--detalle-pedido u-mb-18">
        <form class="formulario-stock" method="post">
            <div class="rejilla-campos">
                <div class="grupo-campo">
                    <label for="id_producto_stock">Producto</label>
                    <select class="campo-select" id="id_producto_stock" name="id_producto" required data-producto-movimiento-stock>
                        <option value="">Seleccionar producto</option>
                        <?php foreach ($productosConVariantes as $producto): ?>
                            <option value="<?php echo (int) $producto['id_producto']; ?>" <?php echo (int) $producto['id_producto'] === $idProductoSeleccionado ? 'selected' : ''; ?>>
                                <?php echo sanear_texto($producto['nombre_producto']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="grupo-campo">
                    <label for="id_variante">Variante</label>
                    <select class="campo-select" id="id_variante" name="id_variante" required data-variante-movimiento-stock>
                        <option value="">Seleccionar variante</option>
                        <?php foreach ($variantes as $variante): ?>
                            <option
                                value="<?php echo (int) $variante['id_variante']; ?>"
                                data-producto-id="<?php echo (int) $variante['id_producto']; ?>"
                                <?php echo (int) ($_POST['id_variante'] ?? 0) === (int) $variante['id_variante'] ? 'selected' : ''; ?>
                            >
                                <?php echo sanear_texto($variante['talle'] . ' (SKU: ' . ($variante['sku'] ?? 'N/A') . ')'); ?>
                                | Stock actual: <?php echo (int) $variante['stock']; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="rejilla-campos">
                <div class="grupo-campo">
                    <label for="tipo_movimiento">Tipo</label>
                    <select class="campo-select" id="tipo_movimiento" name="tipo_movimiento" required>
                        <option value="ingreso">Ingreso</option>
                        <option value="egreso">Egreso</option>
                    </select>
                </div>
            </div>

            <div class="rejilla-campos">
                <div class="grupo-campo">
                    <label for="cantidad">Cantidad</label>
                    <input class="campo-texto" id="cantidad" type="number" name="cantidad" min="1" required>
                </div>

                <div class="grupo-campo">
                    <label for="observacion">Observación</label>
                    <input class="campo-texto" id="observacion" type="text" name="observacion" required placeholder="Ej: Ajuste por mercadería recibida">
                </div>
            </div>

            <div class="acciones-fila acciones-fila--arriba">
                <button class="boton-principal boton-principal--verde" type="submit">Registrar movimiento</button>
            </div>
        </form>
    </div>
</section>

<?php require_once __DIR__ . '/../includes/pie_admin.php'; ?>

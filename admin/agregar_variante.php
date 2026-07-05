<?php

/**
 * Modulo: alta de variante.
 * Responsabilidad: agregar talle, stock inicial y SKU a un producto existente.
 */

require_once __DIR__ . '/../config/conexion_DB.php';
$conexion = obtener_conexion_db();
requiere_admin();

$errores = [];
$datosVariante = [
    'id_producto' => '',
    'talle' => '',
    'stock' => '',
    'sku' => '',
];

// Productos disponibles para que el administrador elija a cual sumar la variante.
$productos = obtener_productos($conexion, '', false);
$tallesPorProducto = [];
$sentenciaUltimoSku = $conexion->query(
    'SELECT sku
     FROM producto_variante
     WHERE sku IS NOT NULL
       AND sku <> ""
     ORDER BY fecha_creacion DESC, id_variante DESC
     LIMIT 1'
);
$ultimoSkuUsado = (string) ($sentenciaUltimoSku->fetchColumn() ?: 'No hay SKU cargados');

foreach ($productos as $producto) {
    $idProductoListado = (int) $producto['id_producto'];
    $variantesListado = obtener_variantes_producto($conexion, $idProductoListado);
    $variantesCreadasListado = array_filter($variantesListado, static function (array $variante): bool {
        return trim((string) ($variante['sku'] ?? '')) !== '';
    });
    $tallesPorProducto[$idProductoListado] = array_map(static function (array $variante): string {
        return (string) $variante['talle'];
    }, $variantesCreadasListado);
}

if (es_post()) {
    $datosVariante['id_producto'] = (string) ((int) ($_POST['id_producto'] ?? 0));
    $datosVariante['talle'] = strtoupper(limpiar_entrada((string) ($_POST['talle'] ?? '')));
    $datosVariante['stock'] = (string) ((int) ($_POST['stock'] ?? 0));
    $datosVariante['sku'] = limpiar_entrada((string) ($_POST['sku'] ?? ''));

    $idProducto = (int) $datosVariante['id_producto'];
    $talle = $datosVariante['talle'];
    $stock = (int) $datosVariante['stock'];
    $sku = $datosVariante['sku'];
    $tallesPermitidos = ['S', 'M', 'L', 'XL'];
    $varianteSinSkuParaCompletar = null;

    if ($idProducto <= 0 || !obtener_producto_por_id($conexion, $idProducto)) {
        $errores[] = 'Selecciona un producto valido.';
    }

    if (!in_array($talle, $tallesPermitidos, true)) {
        $errores[] = 'Selecciona un talle valido.';
    }

    if ($stock < 0) {
        $errores[] = 'El stock inicial no puede ser negativo.';
    }

    if ($sku === '') {
        $errores[] = 'El SKU es obligatorio para identificar la variante.';
    }

    if ($errores === []) {
        $variantesProducto = obtener_variantes_producto($conexion, $idProducto);

        foreach ($variantesProducto as $varianteExistente) {
            if ((string) $varianteExistente['talle'] !== $talle) {
                continue;
            }

            if (trim((string) ($varianteExistente['sku'] ?? '')) === '') {
                $varianteSinSkuParaCompletar = $varianteExistente;
                break;
            }

            $errores[] = 'Ese producto ya tiene una variante para el talle seleccionado.';
            break;
        }
    }

    if ($errores === [] && existe_sku_en_otro_producto($conexion, $sku, 0)) {
        $errores[] = 'Ese SKU ya esta usado en otro producto o variante.';
    }

    if ($errores === []) {
        try {
            $conexion->beginTransaction();
            $stockAnterior = $varianteSinSkuParaCompletar ? (int) $varianteSinSkuParaCompletar['stock'] : 0;

            if ($varianteSinSkuParaCompletar) {
                $idVarianteCreada = (int) $varianteSinSkuParaCompletar['id_variante'];
                $sentencia = $conexion->prepare(
                    'UPDATE producto_variante
                     SET stock = :stock,
                         sku = :sku,
                         estado = :estado,
                         fecha_actualizacion = NOW()
                     WHERE id_variante = :id_variante'
                );
                $sentencia->execute([
                    ':stock' => $stock,
                    ':sku' => $sku,
                    ':estado' => 'activo',
                    ':id_variante' => $idVarianteCreada,
                ]);
            } else {
                $sentencia = $conexion->prepare(
                    'INSERT INTO producto_variante
                        (id_producto, talle, stock, sku, estado, fecha_creacion, fecha_actualizacion)
                     VALUES
                        (:id_producto, :talle, :stock, :sku, :estado, NOW(), NOW())'
                );
                $sentencia->execute([
                    ':id_producto' => $idProducto,
                    ':talle' => $talle,
                    ':stock' => $stock,
                    ':sku' => $sku,
                    ':estado' => 'activo',
                ]);
                $idVarianteCreada = (int) $conexion->lastInsertId();
            }

            $sentenciaMovimiento = $conexion->prepare(
                'INSERT INTO movimiento_stock
                    (id_variante, tipo_movimiento, cantidad, stock_anterior, stock_resultante, observacion, fecha_movimiento)
                 VALUES
                    (:id_variante, :tipo_movimiento, :cantidad, :stock_anterior, :stock_resultante, :observacion, NOW())'
            );
            $sentenciaMovimiento->execute([
                ':id_variante' => $idVarianteCreada,
                ':tipo_movimiento' => 'ingreso',
                ':cantidad' => $stock,
                ':stock_anterior' => $stockAnterior,
                ':stock_resultante' => $stock,
                ':observacion' => 'Stock inicial al crear la variante.',
            ]);

            $conexion->commit();
            guardar_flash('mensaje_exito', 'Variante agregada correctamente.');
            redirigir('/admin/productos.php');
        } catch (Throwable $ex) {
            if ($conexion->inTransaction()) {
                $conexion->rollBack();
            }

            registrar_error_sistema('Error al agregar variante', $ex->getMessage());
            $errores[] = 'No se pudo agregar la variante.';
        }
    }
}

require_once __DIR__ . '/../includes/cabecera_admin.php';
?>

<header class="encabezado-panel">
    <h1>Agregar variante</h1>
    <p>Sumar talle, stock inicial y SKU a un producto existente</p>
</header>

<section class="panel-seccion">
    <?php foreach ($errores as $error): ?>
        <div class="contenido-vacio-admin mensaje-vacio--error u-mb-18 u-text-left"><?php echo sanear_texto($error); ?></div>
    <?php endforeach; ?>

    <div class="tarjeta-resumen tarjeta-resumen--detalle-pedido">
        <form class="formulario-variante" method="post">
            <div class="grupo-campo">
                <label for="id_producto">Producto</label>
                <select class="campo-select" id="id_producto" name="id_producto" required data-producto-para-variante>
                    <option value="">Seleccionar producto</option>
                    <?php foreach ($productos as $producto): ?>
                        <option
                            value="<?php echo (int) $producto['id_producto']; ?>"
                            data-talles-creados="<?php echo sanear_texto(implode(',', $tallesPorProducto[(int) $producto['id_producto']] ?? [])); ?>"
                            <?php echo (string) $producto['id_producto'] === $datosVariante['id_producto'] ? 'selected' : ''; ?>
                        >
                            <?php echo sanear_texto($producto['nombre_producto']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="rejilla-campos">
                <div class="grupo-campo">
                    <label for="talle">Talle</label>
                    <select class="campo-select" id="talle" name="talle" required data-talle-para-variante>
                        <option value="">Seleccionar talle</option>
                        <?php foreach (['S', 'M', 'L', 'XL'] as $tallePermitido): ?>
                            <?php
                            $idProductoSeleccionado = (int) $datosVariante['id_producto'];
                            $talleYaCreado = $idProductoSeleccionado > 0 && in_array($tallePermitido, $tallesPorProducto[$idProductoSeleccionado] ?? [], true);
                            ?>
                            <option value="<?php echo $tallePermitido; ?>" <?php echo $datosVariante['talle'] === $tallePermitido ? 'selected' : ''; ?> <?php echo $talleYaCreado ? 'disabled' : ''; ?>><?php echo $tallePermitido; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="grupo-campo">
                    <label for="stock">Stock inicial</label>
                    <input class="campo-texto" id="stock" type="number" name="stock" min="0" value="<?php echo sanear_texto($datosVariante['stock']); ?>" required>
                </div>

                <div class="grupo-campo">
                    <label for="sku">SKU</label>
                    <div class="campo-sku-ayuda">
                        <input class="campo-texto campo-sku-ayuda__input" id="sku" type="text" name="sku" value="<?php echo sanear_texto($datosVariante['sku']); ?>" data-campo-sku required>
                        <details class="campo-sku-ayuda__detalle">
                            <summary class="campo-sku-ayuda__boton" title="Ultimo SKU usado" aria-label="Mostrar ultimo SKU usado">!</summary>
                            <p class="campo-ayuda-sku">Ultimo SKU usado: <?php echo sanear_texto($ultimoSkuUsado); ?></p>
                        </details>
                    </div>
                    <p class="campo-error" data-error-sku hidden></p>
                </div>
            </div>

            <div class="acciones-fila acciones-fila--arriba">
                <button class="boton-principal boton-principal--verde" type="submit">Agregar variante</button>
                <a class="boton-secundario boton-secundario--gris" href="/admin/productos.php">Cancelar</a>
            </div>
        </form>
    </div>
</section>

<?php require_once __DIR__ . '/../includes/pie_admin.php'; ?>

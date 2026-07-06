<?php

/**
 * Modulo: detalle de producto admin.
 * Responsabilidad: mostrar datos del producto y stock de cada variante sin editar.
 */

require_once __DIR__ . '/../config/conexion_DB.php';
$conexion = obtener_conexion_db();
requiere_admin();

$idProducto = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($idProducto <= 0) {
    guardar_flash('mensaje_error', 'El producto solicitado no existe.');
    redirigir('/admin/productos.php');
}

$producto = obtener_producto_por_id($conexion, $idProducto);

if (!$producto) {
    guardar_flash('mensaje_error', 'El producto solicitado no existe.');
    redirigir('/admin/productos.php');
}

if (es_post()) {
    $accion = limpiar_entrada((string) ($_POST['accion'] ?? ''));
    $idVariante = (int) ($_POST['id_variante'] ?? 0);

    if ($idVariante > 0 && in_array($accion, ['dar_baja_variante', 'activar_variante'], true)) {
        $variante = obtener_variante_por_id($conexion, $idVariante);

        if (!$variante || (int) $variante['id_producto'] !== $idProducto) {
            guardar_flash('mensaje_error', 'La variante seleccionada no pertenece a este producto.');
            redirigir('/admin/producto_detalle.php?id=' . $idProducto);
        }

        $estadoVariante = $accion === 'activar_variante' ? 'activo' : 'inactivo';
        $sentenciaVariante = $conexion->prepare(
            'UPDATE producto_variante
             SET estado = :estado,
                 fecha_actualizacion = NOW()
             WHERE id_variante = :id_variante'
        );
        $sentenciaVariante->execute([
            ':estado' => $estadoVariante,
            ':id_variante' => $idVariante,
        ]);

        guardar_flash(
            'mensaje_exito',
            $accion === 'activar_variante'
                ? 'Variante activada correctamente.'
                : 'Variante dada de baja correctamente.'
        );
        redirigir('/admin/producto_detalle.php?id=' . $idProducto);
    }
}

$variantes = obtener_variantes_producto($conexion, $idProducto);
$imagen = obtener_ruta_imagen_producto($producto['imagen'] ?? null);
$estadoProducto = (string) ($producto['estado'] ?? '') === 'disponible' ? 'Activo' : 'Inactivo';
$esOferta = (int) ($producto['oferta'] ?? 0) === 1;
$stockTotal = array_sum(array_map(static fn (array $variante): int => (int) $variante['stock'], $variantes));

require_once __DIR__ . '/../includes/cabecera_admin.php';
?>

<header class="encabezado-panel">
    <h1>Detalle de producto</h1>
    <p>Variantes, stock y datos principales del producto.</p>
</header>

<section class="panel-seccion">
    <div class="acciones-fila acciones-fila--separada">
        <a class="boton-secundario boton-secundario--gris" href="/admin/productos.php">Volver a productos</a>
        <a class="boton-terciario boton-terciario--azul" href="/admin/producto_formulario.php?id=<?php echo (int) $producto['id_producto']; ?>">Modificar</a>
    </div>

    <div class="detalle-pedido-layout detalle-producto-admin-layout">
        <article class="detalle-pedido-resumen">
            <div class="detalle-producto-admin__presentacion">
                <img class="detalle-producto-admin__imagen" src="<?php echo $imagen; ?>" alt="<?php echo sanear_texto($producto['nombre_producto']); ?>">
                <div>
                    <p class="detalle-pedido-resumen__etiqueta">Producto</p>
                    <h2 class="detalle-producto-admin__nombre"><?php echo sanear_texto($producto['nombre_producto']); ?></h2>
                </div>
            </div>

            <dl class="detalle-pedido-datos">
                <div>
                    <dt>Estado</dt>
                    <dd><?php echo sanear_texto($estadoProducto); ?></dd>
                </div>
                <div>
                    <dt>Precio</dt>
                    <dd><?php echo formatear_precio((float) $producto['precio']); ?></dd>
                </div>
                <div>
                    <dt>Oferta</dt>
                    <dd><?php echo $esOferta ? 'Si' : 'No'; ?></dd>
                </div>
                <div>
                    <dt>Variantes</dt>
                    <dd><?php echo count($variantes); ?></dd>
                </div>
                <div>
                    <dt>Stock total</dt>
                    <dd><?php echo (int) $stockTotal; ?> unidades</dd>
                </div>
            </dl>

            <?php if (trim((string) ($producto['descripcion'] ?? '')) !== ''): ?>
                <div class="detalle-producto-admin__descripcion">
                    <h3>Descripcion</h3>
                    <p><?php echo sanear_texto($producto['descripcion']); ?></p>
                </div>
            <?php endif; ?>
        </article>

        <section class="detalle-pedido-productos">
            <div class="detalle-pedido-productos__encabezado">
                <h2>Variantes por talle</h2>
                <span><?php echo count($variantes); ?> variante<?php echo count($variantes) === 1 ? '' : 's'; ?></span>
            </div>

            <?php if ($variantes === []): ?>
                <div class="contenido-vacio-admin">Este producto todavia no tiene variantes cargadas.</div>
            <?php else: ?>
                <div class="detalle-producto-admin__variantes">
                    <?php foreach ($variantes as $variante): ?>
                        <?php
                        $stock = (int) $variante['stock'];
                        $estadoVariante = (string) ($variante['estado'] ?? '');
                        $claseStock = $stock > 0 ? 'etiqueta--verde' : 'etiqueta--rojo';
                        ?>
                        <article class="detalle-producto-admin__variante">
                            <div>
                                <p class="detalle-producto-admin__talle"><?php echo sanear_texto($variante['talle']); ?></p>
                                <p class="detalle-producto-admin__sku">SKU: <?php echo sanear_texto($variante['sku'] ?: 'Sin SKU'); ?></p>
                            </div>

                            <div class="detalle-producto-admin__stock">
                                <span class="etiqueta <?php echo $claseStock; ?>">Stock: <?php echo $stock; ?></span>
                                <span class="etiqueta etiqueta--gris"><?php echo sanear_texto(ucfirst($estadoVariante)); ?></span>
                                <?php if ($estadoVariante === 'activo'): ?>
                                    <form method="post" data-confirmar="Queres dar de baja esta variante?">
                                        <input type="hidden" name="accion" value="dar_baja_variante">
                                        <input type="hidden" name="id_variante" value="<?php echo (int) $variante['id_variante']; ?>">
                                        <button class="boton-terciario boton-terciario--rojo" type="submit">Dar de baja</button>
                                    </form>
                                <?php else: ?>
                                    <form method="post" data-confirmar="Queres activar esta variante?">
                                        <input type="hidden" name="accion" value="activar_variante">
                                        <input type="hidden" name="id_variante" value="<?php echo (int) $variante['id_variante']; ?>">
                                        <button class="boton-terciario boton-terciario--azul" type="submit">Activar</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    </div>
</section>

<?php require_once __DIR__ . '/../includes/pie_admin.php'; ?>

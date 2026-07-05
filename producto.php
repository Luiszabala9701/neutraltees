<?php

/**
 * Modulo: detalle de producto.
 * Responsabilidad: mostrar imagen, variantes, guia de talles y alta al carrito.
 */

require_once __DIR__ . '/config/conexion_DB.php';
$conexion = obtener_conexion_db();

$idProducto = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$producto = $idProducto > 0 ? obtener_producto_con_variantes($conexion, $idProducto) : null;

if (!$producto) {
    guardar_flash('mensaje_error', 'El producto solicitado no existe.');
    redirigir('/index.php');
}

require_once __DIR__ . '/includes/cabecera_publica.php';

$imagen = obtener_ruta_imagen_producto($producto['imagen'] ?? null);
$variantesActivas = array_values(array_filter($producto['variantes'], static function (array $variante): bool {
    return $variante['estado'] === 'activo' && (int) $variante['stock'] > 0;
}));
$varianteSeleccionada = $variantesActivas[0] ?? null;
$descuentoPorcentaje = calcular_descuento_porcentaje((float) $producto['precio'], (!empty($producto['oferta']) && !empty($producto['precio_anterior'])) ? (float) $producto['precio_anterior'] : null);
?>

<section class="producto-detalle">
    <div class="producto-detalle__imagen">
        <?php if ($descuentoPorcentaje !== null): ?>
            <span class="producto-detalle__badge-oferta">Oferta</span>
        <?php endif; ?>
        <img src="<?php echo $imagen; ?>" alt="<?php echo sanear_texto($producto['nombre_producto']); ?>">
    </div>

    <div class="producto-detalle__panel">
        <a class="enlace-suave" href="/index.php">&larr; Volver</a>
        <h1 class="titulo-seccion producto-detalle__titulo"><?php echo sanear_texto($producto['nombre_producto']); ?></h1>

        <div class="producto-detalle__precio <?php echo $descuentoPorcentaje === null ? 'producto-precio__sin-oferta' : ''; ?>">
            <?php if ($descuentoPorcentaje !== null): ?>
                <span class="precio-anterior producto-precio__anterior-tachado"><?php echo formatear_precio((float) $producto['precio_anterior']); ?></span>
            <?php endif; ?>
            <span class="precio-oferta producto-detalle__precio-principal"><?php echo formatear_precio((float) $producto['precio']); ?></span>
        </div>

        <p class="producto-detalle__descripcion"><?php echo nl2br(sanear_texto((string) $producto['descripcion'])); ?></p>

        <?php if ($variantesActivas === []): ?>
            <div class="mensaje-vacio">No hay variantes disponibles para este producto.</div>
        <?php else: ?>
            <form method="post" action="/carrito.php" data-formulario-producto>
                <input type="hidden" name="accion" value="agregar">
                <div class="producto-detalle__bloque">
                    <div class="producto-detalle__bloque-titulo">
                        <h3>Talle</h3>
                        <button class="boton-guia-talles" type="button" data-abrir-guia-talles>Guia de talles</button>
                    </div>
                    <div class="selector-talles">
                        <?php foreach ($variantesActivas as $indice => $variante): ?>
                            <?php $stockRestante = obtener_stock_disponible_variante($conexion, (int) $variante['id_variante']); ?>
                            <label>
                                <input type="radio" name="id_variante" value="<?php echo (int) $variante['id_variante']; ?>" data-stock-max="<?php echo $stockRestante; ?>" <?php echo $indice === 0 ? 'checked' : ''; ?>>
                                <span><?php echo sanear_texto($variante['talle']); ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="producto-detalle__compra">
                    <div class="grupo-campo producto-detalle__grupo-cantidad">
                        <label for="cantidad">Cantidad</label>
                        <?php $maximoVarianteSeleccionada = $varianteSeleccionada ? obtener_stock_disponible_variante($conexion, (int) $varianteSeleccionada['id_variante']) : 0; ?>
                        <input class="campo-texto" id="cantidad" type="number" name="cantidad" min="1" value="1" data-stock-max="<?php echo $maximoVarianteSeleccionada; ?>" max="<?php echo $maximoVarianteSeleccionada; ?>">
                    </div>

                    <div class="acciones-detalle">
                        <button class="boton-principal" type="submit" <?php echo $maximoVarianteSeleccionada <= 0 ? 'disabled' : ''; ?>>Agregar al carrito</button>
                        <a class="boton-secundario" href="/carrito.php">Ver carrito</a>
                    </div>
                </div>
            </form>
        <?php endif; ?>
    </div>
</section>

<div class="modal-guia-talles" data-modal-guia-talles aria-hidden="true">
    <div class="modal-guia-talles__tarjeta" role="dialog" aria-modal="true" aria-labelledby="titulo_guia_talles">
        <div class="modal-guia-talles__barra">
            <h2 id="titulo_guia_talles">Guia de talles</h2>
            <button class="modal-guia-talles__cerrar" type="button" data-cerrar-guia-talles aria-label="Cerrar guia de talles">Cerrar</button>
        </div>
        <img class="modal-guia-talles__imagen" src="/src/guiaTalles.jpeg" alt="Guia de talles NeutralTees">
    </div>
</div>

<?php require_once __DIR__ . '/includes/pie_publico.php'; ?>


<?php

/**
 * Modulo: inicio publico.
 * Responsabilidad: mostrar ofertas destacadas, hero y catalogo resumido.
 */

require_once __DIR__ . '/config/conexion_DB.php';
$conexion = obtener_conexion_db();

$busqueda = isset($_GET['q'])
    ? trim(strip_tags((string) $_GET['q']))
    : (isset($_GET['busqueda']) ? trim(strip_tags((string) $_GET['busqueda'])) : '');

$esOferta = isset($_GET['ofertas']);
$productos = obtener_productos($conexion, $busqueda, true);

/* El banner del inicio solo se muestra cuando hay productos reales en oferta.
   Si no hay ofertas, la tienda empieza directamente por el catalogo. */
$productosBanner = array_values(array_filter(obtener_productos($conexion, '', true), static function (array $producto): bool {
    return (int) $producto['oferta'] === 1 && (int) ($producto['stock_total'] ?? 0) > 0;
}));
$mostrarBanner = $busqueda === '' && !$esOferta && !isset($_GET['catalogo']) && $productosBanner !== [];

if ($esOferta) {
    $productos = array_values(array_filter($productos, static function (array $producto): bool {
        return (int) $producto['oferta'] === 1;
    }));
}

require_once __DIR__ . '/includes/cabecera_publica.php';
?>

<?php if ($mostrarBanner): ?>
    <section class="tarjeta-hero tarjeta-hero--inicio banner-ofertas" data-banner-ofertas data-banner-intervalo="5500">
        <div class="banner-ofertas__track" data-banner-track>
            <?php foreach ($productosBanner as $productoBanner): ?>
                <?php
                $imagenBanner = obtener_ruta_imagen_producto($productoBanner['imagen'] ?? null);
                $descuentoBanner = calcular_descuento_porcentaje((float) $productoBanner['precio'], (!empty($productoBanner['oferta']) && !empty($productoBanner['precio_anterior'])) ? (float) $productoBanner['precio_anterior'] : null);
                ?>
                <article class="banner-ofertas__slide" data-banner-slide>
                    <div class="banner-ofertas__contenido">
                        <p class="banner-ofertas__etiqueta">Oferta destacada</p>
                        <h1 class="banner-ofertas__titulo"><?php echo sanear_texto($productoBanner['nombre_producto']); ?></h1>
                        <p class="banner-ofertas__texto">Tu basica de todos los dias, ahora con precio especial.</p>
                        <div class="banner-ofertas__precios">
                            <div class="banner-ofertas__precio-principal"><?php echo formatear_precio((float) $productoBanner['precio']); ?></div>
                            <?php if ($descuentoBanner !== null): ?>
                                <div class="banner-ofertas__detalle-precio">
                                    <span class="precio-anterior banner-ofertas__precio-anterior"><?php echo formatear_precio((float) $productoBanner['precio_anterior']); ?></span>
                                    <span class="etiqueta etiqueta--rojo banner-ofertas__badge-descuento producto-precio__badge-descuento--porcentaje"><?php echo (int) $descuentoBanner; ?>% OFF</span>
                                </div>
                            <?php endif; ?>
                        </div>
                        <a class="tarjeta-hero__boton" href="/producto.php?id=<?php echo (int) $productoBanner['id_producto']; ?>">Ver oferta</a>
                    </div>

                    <a class="banner-ofertas__media" href="/producto.php?id=<?php echo (int) $productoBanner['id_producto']; ?>">
                        <img class="banner-ofertas__imagen" src="<?php echo $imagenBanner; ?>" alt="<?php echo sanear_texto($productoBanner['nombre_producto']); ?>">
                    </a>
                </article>
            <?php endforeach; ?>
        </div>

        <?php if (count($productosBanner) > 1): ?>
            <div class="banner-ofertas__indicadores" aria-label="Cambiar oferta destacada">
                <?php foreach ($productosBanner as $indiceBanner => $productoBanner): ?>
                    <button class="banner-ofertas__punto <?php echo $indiceBanner === 0 ? 'is-active' : ''; ?>" type="button" data-banner-dot aria-label="Ver oferta <?php echo $indiceBanner + 1; ?>"></button>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
<?php endif; ?>

<section id="catalogo">
    <h2 class="titulo-seccion"><?php echo $esOferta ? 'Ofertas especiales' : 'Todos los productos'; ?></h2>
    <p class="subtitulo-seccion"><?php echo $esOferta ? 'Precios especiales por tiempo limitado.' : 'Remeras simples, comodas y listas para usar todos los dias.'; ?></p>

    <?php if ($productos === []): ?>
        <div class="mensaje-vacio" data-mensaje-catalogo-vacio>Todavia no hay productos disponibles.</div>
    <?php else: ?>
        <div class="rejilla-productos" data-catalogo-productos>
            <?php foreach ($productos as $producto): ?>
                <?php
                $variantes = obtener_variantes_producto($conexion, (int) $producto['id_producto']);
                $variantesDisponibles = array_values(array_filter($variantes, static function (array $variante): bool {
                    return $variante['estado'] === 'activo' && (int) $variante['stock'] > 0;
                }));
                $tallesDisponibles = array_map(static function (array $variante): string {
                    return (string) $variante['talle'];
                }, $variantesDisponibles);
                $imagen = obtener_ruta_imagen_producto($producto['imagen'] ?? null);
                $descuentoPorcentaje = calcular_descuento_porcentaje((float) $producto['precio'], (!empty($producto['oferta']) && !empty($producto['precio_anterior'])) ? (float) $producto['precio_anterior'] : null);
                $precioDescuento = (float) $producto['precio'];
                $precioNormal = !empty($producto['precio_anterior']) ? (float) $producto['precio_anterior'] : null;
                ?>
                <article class="tarjeta-producto" data-tarjeta-producto data-texto-producto="<?php echo sanear_texto(strtolower($producto['nombre_producto'] . ' ' . implode(' ', $tallesDisponibles) . ' ' . $precioDescuento . ' ' . ($precioNormal !== null ? $precioNormal : ''))); ?>">
                    <a class="tarjeta-producto__imagen" href="/producto.php?id=<?php echo (int) $producto['id_producto']; ?>">
                        <img src="<?php echo $imagen; ?>" alt="<?php echo sanear_texto($producto['nombre_producto']); ?>">
                    </a>
                    <div class="tarjeta-producto__cuerpo">
                        <div>
                            <h3 class="tarjeta-producto__nombre"><?php echo sanear_texto($producto['nombre_producto']); ?></h3>
                            <p class="tarjeta-producto__stock"><?php echo $tallesDisponibles !== [] ? 'Disponible en: ' . sanear_texto(implode(', ', $tallesDisponibles)) : 'Sin stock'; ?></p>
                        </div>
                        <p class="tarjeta-producto__precio <?php echo $descuentoPorcentaje === null ? 'producto-precio__sin-oferta' : ''; ?>">
                            <?php if ($precioNormal !== null && $descuentoPorcentaje !== null): ?>
                                <span class="precio-anterior producto-precio__anterior-tachado"><?php echo formatear_precio($precioNormal); ?></span>
                            <?php endif; ?>
                            <span class="precio-oferta"><?php echo formatear_precio($precioDescuento); ?></span>
                            <?php if ($descuentoPorcentaje !== null): ?>
                                <span class="etiqueta etiqueta--rojo producto-precio__badge-descuento producto-precio__badge-descuento--porcentaje"><?php echo (int) $descuentoPorcentaje; ?>% OFF</span>
                            <?php endif; ?>
                        </p>
                        <a class="boton-pequeno tarjeta-producto__accion" href="/producto.php?id=<?php echo (int) $producto['id_producto']; ?>">Ver producto</a>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
        <div class="mensaje-vacio" data-mensaje-catalogo-vacio hidden>No se encontraron productos con esa busqueda.</div>
    <?php endif; ?>
</section>

<?php require_once __DIR__ . '/includes/pie_publico.php'; ?>

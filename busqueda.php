<?php

/**
 * Modulo: busqueda publica.
 * Responsabilidad: filtrar productos por texto y responder HTML o JSON/AJAX.
 */

require_once __DIR__ . '/config/conexion_DB.php';

$conexion = obtener_conexion_db();
$q = isset($_GET['q']) ? trim(strip_tags((string) $_GET['q'])) : '';
$esAjax = isset($_GET['ajax']) && (string) $_GET['ajax'] === '1';
$productos = obtener_productos($conexion, $q, true);

$renderizarTarjetaProducto = static function (PDO $conexion, array $producto): string {
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

    ob_start();
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
    <?php
    return trim((string) ob_get_clean());
};

if ($esAjax) {
    $html = '';

    if ($productos === []) {
        $html = '<div class="mensaje-vacio" data-mensaje-catalogo-vacio>No se encontraron productos con esa búsqueda.</div>';
    } else {
        foreach ($productos as $producto) {
            $html .= $renderizarTarjetaProducto($conexion, $producto);
        }
    }

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'ok' => true,
        'query' => $q,
        'total' => count($productos),
        'html' => $html,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

require_once __DIR__ . '/includes/cabecera_publica.php';
?>

<section id="catalogo">
    <h2 class="titulo-seccion"><?php echo $q === '' ? 'Resultados de búsqueda' : 'Resultados para "' . sanear_texto($q) . '"'; ?></h2>
    <p class="subtitulo-seccion"><?php echo $q === '' ? 'Escribí una palabra para encontrar productos rápidamente.' : 'Mostrando productos que coinciden con tu búsqueda.'; ?></p>

    <?php if ($productos === []): ?>
        <div class="mensaje-vacio" data-mensaje-catalogo-vacio>Todavía no hay productos disponibles.</div>
    <?php else: ?>
        <div class="rejilla-productos" data-catalogo-productos>
            <?php foreach ($productos as $producto): ?>
                <?php echo $renderizarTarjetaProducto($conexion, $producto); ?>
            <?php endforeach; ?>
        </div>
        <div class="mensaje-vacio" data-mensaje-catalogo-vacio hidden>No se encontraron productos con esa búsqueda.</div>
    <?php endif; ?>
</section>

<?php require_once __DIR__ . '/includes/pie_publico.php'; ?>

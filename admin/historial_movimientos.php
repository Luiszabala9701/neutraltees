<?php

/**
 * Modulo: historial de stock.
 * Responsabilidad: listar movimientos paginados para auditoria interna.
 */

require_once __DIR__ . '/../config/conexion_DB.php';
$conexion = obtener_conexion_db();
requiere_admin();

$itemsPorPagina = 15;
$paginaActual = max(1, (int) ($_GET['pagina'] ?? 1));
$totalMovimientos = (int) $conexion->query('SELECT COUNT(*) FROM movimiento_stock')->fetchColumn();
$totalPaginas = max(1, (int) ceil($totalMovimientos / $itemsPorPagina));
$paginaActual = min($paginaActual, $totalPaginas);
$offsetMovimientos = ($paginaActual - 1) * $itemsPorPagina;

// Historial paginado de ingresos y egresos realizados sobre las variantes.
$sentenciaHistorial = $conexion->prepare(
    'SELECT ms.*, p.nombre_producto, v.talle, v.sku
     FROM movimiento_stock ms
     INNER JOIN producto_variante v ON v.id_variante = ms.id_variante
     INNER JOIN producto p ON p.id_producto = v.id_producto
     ORDER BY ms.fecha_movimiento DESC, ms.id_movimiento_stock DESC
     LIMIT :limite OFFSET :offset'
);
$sentenciaHistorial->bindValue(':limite', $itemsPorPagina, PDO::PARAM_INT);
$sentenciaHistorial->bindValue(':offset', $offsetMovimientos, PDO::PARAM_INT);
$sentenciaHistorial->execute();
$historial = $sentenciaHistorial->fetchAll(PDO::FETCH_ASSOC) ?: [];

require_once __DIR__ . '/../includes/cabecera_admin.php';
?>

<header class="encabezado-panel">
    <h1>Historial de movimientos</h1>
    <p>Registro de ingresos, egresos y ajustes de stock</p>
</header>

<section class="panel-seccion">
    <?php if ($historial === []): ?>
        <div class="contenido-vacio-admin">Todavía no hay movimientos de stock registrados.</div>
    <?php else: ?>
        <div class="lista-admin">
            <?php foreach ($historial as $movimiento): ?>
                <article class="fila-pedido fila-pedido--compacto fila-movimiento-stock">
                    <div class="fila-pedido__encabezado fila-pedido__encabezado--stock fila-movimiento-stock__contenido">
                        <div class="fila-movimiento-stock__producto">
                            <h3 class="pedido__nombre"><?php echo sanear_texto($movimiento['nombre_producto'] . ' - ' . $movimiento['talle']); ?></h3>
                            <p class="pedido__detalle">
                                SKU: <?php echo sanear_texto($movimiento['sku'] ?? 'N/A'); ?>
                                · <?php echo date('d/m/Y H:i', strtotime((string) $movimiento['fecha_movimiento'])); ?>
                            </p>
                            <?php if (trim((string) $movimiento['observacion']) !== ''): ?>
                                <p class="pedido__detalle fila-movimiento-stock__observacion"><?php echo sanear_texto($movimiento['observacion']); ?></p>
                            <?php endif; ?>
                        </div>

                        <div class="pedido__info-derecha pedido__info-derecha--detalle fila-movimiento-stock__resumen">
                            <div class="etiqueta <?php echo $movimiento['tipo_movimiento'] === 'ingreso' ? 'etiqueta--verde' : 'etiqueta--rojo'; ?>">
                                <?php echo $movimiento['tipo_movimiento'] === 'ingreso' ? 'Ingreso' : 'Egreso'; ?>
                            </div>
                            <div class="pedido__total">Cantidad: <?php echo (int) $movimiento['cantidad']; ?></div>
                            <div class="pedido__estado">
                                <?php echo 'Anterior: ' . (int) $movimiento['stock_anterior'] . ' · Resultado: ' . (int) $movimiento['stock_resultante']; ?>
                            </div>
                        </div>
                    </div>

                </article>
            <?php endforeach; ?>
        </div>
        <?php if ($totalPaginas > 1): ?>
            <nav class="paginacion-admin" aria-label="Paginacion de historial">
                <?php for ($pagina = 1; $pagina <= $totalPaginas; $pagina++): ?>
                    <a class="paginacion-admin__enlace <?php echo $pagina === $paginaActual ? 'is-activo' : ''; ?>" href="/admin/historial_movimientos.php?pagina=<?php echo $pagina; ?>"><?php echo $pagina; ?></a>
                <?php endfor; ?>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
</section>

<?php require_once __DIR__ . '/../includes/pie_admin.php'; ?>

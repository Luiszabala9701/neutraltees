<?php

/**
 * Modulo: detalle de pedido admin.
 * Responsabilidad: mostrar datos del cliente, compra y productos del pedido.
 */

require_once __DIR__ . '/../config/conexion_DB.php';
$conexion = obtener_conexion_db();
requiere_admin();

$idPedido = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($idPedido <= 0) {
    guardar_flash('mensaje_error', 'El pedido solicitado no existe.');
    redirigir('/admin/pedidos.php');
}

$sentenciaPedido = $conexion->prepare(
    'SELECT p.*, u.nombre, u.apellido, u.mail
     FROM pedido p
     INNER JOIN usuario u ON u.id_usuario = p.id_usuario
     WHERE p.id_pedido = :id_pedido
     LIMIT 1'
);
$sentenciaPedido->execute([':id_pedido' => $idPedido]);
$pedido = $sentenciaPedido->fetch(PDO::FETCH_ASSOC) ?: null;

if (!$pedido) {
    guardar_flash('mensaje_error', 'El pedido solicitado no existe.');
    redirigir('/admin/pedidos.php');
}

$detalles = obtener_detalles_pedido($conexion, $idPedido);

$estadoPedido = nombre_estado_pedido((string) ($pedido['estado_pedido'] ?? ''));
$estadoPago = nombre_estado_pago((string) ($pedido['estado_pago'] ?? ''));
$metodoPago = nombre_metodo_pago($pedido['metodo_pago'] ?? null);

require_once __DIR__ . '/../includes/cabecera_admin.php';
?>

<header class="encabezado-panel">
    <h1>Detalle de pedido #<?php echo (int) $pedido['id_pedido']; ?></h1>
    <p><?php echo sanear_texto($pedido['nombre'] . ' ' . $pedido['apellido']); ?> - <?php echo sanear_texto($pedido['mail']); ?></p>
</header>

<section class="panel-seccion">
    <div class="acciones-fila acciones-fila--separada">
        <a class="boton-secundario boton-secundario--gris" href="/admin/pedidos.php">Volver a pedidos</a>
    </div>

    <div class="detalle-pedido-layout">
        <article class="detalle-pedido-resumen">
            <div>
                <p class="detalle-pedido-resumen__etiqueta">Total de la compra</p>
                <div class="detalle-pedido-resumen__total"><?php echo formatear_precio((float) $pedido['total']); ?></div>
            </div>

            <dl class="detalle-pedido-datos">
                <div>
                    <dt>Cliente</dt>
                    <dd><?php echo sanear_texto($pedido['nombre'] . ' ' . $pedido['apellido']); ?></dd>
                </div>
                <div>
                    <dt>Email</dt>
                    <dd><?php echo sanear_texto($pedido['mail']); ?></dd>
                </div>
                <div>
                    <dt>Estado</dt>
                    <dd><?php echo sanear_texto($estadoPedido); ?> / <?php echo sanear_texto($estadoPago); ?></dd>
                </div>
                <div>
                    <dt>DNI</dt>
                    <dd><?php echo sanear_texto($pedido['dni_cliente'] ?? 'No informado'); ?></dd>
                </div>
                <div>
                    <dt>Telefono</dt>
                    <dd><?php echo sanear_texto($pedido['telefono_cliente'] ?? 'No informado'); ?></dd>
                </div>
                <div>
                    <dt>Metodo de pago</dt>
                    <dd><?php echo sanear_texto($metodoPago); ?></dd>
                </div>
                <div>
                    <dt>Fecha</dt>
                    <dd><?php echo date('d/m/Y H:i', strtotime($pedido['fecha'])); ?></dd>
                </div>
            </dl>
        </article>

        <section class="detalle-pedido-productos">
            <div class="detalle-pedido-productos__encabezado">
                <h2>Productos</h2>
                <span><?php echo count($detalles); ?> producto<?php echo count($detalles) === 1 ? '' : 's'; ?></span>
            </div>

            <?php if ($detalles === []): ?>
                <div class="contenido-vacio-admin">Este pedido no tiene productos asociados.</div>
            <?php else: ?>
                <div class="detalle-pedido-productos__lista">
                    <?php foreach ($detalles as $linea): ?>
                        <?php $imagen = obtener_ruta_imagen_producto($linea['imagen'] ?? null); ?>
                        <article class="detalle-pedido-linea">
                            <img class="detalle-pedido-linea__img" src="<?php echo $imagen; ?>" alt="<?php echo sanear_texto($linea['nombre_producto']); ?>" width="72" height="72">

                            <div class="detalle-pedido-linea__info">
                                <h3><?php echo sanear_texto($linea['nombre_producto']); ?></h3>
                                <p>Talle: <?php echo sanear_texto($linea['talle'] ?? 'N/A'); ?> - SKU: <?php echo sanear_texto($linea['sku'] ?? 'N/A'); ?></p>
                            </div>

                            <div class="detalle-pedido-linea__precio">
                                <span><?php echo (int) $linea['cantidad']; ?> x <?php echo formatear_precio((float) $linea['precio_unitario']); ?></span>
                                <strong><?php echo formatear_precio((float) $linea['subtotal_linea']); ?></strong>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    </div>
</section>

<?php require_once __DIR__ . '/../includes/pie_admin.php'; ?>

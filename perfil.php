<?php

/**
 * Modulo: perfil del cliente.
 * Responsabilidad: mostrar datos personales, cupones y compras realizadas.
 */

require_once __DIR__ . '/config/conexion_DB.php';
require_once __DIR__ . '/config/funciones_mail.php';
$conexion = obtener_conexion_db();
$usuario = usuario_actual();

if (!$usuario) {
    guardar_flash('mensaje_error', 'Debés iniciar sesión para ver tu perfil.');
    redirigir('/login.php');
}

$idUsuario = (int) $usuario['id_usuario'];

if (es_post()) {
    $accion = trim(strip_tags((string) ($_POST['accion'] ?? '')));
    $idPedido = (int) ($_POST['id_pedido'] ?? 0);

    if ($accion === 'cancelar_pedido' && $idPedido > 0) {
        try {
            cancelar_pedido($conexion, $idPedido, $usuario, 'cliente', $idUsuario);
            $nombreCliente = trim((string) ($usuario['nombre'] ?? '') . ' ' . (string) ($usuario['apellido'] ?? ''));
            $nombreCliente = $nombreCliente !== '' ? $nombreCliente : 'cliente';
            try {
                enviar_mail_pedido_cancelado($conexion, $idPedido, 'Cancelado por cliente ' . $nombreCliente);
            } catch (Throwable $exMail) {
                registrar_error_sistema('No se pudo enviar mail de cancelacion al cliente', $exMail->getMessage());
            }
            guardar_flash('mensaje_exito', 'La orden fue cancelada correctamente.');
        } catch (Throwable $ex) {
            registrar_error_sistema('Error al cancelar pedido desde perfil', $ex->getMessage());
            guardar_flash('mensaje_error', $ex->getMessage());
        }

        redirigir('/perfil.php');
    }
}

$sentenciaUsuario = $conexion->prepare('SELECT * FROM usuario WHERE id_usuario = :id_usuario LIMIT 1');
$sentenciaUsuario->execute([':id_usuario' => $idUsuario]);
$datosUsuario = $sentenciaUsuario->fetch(PDO::FETCH_ASSOC) ?: null;

$sentenciaPedidos = $conexion->prepare(
    'SELECT id_pedido, fecha, subtotal, descuento, total, estado_pedido, estado_pago
     FROM pedido
     WHERE id_usuario = :id_usuario
     ORDER BY fecha DESC'
);
$sentenciaPedidos->execute([':id_usuario' => $idUsuario]);
$pedidos = $sentenciaPedidos->fetchAll(PDO::FETCH_ASSOC) ?: [];

// Preparar consulta para obtener los detalles (productos) de cada pedido
$sentenciaDetalles = $conexion->prepare(
    'SELECT dp.id_variante, dp.cantidad, dp.precio_unitario, dp.subtotal_linea, v.talle, v.sku, p.nombre_producto, p.imagen
     FROM detalle_pedido dp
     LEFT JOIN producto_variante v ON v.id_variante = dp.id_variante
     LEFT JOIN producto p ON p.id_producto = v.id_producto
     WHERE dp.id_pedido = :id_pedido'
);

require_once __DIR__ . '/includes/cabecera_publica.php';
?>

<section class="tarjeta-formulario tarjeta-formulario--perfil">
    <h1 class="tarjeta-formulario__titulo">Mi perfil</h1>
    <p class="tarjeta-formulario__texto">Estos son tus datos y tus compras realizadas.</p>

    <?php if ($datosUsuario): ?>
        <div class="tarjeta-resumen">
            <p class="tarjeta-resumen__texto">Nombre</p>
            <div class="tarjeta-resumen__valor u-fs-16"><?php echo sanear_texto($datosUsuario['nombre'] . ' ' . $datosUsuario['apellido']); ?></div>
            <p class="tarjeta-resumen__texto">Correo: <?php echo sanear_texto($datosUsuario['mail']); ?></p>
            <p class="tarjeta-resumen__texto">Teléfono: <?php echo sanear_texto((string) ($datosUsuario['telefono'] ?? 'Sin registrar')); ?></p>
        </div>
    <?php endif; ?>

    <div class="u-mt-24">
        <h2 class="tarjeta-formulario__titulo">Compras realizadas</h2>
        <?php if ($pedidos === []): ?>
            <div class="mensaje-vacio">Todavía no tenés pedidos realizados.</div>
        <?php else: ?>
            <div class="lista-admin">
                <?php foreach ($pedidos as $pedido): ?>
                    <?php
                    $idPedido = (int) $pedido['id_pedido'];
                    $estadoPedido = (string) $pedido['estado_pedido'];
                    $estadoPago = (string) $pedido['estado_pago'];
                    ?>
                    <article class="fila-pedido">
                        <div class="fila-pedido__encabezado">
                            <div>
                                <h3 class="pedido__nombre">Pedido #<?php echo $idPedido; ?></h3>
                                <p class="pedido__detalle"><?php echo date('d/m/Y H:i', strtotime($pedido['fecha'])); ?></p>
                            </div>
                            <div class="pedido__info-derecha">
                                <div class="pedido__total"><?php echo formatear_precio((float) $pedido['total']); ?></div>
                                <div class="pedido__chips">
                                    <span class="pedido-chip <?php echo clase_estado_pedido($estadoPedido); ?>"><?php echo sanear_texto(nombre_estado_pedido($estadoPedido)); ?></span>
                                    <?php if ($estadoPedido !== 'cancelado'): ?>
                                        <span class="pedido-chip <?php echo clase_estado_pago($estadoPago); ?>"><?php echo sanear_texto(nombre_estado_pago($estadoPago)); ?></span>
                                    <?php endif; ?>
                                </div>
                                <?php if (pedido_puede_cancelarse($estadoPedido, $estadoPago)): ?>
                                    <form
                                        class="pedido__formulario-cancelar"
                                        method="post"
                                        data-confirmar="¿Seguro que deseas cancelar esta orden?"
                                        data-confirmar-aceptar="Sí"
                                        data-confirmar-cancelar="no"
                                    >
                                        <input type="hidden" name="accion" value="cancelar_pedido">
                                        <input type="hidden" name="id_pedido" value="<?php echo $idPedido; ?>">
                                        <button class="boton-terciario boton-terciario--rojo" type="submit">Cancelar</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php
                            // Obtener y mostrar detalles del pedido
                            $sentenciaDetalles->execute([':id_pedido' => $idPedido]);
                            $detalles = $sentenciaDetalles->fetchAll(PDO::FETCH_ASSOC) ?: [];
                        ?>
                        <?php if ($detalles !== []): ?>
                            <div class="pedido__detalles">
                                <?php foreach ($detalles as $linea): ?>
                                    <div class="pedido-linea">
                                        <div class="pedido-linea__info">
                                            <img class="pedido-linea__img" src="<?php echo obtener_ruta_imagen_producto($linea['imagen'] ?? null); ?>" alt="<?php echo sanear_texto($linea['nombre_producto']); ?>" width="72" height="72">
                                            <div>
                                                <div class="pedido-linea__nombre"><?php echo sanear_texto($linea['nombre_producto']); ?></div>
                                                <div class="pedido-linea__meta">Talle: <?php echo sanear_texto($linea['talle'] ?? 'N/A'); ?> • SKU: <?php echo sanear_texto($linea['sku'] ?? 'N/A'); ?></div>
                                            </div>
                                        </div>
                                        <div class="pedido-linea__precio">
                                            <div><?php echo (int) $linea['cantidad']; ?> × <?php echo formatear_precio((float) $linea['precio_unitario']); ?></div>
                                            <div class="pedido-linea__subtotal"><?php echo formatear_precio((float) $linea['subtotal_linea']); ?></div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php require_once __DIR__ . '/includes/pie_publico.php'; ?>

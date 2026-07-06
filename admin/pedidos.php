<?php

/**
 * Modulo: gestion de pedidos.
 * Responsabilidad: filtrar, paginar y avanzar estados de pedidos y pagos.
 */

require_once __DIR__ . '/../config/conexion_DB.php';
require_once __DIR__ . '/../config/funciones_mail.php';
$conexion = obtener_conexion_db();
requiere_admin();

/* La vista separa los pedidos que todavia se estan trabajando de los que ya cerraron.
   Como el proyecto es academico y no gestiona envios, "entregado" es el cierre final. */
$vista = limpiar_entrada((string) ($_POST['vista'] ?? $_GET['vista'] ?? 'activos'));
$vistasPermitidas = ['activos', 'archivados'];

if (!in_array($vista, $vistasPermitidas, true)) {
    $vista = 'activos';
}

/* El buscador filtra por numero de pedido o por nombre/apellido del cliente. */
$busqueda = trim(limpiar_entrada((string) ($_POST['q'] ?? $_GET['q'] ?? '')));
$filtroEstadoPedido = limpiar_entrada((string) ($_POST['filtro_estado_pedido'] ?? $_GET['estado_pedido'] ?? ''));
$filtroMetodoPago = limpiar_entrada((string) ($_POST['filtro_metodo_pago'] ?? $_GET['metodo_pago'] ?? ''));
$estadosPedidoFiltro = ['pendiente', 'preparando', 'preparado', 'entregado', 'cancelado'];
$metodosPagoFiltro = ['efectivo', 'mercado_pago'];
$itemsPorPagina = 15;
$paginaActual = max(1, (int) ($_POST['pagina'] ?? $_GET['pagina'] ?? 1));

if (!in_array($filtroEstadoPedido, $estadosPedidoFiltro, true)) {
    $filtroEstadoPedido = '';
}

if (!in_array($filtroMetodoPago, $metodosPagoFiltro, true)) {
    $filtroMetodoPago = '';
}

function construir_ruta_pedidos(string $vista, string $busqueda = '', string $estadoPedido = '', string $metodoPago = '', int $pagina = 1): string
{
    $parametros = ['vista' => $vista];

    if ($busqueda !== '') {
        $parametros['q'] = $busqueda;
    }

    if ($estadoPedido !== '') {
        $parametros['estado_pedido'] = $estadoPedido;
    }

    if ($metodoPago !== '') {
        $parametros['metodo_pago'] = $metodoPago;
    }

    if ($pagina > 1) {
        $parametros['pagina'] = $pagina;
    }

    return '/admin/pedidos.php?' . http_build_query($parametros);
}

if (es_post()) {
    // Acciones directas desde la tabla: avanzar estado o marcar pago recibido.
    $accion = trim(strip_tags((string) ($_POST['accion'] ?? '')));
    $idPedido = (int) ($_POST['id_pedido'] ?? 0);
    $rutaRetorno = construir_ruta_pedidos($vista, $busqueda, $filtroEstadoPedido, $filtroMetodoPago, $paginaActual);

    if ($accion === 'cambiar_estado_pedido' && $idPedido > 0) {
        $estadoPedido = trim(strip_tags((string) ($_POST['estado_pedido'] ?? 'pendiente')));
        $estadosPermitidos = ['pendiente', 'preparando', 'preparado', 'entregado'];

        if (!in_array($estadoPedido, $estadosPermitidos, true)) {
            guardar_flash('mensaje_error', 'Estado de pedido invalido.');
            redirigir($rutaRetorno);
        }

        $sentenciaPedidoActual = $conexion->prepare('SELECT estado_pedido, estado_pago FROM pedido WHERE id_pedido = :id_pedido LIMIT 1');
        $sentenciaPedidoActual->execute([':id_pedido' => $idPedido]);
        $pedidoActual = $sentenciaPedidoActual->fetch(PDO::FETCH_ASSOC) ?: [];
        $estadoActual = (string) ($pedidoActual['estado_pedido'] ?? '');
        $estadoPagoActual = (string) ($pedidoActual['estado_pago'] ?? '');

        if ($estadoActual === '') {
            guardar_flash('mensaje_error', 'El pedido no existe.');
            redirigir($rutaRetorno);
        }

        if (!pedido_puede_cancelarse($estadoActual)) {
            guardar_flash('mensaje_error', 'Este pedido ya no se puede modificar.');
            redirigir($rutaRetorno);
        }

        if (orden_estado_pedido($estadoPedido) < orden_estado_pedido($estadoActual)) {
            guardar_flash('mensaje_error', 'No se puede volver a un estado anterior del pedido.');
            redirigir($rutaRetorno);
        }

        if ($estadoPedido === 'entregado' && !pago_esta_confirmado($estadoPagoActual)) {
            guardar_flash('mensaje_error', 'No se puede entregar un pedido con pago pendiente.');
            redirigir($rutaRetorno);
        }

        $sentencia = $conexion->prepare('UPDATE pedido SET estado_pedido = :estado_pedido, fecha_actualizacion = NOW() WHERE id_pedido = :id_pedido');
        $sentencia->execute([
            ':estado_pedido' => $estadoPedido,
            ':id_pedido' => $idPedido,
        ]);

        if ($estadoPedido !== $estadoActual) {
            enviar_mail_estado_pedido($conexion, $idPedido, $estadoActual, $estadoPedido);
        }

        guardar_flash('mensaje_exito', 'Estado del pedido actualizado.');
        redirigir($rutaRetorno);
    }

    if ($accion === 'marcar_pago_recibido' && $idPedido > 0) {
        $sentenciaPedidoActual = $conexion->prepare('SELECT estado_pedido, estado_pago FROM pedido WHERE id_pedido = :id_pedido LIMIT 1');
        $sentenciaPedidoActual->execute([':id_pedido' => $idPedido]);
        $pedidoActual = $sentenciaPedidoActual->fetch(PDO::FETCH_ASSOC) ?: [];

        if ($pedidoActual === []) {
            guardar_flash('mensaje_error', 'El pedido no existe.');
            redirigir($rutaRetorno);
        }

        if (!pedido_puede_cancelarse((string) ($pedidoActual['estado_pedido'] ?? ''), (string) ($pedidoActual['estado_pago'] ?? ''))) {
            guardar_flash('mensaje_error', 'Este pedido ya no se puede modificar.');
            redirigir($rutaRetorno);
        }

        $estadoPagoActual = (string) ($pedidoActual['estado_pago'] ?? '');

        $sentencia = $conexion->prepare('UPDATE pedido SET estado_pago = :estado_pago, fecha_actualizacion = NOW() WHERE id_pedido = :id_pedido');
        $sentencia->execute([
            ':estado_pago' => 'recibido',
            ':id_pedido' => $idPedido,
        ]);

        if ($estadoPagoActual !== 'recibido') {
            enviar_mail_estado_pago($conexion, $idPedido, $estadoPagoActual, 'recibido');
        }

        guardar_flash('mensaje_exito', 'Pago marcado como recibido.');
        redirigir($rutaRetorno);
    }

    if ($accion === 'cancelar_pedido' && $idPedido > 0) {
        try {
            $admin = usuario_actual() ?? [];
            cancelar_pedido($conexion, $idPedido, $admin, 'admin');
            $nombreAdmin = trim((string) ($admin['nombre'] ?? '') . ' ' . (string) ($admin['apellido'] ?? ''));
            $nombreAdmin = $nombreAdmin !== '' ? $nombreAdmin : 'Administrador';
            try {
                enviar_mail_pedido_cancelado($conexion, $idPedido, 'Cancelado por ' . $nombreAdmin . '(admin)');
            } catch (Throwable $exMail) {
                registrar_error_sistema('No se pudo enviar mail de cancelacion desde admin', $exMail->getMessage());
            }
            guardar_flash('mensaje_exito', 'Pedido cancelado y stock restaurado.');
        } catch (Throwable $ex) {
            registrar_error_sistema('Error al cancelar pedido desde admin', $ex->getMessage());
            guardar_flash('mensaje_error', $ex->getMessage());
        }

        redirigir($rutaRetorno);
    }
}

// Totales de pestanas para que el admin vea cuantas ventas quedan activas.
$totalActivos = (int) $conexion
    ->query("SELECT COUNT(*) FROM pedido WHERE estado_pedido IN ('pendiente', 'preparando', 'preparado', 'enviado')")
    ->fetchColumn();

$totalArchivados = (int) $conexion
    ->query("SELECT COUNT(*) FROM pedido WHERE estado_pedido IN ('entregado', 'cancelado')")
    ->fetchColumn();

// Las condiciones se arman de forma incremental segun pestana, buscador y filtros.
$condiciones = [];
$parametros = [];

if ($vista === 'archivados') {
    $condiciones[] = "p.estado_pedido IN ('entregado', 'cancelado')";
} else {
    $condiciones[] = "p.estado_pedido IN ('pendiente', 'preparando', 'preparado', 'enviado')";
}

if ($busqueda !== '') {
    /* MySQL con prepared statements nativos no reutiliza bien el mismo placeholder
       varias veces, por eso cada coincidencia usa su propio parametro. */
    $condiciones[] = "(CAST(p.id_pedido AS CHAR) LIKE :busqueda_pedido OR CONCAT(u.nombre, ' ', u.apellido) LIKE :busqueda_nombre_completo OR u.nombre LIKE :busqueda_nombre OR u.apellido LIKE :busqueda_apellido)";
    $valorBusqueda = '%' . $busqueda . '%';
    $parametros[':busqueda_pedido'] = $valorBusqueda;
    $parametros[':busqueda_nombre_completo'] = $valorBusqueda;
    $parametros[':busqueda_nombre'] = $valorBusqueda;
    $parametros[':busqueda_apellido'] = $valorBusqueda;
}

if ($filtroEstadoPedido !== '') {
    if ($filtroEstadoPedido === 'preparado') {
        $condiciones[] = "p.estado_pedido IN ('preparado', 'enviado')";
    } else {
        $condiciones[] = 'p.estado_pedido = :filtro_estado_pedido';
        $parametros[':filtro_estado_pedido'] = $filtroEstadoPedido;
    }
}

if ($filtroMetodoPago !== '') {
    $condiciones[] = 'p.metodo_pago = :filtro_metodo_pago';
    $parametros[':filtro_metodo_pago'] = $filtroMetodoPago;
}

$sqlTotalPedidos = '
    SELECT COUNT(*)
    FROM pedido p
    INNER JOIN usuario u ON u.id_usuario = p.id_usuario
    WHERE ' . implode(' AND ', $condiciones);

$sentenciaTotalPedidos = $conexion->prepare($sqlTotalPedidos);
$sentenciaTotalPedidos->execute($parametros);
$totalPedidosFiltrados = (int) $sentenciaTotalPedidos->fetchColumn();
$totalPaginas = max(1, (int) ceil($totalPedidosFiltrados / $itemsPorPagina));
$paginaActual = min($paginaActual, $totalPaginas);
$offsetPedidos = ($paginaActual - 1) * $itemsPorPagina;

$sqlPedidos = '
    SELECT
        p.*,
        u.nombre,
        u.apellido,
        u.mail,
        COALESCE(resumen_productos.unidades, 0) AS unidades_pedido
    FROM pedido p
    INNER JOIN usuario u ON u.id_usuario = p.id_usuario
    LEFT JOIN (
        SELECT
            id_pedido,
            SUM(cantidad) AS unidades
        FROM detalle_pedido
        GROUP BY id_pedido
    ) resumen_productos ON resumen_productos.id_pedido = p.id_pedido
    WHERE ' . implode(' AND ', $condiciones) . '
    ORDER BY p.fecha DESC
    LIMIT :limite OFFSET :offset';

$sentenciaPedidos = $conexion->prepare($sqlPedidos);
foreach ($parametros as $clave => $valor) {
    $sentenciaPedidos->bindValue($clave, $valor);
}
$sentenciaPedidos->bindValue(':limite', $itemsPorPagina, PDO::PARAM_INT);
$sentenciaPedidos->bindValue(':offset', $offsetPedidos, PDO::PARAM_INT);
$sentenciaPedidos->execute();
$pedidos = $sentenciaPedidos->fetchAll(PDO::FETCH_ASSOC) ?: [];

require_once __DIR__ . '/../includes/cabecera_admin.php';
?>

<header class="encabezado-panel">
    <h1>Pedidos</h1>
    <p>Ventas, pagos y seguimiento de compras</p>
</header>

<section class="panel-seccion">
    <div class="pedidos-panel">
        <div class="pedidos-panel__barra">
            <form class="pedidos-buscador" method="get" action="/admin/pedidos.php">
                <input type="hidden" name="vista" value="<?php echo sanear_texto($vista); ?>">
                <label class="sr-only" for="busqueda_pedido">Buscar pedido</label>
                <input
                    class="campo-texto pedidos-buscador__entrada"
                    id="busqueda_pedido"
                    type="search"
                    name="q"
                    value="<?php echo sanear_texto($busqueda); ?>"
                    placeholder="Buscar por pedido o cliente"
                >
                <label class="sr-only" for="filtro_estado_pedido">Filtrar por estado</label>
                <select class="campo-select pedidos-buscador__filtro" id="filtro_estado_pedido" name="estado_pedido">
                    <option value="">Todos los estados</option>
                    <option value="pendiente" <?php echo $filtroEstadoPedido === 'pendiente' ? 'selected' : ''; ?>>Pendiente</option>
                    <option value="preparando" <?php echo $filtroEstadoPedido === 'preparando' ? 'selected' : ''; ?>>En preparación</option>
                    <option value="preparado" <?php echo $filtroEstadoPedido === 'preparado' ? 'selected' : ''; ?>>Preparado</option>
                    <option value="entregado" <?php echo $filtroEstadoPedido === 'entregado' ? 'selected' : ''; ?>>Entregado</option>
                    <option value="cancelado" <?php echo $filtroEstadoPedido === 'cancelado' ? 'selected' : ''; ?>>Cancelado</option>
                </select>
                <label class="sr-only" for="filtro_metodo_pago">Filtrar por metodo de pago</label>
                <select class="campo-select pedidos-buscador__filtro" id="filtro_metodo_pago" name="metodo_pago">
                    <option value="">Todos los pagos</option>
                    <option value="efectivo" <?php echo $filtroMetodoPago === 'efectivo' ? 'selected' : ''; ?>>Efectivo</option>
                    <option value="mercado_pago" <?php echo $filtroMetodoPago === 'mercado_pago' ? 'selected' : ''; ?>>Mercado Pago</option>
                </select>
                <button class="boton-secundario boton-secundario--gris" type="submit">Buscar</button>
                <?php if ($busqueda !== '' || $filtroEstadoPedido !== '' || $filtroMetodoPago !== ''): ?>
                    <a class="boton-secundario boton-secundario--gris" href="<?php echo construir_ruta_pedidos($vista); ?>">Limpiar</a>
                <?php endif; ?>
            </form>

            <nav class="pedidos-pestanas" aria-label="Vistas de pedidos">
                <a class="pedidos-pestanas__enlace <?php echo $vista === 'activos' ? 'is-activo' : ''; ?>" href="<?php echo construir_ruta_pedidos('activos', $busqueda, $filtroEstadoPedido, $filtroMetodoPago); ?>">
                    Activos <span><?php echo $totalActivos; ?></span>
                </a>
                <a class="pedidos-pestanas__enlace <?php echo $vista === 'archivados' ? 'is-activo' : ''; ?>" href="<?php echo construir_ruta_pedidos('archivados', $busqueda, $filtroEstadoPedido, $filtroMetodoPago); ?>">
                    Archivados <span><?php echo $totalArchivados; ?></span>
                </a>
            </nav>
        </div>

        <?php if ($pedidos === []): ?>
            <div class="contenido-vacio-admin">No hay pedidos para esta vista.</div>
        <?php else: ?>
            <div class="tabla-pedidos__envoltorio">
                <table class="tabla-pedidos">
                    <thead>
                        <tr>
                            <th>Pedido</th>
                            <th>Cliente</th>
                            <th>Compra</th>
                            <th>Pago</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pedidos as $pedido): ?>
                            <?php
                            $idPedido = (int) $pedido['id_pedido'];
                            $nombreCliente = trim((string) $pedido['nombre'] . ' ' . (string) $pedido['apellido']);
                            $estadoPedido = (string) $pedido['estado_pedido'];
                            $estadoPago = (string) $pedido['estado_pago'];
                            $pagoConfirmado = pago_esta_confirmado($estadoPago);
                            $cantidadUnidades = (int) $pedido['unidades_pedido'];
                            $pedidoSinAcciones = !pedido_puede_cancelarse($estadoPedido);
                            ?>
                            <tr>
                                <td>
                                    <div class="tabla-pedidos__pedido-grupo">
                                        <a class="tabla-pedidos__pedido" href="/admin/pedido_detalle.php?id=<?php echo $idPedido; ?>">#<?php echo $idPedido; ?></a>
                                        <a class="boton-ver-pedido" href="/admin/pedido_detalle.php?id=<?php echo $idPedido; ?>">Ver</a>
                                    </div>
                                    <span class="tabla-pedidos__muted"><?php echo date('d/m/Y', strtotime($pedido['fecha'])); ?></span>
                                    <span class="tabla-pedidos__muted"><?php echo date('H:i', strtotime($pedido['fecha'])); ?></span>
                                </td>
                                <td>
                                    <strong class="tabla-pedidos__cliente"><?php echo sanear_texto($nombreCliente); ?></strong>
                                    <span class="tabla-pedidos__muted"><?php echo sanear_texto($pedido['mail']); ?></span>
                                </td>
                                <td>
                                    <span class="tabla-pedidos__total"><?php echo formatear_precio((float) $pedido['total']); ?> <span class="tabla-pedidos__unidades"><?php echo $cantidadUnidades; ?> item<?php echo $cantidadUnidades === 1 ? '' : 's'; ?></span></span>
                                </td>
                                <td>
                                    <?php if ($estadoPedido !== 'cancelado'): ?>
                                        <span class="pedido-chip <?php echo clase_estado_pago($estadoPago); ?>"><?php echo sanear_texto(nombre_estado_pago($estadoPago)); ?></span>
                                    <?php endif; ?>
                                    <span class="pedido-chip pedido-chip--metodo <?php echo clase_metodo_pago($pedido['metodo_pago'] ?? null); ?>"><?php echo sanear_texto(nombre_metodo_pago($pedido['metodo_pago'] ?? null)); ?></span>
                                </td>
                                <td>
                                    <span class="pedido-chip <?php echo clase_estado_pedido($estadoPedido); ?>"><?php echo sanear_texto(nombre_estado_pedido($estadoPedido)); ?></span>
                                </td>
                                <td>
                                    <?php if ($pedidoSinAcciones): ?>
                                        <span class="tabla-pedidos__muted tabla-pedidos__sin-acciones">Sin acciones</span>
                                    <?php else: ?>
                                    <div class="tabla-pedidos__acciones">
                                        <form class="formulario-estado-pedido" method="post">
                                            <input type="hidden" name="accion" value="cambiar_estado_pedido">
                                            <input type="hidden" name="id_pedido" value="<?php echo $idPedido; ?>">
                                            <input type="hidden" name="vista" value="<?php echo sanear_texto($vista); ?>">
                                            <input type="hidden" name="q" value="<?php echo sanear_texto($busqueda); ?>">
                                            <input type="hidden" name="filtro_estado_pedido" value="<?php echo sanear_texto($filtroEstadoPedido); ?>">
                                            <input type="hidden" name="filtro_metodo_pago" value="<?php echo sanear_texto($filtroMetodoPago); ?>">
                                            <input type="hidden" name="pagina" value="<?php echo $paginaActual; ?>">
                                            <label class="sr-only" for="estado_pedido_<?php echo $idPedido; ?>">Cambiar estado</label>
                                            <select class="campo-select formulario-estado-pedido__select" id="estado_pedido_<?php echo $idPedido; ?>" name="estado_pedido" data-envio-cambio-estado>
                                                <option value="pendiente" <?php echo $estadoPedido === 'pendiente' ? 'selected' : ''; ?> <?php echo orden_estado_pedido('pendiente') < orden_estado_pedido($estadoPedido) ? 'disabled' : ''; ?>>Pendiente</option>
                                                <option value="preparando" <?php echo $estadoPedido === 'preparando' ? 'selected' : ''; ?> <?php echo orden_estado_pedido('preparando') < orden_estado_pedido($estadoPedido) ? 'disabled' : ''; ?>>En preparación</option>
                                                <option value="preparado" <?php echo $estadoPedido === 'preparado' ? 'selected' : ''; ?> <?php echo orden_estado_pedido('preparado') < orden_estado_pedido($estadoPedido) ? 'disabled' : ''; ?>>Preparado</option>
                                                <option value="entregado" <?php echo $estadoPedido === 'entregado' ? 'selected' : ''; ?> <?php echo (orden_estado_pedido('entregado') < orden_estado_pedido($estadoPedido) || !$pagoConfirmado) ? 'disabled' : ''; ?>>Entregado</option>
                                            </select>
                                        </form>

                                        <?php if (!$pagoConfirmado): ?>
                                            <form method="post">
                                                <input type="hidden" name="accion" value="marcar_pago_recibido">
                                                <input type="hidden" name="id_pedido" value="<?php echo $idPedido; ?>">
                                                <input type="hidden" name="vista" value="<?php echo sanear_texto($vista); ?>">
                                                <input type="hidden" name="q" value="<?php echo sanear_texto($busqueda); ?>">
                                                <input type="hidden" name="filtro_estado_pedido" value="<?php echo sanear_texto($filtroEstadoPedido); ?>">
                                                <input type="hidden" name="filtro_metodo_pago" value="<?php echo sanear_texto($filtroMetodoPago); ?>">
                                                <input type="hidden" name="pagina" value="<?php echo $paginaActual; ?>">
                                                <button class="boton-terciario boton-terciario--verde" type="submit">Pago recibido</button>
                                            </form>
                                        <?php endif; ?>

                                        <?php if (!$pagoConfirmado): ?>
                                        <form
                                            method="post"
                                            data-confirmar="¿Seguro que deseas cancelar esta orden?"
                                            data-confirmar-aceptar="Sí"
                                            data-confirmar-cancelar="no"
                                        >
                                            <input type="hidden" name="accion" value="cancelar_pedido">
                                            <input type="hidden" name="id_pedido" value="<?php echo $idPedido; ?>">
                                            <input type="hidden" name="vista" value="<?php echo sanear_texto($vista); ?>">
                                            <input type="hidden" name="q" value="<?php echo sanear_texto($busqueda); ?>">
                                            <input type="hidden" name="filtro_estado_pedido" value="<?php echo sanear_texto($filtroEstadoPedido); ?>">
                                            <input type="hidden" name="filtro_metodo_pago" value="<?php echo sanear_texto($filtroMetodoPago); ?>">
                                            <input type="hidden" name="pagina" value="<?php echo $paginaActual; ?>">
                                            <button class="boton-terciario boton-terciario--rojo" type="submit">Cancelar</button>
                                        </form>
                                        <?php endif; ?>
                                    </div>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php if ($totalPaginas > 1): ?>
                <nav class="paginacion-admin" aria-label="Paginacion de pedidos">
                    <?php for ($pagina = 1; $pagina <= $totalPaginas; $pagina++): ?>
                        <a class="paginacion-admin__enlace <?php echo $pagina === $paginaActual ? 'is-activo' : ''; ?>" href="<?php echo construir_ruta_pedidos($vista, $busqueda, $filtroEstadoPedido, $filtroMetodoPago, $pagina); ?>"><?php echo $pagina; ?></a>
                    <?php endfor; ?>
                </nav>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</section>

<?php require_once __DIR__ . '/../includes/pie_admin.php'; ?>


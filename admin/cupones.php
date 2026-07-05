<?php

/**
 * Modulo: cupones admin.
 * Responsabilidad: listar cupones activos, consultar archivados y dar de baja cupones vigentes.
 */

require_once __DIR__ . '/../config/conexion_DB.php';
$conexion = obtener_conexion_db();
requiere_admin();

$itemsPorPagina = 15;
$paginaActual = max(1, (int) ($_POST['pagina'] ?? $_GET['pagina'] ?? 1));
$busqueda = trim(limpiar_entrada((string) ($_POST['q'] ?? $_GET['q'] ?? '')));
$vistasPermitidas = ['activos', 'archivados'];
$vista = limpiar_entrada((string) ($_POST['vista'] ?? $_GET['vista'] ?? 'activos'));

if (!in_array($vista, $vistasPermitidas, true)) {
    $vista = 'activos';
}

/**
 * Construye enlaces conservando la vista, la busqueda y la pagina actual.
 */
function construir_ruta_cupones(string $vista = 'activos', string $busqueda = '', int $pagina = 1): string
{
    $parametros = ['vista' => $vista];

    if ($busqueda !== '') {
        $parametros['q'] = $busqueda;
    }

    if ($pagina > 1) {
        $parametros['pagina'] = $pagina;
    }

    return '/admin/cupones.php?' . http_build_query($parametros);
}

if (es_post()) {
    $accion = limpiar_entrada((string) ($_POST['accion'] ?? ''));
    $idCupon = (int) ($_POST['id_cupon'] ?? 0);
    $rutaRetorno = construir_ruta_cupones($vista, $busqueda, $paginaActual);

    if ($accion === 'dar_baja_cupon' && $idCupon > 0 && $vista === 'activos') {
        $sentenciaBaja = $conexion->prepare('UPDATE cupon SET activo = 0 WHERE id_cupon = :id_cupon');
        $sentenciaBaja->execute([':id_cupon' => $idCupon]);

        guardar_flash('mensaje_exito', 'Cupon dado de baja.');
        redirigir($rutaRetorno);
    }
}

/* Activo: esta habilitado y aun no vencio. Archivado: fue dado de baja o ya vencio. */
$condicionActivos = 'activo = 1 AND (fecha_fin IS NULL OR fecha_fin >= CURDATE())';
$condicionArchivados = 'activo = 0 OR (fecha_fin IS NOT NULL AND fecha_fin < CURDATE())';

$totalActivos = (int) $conexion->query("SELECT COUNT(*) FROM cupon WHERE $condicionActivos")->fetchColumn();
$totalArchivados = (int) $conexion->query("SELECT COUNT(*) FROM cupon WHERE $condicionArchivados")->fetchColumn();

$condiciones = $vista === 'archivados'
    ? ['(c.activo = 0 OR (c.fecha_fin IS NOT NULL AND c.fecha_fin < CURDATE()))']
    : ['c.activo = 1', '(c.fecha_fin IS NULL OR c.fecha_fin >= CURDATE())'];
$parametros = [];

if ($busqueda !== '') {
    $condiciones[] = '(c.codigo LIKE :busqueda_codigo OR c.descripcion LIKE :busqueda_descripcion)';
    $valorBusqueda = '%' . $busqueda . '%';
    $parametros[':busqueda_codigo'] = $valorBusqueda;
    $parametros[':busqueda_descripcion'] = $valorBusqueda;
}

$whereCupones = ' WHERE ' . implode(' AND ', $condiciones);

$sentenciaTotal = $conexion->prepare('SELECT COUNT(*) FROM cupon c' . $whereCupones);
$sentenciaTotal->execute($parametros);
$totalCupones = (int) $sentenciaTotal->fetchColumn();
$totalPaginas = max(1, (int) ceil($totalCupones / $itemsPorPagina));
$paginaActual = min($paginaActual, $totalPaginas);
$offsetCupones = ($paginaActual - 1) * $itemsPorPagina;

// Se muestra la cantidad de usos para distinguir cupones creados de cupones ya utilizados.
$sentenciaCupones = $conexion->prepare(
    'SELECT c.*, COALESCE(usos.usos_realizados, 0) AS usos_realizados
     FROM cupon c
     LEFT JOIN (
        SELECT id_cupon, COUNT(*) AS usos_realizados
        FROM uso_cupon
        GROUP BY id_cupon
     ) usos ON usos.id_cupon = c.id_cupon
     ' . $whereCupones . '
     ORDER BY c.fecha_creacion DESC, c.id_cupon DESC
     LIMIT :limite OFFSET :offset'
);

foreach ($parametros as $clave => $valor) {
    $sentenciaCupones->bindValue($clave, $valor, PDO::PARAM_STR);
}

$sentenciaCupones->bindValue(':limite', $itemsPorPagina, PDO::PARAM_INT);
$sentenciaCupones->bindValue(':offset', $offsetCupones, PDO::PARAM_INT);
$sentenciaCupones->execute();
$cupones = $sentenciaCupones->fetchAll(PDO::FETCH_ASSOC) ?: [];

require_once __DIR__ . '/../includes/cabecera_admin.php';
?>

<header class="encabezado-panel">
    <h1><?php echo $vista === 'archivados' ? 'Cupones archivados' : 'Cupones activos'; ?></h1>
    <p><?php echo $vista === 'archivados' ? 'Historial de cupones vencidos o dados de baja' : 'Administrar descuentos disponibles para la tienda'; ?></p>
</header>

<section class="panel-seccion">
    <div class="pedidos-panel usuarios-panel-filtros">
        <div class="pedidos-panel__barra">
            <form class="pedidos-buscador" method="get" action="/admin/cupones.php">
                <input type="hidden" name="vista" value="<?php echo sanear_texto($vista); ?>">
                <label class="sr-only" for="busqueda_cupon">Buscar cupon</label>
                <input
                    class="campo-texto pedidos-buscador__entrada"
                    id="busqueda_cupon"
                    type="search"
                    name="q"
                    value="<?php echo sanear_texto($busqueda); ?>"
                    placeholder="Buscar por codigo o descripcion"
                >
                <button class="boton-secundario boton-secundario--gris" type="submit">Buscar</button>
                <?php if ($busqueda !== ''): ?>
                    <a class="boton-secundario boton-secundario--gris" href="<?php echo construir_ruta_cupones($vista); ?>">Limpiar</a>
                <?php endif; ?>
                <a class="boton-principal" href="/admin/cupon_formulario.php">Crear cupon</a>
            </form>

            <div class="pedidos-pestanas" aria-label="Vistas de cupones">
                <a class="pedidos-pestanas__enlace <?php echo $vista === 'activos' ? 'is-activo' : ''; ?>" href="<?php echo construir_ruta_cupones('activos', $busqueda); ?>">
                    Activos <span><?php echo $totalActivos; ?></span>
                </a>
                <a class="pedidos-pestanas__enlace <?php echo $vista === 'archivados' ? 'is-activo' : ''; ?>" href="<?php echo construir_ruta_cupones('archivados', $busqueda); ?>">
                    Archivados <span><?php echo $totalArchivados; ?></span>
                </a>
            </div>
        </div>
    </div>

    <?php if ($cupones === []): ?>
        <div class="contenido-vacio-admin">No hay cupones <?php echo $vista === 'archivados' ? 'archivados' : 'activos'; ?> para mostrar.</div>
    <?php else: ?>
        <div class="lista-admin">
            <?php foreach ($cupones as $cupon): ?>
                <?php
                $tipoDescuento = (string) $cupon['tipo_descuento'];
                $valorDescuento = $tipoDescuento === 'porcentaje'
                    ? number_format((float) $cupon['valor'], 0, ',', '.') . '%'
                    : formatear_precio((float) $cupon['valor']);
                $fechaInicio = !empty($cupon['fecha_inicio']) ? date('d/m/Y', strtotime((string) $cupon['fecha_inicio'])) : 'Sin inicio';
                $fechaFin = !empty($cupon['fecha_fin']) ? date('d/m/Y', strtotime((string) $cupon['fecha_fin'])) : 'Sin fin';
                $cuponExpirado = !empty($cupon['fecha_fin']) && (string) $cupon['fecha_fin'] < date('Y-m-d');
                $cuponDadoDeBaja = (int) $cupon['activo'] !== 1;
                ?>
                <article class="fila-producto fila-cupon">
                    <div class="fila-producto__encabezado fila-cupon__encabezado">
                        <div>
                            <h3 class="producto-card__nombre fila-cupon__codigo"><?php echo sanear_texto(strtoupper((string) $cupon['codigo'])); ?></h3>
                            <p class="pedido__detalle"><?php echo sanear_texto((string) ($cupon['descripcion'] ?? 'Sin descripcion')); ?></p>
                        </div>

                        <?php if ($vista === 'activos'): ?>
                            <div class="acciones-fila producto-card__chips">
                                <form method="post" data-confirmar="Queres dar de baja este cupon?">
                                    <input type="hidden" name="accion" value="dar_baja_cupon">
                                    <input type="hidden" name="id_cupon" value="<?php echo (int) $cupon['id_cupon']; ?>">
                                    <input type="hidden" name="q" value="<?php echo sanear_texto($busqueda); ?>">
                                    <input type="hidden" name="pagina" value="<?php echo $paginaActual; ?>">
                                    <input type="hidden" name="vista" value="<?php echo sanear_texto($vista); ?>">
                                    <button class="boton-terciario boton-terciario--rojo" type="submit">Dar de baja</button>
                                </form>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="cupon-datos">
                        <span class="etiqueta etiqueta--azul"><?php echo $tipoDescuento === 'porcentaje' ? 'Porcentaje' : 'Monto fijo'; ?>: <?php echo sanear_texto($valorDescuento); ?></span>
                        <span class="etiqueta etiqueta--gris">Compra minima: <?php echo formatear_precio((float) $cupon['compra_minima']); ?></span>
                        <?php if ($cupon['tope_descuento'] !== null): ?>
                            <span class="etiqueta etiqueta--amarillo">Tope: <?php echo formatear_precio((float) $cupon['tope_descuento']); ?></span>
                        <?php endif; ?>
                        <span class="etiqueta etiqueta--verde">Usos: <?php echo (int) $cupon['usos_realizados']; ?><?php echo $cupon['max_usos_total'] !== null ? ' / ' . (int) $cupon['max_usos_total'] : ''; ?></span>
                        <span class="etiqueta etiqueta--gris"><?php echo sanear_texto($fechaInicio . ' - ' . $fechaFin); ?></span>
                        <?php if ($cuponExpirado): ?>
                            <span class="etiqueta etiqueta--rojo">Cupon expirado</span>
                        <?php endif; ?>
                        <?php if ($cuponDadoDeBaja): ?>
                            <span class="etiqueta etiqueta--rojo">Dado de baja</span>
                        <?php endif; ?>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>

        <?php if ($totalPaginas > 1): ?>
            <nav class="paginacion-admin" aria-label="Paginacion de cupones">
                <?php for ($pagina = 1; $pagina <= $totalPaginas; $pagina++): ?>
                    <a class="paginacion-admin__enlace <?php echo $pagina === $paginaActual ? 'is-activo' : ''; ?>" href="<?php echo construir_ruta_cupones($vista, $busqueda, $pagina); ?>"><?php echo $pagina; ?></a>
                <?php endfor; ?>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
</section>

<?php require_once __DIR__ . '/../includes/pie_admin.php'; ?>

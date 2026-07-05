<?php

/**
 * Modulo: cupones admin.
 * Responsabilidad: listar, filtrar y dar de baja cupones existentes.
 */

require_once __DIR__ . '/../config/conexion_DB.php';
$conexion = obtener_conexion_db();
requiere_admin();

$itemsPorPagina = 15;
$paginaActual = max(1, (int) ($_POST['pagina'] ?? $_GET['pagina'] ?? 1));
$busqueda = trim(limpiar_entrada((string) ($_POST['q'] ?? $_GET['q'] ?? '')));

function construir_ruta_cupones(string $busqueda = '', int $pagina = 1): string
{
    $parametros = [];

    if ($busqueda !== '') {
        $parametros['q'] = $busqueda;
    }

    if ($pagina > 1) {
        $parametros['pagina'] = $pagina;
    }

    return '/admin/cupones.php' . ($parametros === [] ? '' : '?' . http_build_query($parametros));
}

if (es_post()) {
    $accion = limpiar_entrada((string) ($_POST['accion'] ?? ''));
    $idCupon = (int) ($_POST['id_cupon'] ?? 0);
    $rutaRetorno = construir_ruta_cupones($busqueda, $paginaActual);

    if ($accion === 'dar_baja_cupon' && $idCupon > 0) {
        $sentenciaBaja = $conexion->prepare('UPDATE cupon SET activo = 0 WHERE id_cupon = :id_cupon');
        $sentenciaBaja->execute([':id_cupon' => $idCupon]);

        guardar_flash('mensaje_exito', 'Cupón dado de baja.');
        redirigir($rutaRetorno);
    }
}

$condiciones = ['c.activo = 1'];
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

// Se muestra también la cantidad de usos para que el administrador vea si el cupón ya circuló.
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
    <h1>Cupones activos</h1>
    <p>Administrar descuentos disponibles para la tienda</p>
</header>

<section class="panel-seccion">
    <div class="pedidos-panel usuarios-panel-filtros">
        <div class="pedidos-panel__barra">
            <form class="pedidos-buscador" method="get" action="/admin/cupones.php">
                <label class="sr-only" for="busqueda_cupon">Buscar cupón</label>
                <input
                    class="campo-texto pedidos-buscador__entrada"
                    id="busqueda_cupon"
                    type="search"
                    name="q"
                    value="<?php echo sanear_texto($busqueda); ?>"
                    placeholder="Buscar por código o descripción"
                >
                <button class="boton-secundario boton-secundario--gris" type="submit">Buscar</button>
                <?php if ($busqueda !== ''): ?>
                    <a class="boton-secundario boton-secundario--gris" href="/admin/cupones.php">Limpiar</a>
                <?php endif; ?>
                <a class="boton-principal" href="/admin/cupon_formulario.php">Crear cupón</a>
            </form>
        </div>
    </div>

    <?php if ($cupones === []): ?>
        <div class="contenido-vacio-admin">No hay cupones activos para mostrar.</div>
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
                ?>
                <article class="fila-producto fila-cupon">
                    <div class="fila-producto__encabezado fila-cupon__encabezado">
                        <div>
                            <h3 class="producto-card__nombre fila-cupon__codigo"><?php echo sanear_texto(strtoupper((string) $cupon['codigo'])); ?></h3>
                            <p class="pedido__detalle"><?php echo sanear_texto((string) ($cupon['descripcion'] ?? 'Sin descripción')); ?></p>
                        </div>

                        <div class="acciones-fila producto-card__chips">
                            <form method="post" data-confirmar="¿Querés dar de baja este cupón?">
                                <input type="hidden" name="accion" value="dar_baja_cupon">
                                <input type="hidden" name="id_cupon" value="<?php echo (int) $cupon['id_cupon']; ?>">
                                <input type="hidden" name="q" value="<?php echo sanear_texto($busqueda); ?>">
                                <input type="hidden" name="pagina" value="<?php echo $paginaActual; ?>">
                                <button class="boton-terciario boton-terciario--rojo" type="submit">Dar de baja</button>
                            </form>
                        </div>
                    </div>

                    <div class="cupon-datos">
                        <span class="etiqueta etiqueta--azul"><?php echo $tipoDescuento === 'porcentaje' ? 'Porcentaje' : 'Monto fijo'; ?>: <?php echo sanear_texto($valorDescuento); ?></span>
                        <span class="etiqueta etiqueta--gris">Compra mínima: <?php echo formatear_precio((float) $cupon['compra_minima']); ?></span>
                        <?php if ($cupon['tope_descuento'] !== null): ?>
                            <span class="etiqueta etiqueta--amarillo">Tope: <?php echo formatear_precio((float) $cupon['tope_descuento']); ?></span>
                        <?php endif; ?>
                        <span class="etiqueta etiqueta--verde">Usos: <?php echo (int) $cupon['usos_realizados']; ?><?php echo $cupon['max_usos_total'] !== null ? ' / ' . (int) $cupon['max_usos_total'] : ''; ?></span>
                        <span class="etiqueta etiqueta--gris"><?php echo sanear_texto($fechaInicio . ' - ' . $fechaFin); ?></span>
                        <?php if ($cuponExpirado): ?>
                            <span class="etiqueta etiqueta--rojo">Cupón expirado</span>
                        <?php endif; ?>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>

        <?php if ($totalPaginas > 1): ?>
            <nav class="paginacion-admin" aria-label="Paginacion de cupones">
                <?php for ($pagina = 1; $pagina <= $totalPaginas; $pagina++): ?>
                    <a class="paginacion-admin__enlace <?php echo $pagina === $paginaActual ? 'is-activo' : ''; ?>" href="<?php echo construir_ruta_cupones($busqueda, $pagina); ?>"><?php echo $pagina; ?></a>
                <?php endfor; ?>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
</section>

<?php require_once __DIR__ . '/../includes/pie_admin.php'; ?>

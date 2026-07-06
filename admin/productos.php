<?php

/**
 * Modulo: listado de productos admin.
 * Responsabilidad: ver productos activos/inactivos y dar de baja productos sin borrar historial.
 */

require_once __DIR__ . '/../config/conexion_DB.php';
$conexion = obtener_conexion_db();
requiere_admin();

$vistasPermitidas = ['activos', 'inactivos'];
$vista = limpiar_entrada((string) ($_POST['vista'] ?? $_GET['vista'] ?? 'activos'));

if (!in_array($vista, $vistasPermitidas, true)) {
    $vista = 'activos';
}

function construir_ruta_productos(string $vista = 'activos'): string
{
    return '/admin/productos.php?vista=' . urlencode($vista);
}

if (es_post()) {
    $accion = limpiar_entrada((string) ($_POST['accion'] ?? ''));
    $idProducto = (int) ($_POST['id_producto'] ?? 0);

    if ($idProducto > 0 && in_array($accion, ['dar_baja', 'activar'], true)) {
        $estadoProducto = $accion === 'activar' ? 'disponible' : 'inactivo';
        $estadoVariantes = $accion === 'activar' ? 'activo' : 'inactivo';

        try {
            $conexion->beginTransaction();

            $sentenciaProducto = $conexion->prepare(
                'UPDATE producto
                 SET estado = :estado,
                     fecha_actualizacion = NOW()
                 WHERE id_producto = :id_producto'
            );
            $sentenciaProducto->execute([
                ':estado' => $estadoProducto,
                ':id_producto' => $idProducto,
            ]);

            $sentenciaVariantes = $conexion->prepare(
                'UPDATE producto_variante
                 SET estado = :estado,
                     fecha_actualizacion = NOW()
                 WHERE id_producto = :id_producto'
            );
            $sentenciaVariantes->execute([
                ':estado' => $estadoVariantes,
                ':id_producto' => $idProducto,
            ]);

            $conexion->commit();

            guardar_flash(
                'mensaje_exito',
                $accion === 'activar'
                    ? 'Producto activado correctamente.'
                    : 'Producto dado de baja correctamente. Sus variantes tambien quedaron inactivas.'
            );
        } catch (Throwable $ex) {
            if ($conexion->inTransaction()) {
                $conexion->rollBack();
            }

            registrar_error_sistema('No se pudo cambiar el estado del producto', $ex->getMessage());
            guardar_flash('mensaje_error', 'No se pudo actualizar el estado del producto.');
        }

        redirigir(construir_ruta_productos($vista));
    }
}

$todosLosProductos = obtener_productos($conexion, '', false);
$totalActivos = count(array_filter($todosLosProductos, static function (array $producto): bool {
    return (string) $producto['estado'] === 'disponible';
}));
$totalInactivos = count(array_filter($todosLosProductos, static function (array $producto): bool {
    return (string) $producto['estado'] !== 'disponible';
}));

$productos = array_values(array_filter($todosLosProductos, static function (array $producto) use ($vista): bool {
    $estaActivo = (string) $producto['estado'] === 'disponible';
    return $vista === 'activos' ? $estaActivo : !$estaActivo;
}));

require_once __DIR__ . '/../includes/cabecera_admin.php';
?>

<header class="encabezado-panel">
    <h1><?php echo $vista === 'inactivos' ? 'Productos inactivos' : 'Modificacion de Producto'; ?></h1>
    <p><?php echo $vista === 'inactivos' ? 'Productos retirados del catalogo' : 'Editar productos existentes'; ?></p>
</header>

<section class="panel-seccion">
    <div class="pedidos-panel usuarios-panel-filtros">
        <div class="pedidos-panel__barra">
            <div class="pedidos-pestanas" aria-label="Vistas de productos">
                <a class="pedidos-pestanas__enlace <?php echo $vista === 'activos' ? 'is-activo' : ''; ?>" href="<?php echo construir_ruta_productos('activos'); ?>">
                    Activos <span><?php echo $totalActivos; ?></span>
                </a>
                <a class="pedidos-pestanas__enlace <?php echo $vista === 'inactivos' ? 'is-activo' : ''; ?>" href="<?php echo construir_ruta_productos('inactivos'); ?>">
                    Inactivos <span><?php echo $totalInactivos; ?></span>
                </a>
            </div>
        </div>
    </div>

    <?php if ($productos === []): ?>
        <div class="contenido-vacio-admin">No hay productos <?php echo $vista === 'inactivos' ? 'inactivos' : 'activos'; ?> para mostrar.</div>
    <?php else: ?>
        <div class="lista-admin">
            <?php foreach ($productos as $producto): ?>
                <?php
                $variantes = obtener_variantes_producto($conexion, (int) $producto['id_producto']);
                $variantesActivas = array_values(array_filter($variantes, static function (array $variante): bool {
                    return $variante['estado'] === 'activo';
                }));
                $variantesConStock = array_values(array_filter($variantesActivas, static function (array $variante): bool {
                    return (int) $variante['stock'] > 0;
                }));
                $tallesConStock = array_map(static function (array $variante): string {
                    return (string) $variante['talle'];
                }, $variantesConStock);
                $imagen = obtener_ruta_imagen_producto($producto['imagen'] ?? null);
                $estado = (string) $producto['estado'] === 'disponible' ? 'Activo' : 'Inactivo';
                ?>
                <article class="fila-producto">
                    <div class="fila-producto__encabezado">
                        <div class="producto-card__fila">
                            <img class="producto-card__imagen" src="<?php echo $imagen; ?>" alt="<?php echo sanear_texto($producto['nombre_producto']); ?>">
                            <div>
                                <h3 class="producto-card__nombre"><?php echo sanear_texto($producto['nombre_producto']); ?></h3>
                            </div>
                        </div>

                        <div class="acciones-fila producto-card__chips">
                            <a class="boton-terciario" href="/admin/producto_detalle.php?id=<?php echo (int) $producto['id_producto']; ?>">Ver producto</a>
                            <?php if ($vista === 'activos'): ?>
                                <a class="boton-terciario boton-terciario--azul" href="/admin/producto_formulario.php?id=<?php echo (int) $producto['id_producto']; ?>">Modificar</a>
                                <form method="post" data-confirmar="Queres dar de baja este producto? No se borrara el historial de pedidos.">
                                    <input type="hidden" name="accion" value="dar_baja">
                                    <input type="hidden" name="id_producto" value="<?php echo (int) $producto['id_producto']; ?>">
                                    <input type="hidden" name="vista" value="<?php echo sanear_texto($vista); ?>">
                                    <button class="boton-terciario boton-terciario--rojo" type="submit">Dar de baja</button>
                                </form>
                            <?php else: ?>
                                <form method="post" data-confirmar="Queres volver a activar este producto y sus variantes?">
                                    <input type="hidden" name="accion" value="activar">
                                    <input type="hidden" name="id_producto" value="<?php echo (int) $producto['id_producto']; ?>">
                                    <input type="hidden" name="vista" value="<?php echo sanear_texto($vista); ?>">
                                    <button class="boton-terciario boton-terciario--azul" type="submit">Activar</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="producto-card__chips">
                        <span class="etiqueta <?php echo $vista === 'activos' ? 'etiqueta--verde' : 'etiqueta--rojo'; ?>"><?php echo $estado; ?></span>
                        <span class="etiqueta etiqueta--azul"><?php echo count($variantesActivas) === 1 ? '1 variante activa' : count($variantesActivas) . ' variantes activas'; ?><?php echo $tallesConStock !== [] ? ' (' . sanear_texto(implode(', ', $tallesConStock)) . ')' : ''; ?></span>
                        <span class="etiqueta etiqueta--gris">Precio: <?php echo formatear_precio((float) $producto['precio']); ?></span>
                        <span class="etiqueta etiqueta--amarillo"><?php echo ((int) $producto['oferta'] === 1) ? 'Oferta' : 'Precio normal'; ?></span>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<?php require_once __DIR__ . '/../includes/pie_admin.php'; ?>

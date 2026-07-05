<?php

/**
 * Modulo: listado de productos admin.
 * Responsabilidad: ver productos, acceder a modificacion y dar de baja.
 */

require_once __DIR__ . '/../config/conexion_DB.php';
$conexion = obtener_conexion_db();
requiere_admin();

if (es_post()) {
    $accion = trim(strip_tags((string) ($_POST['accion'] ?? '')));
    $idProducto = (int) ($_POST['id_producto'] ?? 0);

    if ($accion === 'eliminar' && $idProducto > 0) {
        try {
            $conexion->beginTransaction();
            $sentenciaVariantes = $conexion->prepare('SELECT id_variante FROM producto_variante WHERE id_producto = :id_producto');
            $sentenciaVariantes->execute([':id_producto' => $idProducto]);
            $idsVariantes = $sentenciaVariantes->fetchAll(PDO::FETCH_COLUMN) ?: [];

            if ($idsVariantes !== []) {
                $marcadores = implode(',', array_fill(0, count($idsVariantes), '?'));
                $sentenciaVariantesEliminar = $conexion->prepare("DELETE FROM producto_variante WHERE id_variante IN ($marcadores)");
                $sentenciaVariantesEliminar->execute($idsVariantes);
            }

            $sentenciaProducto = $conexion->prepare('DELETE FROM producto WHERE id_producto = :id_producto');
            $sentenciaProducto->execute([':id_producto' => $idProducto]);

            $conexion->commit();
            guardar_flash('mensaje_exito', 'Producto eliminado.');
        } catch (Throwable $ex) {
            if ($conexion->inTransaction()) {
                $conexion->rollBack();
            }

            registrar_error_sistema('No se pudo eliminar el producto', $ex->getMessage());
            $sentencia = $conexion->prepare('UPDATE producto SET estado = :estado, fecha_actualizacion = NOW() WHERE id_producto = :id_producto');
            $sentencia->execute([':estado' => 'inactivo', ':id_producto' => $idProducto]);
            $conexion->prepare('UPDATE producto_variante SET estado = :estado, fecha_actualizacion = NOW() WHERE id_producto = :id_producto')
                ->execute([':estado' => 'inactivo', ':id_producto' => $idProducto]);
            guardar_flash('mensaje_error', 'No se pudo eliminar de forma permanente porque tiene datos relacionados (por ejemplo, pedidos). Se desactivó el producto.');
        }

        redirigir('/admin/productos.php');
    }
}

$productos = obtener_productos($conexion, '', false);
require_once __DIR__ . '/../includes/cabecera_admin.php';
?>

<header class="encabezado-panel">
    <h1>Modificación de Producto</h1>
    <p>Editar productos existentes</p>
</header>

<section class="panel-seccion">
    <?php if ($productos === []): ?>
        <div class="contenido-vacio-admin">Todavía no hay productos cargados.</div>
    <?php else: ?>
        <div class="lista-admin">
            <?php foreach ($productos as $producto): ?>
                <?php
                $variantes = obtener_variantes_producto($conexion, (int) $producto['id_producto']);
                $variantesConStock = array_values(array_filter($variantes, static function (array $variante): bool {
                    return $variante['estado'] === 'activo' && (int) $variante['stock'] > 0;
                }));
                $tallesConStock = array_map(static function (array $variante): string {
                    return (string) $variante['talle'];
                }, $variantesConStock);
                $imagen = obtener_ruta_imagen_producto($producto['imagen'] ?? null);
                $estado = $producto['estado'] === 'disponible' ? 'Activo' : 'Inactivo';
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
                            <a class="boton-terciario boton-terciario--azul" href="/admin/producto_formulario.php?id=<?php echo (int) $producto['id_producto']; ?>">Modificar</a>
                            <form method="post" data-confirmar="¿Querés eliminar este producto? Esta acción puede dejarlo inactivo si existen pedidos.">
                                <input type="hidden" name="accion" value="eliminar">
                                <input type="hidden" name="id_producto" value="<?php echo (int) $producto['id_producto']; ?>">
                                <button class="boton-terciario boton-terciario--rojo" type="submit">Eliminar</button>
                            </form>
                        </div>
                    </div>

                    <div class="producto-card__chips">
                        <span class="etiqueta etiqueta--verde"><?php echo $estado; ?></span>
                        <span class="etiqueta etiqueta--azul"><?php echo count($variantesConStock) === 1 ? '1 variante' : count($variantesConStock) . ' variantes'; ?><?php echo $tallesConStock !== [] ? ' (' . sanear_texto(implode(', ', $tallesConStock)) . ')' : ''; ?></span>
                        <span class="etiqueta etiqueta--gris">Precio: <?php echo formatear_precio((float) $producto['precio']); ?></span>
                        <span class="etiqueta etiqueta--amarillo"><?php echo ((int) $producto['oferta'] === 1) ? 'Oferta' : 'Precio normal'; ?></span>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<?php require_once __DIR__ . '/../includes/pie_admin.php'; ?>

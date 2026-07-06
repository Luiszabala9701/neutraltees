<?php

/**
 * Modulo: cabecera de administrador.
 * Responsabilidad: proteger y renderizar el layout lateral del dashboard.
 */

require_once __DIR__ . '/../config/conexion_DB.php';
requiere_admin();
$conexion = obtener_conexion_db();
// El dashboard no debe quedar cacheado porque contiene datos privados.
$sinCache = [
    'Cache-Control: no-store, no-cache, must-revalidate, max-age=0',
    'Pragma: no-cache',
    'Expires: 0',
];

foreach ($sinCache as $cabecera) {
    header($cabecera);
}

$usuarioActual = usuario_actual();
$flashExito = obtener_flash('mensaje_exito');
$flashError = obtener_flash('mensaje_error');
// La ruta actual permite abrir automaticamente el menu lateral correspondiente.
$rutaActualAdmin = basename((string) parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH));
$menuProductosAbierto = in_array($rutaActualAdmin, ['producto_formulario.php', 'producto_detalle.php', 'productos.php', 'agregar_variante.php'], true);
$menuStockAbierto = in_array($rutaActualAdmin, ['movimientos_stock.php', 'historial_movimientos.php'], true);
$menuPedidosAbierto = $rutaActualAdmin === 'pedidos.php' || $rutaActualAdmin === 'pedido_detalle.php';
$menuCuponesAbierto = in_array($rutaActualAdmin, ['cupon_formulario.php', 'cupones.php'], true);
$menuCuentaAbierto = in_array($rutaActualAdmin, ['seguridad.php', 'ayuda.php'], true);

?><!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NeutralTees - Administración</title>
    <link rel="icon" href="/src/neutralTees.ico" type="image/x-icon">
    <link rel="stylesheet" href="/assets/css/estilos.css">
    <script defer src="/assets/js/app.js"></script>
</head>
<body class="cuerpo-admin" data-control-inactividad data-tiempo-inactividad="<?php echo tiempo_limite_inactividad_sesion(); ?>">
    <?php if ($flashExito): ?>
        <div class="mensaje-flash mensaje-exito"><?php echo sanear_texto($flashExito); ?></div>
    <?php endif; ?>
    <?php if ($flashError): ?>
        <div class="mensaje-flash mensaje-error"><?php echo sanear_texto($flashError); ?></div>
    <?php endif; ?>
    <div class="panel-admin">
        <aside class="panel-admin__barra">
            <div>
                <a class="marca marca--clara" href="/admin/index.php">NeutralTees</a>
                <p class="subtitulo-barra">Admin Dashboard</p>
            </div>

            <nav class="menu-admin" aria-label="Menú de administración">
                <a class="menu-admin__enlace" href="/admin/index.php">Inicio</a>
                <details class="menu-admin__desplegable" <?php echo $menuProductosAbierto ? 'open' : ''; ?>>
                    <summary class="menu-admin__enlace menu-admin__resumen">Productos</summary>
                    <div class="menu-admin__subenlaces">
                        <a class="menu-admin__subenlace" href="/admin/producto_formulario.php">Alta producto</a>
                        <a class="menu-admin__subenlace" href="/admin/productos.php">Ver productos</a>
                        <a class="menu-admin__subenlace" href="/admin/productos.php?vista=inactivos">Productos inactivos</a>
                        <a class="menu-admin__subenlace" href="/admin/agregar_variante.php">Agregar variante</a>
                    </div>
                </details>

                <details class="menu-admin__desplegable" <?php echo $menuStockAbierto ? 'open' : ''; ?>>
                    <summary class="menu-admin__enlace menu-admin__resumen">Movimientos de Stock</summary>
                    <div class="menu-admin__subenlaces">
                        <a class="menu-admin__subenlace" href="/admin/movimientos_stock.php">Movimiento de stock</a>
                        <a class="menu-admin__subenlace" href="/admin/historial_movimientos.php">Historial de movimientos</a>
                    </div>
                </details>
                <details class="menu-admin__desplegable" <?php echo $menuPedidosAbierto ? 'open' : ''; ?>>
                    <summary class="menu-admin__enlace menu-admin__resumen">Pedidos</summary>
                    <div class="menu-admin__subenlaces">
                        <a class="menu-admin__subenlace" href="/admin/pedidos.php?vista=activos">Activos</a>
                        <a class="menu-admin__subenlace" href="/admin/pedidos.php?vista=archivados">Archivados</a>
                    </div>
                </details>
                <details class="menu-admin__desplegable" <?php echo $menuCuponesAbierto ? 'open' : ''; ?>>
                    <summary class="menu-admin__enlace menu-admin__resumen">Cupones</summary>
                    <div class="menu-admin__subenlaces">
                        <a class="menu-admin__subenlace" href="/admin/cupon_formulario.php">Crear cupón</a>
                        <a class="menu-admin__subenlace" href="/admin/cupones.php">Cupones activos</a>
                        <a class="menu-admin__subenlace" href="/admin/cupones.php?vista=archivados">Cupones archivados</a>
                    </div>
                </details>
                <a class="menu-admin__enlace" href="/admin/usuarios.php">Usuarios</a>
                <details class="menu-admin__desplegable" <?php echo $menuCuentaAbierto ? 'open' : ''; ?>>
                    <summary class="menu-admin__enlace menu-admin__resumen">Cuenta</summary>
                    <div class="menu-admin__subenlaces">
                        <a class="menu-admin__subenlace" href="/admin/seguridad.php">Seguridad</a>
                        <a class="menu-admin__subenlace" href="/admin/ayuda.php">Ayuda</a>
                        <form method="post" action="/eliminar_cuenta.php" data-confirmar="Seguro que queres eliminar tu cuenta administradora?" data-confirmar-aceptar="Si" data-confirmar-cancelar="No">
                            <button class="menu-admin__subenlace menu-admin__subenlace--peligro" type="submit">Eliminar cuenta</button>
                        </form>
                        <form method="post" action="/salir.php" data-confirmar="Cerrar sesion del panel?">
                            <button class="menu-admin__subenlace" type="submit">Cerrar sesion</button>
                        </form>
                    </div>
                </details>
            </nav>
        </aside>
        <section class="panel-admin__contenido">

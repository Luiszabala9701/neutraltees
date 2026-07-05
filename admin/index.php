<?php

/**
 * Modulo: inicio del dashboard.
 * Responsabilidad: mostrar resumen general de productos, pedidos, usuarios y ventas.
 */

require_once __DIR__ . '/../config/conexion_DB.php';
$conexion = obtener_conexion_db();
requiere_admin();
$resumen = obtener_resumen_admin($conexion);

require_once __DIR__ . '/../includes/cabecera_admin.php';
?>

<header class="encabezado-panel">
    <h1>Inicio</h1>
    <p>Vista general del sistema</p>
</header>

<section class="panel-seccion">
    <div class="tarjeta-resumen tarjeta-resumen--bienvenida">
        <p class="etiqueta etiqueta--amarillo etiqueta--administrador">Administrador</p>
        <h2 class="tarjeta-resumen__titulo-bienvenida">Hola, <?php echo sanear_texto($usuarioActual['nombre'] ?? 'Administrador'); ?></h2>
        <p class="tarjeta-resumen__texto-bienvenida">Tenés <?php echo (int) $resumen['pedidos']; ?> pedidos en el sistema.</p>
    </div>

    <div class="rejilla-resumen">
        <article class="tarjeta-resumen">
            <p class="tarjeta-resumen__texto">Productos</p>
            <div class="tarjeta-resumen__valor"><?php echo (int) $resumen['productos']; ?></div>
            <p class="tarjeta-resumen__texto">Productos cargados</p>
        </article>
        <article class="tarjeta-resumen">
            <p class="tarjeta-resumen__texto">Pedidos</p>
            <div class="tarjeta-resumen__valor"><?php echo (int) $resumen['pedidos']; ?></div>
            <p class="tarjeta-resumen__texto">Pedidos registrados</p>
        </article>
        <article class="tarjeta-resumen">
            <p class="tarjeta-resumen__texto">Usuarios</p>
            <div class="tarjeta-resumen__valor"><?php echo (int) $resumen['usuarios']; ?></div>
            <p class="tarjeta-resumen__texto">Cuentas activas e inactivas</p>
        </article>
        <article class="tarjeta-resumen">
            <p class="tarjeta-resumen__texto">Ventas</p>
            <div class="tarjeta-resumen__valor"><?php echo formatear_precio((float) $resumen['ventas']); ?></div>
            <p class="tarjeta-resumen__texto">Suma de pedidos</p>
        </article>
    </div>
</section>

<?php require_once __DIR__ . '/../includes/pie_admin.php'; ?>

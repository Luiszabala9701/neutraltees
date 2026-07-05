<?php

/**
 * Modulo: ayuda del administrador.
 * Responsabilidad: explicar las acciones principales del dashboard.
 */

require_once __DIR__ . '/../config/conexion_DB.php';
$conexion = obtener_conexion_db();
requiere_admin();

require_once __DIR__ . '/../includes/cabecera_admin.php';
?>

<header class="encabezado-panel">
    <h1>Ayuda</h1>
    <p>Guia rapida para administrar NeutralTees.</p>
</header>

<section class="panel-seccion">
    <div class="lista-admin">
        <article class="tarjeta-resumen">
            <h2 class="tarjeta-formulario__texto u-mb-0">Inicio</h2>
            <p class="tarjeta-resumen__texto">Muestra un resumen general de productos, pedidos, usuarios y ventas registradas.</p>
        </article>

        <article class="tarjeta-resumen">
            <h2 class="tarjeta-formulario__texto u-mb-0">Alta de producto</h2>
            <p class="tarjeta-resumen__texto">Desde Productos, entra en Alta producto para cargar nombre, descripcion, precio, imagen y estado inicial del producto.</p>
        </article>

        <article class="tarjeta-resumen">
            <h2 class="tarjeta-formulario__texto u-mb-0">Ver productos</h2>
            <p class="tarjeta-resumen__texto">Permite consultar los productos cargados. Desde cada producto podes entrar a modificar sus datos principales o darlo de baja.</p>
        </article>

        <article class="tarjeta-resumen">
            <h2 class="tarjeta-formulario__texto u-mb-0">Agregar variante</h2>
            <p class="tarjeta-resumen__texto">Sirve para sumar un talle nuevo a un producto existente. Se elige el producto, el talle disponible, el stock inicial y el SKU.</p>
        </article>

        <article class="tarjeta-resumen">
            <h2 class="tarjeta-formulario__texto u-mb-0">Movimiento de stock</h2>
            <p class="tarjeta-resumen__texto">Permite registrar ingresos o egresos de stock. Primero selecciona el producto, despues la variante y luego indica la cantidad.</p>
        </article>

        <article class="tarjeta-resumen">
            <h2 class="tarjeta-formulario__texto u-mb-0">Historial de movimientos</h2>
            <p class="tarjeta-resumen__texto">Muestra los cambios de stock realizados, con producto, talle, cantidad, stock anterior, stock resultante y observacion.</p>
        </article>

        <article class="tarjeta-resumen">
            <h2 class="tarjeta-formulario__texto u-mb-0">Pedidos activos</h2>
            <p class="tarjeta-resumen__texto">Lista los pedidos pendientes, en preparacion o preparados. Desde acciones podes avanzar el estado, marcar pago recibido o cancelar cuando corresponda.</p>
        </article>

        <article class="tarjeta-resumen">
            <h2 class="tarjeta-formulario__texto u-mb-0">Pedidos archivados</h2>
            <p class="tarjeta-resumen__texto">Reune pedidos entregados o cancelados. Estas ordenes quedan para consulta y no tienen acciones de edicion.</p>
        </article>

        <article class="tarjeta-resumen">
            <h2 class="tarjeta-formulario__texto u-mb-0">Cupones</h2>
            <p class="tarjeta-resumen__texto">Desde Crear cupon podes cargar descuentos. En Cupones activos podes revisar los disponibles y dar de baja los que ya no quieras ofrecer.</p>
        </article>

        <article class="tarjeta-resumen">
            <h2 class="tarjeta-formulario__texto u-mb-0">Usuarios</h2>
            <p class="tarjeta-resumen__texto">Permite buscar cuentas por nombre, filtrar por rol o estado y dar de baja usuarios clientes cuando sea necesario.</p>
        </article>

        <article class="tarjeta-resumen">
            <h2 class="tarjeta-formulario__texto u-mb-0">Seguridad</h2>
            <p class="tarjeta-resumen__texto">Desde Seguridad podes cambiar tu contrasena del panel. Tambien podes pedir un codigo por mail si no recordas la actual.</p>
        </article>

        <article class="tarjeta-resumen">
            <h2 class="tarjeta-formulario__texto u-mb-0">Eliminar cuenta administradora</h2>
            <p class="tarjeta-resumen__texto">Desde Cuenta podes eliminar tu propia cuenta con confirmacion previa. Si es la unica cuenta administradora activa, la baja se bloquea para no dejar el panel sin acceso.</p>
        </article>
    </div>
</section>

<?php require_once __DIR__ . '/../includes/pie_admin.php'; ?>

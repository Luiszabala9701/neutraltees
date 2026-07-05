<?php

/**
 * Modulo: cabecera publica.
 * Responsabilidad: renderizar navegacion, buscador, perfil y mensajes flash.
 */

require_once __DIR__ . '/../config/conexion_DB.php';
$conexion = obtener_conexion_db();
// Evita que paginas con sesion o carrito queden guardadas en el navegador.
$sinCache = [
    'Cache-Control: no-store, no-cache, must-revalidate, max-age=0',
    'Pragma: no-cache',
    'Expires: 0',
];

foreach ($sinCache as $cabecera) {
    header($cabecera);
}

// Datos compartidos por el menu superior en todas las paginas publicas.
$usuarioActual = usuario_actual();
$cantidadCarrito = obtener_cantidad_carrito();
$flashExito = obtener_flash('mensaje_exito');
$flashError = obtener_flash('mensaje_error');

?><!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NeutralTees</title>
    <link rel="icon" href="/src/neutralTees.ico" type="image/x-icon">
    <link rel="stylesheet" href="/assets/css/estilos.css">
    <script defer src="/assets/js/app.js"></script>
</head>
<body class="cuerpo-publico"<?php echo $usuarioActual ? ' data-control-inactividad data-tiempo-inactividad="' . tiempo_limite_inactividad_sesion() . '"' : ''; ?>>
    <?php if ($flashExito): ?>
        <div class="mensaje-flash mensaje-exito"><?php echo sanear_texto($flashExito); ?></div>
    <?php endif; ?>
    <?php if ($flashError): ?>
        <div class="mensaje-flash mensaje-error"><?php echo sanear_texto($flashError); ?></div>
    <?php endif; ?>
    <header class="encabezado-publico">
        <div class="contenedor encabezado-publico__contenido">
            <a class="marca" href="/index.php">NeutralTees</a>

            <nav class="navegacion-publica" aria-label="Navegación principal">
                <a class="navegacion-publica__enlace" href="/index.php">Inicio</a>
                <a class="navegacion-publica__enlace" href="/index.php?catalogo=1#catalogo">Catálogo</a>
                <a class="navegacion-publica__enlace" href="/index.php?ofertas=1">Ofertas</a>
                <a class="navegacion-publica__enlace" href="/faq.php">Ayuda</a>
            </nav>

            <form class="buscador-publico" method="get" action="/busqueda.php">
                <input class="buscador-publico__entrada" type="search" name="q" placeholder="Buscar productos..." value="<?php echo isset($_GET['q']) ? sanear_texto((string) $_GET['q']) : (isset($_GET['busqueda']) ? sanear_texto((string) $_GET['busqueda']) : ''); ?>" data-buscador-publico>
            </form>

            <div class="acciones-publicas">
                <a class="acciones-publicas__icono" href="/carrito.php" title="Ver carrito" aria-label="Ver carrito" data-enlace-carrito>
                    <span class="acciones-publicas__icono-emoji">🛒</span>
                    <span class="contador-carrito" <?php echo $cantidadCarrito > 0 ? '' : 'hidden'; ?>><?php echo (string) $cantidadCarrito; ?></span>
                </a>
                <?php if ($usuarioActual): ?>
                    <details class="perfil-usuario">
                        <summary class="acciones-publicas__icono perfil-usuario__resumen" aria-label="Abrir opciones de usuario">
                            <span class="acciones-publicas__icono-emoji">👤</span>
                        </summary>
                        <div class="perfil-usuario__menu">
                            <a class="perfil-usuario__enlace" href="/perfil.php">Perfil</a>
                            <a class="perfil-usuario__enlace" href="/cupones.php">Cupones</a>
                            <a class="perfil-usuario__enlace" href="/seguridad.php">Seguridad</a>
                            <button class="perfil-usuario__boton" type="submit" form="form-cerrar-sesion-publico">Cerrar sesión</button>
                        </div>
                    </details>
                <?php else: ?>
                    <a class="acciones-publicas__icono" href="/login.php" title="Acceso de usuario" aria-label="Acceso de usuario">
                        <span class="acciones-publicas__icono-emoji">👤</span>
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </header>
    <form id="form-cerrar-sesion-publico" method="post" action="/salir.php" data-confirmar="¿Querés cerrar sesión?"></form>
    <main class="contenedor contenido-principal">

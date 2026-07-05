<?php

/**
 * Modulo: expiracion por inactividad.
 * Responsabilidad: cerrar la sesion cuando el navegador detecta inactividad prolongada.
 */

require_once __DIR__ . '/config/conexion_DB.php';

if (usuario_actual()) {
    destruir_sesion_actual();
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

guardar_flash('mensaje_error', 'Tu sesión expiró por inactividad. Volvé a iniciar sesión.');
redirigir('/login.php');

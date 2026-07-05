<?php

/**
 * Modulo: cierre de sesion.
 * Responsabilidad: limpiar la sesion actual y volver al inicio.
 */

require_once __DIR__ . '/config/conexion_DB.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
	redirigir('/login.php');
}

destruir_sesion_actual();
redirigir('/login.php');

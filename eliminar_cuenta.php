<?php

/**
 * Modulo: baja de cuenta propia.
 * Responsabilidad: permitir que cliente o administrador desactive su cuenta actual.
 */

require_once __DIR__ . '/config/conexion_DB.php';
$conexion = obtener_conexion_db();
requiere_login();

if (!es_post()) {
    redirigir(es_admin() ? '/admin/index.php' : '/perfil.php');
}

$usuarioActual = usuario_actual();
$idUsuario = (int) ($usuarioActual['id_usuario'] ?? 0);

if ($idUsuario <= 0) {
    guardar_flash('mensaje_error', 'No pudimos identificar la cuenta.');
    redirigir('/login.php');
}

/* Si el administrador actual es el unico activo, no se permite la baja para
   evitar que el panel quede sin una cuenta con permisos de acceso. */
if (es_admin()) {
    $sentenciaAdmins = $conexion->query('SELECT COUNT(*) FROM usuario WHERE is_admin = 1 AND activo = 1');
    $adminsActivos = (int) $sentenciaAdmins->fetchColumn();

    if ($adminsActivos <= 1) {
        guardar_flash('mensaje_error', 'No se puede eliminar la unica cuenta administradora activa.');
        redirigir('/admin/seguridad.php');
    }
}

dar_baja_cuenta_usuario($conexion, $idUsuario);
destruir_sesion_actual();

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

guardar_flash('mensaje_exito', 'Tu cuenta fue eliminada correctamente.');
redirigir('/login.php');

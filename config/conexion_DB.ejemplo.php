<?php

/**
 * Modulo: conexion a base de datos.
 * Responsabilidad: crear una instancia PDO reusable para MySQL.
 *
 * IMPORTANTE:
 * Copiar este archivo como conexion_DB.php y completar los datos reales
 * del hosting o del entorno local antes de ejecutar el proyecto.
 */

declare(strict_types=1);

require_once __DIR__ . '/funciones.php';

/**
 * Devuelve una unica conexion PDO durante la peticion actual.
 */
function obtener_conexion_db(): PDO
{
    static $conexion = null;

    if ($conexion instanceof PDO) {
        return $conexion;
    }

    $host = 'localhost';
    $nombreBase = 'nombre_base_de_datos';
    $usuario = 'usuario_base_de_datos';
    $contrasena = 'contrasena_base_de_datos';
    $charset = 'utf8mb4';

    $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', $host, $nombreBase, $charset);

    try {
        $conexion = new PDO($dsn, $usuario, $contrasena, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    } catch (Throwable $ex) {
        registrar_error_sistema('Error de conexion con MySQL', $ex->getMessage());
        http_response_code(500);
        exit('No se pudo conectar con la base de datos. Revisa la configuracion.');
    }

    return $conexion;
}

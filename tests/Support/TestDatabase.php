<?php

declare(strict_types=1);

final class TestDatabase
{
    public static function sqlite(): PDO
    {
        $conexion = new PDO('sqlite::memory:');
        $conexion->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $conexion->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $conexion->sqliteCreateFunction('NOW', static fn (): string => date('Y-m-d H:i:s'));

        return $conexion;
    }

    public static function mysqlDisponible(): bool
    {
        try {
            self::mysqlServidor();
            return true;
        } catch (Throwable) {
            return false;
        }
    }

    public static function mysqlServidor(): PDO
    {
        return new PDO('mysql:host=localhost;charset=utf8mb4', 'root', '', [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }

    public static function mysqlNeutralTeesTest(): PDO
    {
        $servidor = self::mysqlServidor();
        $servidor->exec('CREATE DATABASE IF NOT EXISTS neutraltees_phpunit CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');

        return new PDO('mysql:host=localhost;dbname=neutraltees_phpunit;charset=utf8mb4', 'root', '', [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }
}

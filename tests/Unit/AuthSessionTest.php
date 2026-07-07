<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class AuthSessionTest extends TestCase
{
    private PDO $conexion;

    protected function setUp(): void
    {
        $_SESSION = [];
        $this->conexion = TestDatabase::sqlite();
        $this->conexion->exec(
            'CREATE TABLE usuario (
                id_usuario INTEGER PRIMARY KEY,
                nombre TEXT NOT NULL,
                apellido TEXT NOT NULL,
                mail TEXT NOT NULL,
                password TEXT NULL,
                is_admin INTEGER NOT NULL DEFAULT 0,
                email_verificado INTEGER NOT NULL DEFAULT 1,
                activo INTEGER NOT NULL DEFAULT 1,
                sesion_activa_token TEXT NULL,
                fecha_actualizacion TEXT NULL
            )'
        );
    }

    public function testBuscarUsuarioPorCorreoPermiteValidarUnicidad(): void
    {
        $this->insertarUsuario(id: 1, mail: 'admin@neutraltees.test', isAdmin: 1);

        $usuario = obtener_usuario_por_correo($this->conexion, 'admin@neutraltees.test');

        self::assertNotNull($usuario);
        self::assertSame(1, (int) $usuario['is_admin']);
        self::assertNull(obtener_usuario_por_correo($this->conexion, 'otro@neutraltees.test'));
    }

    public function testIniciarSesionGuardaTokenYRolCorrectos(): void
    {
        $this->insertarUsuario(id: 2, mail: 'cliente@neutraltees.test', isAdmin: 0);
        $usuario = obtener_usuario_por_correo($this->conexion, 'cliente@neutraltees.test');

        iniciar_sesion_usuario($this->conexion, $usuario);

        self::assertSame(2, $_SESSION['usuario_actual']['id_usuario']);
        self::assertFalse($_SESSION['usuario_actual']['is_admin']);
        self::assertNotEmpty($_SESSION['usuario_actual']['sesion_token']);
        self::assertSame($_SESSION['usuario_actual']['sesion_token'], $this->tokenPersistido(2));
    }

    public function testNuevaSesionReemplazaTokenAnterior(): void
    {
        $this->insertarUsuario(id: 3, mail: 'cliente2@neutraltees.test', isAdmin: 0);
        $usuario = obtener_usuario_por_correo($this->conexion, 'cliente2@neutraltees.test');

        iniciar_sesion_usuario($this->conexion, $usuario);
        $primerToken = $_SESSION['usuario_actual']['sesion_token'];

        $_SESSION = [];
        iniciar_sesion_usuario($this->conexion, $usuario);
        $segundoToken = $_SESSION['usuario_actual']['sesion_token'];

        self::assertNotSame($primerToken, $segundoToken);
        self::assertSame($segundoToken, $this->tokenPersistido(3));
    }

    private function insertarUsuario(int $id, string $mail, int $isAdmin): void
    {
        $sentencia = $this->conexion->prepare(
            "INSERT INTO usuario
                (id_usuario, nombre, apellido, mail, password, is_admin, email_verificado, activo, sesion_activa_token, fecha_actualizacion)
             VALUES
                (:id_usuario, 'Usuario', 'Test', :mail, NULL, :is_admin, 1, 1, NULL, NOW())"
        );
        $sentencia->execute([
            ':id_usuario' => $id,
            ':mail' => $mail,
            ':is_admin' => $isAdmin,
        ]);
    }

    private function tokenPersistido(int $idUsuario): ?string
    {
        $sentencia = $this->conexion->prepare('SELECT sesion_activa_token FROM usuario WHERE id_usuario = :id_usuario');
        $sentencia->execute([':id_usuario' => $idUsuario]);
        $token = $sentencia->fetchColumn();

        return $token === false ? null : (string) $token;
    }
}

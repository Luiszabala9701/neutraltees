<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class CuponTest extends TestCase
{
    private PDO $conexion;

    protected function setUp(): void
    {
        $this->conexion = TestDatabase::sqlite();
        $this->conexion->exec(
            'CREATE TABLE uso_cupon (
                id_uso INTEGER PRIMARY KEY AUTOINCREMENT,
                id_pedido INTEGER NULL,
                id_usuario INTEGER NOT NULL,
                id_cupon INTEGER NOT NULL,
                monto_descuento REAL DEFAULT 0,
                fecha_uso TEXT NULL
            )'
        );
    }

    public function testCuponActivoVigenteYSinUsoEstaDisponible(): void
    {
        $resultado = validar_cupon_para_usuario($this->conexion, $this->cuponBase(), 7, 15000);

        self::assertTrue($resultado['ok']);
        self::assertStringContainsString('disponible', $resultado['mensaje']);
    }

    public function testCuponesInvalidosSeRechazan(): void
    {
        self::assertFalse(validar_cupon_para_usuario($this->conexion, $this->cuponBase(['activo' => 0]), 7, 15000)['ok']);
        self::assertFalse(validar_cupon_para_usuario($this->conexion, $this->cuponBase(['fecha_inicio' => date('Y-m-d', strtotime('+1 day'))]), 7, 15000)['ok']);
        self::assertFalse(validar_cupon_para_usuario($this->conexion, $this->cuponBase(['fecha_fin' => date('Y-m-d', strtotime('-1 day'))]), 7, 15000)['ok']);
        self::assertFalse(validar_cupon_para_usuario($this->conexion, $this->cuponBase(['compra_minima' => 20000]), 7, 15000)['ok']);
    }

    public function testCuponYaUsadoPorUsuarioNoPuedeReutilizarse(): void
    {
        $this->insertarUsoCupon(idUsuario: 7, idCupon: 10);

        $resultado = validar_cupon_para_usuario($this->conexion, $this->cuponBase(), 7, 15000);

        self::assertFalse($resultado['ok']);
        self::assertStringContainsString('usaste', $resultado['mensaje']);
    }

    public function testCuponConMaximoDeUsosAgotadoNoPuedeUsarse(): void
    {
        $cupon = $this->cuponBase(['max_usos_total' => 2]);
        $this->insertarUsoCupon(idUsuario: 1, idCupon: 10);
        $this->insertarUsoCupon(idUsuario: 2, idCupon: 10);

        $resultado = validar_cupon_para_usuario($this->conexion, $cupon, 7, 15000);

        self::assertFalse($resultado['ok']);
        self::assertStringContainsString('usos', $resultado['mensaje']);
    }

    /**
     * @param array<string,mixed> $sobrescribir
     * @return array<string,mixed>
     */
    private function cuponBase(array $sobrescribir = []): array
    {
        return array_merge([
            'id_cupon' => 10,
            'codigo' => 'TEST10',
            'tipo_descuento' => 'porcentaje',
            'valor' => 10,
            'tope_descuento' => null,
            'compra_minima' => 10000,
            'max_usos_total' => null,
            'activo' => 1,
            'fecha_inicio' => date('Y-m-d', strtotime('-1 day')),
            'fecha_fin' => date('Y-m-d', strtotime('+1 day')),
        ], $sobrescribir);
    }

    private function insertarUsoCupon(int $idUsuario, int $idCupon): void
    {
        $sentencia = $this->conexion->prepare(
            'INSERT INTO uso_cupon (id_usuario, id_cupon, monto_descuento, fecha_uso)
             VALUES (:id_usuario, :id_cupon, 100, :fecha_uso)'
        );
        $sentencia->execute([
            ':id_usuario' => $idUsuario,
            ':id_cupon' => $idCupon,
            ':fecha_uso' => date('Y-m-d H:i:s'),
        ]);
    }
}

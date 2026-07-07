<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class PedidoCancelacionTest extends TestCase
{
    private PDO $conexion;

    protected function setUp(): void
    {
        if (!TestDatabase::mysqlDisponible()) {
            self::markTestSkipped('MySQL no disponible para pruebas de cancelacion.');
        }

        $this->conexion = TestDatabase::mysqlNeutralTeesTest();
        $this->crearEsquema();
    }

    public function testCancelarPedidoPendienteConPagoPendienteRestauraStockYRegistraMovimiento(): void
    {
        $this->insertarPedidoConDetalle(estadoPedido: 'pendiente', estadoPago: 'pendiente', stockInicial: 3, cantidad: 2);

        $resultado = cancelar_pedido(
            $this->conexion,
            1,
            ['nombre' => 'Admin', 'apellido' => 'Test'],
            'admin'
        );

        self::assertSame('pendiente', $resultado['estado_anterior']);
        self::assertSame('cancelado', $resultado['estado_nuevo']);
        self::assertSame('cancelado', $this->estadoPedido(1));
        self::assertSame(5, $this->stockVariante(1));
        self::assertSame(1, $this->cantidadMovimientos());
    }

    public function testPedidoEntregadoNoPuedeCancelarse(): void
    {
        $this->insertarPedidoConDetalle(estadoPedido: 'entregado', estadoPago: 'pendiente', stockInicial: 3, cantidad: 2);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('no se puede cancelar');

        try {
            cancelar_pedido($this->conexion, 1, ['nombre' => 'Cliente', 'apellido' => 'Test'], 'cliente', 1);
        } finally {
            self::assertSame('entregado', $this->estadoPedido(1));
            self::assertSame(3, $this->stockVariante(1));
            self::assertSame(0, $this->cantidadMovimientos());
        }
    }

    public function testPedidoPagadoNoPuedeCancelarse(): void
    {
        $this->insertarPedidoConDetalle(estadoPedido: 'preparando', estadoPago: 'pagado', stockInicial: 3, cantidad: 2);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('no se puede cancelar');

        try {
            cancelar_pedido($this->conexion, 1, ['nombre' => 'Cliente', 'apellido' => 'Test'], 'cliente', 1);
        } finally {
            self::assertSame('preparando', $this->estadoPedido(1));
            self::assertSame(3, $this->stockVariante(1));
            self::assertSame(0, $this->cantidadMovimientos());
        }
    }

    public function testClienteNoPuedeCancelarPedidoDeOtroUsuario(): void
    {
        $this->insertarPedidoConDetalle(estadoPedido: 'pendiente', estadoPago: 'pendiente', stockInicial: 3, cantidad: 2);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('No tenes permiso');

        cancelar_pedido($this->conexion, 1, ['nombre' => 'Otro', 'apellido' => 'Cliente'], 'cliente', 99);
    }

    private function crearEsquema(): void
    {
        $this->conexion->exec('SET FOREIGN_KEY_CHECKS=0');
        foreach (['movimiento_stock', 'detalle_pedido', 'pedido', 'producto_variante', 'producto', 'usuario'] as $tabla) {
            $this->conexion->exec('DROP TABLE IF EXISTS ' . $tabla);
        }
        $this->conexion->exec('SET FOREIGN_KEY_CHECKS=1');

        $this->conexion->exec(
            'CREATE TABLE usuario (
                id_usuario INT PRIMARY KEY,
                nombre VARCHAR(100) NOT NULL,
                apellido VARCHAR(100) NOT NULL,
                mail VARCHAR(150) NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );
        $this->conexion->exec(
            'CREATE TABLE producto (
                id_producto INT PRIMARY KEY,
                nombre_producto VARCHAR(150) NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );
        $this->conexion->exec(
            'CREATE TABLE producto_variante (
                id_variante INT PRIMARY KEY,
                id_producto INT NOT NULL,
                stock INT NOT NULL,
                fecha_actualizacion DATETIME NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );
        $this->conexion->exec(
            'CREATE TABLE pedido (
                id_pedido INT PRIMARY KEY,
                id_usuario INT NOT NULL,
                estado_pedido VARCHAR(30) NOT NULL,
                estado_pago VARCHAR(30) NOT NULL,
                fecha_actualizacion DATETIME NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );
        $this->conexion->exec(
            'CREATE TABLE detalle_pedido (
                id_detalle INT AUTO_INCREMENT PRIMARY KEY,
                id_pedido INT NOT NULL,
                id_variante INT NOT NULL,
                cantidad INT NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );
        $this->conexion->exec(
            'CREATE TABLE movimiento_stock (
                id_movimiento_stock INT AUTO_INCREMENT PRIMARY KEY,
                id_variante INT NOT NULL,
                tipo_movimiento VARCHAR(30) NOT NULL,
                cantidad INT NOT NULL,
                stock_anterior INT NOT NULL,
                stock_resultante INT NOT NULL,
                observacion TEXT NULL,
                fecha_movimiento DATETIME NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );
    }

    private function insertarPedidoConDetalle(string $estadoPedido, string $estadoPago, int $stockInicial, int $cantidad): void
    {
        $this->conexion->exec("INSERT INTO usuario (id_usuario, nombre, apellido, mail) VALUES (1, 'Luis', 'Test', 'luis@test.com')");
        $this->conexion->exec("INSERT INTO producto (id_producto, nombre_producto) VALUES (1, 'Remera Test')");
        $this->conexion->prepare('INSERT INTO producto_variante (id_variante, id_producto, stock, fecha_actualizacion) VALUES (1, 1, :stock, NOW())')
            ->execute([':stock' => $stockInicial]);
        $this->conexion->prepare('INSERT INTO pedido (id_pedido, id_usuario, estado_pedido, estado_pago, fecha_actualizacion) VALUES (1, 1, :estado_pedido, :estado_pago, NOW())')
            ->execute([':estado_pedido' => $estadoPedido, ':estado_pago' => $estadoPago]);
        $this->conexion->prepare('INSERT INTO detalle_pedido (id_pedido, id_variante, cantidad) VALUES (1, 1, :cantidad)')
            ->execute([':cantidad' => $cantidad]);
    }

    private function estadoPedido(int $idPedido): string
    {
        $sentencia = $this->conexion->prepare('SELECT estado_pedido FROM pedido WHERE id_pedido = :id_pedido');
        $sentencia->execute([':id_pedido' => $idPedido]);
        return (string) $sentencia->fetchColumn();
    }

    private function stockVariante(int $idVariante): int
    {
        $sentencia = $this->conexion->prepare('SELECT stock FROM producto_variante WHERE id_variante = :id_variante');
        $sentencia->execute([':id_variante' => $idVariante]);
        return (int) $sentencia->fetchColumn();
    }

    private function cantidadMovimientos(): int
    {
        return (int) $this->conexion->query('SELECT COUNT(*) FROM movimiento_stock')->fetchColumn();
    }
}

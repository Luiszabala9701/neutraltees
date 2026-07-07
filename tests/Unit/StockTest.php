<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class StockTest extends TestCase
{
    private PDO $conexion;

    protected function setUp(): void
    {
        $_SESSION = [];
        $this->conexion = TestDatabase::sqlite();
        $this->crearTablas();
        $this->insertarProductoYVariante(stock: 10, estadoProducto: 'disponible', estadoVariante: 'activo');
    }

    public function testIngresoAumentaStockYRegistraHistorial(): void
    {
        $resultado = registrar_movimiento_stock($this->conexion, 1, 'ingreso', 5, 'Ingreso PHPUnit');

        self::assertSame(['stock_anterior' => 10, 'stock_resultante' => 15], $resultado);
        self::assertSame(15, $this->stockActual());
        self::assertSame(1, $this->cantidadMovimientos());
    }

    public function testEgresoDescuentaStockYRegistraHistorial(): void
    {
        $resultado = registrar_movimiento_stock($this->conexion, 1, 'egreso', 4, 'Egreso PHPUnit');

        self::assertSame(['stock_anterior' => 10, 'stock_resultante' => 6], $resultado);
        self::assertSame(6, $this->stockActual());
        self::assertSame(1, $this->cantidadMovimientos());
    }

    public function testEgresoMayorAlStockFallaSinModificarDatos(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('No hay stock suficiente');

        try {
            registrar_movimiento_stock($this->conexion, 1, 'egreso', 11, 'Egreso imposible');
        } finally {
            self::assertSame(10, $this->stockActual());
            self::assertSame(0, $this->cantidadMovimientos());
        }
    }

    public function testStockDisponibleRespetaCarritoYLimitePorProducto(): void
    {
        agregar_al_carrito(1, 4);

        self::assertSame(6, obtener_stock_disponible_variante($this->conexion, 1));

        agregar_al_carrito(2, 6);
        self::assertSame(0, obtener_stock_disponible_variante($this->conexion, 1));
    }

    public function testProductoNoDisponibleNoTieneStockComprable(): void
    {
        $this->conexion->exec("UPDATE producto SET estado = 'pausado' WHERE id_producto = 1");

        self::assertSame(0, obtener_stock_disponible_variante($this->conexion, 1));
        self::assertSame([], obtener_variantes_con_producto($this->conexion));
    }

    public function testVarianteInactivaNoTieneStockComprable(): void
    {
        $this->conexion->exec("UPDATE producto_variante SET estado = 'inactivo' WHERE id_variante = 1");

        self::assertSame(0, obtener_stock_disponible_variante($this->conexion, 1));
        $idsDisponibles = array_map('intval', array_column(obtener_variantes_con_producto($this->conexion), 'id_variante'));
        self::assertNotContains(1, $idsDisponibles);
    }

    private function crearTablas(): void
    {
        $this->conexion->exec(
            'CREATE TABLE producto (
                id_producto INTEGER PRIMARY KEY,
                nombre_producto TEXT NOT NULL,
                precio REAL NOT NULL,
                precio_anterior REAL NULL,
                descripcion TEXT NULL,
                imagen TEXT NULL,
                oferta INTEGER DEFAULT 0,
                estado TEXT NOT NULL
            )'
        );
        $this->conexion->exec(
            'CREATE TABLE producto_variante (
                id_variante INTEGER PRIMARY KEY,
                id_producto INTEGER NOT NULL,
                talle TEXT NOT NULL,
                stock INTEGER NOT NULL,
                sku TEXT NULL,
                estado TEXT NOT NULL,
                fecha_actualizacion TEXT NULL
            )'
        );
        $this->conexion->exec(
            'CREATE TABLE movimiento_stock (
                id_movimiento_stock INTEGER PRIMARY KEY AUTOINCREMENT,
                id_variante INTEGER NOT NULL,
                tipo_movimiento TEXT NOT NULL,
                cantidad INTEGER NOT NULL,
                stock_anterior INTEGER NOT NULL,
                stock_resultante INTEGER NOT NULL,
                observacion TEXT NULL,
                fecha_movimiento TEXT NULL
            )'
        );
    }

    private function insertarProductoYVariante(int $stock, string $estadoProducto, string $estadoVariante): void
    {
        $this->conexion->prepare(
            "INSERT INTO producto (id_producto, nombre_producto, precio, precio_anterior, descripcion, imagen, oferta, estado)
             VALUES (1, 'Remera Test', 10000, NULL, 'Producto de prueba', NULL, 0, :estado)"
        )->execute([':estado' => $estadoProducto]);

        $sentencia = $this->conexion->prepare(
            "INSERT INTO producto_variante (id_variante, id_producto, talle, stock, sku, estado, fecha_actualizacion)
             VALUES (1, 1, 'M', :stock, 'TEST-M', :estado, NOW())"
        );
        $sentencia->execute([':stock' => $stock, ':estado' => $estadoVariante]);

        $this->conexion->exec(
            "INSERT INTO producto_variante (id_variante, id_producto, talle, stock, sku, estado, fecha_actualizacion)
             VALUES (2, 1, 'L', 20, 'TEST-L', 'activo', NOW())"
        );
    }

    private function stockActual(): int
    {
        return (int) $this->conexion->query('SELECT stock FROM producto_variante WHERE id_variante = 1')->fetchColumn();
    }

    private function cantidadMovimientos(): int
    {
        return (int) $this->conexion->query('SELECT COUNT(*) FROM movimiento_stock')->fetchColumn();
    }
}

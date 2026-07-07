<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class CheckoutPersistenceTest extends TestCase
{
    private PDO $conexion;

    protected function setUp(): void
    {
        if (!TestDatabase::mysqlDisponible()) {
            self::markTestSkipped('MySQL no disponible para pruebas de checkout.');
        }

        $_SESSION = [];
        $this->conexion = TestDatabase::mysqlNeutralTeesTest();
        $this->crearEsquema();
    }

    public function testValidacionesBasicasDeDniTelefonoYMetodoDePago(): void
    {
        self::assertFalse($this->datosCheckoutValidos('', '1123456789', 'efectivo'));
        self::assertFalse($this->datosCheckoutValidos('30123ABC', '1123456789', 'efectivo'));
        self::assertFalse($this->datosCheckoutValidos('30123456', '', 'efectivo'));
        self::assertFalse($this->datosCheckoutValidos('30123456', '11ABC', 'efectivo'));
        self::assertFalse($this->datosCheckoutValidos('30123456', '1123456789', 'bitcoin'));
        self::assertTrue($this->datosCheckoutValidos('30123456', '1123456789', 'efectivo'));
        self::assertTrue($this->datosCheckoutValidos('30123456', '1123456789', 'mercado_pago'));
    }

    public function testCheckoutEfectivoCreaPedidoConPagoPendienteYDescuentaStock(): void
    {
        $this->insertarDatosBase(stock: 8, precio: 12000);

        $idPedido = $this->registrarPedidoCheckout(metodoPago: 'efectivo', cantidad: 2);

        self::assertSame('pendiente', $this->estadoPagoPedido($idPedido));
        self::assertSame(1, $this->cantidadPedidos());
        self::assertSame(1, $this->cantidadDetalles());
        self::assertSame(6, $this->stockVariante());
        self::assertSame(24000.0, $this->totalPedido($idPedido));
    }

    public function testCheckoutMercadoPagoCreaPedidoConPagoPagado(): void
    {
        $this->insertarDatosBase(stock: 8, precio: 10000);

        $idPedido = $this->registrarPedidoCheckout(metodoPago: 'mercado_pago', cantidad: 1);

        self::assertSame('pagado', $this->estadoPagoPedido($idPedido));
    }

    public function testCheckoutConCuponGuardaDescuentoYUso(): void
    {
        $this->insertarDatosBase(stock: 8, precio: 10000);

        $idPedido = $this->registrarPedidoCheckout(
            metodoPago: 'mercado_pago',
            cantidad: 3,
            cupon: $this->cuponBase()
        );

        self::assertSame(30000.0, $this->subtotalPedido($idPedido));
        self::assertSame(6000.0, $this->descuentoPedido($idPedido));
        self::assertSame(24000.0, $this->totalPedido($idPedido));
        self::assertSame(5, $this->stockVariante());
        self::assertSame(1, $this->cantidadUsosCupon());
    }

    public function testCheckoutRechazaProductoNoDisponible(): void
    {
        $this->insertarDatosBase(stock: 8, precio: 10000, estadoProducto: 'pausado');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('no esta disponible');

        $this->registrarPedidoCheckout(metodoPago: 'efectivo', cantidad: 1);
    }

    private function datosCheckoutValidos(string $dni, string $telefono, string $metodoPago): bool
    {
        return $dni !== ''
            && ctype_digit($dni)
            && $telefono !== ''
            && ctype_digit($telefono)
            && in_array($metodoPago, ['efectivo', 'mercado_pago'], true);
    }

    private function registrarPedidoCheckout(string $metodoPago, int $cantidad, ?array $cupon = null): int
    {
        $variante = obtener_variante_por_id($this->conexion, 1);

        if (!$variante || $variante['estado'] !== 'activo' || (string) ($variante['estado_producto'] ?? '') !== 'disponible') {
            throw new RuntimeException('El producto seleccionado no esta disponible.');
        }

        if ((int) $variante['stock'] < $cantidad) {
            throw new RuntimeException('Stock insuficiente.');
        }

        if ($cantidad > limite_unidades_por_producto()) {
            throw new RuntimeException('Solo podes comprar hasta ' . limite_unidades_por_producto() . ' unidades por producto.');
        }

        $subtotal = (float) $variante['precio'] * $cantidad;
        $descuento = 0.0;

        if ($cupon !== null) {
            $validacion = validar_cupon_para_usuario($this->conexion, $cupon, 1, $subtotal);

            if (!$validacion['ok']) {
                throw new RuntimeException($validacion['mensaje']);
            }

            $descuento = calcular_descuento_cupon($cupon, $subtotal);
        }

        $total = max(0.0, $subtotal - $descuento);
        $estadoPagoInicial = $metodoPago === 'efectivo' ? 'pendiente' : 'pagado';

        $this->conexion->beginTransaction();
        $this->conexion->prepare(
            'INSERT INTO pedido
                (id_usuario, dni_cliente, telefono_cliente, metodo_pago, fecha, subtotal, descuento, total, estado_pedido, estado_pago, fecha_actualizacion)
             VALUES
                (1, "30123456", "1123456789", :metodo_pago, NOW(), :subtotal, :descuento, :total, "pendiente", :estado_pago, NOW())'
        )->execute([
            ':metodo_pago' => $metodoPago,
            ':subtotal' => $subtotal,
            ':descuento' => $descuento,
            ':total' => $total,
            ':estado_pago' => $estadoPagoInicial,
        ]);

        $idPedido = (int) $this->conexion->lastInsertId();

        $this->conexion->prepare(
            'INSERT INTO detalle_pedido (id_pedido, id_variante, cantidad, precio_unitario, subtotal_linea)
             VALUES (:id_pedido, 1, :cantidad, :precio_unitario, :subtotal_linea)'
        )->execute([
            ':id_pedido' => $idPedido,
            ':cantidad' => $cantidad,
            ':precio_unitario' => (float) $variante['precio'],
            ':subtotal_linea' => $subtotal,
        ]);

        $this->conexion->prepare('UPDATE producto_variante SET stock = stock - :cantidad, fecha_actualizacion = NOW() WHERE id_variante = 1')
            ->execute([':cantidad' => $cantidad]);

        if ($cupon !== null && $descuento > 0) {
            $this->conexion->prepare(
                'INSERT INTO uso_cupon (id_pedido, id_usuario, id_cupon, monto_descuento, fecha_uso)
                 VALUES (:id_pedido, 1, :id_cupon, :monto_descuento, NOW())'
            )->execute([
                ':id_pedido' => $idPedido,
                ':id_cupon' => (int) $cupon['id_cupon'],
                ':monto_descuento' => $descuento,
            ]);
        }

        $this->conexion->commit();

        return $idPedido;
    }

    private function crearEsquema(): void
    {
        $this->conexion->exec('SET FOREIGN_KEY_CHECKS=0');
        foreach (['uso_cupon', 'detalle_pedido', 'pedido', 'producto_variante', 'producto', 'usuario'] as $tabla) {
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
                nombre_producto VARCHAR(150) NOT NULL,
                precio DECIMAL(10,2) NOT NULL,
                precio_anterior DECIMAL(10,2) NULL,
                descripcion TEXT NULL,
                imagen VARCHAR(255) NULL,
                oferta TINYINT DEFAULT 0,
                estado VARCHAR(30) NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );
        $this->conexion->exec(
            'CREATE TABLE producto_variante (
                id_variante INT PRIMARY KEY,
                id_producto INT NOT NULL,
                talle VARCHAR(20) NOT NULL,
                stock INT NOT NULL,
                sku VARCHAR(80) NULL,
                estado VARCHAR(30) NOT NULL,
                fecha_actualizacion DATETIME NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );
        $this->conexion->exec(
            'CREATE TABLE pedido (
                id_pedido INT AUTO_INCREMENT PRIMARY KEY,
                id_usuario INT NOT NULL,
                dni_cliente VARCHAR(20) NOT NULL,
                telefono_cliente VARCHAR(30) NOT NULL,
                metodo_pago VARCHAR(30) NOT NULL,
                fecha DATETIME NOT NULL,
                subtotal DECIMAL(10,2) NOT NULL,
                descuento DECIMAL(10,2) NOT NULL,
                total DECIMAL(10,2) NOT NULL,
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
                cantidad INT NOT NULL,
                precio_unitario DECIMAL(10,2) NOT NULL,
                subtotal_linea DECIMAL(10,2) NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );
        $this->conexion->exec(
            'CREATE TABLE uso_cupon (
                id_uso INT AUTO_INCREMENT PRIMARY KEY,
                id_pedido INT NULL,
                id_usuario INT NOT NULL,
                id_cupon INT NOT NULL,
                monto_descuento DECIMAL(10,2) NOT NULL,
                fecha_uso DATETIME NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );
    }

    private function insertarDatosBase(int $stock, float $precio, string $estadoProducto = 'disponible'): void
    {
        $this->conexion->exec("INSERT INTO usuario (id_usuario, nombre, apellido, mail) VALUES (1, 'Luis', 'Test', 'luis@test.com')");
        $this->conexion->prepare(
            "INSERT INTO producto (id_producto, nombre_producto, precio, precio_anterior, descripcion, imagen, oferta, estado)
             VALUES (1, 'Remera Test', :precio, NULL, 'Producto de prueba', NULL, 0, :estado)"
        )->execute([':precio' => $precio, ':estado' => $estadoProducto]);
        $this->conexion->prepare(
            "INSERT INTO producto_variante (id_variante, id_producto, talle, stock, sku, estado, fecha_actualizacion)
             VALUES (1, 1, 'M', :stock, 'TEST-M', 'activo', NOW())"
        )->execute([':stock' => $stock]);
    }

    /** @return array<string,mixed> */
    private function cuponBase(): array
    {
        return [
            'id_cupon' => 5,
            'codigo' => 'TEST20',
            'tipo_descuento' => 'porcentaje',
            'valor' => 20,
            'tope_descuento' => null,
            'compra_minima' => 0,
            'max_usos_total' => null,
            'activo' => 1,
            'fecha_inicio' => date('Y-m-d', strtotime('-1 day')),
            'fecha_fin' => date('Y-m-d', strtotime('+1 day')),
        ];
    }

    private function cantidadPedidos(): int
    {
        return (int) $this->conexion->query('SELECT COUNT(*) FROM pedido')->fetchColumn();
    }

    private function cantidadDetalles(): int
    {
        return (int) $this->conexion->query('SELECT COUNT(*) FROM detalle_pedido')->fetchColumn();
    }

    private function cantidadUsosCupon(): int
    {
        return (int) $this->conexion->query('SELECT COUNT(*) FROM uso_cupon')->fetchColumn();
    }

    private function stockVariante(): int
    {
        return (int) $this->conexion->query('SELECT stock FROM producto_variante WHERE id_variante = 1')->fetchColumn();
    }

    private function estadoPagoPedido(int $idPedido): string
    {
        return $this->campoPedido($idPedido, 'estado_pago');
    }

    private function subtotalPedido(int $idPedido): float
    {
        return (float) $this->campoPedido($idPedido, 'subtotal');
    }

    private function descuentoPedido(int $idPedido): float
    {
        return (float) $this->campoPedido($idPedido, 'descuento');
    }

    private function totalPedido(int $idPedido): float
    {
        return (float) $this->campoPedido($idPedido, 'total');
    }

    private function campoPedido(int $idPedido, string $campo): string
    {
        $sentencia = $this->conexion->prepare('SELECT ' . $campo . ' FROM pedido WHERE id_pedido = :id_pedido');
        $sentencia->execute([':id_pedido' => $idPedido]);

        return (string) $sentencia->fetchColumn();
    }
}

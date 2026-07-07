<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class ClienteTest extends TestCase
{
    protected function setUp(): void
    {
        $_SESSION = [];
    }

    public function testAceptaPasswordSegura(): void
    {
        self::assertSame([], validar_contrasena_segura('Cliente#2026'));
    }

    public function testRechazaPasswordsInseguras(): void
    {
        self::assertNotEmpty(validar_contrasena_segura('abc123'));
        self::assertNotEmpty(validar_contrasena_segura('Cliente2026'));
        self::assertNotEmpty(validar_contrasena_segura('Cliente#'));
    }

    public function testFormateaPrecioEnPesos(): void
    {
        self::assertSame('$12.500', formatear_precio(12500));
    }

    public function testNormalizaImagenDeProductoConRutasDeHostinger(): void
    {
        self::assertSame('/assets/img/productos/imagen-placeholder.svg', obtener_ruta_imagen_producto(null));
        self::assertSame('/assets/img/productos/foto.jpg', obtener_ruta_imagen_producto('assets/img/productos/foto.jpg'));
        self::assertSame('/assets/img/productos/foto.jpg', obtener_ruta_imagen_producto('/assets/img/productos/foto.jpg'));
    }

    public function testCalculaDescuentoVisualDeProducto(): void
    {
        self::assertSame(20, calcular_descuento_porcentaje(8000, 10000));
        self::assertNull(calcular_descuento_porcentaje(10000, 8000));
        self::assertNull(calcular_descuento_porcentaje(10000, null));
    }

    public function testCalculaCuponPorcentualConTopeYTotalNoNegativo(): void
    {
        self::assertSame(1000.0, calcular_descuento_cupon([
            'tipo_descuento' => 'porcentaje',
            'valor' => 10,
            'tope_descuento' => null,
        ], 10000));

        self::assertSame(2000.0, calcular_descuento_cupon([
            'tipo_descuento' => 'porcentaje',
            'valor' => 50,
            'tope_descuento' => 2000,
        ], 10000));

        self::assertSame(10000.0, calcular_descuento_cupon([
            'tipo_descuento' => 'fijo',
            'valor' => 15000,
            'tope_descuento' => null,
        ], 10000));
    }

    public function testCarritoAcumulaActualizaYVaciaCantidades(): void
    {
        agregar_al_carrito(10, 2);
        agregar_al_carrito(10, 1);

        self::assertSame(3, obtener_cantidad_variante_en_carrito(10));
        self::assertSame(3, obtener_cantidad_carrito());

        actualizar_carrito(10, 0);
        self::assertSame(0, obtener_cantidad_variante_en_carrito(10));

        agregar_al_carrito(11, 4);
        vaciar_carrito();

        self::assertSame(0, obtener_cantidad_carrito());
    }
}

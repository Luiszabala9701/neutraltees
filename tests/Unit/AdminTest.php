<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class AdminTest extends TestCase
{
    protected function setUp(): void
    {
        $_SESSION = [];
    }

    public function testReconocePermisosDeAdministrador(): void
    {
        $_SESSION['usuario_actual'] = ['is_admin' => 1, 'nombre' => 'Admin'];
        self::assertTrue(es_admin());

        $_SESSION['usuario_actual'] = ['is_admin' => 0, 'nombre' => 'Cliente'];
        self::assertFalse(es_admin());
    }

    public function testLimitesYSesion(): void
    {
        self::assertSame(10, limite_unidades_por_producto());
        self::assertSame(600, tiempo_limite_inactividad_sesion());
    }

    public function testEtiquetasDeEstadosPedidoPagoYMetodo(): void
    {
        self::assertSame('Pendiente', nombre_estado_pedido('pendiente'));
        self::assertSame('En preparacion', nombre_estado_pedido('preparando'));
        self::assertSame('Preparado', nombre_estado_pedido('preparado'));
        self::assertSame('Cancelado', nombre_estado_pedido('cancelado'));
        self::assertSame('Recibido', nombre_estado_pago('recibido'));
        self::assertSame('Pagado', nombre_estado_pago('pagado'));
        self::assertSame('Mercado Pago', nombre_metodo_pago('mercado_pago'));
        self::assertSame('Efectivo', nombre_metodo_pago('efectivo'));
        self::assertSame('No informado', nombre_metodo_pago(null));
    }

    public function testReglasDeCancelacionConsideranEstadoDePago(): void
    {
        self::assertTrue(pedido_puede_cancelarse('pendiente', 'pendiente'));
        self::assertTrue(pedido_puede_cancelarse('preparado', 'pendiente'));
        self::assertFalse(pedido_puede_cancelarse('entregado', 'pendiente'));
        self::assertFalse(pedido_puede_cancelarse('cancelado', 'pendiente'));
        self::assertFalse(pedido_puede_cancelarse('pendiente', 'pagado'));
        self::assertFalse(pedido_puede_cancelarse('preparando', 'recibido'));
    }

    public function testPagoConfirmado(): void
    {
        self::assertTrue(pago_esta_confirmado('pagado'));
        self::assertTrue(pago_esta_confirmado('recibido'));
        self::assertFalse(pago_esta_confirmado('pendiente'));
        self::assertFalse(pago_esta_confirmado(''));
    }

    public function testClasesVisualesDeEstados(): void
    {
        self::assertSame('pedido-chip--pendiente', clase_estado_pedido('pendiente'));
        self::assertSame('pedido-chip--cancelado', clase_estado_pedido('cancelado'));
        self::assertSame('pedido-chip--pago-ok', clase_estado_pago('pagado'));
        self::assertSame('pedido-chip--pago-pendiente', clase_estado_pago('pendiente'));
        self::assertSame('pedido-chip--mercado-pago', clase_metodo_pago('mercado_pago'));
        self::assertSame('pedido-chip--efectivo', clase_metodo_pago('efectivo'));
    }
}

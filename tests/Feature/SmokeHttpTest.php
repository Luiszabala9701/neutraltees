<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SmokeHttpTest extends TestCase
{
    private static ?string $baseUrl = null;
    private static bool $sitioDisponible = false;

    public static function setUpBeforeClass(): void
    {
        $config = require dirname(__DIR__, 2) . '/config/app.php';
        self::$baseUrl = rtrim((string) ($config['url_base'] ?? 'http://localhost'), '/');

        [$codigo] = self::consultarUrl(self::$baseUrl . '/');
        self::$sitioDisponible = $codigo !== 0;
    }

    #[DataProvider('rutasPublicasProvider')]
    public function testRutasPublicasRespondenSinErrorServidor(string $ruta, string $descripcion): void
    {
        if (!self::$sitioDisponible) {
            self::markTestSkipped('El sitio no responde en ' . self::$baseUrl);
        }

        [$codigo] = self::consultarUrl(self::$baseUrl . $ruta);

        self::assertGreaterThanOrEqual(200, $codigo, $descripcion . ' no respondio correctamente.');
        self::assertLessThan(500, $codigo, $descripcion . ' devolvio error de servidor.');
    }

    #[DataProvider('rutasAdminProvider')]
    public function testRutasAdminProtegidasRespondenORedireccionan(string $ruta, string $descripcion): void
    {
        if (!self::$sitioDisponible) {
            self::markTestSkipped('El sitio no responde en ' . self::$baseUrl);
        }

        [$codigo] = self::consultarUrl(self::$baseUrl . $ruta);

        self::assertContains($codigo, [200, 302, 303], $descripcion . ' no respondio ni redirecciono correctamente.');
    }

    public static function rutasPublicasProvider(): iterable
    {
        yield 'inicio publico' => ['/', 'Inicio publico'];
        yield 'login publico' => ['/login.php', 'Login publico'];
        yield 'registro publico' => ['/registro.php', 'Registro publico'];
        yield 'busqueda publica' => ['/busqueda.php', 'Busqueda publica'];
        yield 'faq publica' => ['/faq.php', 'FAQ publica'];
        yield 'recuperar password' => ['/olvide_contrasena.php', 'Olvido de contrasena'];
    }

    public static function rutasAdminProvider(): iterable
    {
        yield 'dashboard admin' => ['/admin/index.php', 'Dashboard admin protegido'];
        yield 'productos admin' => ['/admin/productos.php', 'Productos admin protegido'];
        yield 'pedidos admin' => ['/admin/pedidos.php', 'Pedidos admin protegido'];
        yield 'usuarios admin' => ['/admin/usuarios.php', 'Usuarios admin protegido'];
    }

    /**
     * @return array{0:int,1:string|null}
     */
    private static function consultarUrl(string $url): array
    {
        $contexto = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 4,
                'ignore_errors' => true,
                'follow_location' => 0,
            ],
        ]);

        $cuerpo = @file_get_contents($url, false, $contexto);
        $headers = $http_response_header ?? [];
        $codigo = 0;

        if (isset($headers[0]) && preg_match('/\s(\d{3})\s/', $headers[0], $coincidencias)) {
            $codigo = (int) $coincidencias[1];
        }

        return [$codigo, $cuerpo === false ? null : $cuerpo];
    }
}

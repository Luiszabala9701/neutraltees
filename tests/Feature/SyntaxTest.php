<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SyntaxTest extends TestCase
{
    #[DataProvider('archivosPhpProvider')]
    public function testArchivoPhpTieneSintaxisValida(string $archivo): void
    {
        $comando = escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($archivo);
        exec($comando, $salida, $codigo);

        self::assertSame(
            0,
            $codigo,
            'Error de sintaxis en ' . $archivo . PHP_EOL . implode(PHP_EOL, $salida)
        );
    }

    public static function archivosPhpProvider(): iterable
    {
        $raizProyecto = dirname(__DIR__, 2);
        $ignorados = [
            DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR,
            DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR,
            DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR,
            DIRECTORY_SEPARATOR . 'tmp' . DIRECTORY_SEPARATOR,
            DIRECTORY_SEPARATOR . 'outputs' . DIRECTORY_SEPARATOR,
            DIRECTORY_SEPARATOR . '.git' . DIRECTORY_SEPARATOR,
            DIRECTORY_SEPARATOR . '.agents' . DIRECTORY_SEPARATOR,
            DIRECTORY_SEPARATOR . '.codex' . DIRECTORY_SEPARATOR,
            DIRECTORY_SEPARATOR . '.phpunit.cache' . DIRECTORY_SEPARATOR,
            DIRECTORY_SEPARATOR . '.tmp_sessions' . DIRECTORY_SEPARATOR,
        ];

        $iterador = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($raizProyecto, FilesystemIterator::SKIP_DOTS)
        );

        $archivos = [];

        foreach ($iterador as $archivo) {
            if (!$archivo instanceof SplFileInfo || strtolower($archivo->getExtension()) !== 'php') {
                continue;
            }

            $ruta = $archivo->getPathname();
            $rutaNormalizada = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $ruta);

            foreach ($ignorados as $ignorado) {
                if (str_contains($rutaNormalizada, $ignorado)) {
                    continue 2;
                }
            }

            $archivos[] = $ruta;
        }

        sort($archivos);

        foreach ($archivos as $archivo) {
            yield str_replace($raizProyecto . DIRECTORY_SEPARATOR, '', $archivo) => [$archivo];
        }
    }
}

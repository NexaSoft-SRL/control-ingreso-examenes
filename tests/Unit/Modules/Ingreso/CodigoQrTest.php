<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Ingreso;

use App\Modules\Ingreso\Application\Contracts\CodigoQrGateway;
use InvalidArgumentException;
use Tests\TestCase;

final class CodigoQrTest extends TestCase
{
    public function test_genera_y_resuelve_codigo_del_examen(): void
    {
        $qr = app(CodigoQrGateway::class);

        $contenido = $qr->generar(19, '202100001');

        self::assertStringStartsWith('SCIQR1:', $contenido);
        self::assertStringNotContainsString('202100001', $contenido);
        self::assertSame('202100001', $qr->resolver($contenido, 19));
    }

    public function test_rechaza_qr_de_otro_examen(): void
    {
        $qr = app(CodigoQrGateway::class);
        $contenido = $qr->generar(19, '202100001');

        $this->expectException(InvalidArgumentException::class);

        $qr->resolver($contenido, 20);
    }

    public function test_rechaza_qr_adulterado(): void
    {
        $qr = app(CodigoQrGateway::class);
        $contenido = $qr->generar(19, '202100001');

        $alterado = substr($contenido, 0, -1)
            .(substr($contenido, -1) === 'A' ? 'B' : 'A');

        $this->expectException(InvalidArgumentException::class);

        $qr->resolver($alterado, 19);
    }

    public function test_rechaza_qr_sin_formato_reconocido(): void
    {
        $this->expectException(InvalidArgumentException::class);

        app(CodigoQrGateway::class)->resolver('202100001', 19);
    }
}

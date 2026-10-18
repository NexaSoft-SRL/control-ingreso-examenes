<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Ingreso;

use App\Modules\Ingreso\Application\DTOs\ResultadoResolucionPuertaData;
use App\Modules\Ingreso\Domain\Enums\VeredictoIngreso;
use PHPUnit\Framework\TestCase;

final class ResultadoResolucionPuertaDataTest extends TestCase
{
    public function test_admision_valida(): void
    {
        $resultado = new ResultadoResolucionPuertaData(
            5,
            VeredictoIngreso::Admitido,
        );

        self::assertTrue($resultado->puedeAdmitir());
    }

    public function test_rechazo_no_admite(): void
    {
        $resultado = new ResultadoResolucionPuertaData(
            5,
            VeredictoIngreso::NoHabilitado,
        );

        self::assertFalse($resultado->puedeAdmitir());
    }

    public function test_ambiguedad_no_admite(): void
    {
        $resultado = new ResultadoResolucionPuertaData(
            null,
            null,
            true,
        );

        self::assertFalse($resultado->puedeAdmitir());
        self::assertTrue($resultado->ambiguo);
    }
}

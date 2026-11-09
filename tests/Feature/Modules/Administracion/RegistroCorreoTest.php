<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Administracion;

use App\Modules\Administracion\Application\Contracts\RegistroCorreoGateway;
use App\Modules\Administracion\Application\DTOs\RegistroCorreoData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\Support\UsuarioConPermisos;
use Tests\TestCase;

/**
 * El registro de correos (`envios_correo`): cada correo se anota como
 * pendiente y se cierra con su resultado.
 */
final class RegistroCorreoTest extends TestCase
{
    use RefreshDatabase;
    use UsuarioConPermisos;

    private function registro(): RegistroCorreoGateway
    {
        return $this->app->make(RegistroCorreoGateway::class);
    }

    public function test_an_email_is_recorded_as_pending(): void
    {
        $solicitante = $this->usuarioConPermisos([])->getKey();
        $destinataria = $this->usuarioConPermisos([])->getKey();

        self::assertIsInt($solicitante);
        self::assertIsInt($destinataria);

        $id = $this->registro()->registrar(new RegistroCorreoData(
            tipo: RegistroCorreoData::TIPO_CREDENCIALES,
            destinatario: 'x@umss.edu.bo',
            usuarioId: $destinataria,
            solicitadoPor: $solicitante,
        ));

        $this->assertDatabaseHas('envios_correo', [
            'id' => $id,
            'tipo' => 'CREDENCIALES',
            'destinatario' => 'x@umss.edu.bo',
            'user_id' => $destinataria,
            'examen_id' => null,
            'estudiante_id' => null,
            'estado' => 'PENDIENTE',
            'error' => null,
            'solicitado_por' => $solicitante,
            'enviado_en' => null,
        ]);
    }

    public function test_a_sent_email_keeps_the_time_it_left(): void
    {
        $this->travelTo('2026-10-12 12:21:00');

        $id = $this->registro()->registrar(new RegistroCorreoData(
            RegistroCorreoData::TIPO_CODIGO_QR,
            '202100001@est.umss.edu',
        ));

        $this->registro()->marcarEnviado($id);

        $this->assertDatabaseHas('envios_correo', [
            'id' => $id,
            'tipo' => 'CODIGO_QR',
            'estado' => 'ENVIADO',
            'enviado_en' => '2026-10-12 12:21:00',
        ]);
    }

    public function test_a_failed_email_keeps_its_error(): void
    {
        $id = $this->registro()->registrar(new RegistroCorreoData(
            RegistroCorreoData::TIPO_RESTABLECIMIENTO,
            'x@umss.edu.bo',
        ));

        $this->registro()->marcarFallido($id, 'Buzón inexistente.');

        $this->assertDatabaseHas('envios_correo', [
            'id' => $id,
            'estado' => 'FALLIDO',
            'error' => 'Buzón inexistente.',
            'enviado_en' => null,
        ]);
    }

    public function test_an_unknown_type_is_refused(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->registro()->registrar(new RegistroCorreoData('BOLETIN', 'x@umss.edu.bo'));
    }
}

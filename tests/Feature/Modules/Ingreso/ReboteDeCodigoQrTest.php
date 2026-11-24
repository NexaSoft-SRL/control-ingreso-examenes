<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Ingreso;

use App\Modules\Administracion\Application\Actions\ProcesarRebotesDeCorreo;
use App\Modules\Administracion\Application\Contracts\BuzonDeRebotes;
use App\Modules\Administracion\Application\DTOs\ReboteData;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuzonFalso;
use Tests\TestCase;

/**
 * El codigo QR enviado a un correo institucional que no existe: al leer el
 * rebote, el docente lo ve como fallido y el codigo, sin enviar.
 */
final class ReboteDeCodigoQrTest extends TestCase
{
    use EscenarioDePuerta;
    use RefreshDatabase;

    public function test_a_bounced_code_shows_as_failed_with_the_reason(): void
    {
        $this->armarExamen();
        [$estudiante] = $this->habilitados(1);
        $token = $this->emitirCodigo($estudiante);

        $this->actingAs($this->cuentaDocente)
            ->postJson("/api/examenes/{$this->examen->id}/codigos-qr/envios")
            ->assertStatus(202);

        $buzon = new BuzonFalso;
        $buzon->avisos = [new ReboteData(
            $estudiante->codigo_universitario.'@est.umss.edu',
            DateTimeImmutable::createFromInterface(now())->modify('+1 minute'),
            '12',
        )];
        $this->app->instance(BuzonDeRebotes::class, $buzon);
        config(['umss.rebotes.habilitado' => true]);

        self::assertSame(1, $this->app->make(ProcesarRebotesDeCorreo::class)->execute());

        $this->assertDatabaseHas('codigos_qr', ['token' => $token, 'enviado_en' => null]);
        $this->actingAs($this->cuentaDocente)
            ->getJson("/api/examenes/{$this->examen->id}/codigos-qr")
            ->assertJsonPath('meta.enviados', 0)
            ->assertJsonPath('meta.fallidos', 1)
            ->assertJsonPath('data.0.envio', 'fallido')
            ->assertJsonPath('data.0.error', ProcesarRebotesDeCorreo::ERROR);
    }
}

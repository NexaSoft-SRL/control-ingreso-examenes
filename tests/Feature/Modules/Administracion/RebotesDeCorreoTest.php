<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Administracion;

use App\Modules\Administracion\Application\Actions\ProcesarRebotesDeCorreo;
use App\Modules\Administracion\Application\Contracts\BuzonDeRebotes;
use App\Modules\Administracion\Application\DTOs\ReboteData;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\PendingCommand;
use Tests\Support\BuzonFalso;
use Tests\TestCase;

/**
 * Un correo a una direccion que no existe rebota al buzon del remitente:
 * el sistema lee el aviso y deja ese envio como fallido.
 */
final class RebotesDeCorreoTest extends TestCase
{
    use RefreshDatabase;

    private BuzonFalso $buzon;

    protected function setUp(): void
    {
        parent::setUp();

        $this->buzon = new BuzonFalso;
        $this->app->instance(BuzonDeRebotes::class, $this->buzon);
        config(['umss.rebotes.habilitado' => true]);
    }

    private function envio(string $destinatario, string $estado, ?string $enviadoEn, string $tipo = 'CREDENCIALES'): int
    {
        return DB::table('envios_correo')->insertGetId([
            'tipo' => $tipo,
            'destinatario' => $destinatario,
            'estado' => $estado,
            'enviado_en' => $enviadoEn,
            'created_at' => '2026-11-20 10:00:00',
            'updated_at' => '2026-11-20 10:00:00',
        ]);
    }

    private function procesar(): int
    {
        return $this->app->make(ProcesarRebotesDeCorreo::class)->execute();
    }

    public function test_a_bounce_marks_the_recent_deliveries_to_that_address_as_failed(): void
    {
        $rebotado = $this->envio('20012030@est.umss.edu', 'ENVIADO', '2026-11-21 19:25:00');
        $viejo = $this->envio('20012030@est.umss.edu', 'ENVIADO', '2026-11-10 08:00:00');
        $otro = $this->envio('202112345@est.umss.edu', 'ENVIADO', '2026-11-21 19:25:00');
        $pendiente = $this->envio('20012030@est.umss.edu', 'PENDIENTE', null);
        // El aviso llega en UTC; la tabla guarda la hora de La Paz.
        $this->buzon->avisos = [
            new ReboteData('20012030@est.umss.edu', new DateTimeImmutable('2026-11-21 23:25:30+00:00'), '41'),
        ];

        self::assertSame(1, $this->procesar());

        $this->assertDatabaseHas('envios_correo', [
            'id' => $rebotado,
            'estado' => 'FALLIDO',
            'error' => ProcesarRebotesDeCorreo::ERROR,
        ]);
        // Fuera de los 7 dias, de otra direccion o sin salir: no se tocan.
        $this->assertDatabaseHas('envios_correo', ['id' => $viejo, 'estado' => 'ENVIADO']);
        $this->assertDatabaseHas('envios_correo', ['id' => $otro, 'estado' => 'ENVIADO']);
        $this->assertDatabaseHas('envios_correo', ['id' => $pendiente, 'estado' => 'PENDIENTE']);
    }

    public function test_reading_the_same_bounce_again_changes_nothing(): void
    {
        $this->envio('20012030@est.umss.edu', 'ENVIADO', '2026-11-21 19:25:00');
        $this->buzon->avisos = [
            new ReboteData('20012030@est.umss.edu', new DateTimeImmutable('2026-11-21 19:30:00'), '41'),
        ];

        self::assertSame(1, $this->procesar());
        self::assertSame(0, $this->procesar());

        self::assertSame(1, DB::table('bitacora_operaciones')->where('operacion', 'correo.rebote')->count());
    }

    public function test_the_bounce_is_written_to_the_audit_log_by_the_system(): void
    {
        $this->envio('Juan.Perez@umss.edu.bo', 'ENVIADO', '2026-11-21 19:25:00');
        $this->envio('juan.perez@umss.edu.bo', 'ENVIADO', '2026-11-21 19:26:00');
        $this->buzon->avisos = [
            new ReboteData('juan.perez@umss.edu.bo', new DateTimeImmutable('2026-11-21 19:30:00'), '7'),
        ];

        self::assertSame(2, $this->procesar());

        $this->assertDatabaseHas('bitacora_operaciones', [
            'usuario_id' => null,
            'operacion' => 'correo.rebote',
            'tabla_afectada' => 'envios_correo',
            'descripcion' => 'juan.perez@umss.edu.bo · 2 correos no llegaron',
        ]);
    }

    public function test_a_bounce_without_deliveries_is_archived_without_trace(): void
    {
        $this->buzon->avisos = [
            new ReboteData('nadie@umss.edu.bo', new DateTimeImmutable('2026-11-21 19:30:00'), '9'),
        ];

        self::assertSame(0, $this->procesar());

        $this->assertDatabaseMissing('bitacora_operaciones', ['operacion' => 'correo.rebote']);
    }

    public function test_disabled_the_mailbox_is_never_read(): void
    {
        config(['umss.rebotes.habilitado' => false]);
        $this->buzon->avisos = [
            new ReboteData('x@umss.edu.bo', new DateTimeImmutable('2026-11-21 19:30:00'), '1'),
        ];

        self::assertSame(0, $this->procesar());
        self::assertSame(0, $this->buzon->lecturas);
    }

    public function test_the_command_processes_the_bounces(): void
    {
        $this->envio('x@umss.edu.bo', 'ENVIADO', '2026-11-21 19:25:00');
        $this->buzon->avisos = [
            new ReboteData('x@umss.edu.bo', new DateTimeImmutable('2026-11-21 19:30:00'), '3'),
        ];

        $comando = $this->artisan('correo:rebotes');

        self::assertInstanceOf(PendingCommand::class, $comando);
        $comando->expectsOutput('1 envios marcados como no entregados.')->assertSuccessful();
    }

    public function test_after_a_web_request_the_mailbox_is_read_at_most_every_five_minutes(): void
    {
        $this->get('/login');
        $this->get('/login');

        self::assertSame(1, $this->buzon->lecturas);
    }
}

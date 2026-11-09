<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Ingreso;

use App\Modules\Estudiantes\Domain\Models\Estudiante;
use App\Modules\Ingreso\Infrastructure\Mail\CodigoQrMail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * El estado del envio de cada credencial (enviado, sin enviar, fallido),
 * su filtro, el reenvio por estudiante y las tandas de la cola
 * sincronica.
 */
final class EstadoDeEnviosTest extends TestCase
{
    use EscenarioDePuerta;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->armarExamen();
    }

    private function lista(): string
    {
        return "/api/examenes/{$this->examen->id}/codigos-qr";
    }

    private function registrarEnvio(Estudiante $estudiante, string $estado, ?string $error = null): void
    {
        DB::table('envios_correo')->insert([
            'tipo' => 'CODIGO_QR',
            'destinatario' => $estudiante->codigo_universitario.'@est.umss.edu',
            'examen_id' => $this->examen->id,
            'estudiante_id' => $estudiante->id,
            'estado' => $estado,
            'error' => $error,
            'created_at' => now()->subHour(),
            'updated_at' => now()->subHour(),
        ]);
    }

    /**
     * Cuatro habilitados, uno en cada estado, por orden de lista.
     *
     * @return array{sin_emitir: Estudiante, sin_enviar: Estudiante, enviado: Estudiante, fallido: Estudiante}
     */
    private function unoDeCadaEstado(): array
    {
        [$sinEmitir, $sinEnviar, $enviado, $fallido] = $this->habilitados(4);

        $this->emitirCodigo($sinEnviar);
        $this->emitirCodigo($enviado);
        $this->emitirCodigo($fallido);

        DB::table('codigos_qr')->where('estudiante_id', $enviado->id)->update(['enviado_en' => now()]);
        $this->registrarEnvio($enviado, 'ENVIADO');
        // Un fallo anterior no cuenta: manda el ultimo envio.
        $this->registrarEnvio($fallido, 'ENVIADO');
        $this->registrarEnvio($fallido, 'FALLIDO', 'Buzón inexistente.');

        return ['sin_emitir' => $sinEmitir, 'sin_enviar' => $sinEnviar, 'enviado' => $enviado, 'fallido' => $fallido];
    }

    public function test_each_row_tells_its_delivery_state_its_error_and_the_institutional_address(): void
    {
        $estudiantes = $this->unoDeCadaEstado();

        $respuesta = $this->actingAs($this->cuentaDocente)
            ->getJson($this->lista())
            ->assertOk()
            ->assertJsonPath('meta.habilitados', 4)
            ->assertJsonPath('meta.emitidos', 3)
            ->assertJsonPath('meta.enviados', 1)
            ->assertJsonPath('meta.fallidos', 1);

        /** @var list<array<string, mixed>> $filas */
        $filas = $respuesta->json('data');
        $porEstudiante = array_column($filas, null, 'estudiante_id');

        foreach ($estudiantes as $estado => $estudiante) {
            $fila = $porEstudiante[$estudiante->id];

            $this->assertSame($estado, $fila['envio']);
            $this->assertSame($estudiante->codigo_universitario.'@est.umss.edu', $fila['correo']);
            $this->assertSame($estado === 'fallido' ? 'Buzón inexistente.' : null, $fila['error']);
        }
    }

    public function test_the_list_is_filtered_by_delivery_state(): void
    {
        $estudiantes = $this->unoDeCadaEstado();

        foreach ($estudiantes as $estado => $estudiante) {
            $this->actingAs($this->cuentaDocente)
                ->getJson($this->lista().'?envio='.$estado)
                ->assertOk()
                ->assertJsonPath('meta.total', 1)
                ->assertJsonPath('meta.habilitados', 4)
                ->assertJsonPath('data.0.estudiante_id', $estudiante->id)
                ->assertJsonPath('data.0.envio', $estado);
        }

        $this->actingAs($this->cuentaDocente)
            ->getJson($this->lista().'?envio=perdido')
            ->assertStatus(422)
            ->assertJsonPath('errors.envio.0', 'El estado de envío no es válido.');
    }

    public function test_sending_can_be_limited_to_the_failed_ones(): void
    {
        Mail::fake();
        $estudiantes = $this->unoDeCadaEstado();

        $this->actingAs($this->cuentaDocente)
            ->postJson($this->lista().'/envios', ['envio' => 'fallido'])
            ->assertStatus(202)
            ->assertJsonPath('encolados', 1)
            ->assertJsonPath('pendientes', 0);

        Mail::assertQueued(
            CodigoQrMail::class,
            fn (CodigoQrMail $correo): bool => $correo->credencial->estudianteId === $estudiantes['fallido']->id,
        );
        Mail::assertQueuedCount(1);
    }

    public function test_the_credential_carries_the_institutional_address(): void
    {
        [$estudiante] = $this->habilitados(1);

        $this->actingAs($this->cuentaDocente)
            ->getJson($this->lista().'/'.$estudiante->id)
            ->assertOk()
            ->assertJsonPath('data.correo', $estudiante->codigo_universitario.'@est.umss.edu');
    }

    public function test_one_student_can_be_sent_the_code_again(): void
    {
        [$estudiante, $otro] = $this->habilitados(2);
        $this->emitirCodigo($estudiante);
        $this->emitirCodigo($otro);
        DB::table('codigos_qr')->where('estudiante_id', $estudiante->id)->update(['enviado_en' => '2026-10-11 10:00:00']);

        $this->actingAs($this->cuentaDocente)
            ->postJson($this->lista().'/'.$estudiante->id.'/envio')
            ->assertStatus(202)
            ->assertExactJson([
                'message' => '1 código enviado.',
                'encolados' => 1,
                'pendientes' => 0,
                'correo' => $estudiante->codigo_universitario.'@est.umss.edu',
            ]);

        $this->assertDatabaseHas('envios_correo', [
            'tipo' => 'CODIGO_QR',
            'estudiante_id' => $estudiante->id,
            'estado' => 'ENVIADO',
            'solicitado_por' => $this->cuentaDocente->id,
        ]);
        $this->assertDatabaseHas('codigos_qr', [
            'estudiante_id' => $estudiante->id,
            'enviado_en' => '2026-10-12 08:20:00',
        ]);
        $this->assertDatabaseMissing('envios_correo', ['estudiante_id' => $otro->id]);
        $this->assertDatabaseHas('bitacora_operaciones', [
            'usuario_id' => $this->cuentaDocente->id,
            'operacion' => 'qr.enviar',
            'registro_id' => $this->examen->id,
        ]);
    }

    public function test_the_single_delivery_checks_the_student_the_code_and_the_scope(): void
    {
        [$sinCodigo] = $this->habilitados(1);
        [$inhabilitado] = $this->estudiantesInscritos($this->grupo, 1);
        $this->inhabilitar($this->examen, [$inhabilitado]);

        $this->actingAs($this->cuentaDocente)
            ->postJson($this->lista().'/'.$sinCodigo->id.'/envio')
            ->assertStatus(409)
            ->assertJsonPath('codigo', 'SIN_EMITIR');

        $this->actingAs($this->cuentaDocente)
            ->postJson($this->lista().'/'.$inhabilitado->id.'/envio')
            ->assertStatus(404);

        $this->actingAs($this->docenteAjeno())
            ->postJson($this->lista().'/'.$sinCodigo->id.'/envio')
            ->assertStatus(403)
            ->assertJsonPath('alcance', true);

        $this->assertDatabaseCount('envios_correo', 0);
    }

    public function test_the_last_batch_does_not_resend_to_who_already_received_it(): void
    {
        Mail::fake();

        foreach ($this->habilitados(105) as $estudiante) {
            $this->emitirCodigo($estudiante);
        }

        DB::table('codigos_qr')->orderBy('id')->limit(100)->update(['enviado_en' => now()]);
        $yaEnviados = DB::table('codigos_qr')->whereNotNull('enviado_en')->pluck('estudiante_id')->all();

        $this->actingAs($this->cuentaDocente)
            ->postJson($this->lista().'/envios')
            ->assertStatus(202)
            ->assertJsonPath('encolados', 5)
            ->assertJsonPath('pendientes', 0);

        $this->assertSame(0, DB::table('envios_correo')->whereIn('estudiante_id', $yaEnviados)->count());
    }

    public function test_resending_to_everyone_advances_in_batches_without_repeating(): void
    {
        Mail::fake();

        foreach ($this->habilitados(105) as $estudiante) {
            $this->emitirCodigo($estudiante);
        }

        DB::table('codigos_qr')->update(['enviado_en' => '2026-10-11 10:00:00']);

        $tanda = $this->actingAs($this->cuentaDocente)
            ->postJson($this->lista().'/envios')
            ->assertStatus(202)
            ->assertJsonPath('encolados', 100)
            ->assertJsonPath('pendientes', 5)
            ->assertJsonPath('tanda', $this->iso('2026-10-12 08:20:00'))
            ->json('tanda');

        $this->actingAs($this->cuentaDocente)
            ->postJson($this->lista().'/envios', ['tanda' => $tanda])
            ->assertStatus(202)
            ->assertJsonPath('encolados', 5)
            ->assertJsonPath('pendientes', 0);

        $this->assertSame(105, DB::table('envios_correo')->count());
        $this->assertSame(105, DB::table('envios_correo')->distinct()->count('estudiante_id'));

        $this->actingAs($this->cuentaDocente)
            ->postJson($this->lista().'/envios', ['tanda' => $tanda])
            ->assertStatus(202)
            ->assertJsonPath('encolados', 0)
            ->assertJsonPath('pendientes', 0);
    }
}

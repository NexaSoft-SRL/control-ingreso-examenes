<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Ingreso;

use App\Modules\Ingreso\Infrastructure\Mail\AvisoExamenMail;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Transport\ArrayTransport;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mime\Email;
use Tests\TestCase;

/**
 * HU-20: el docente avisa por correo a los habilitados la fecha, el aula,
 * las normas y sus recomendaciones.
 */
final class AvisosDeExamenTest extends TestCase
{
    use EscenarioDeCodigosQr;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-26 09:30:00');
        $this->armarExamen();
    }

    private function avisos(): string
    {
        return $this->ruta('/avisos');
    }

    private function marcarNorma(string $texto, int $orden): void
    {
        DB::table('examen_norma')->insert([
            'examen_id' => $this->examen->id,
            'plantilla_id' => null,
            'texto' => $texto,
            'orden' => $orden,
        ]);
    }

    public function test_no_notice_leaves_while_enabled_students_have_no_room(): void
    {
        Mail::fake();
        $this->habilitar($this->examen, $this->estudiantesInscritos($this->grupo, 2));

        $this->actingAs($this->cuentaDocente)
            ->postJson($this->avisos())
            ->assertStatus(409)
            ->assertJsonPath('codigo', 'SIN_AULA');

        Mail::assertNothingQueued();
    }

    public function test_one_notice_per_enabled_student_is_queued_and_recorded(): void
    {
        Mail::fake();

        [$conCodigo, $sinCodigo] = $this->habilitados(2);
        $this->emitirCodigo($conCodigo);
        [$inhabilitado] = $this->estudiantesInscritos($this->grupo, 1);
        $this->inhabilitar($this->examen, [$inhabilitado]);

        $this->actingAs($this->cuentaDocente)
            ->postJson($this->avisos(), ['recomendaciones' => 'Llegar 20 minutos antes.'])
            ->assertStatus(202)
            ->assertJsonPath('message', '2 avisos enviados.')
            ->assertJsonPath('encolados', 2)
            ->assertJsonPath('pendientes', 0)
            ->assertJsonPath('tanda', '2026-10-26T09:30:00-04:00');

        Mail::assertQueuedCount(2);

        foreach ([$conCodigo, $sinCodigo] as $estudiante) {
            Mail::assertQueued(
                AvisoExamenMail::class,
                fn (AvisoExamenMail $correo): bool => $correo->hasTo($estudiante->codigo_universitario.'@est.umss.edu')
                    && $correo->aviso->recomendaciones === 'Llegar 20 minutos antes.',
            );

            $this->assertDatabaseHas('envios_correo', [
                'tipo' => 'AVISO',
                'destinatario' => $estudiante->codigo_universitario.'@est.umss.edu',
                'examen_id' => $this->examen->id,
                'estudiante_id' => $estudiante->id,
                'estado' => 'PENDIENTE',
                'solicitado_por' => $this->cuentaDocente->id,
            ]);
        }

        $this->assertDatabaseMissing('envios_correo', ['estudiante_id' => $inhabilitado->id]);
        $this->assertDatabaseHas('bitacora_operaciones', [
            'usuario_id' => $this->cuentaDocente->id,
            'operacion' => 'aviso.enviar',
            'tabla_afectada' => 'examenes',
            'registro_id' => $this->examen->id,
        ]);
    }

    public function test_the_notice_carries_the_date_the_room_the_rules_and_the_recommendations(): void
    {
        $this->examen->update(['normas' => 'Hoja de fórmulas A4.']);
        $this->marcarNorma('Sin celular', 2);
        $this->marcarNorma('Documento de identidad a la vista', 1);
        [$estudiante] = $this->habilitados(1);
        $this->emitirCodigo($estudiante);

        $this->actingAs($this->cuentaDocente)
            ->postJson($this->avisos(), ['recomendaciones' => "Llegar antes.\nTraer lápiz."])
            ->assertStatus(202)
            ->assertJsonPath('message', '1 aviso enviado.');

        $transporte = Mail::mailer()->getSymfonyTransport();
        self::assertInstanceOf(ArrayTransport::class, $transporte);
        $this->assertCount(1, $transporte->messages());

        $enviado = $transporte->messages()->first();
        self::assertInstanceOf(SentMessage::class, $enviado);
        $correo = $enviado->getOriginalMessage();
        self::assertInstanceOf(Email::class, $correo);
        $html = (string) $correo->getHtmlBody();

        $this->assertSame($estudiante->codigo_universitario.'@est.umss.edu', $correo->getTo()[0]->getAddress());
        $this->assertSame('Aviso de examen · Primer parcial · Introducción a la Programación', $correo->getSubject());
        $this->assertStringContainsString('30 oct 2026 · 08:15 · 90 min', $html);
        $this->assertStringContainsString('Aula 691A', $html);
        $this->assertStringContainsString('Hoja de fórmulas A4.', $html);
        $this->assertStringContainsString('Traer lápiz.', $html);
        $this->assertLessThan(
            strpos($html, 'Sin celular'),
            strpos($html, 'Documento de identidad a la vista'),
        );

        // El aviso queda enviado, pero no marca el codigo como recibido.
        $this->assertDatabaseHas('envios_correo', [
            'tipo' => 'AVISO',
            'estudiante_id' => $estudiante->id,
            'estado' => 'ENVIADO',
            'enviado_en' => '2026-10-26 09:30:00',
        ]);
        $this->assertDatabaseHas('codigos_qr', ['estudiante_id' => $estudiante->id, 'enviado_en' => null]);
    }

    public function test_the_filters_limit_who_is_notified(): void
    {
        Mail::fake();

        $otra = $this->aula('617');
        $this->sumarAula($otra);
        [$aqui] = $this->habilitados(1);
        [$alla] = $this->habilitados(1, $otra);

        $this->actingAs($this->cuentaDocente)
            ->postJson($this->avisos(), ['aula' => $otra->id])
            ->assertStatus(202)
            ->assertJsonPath('encolados', 1);

        $this->assertDatabaseHas('envios_correo', ['tipo' => 'AVISO', 'estudiante_id' => $alla->id]);
        $this->assertDatabaseMissing('envios_correo', ['estudiante_id' => $aqui->id]);
    }

    public function test_with_a_synchronous_queue_the_notices_go_in_batches_without_repeating(): void
    {
        Mail::fake();
        $this->habilitados(105);

        $tanda = $this->actingAs($this->cuentaDocente)
            ->postJson($this->avisos())
            ->assertStatus(202)
            ->assertJsonPath('encolados', 100)
            ->assertJsonPath('pendientes', 5)
            ->json('tanda');

        $this->actingAs($this->cuentaDocente)
            ->postJson($this->avisos(), ['tanda' => $tanda])
            ->assertStatus(202)
            ->assertJsonPath('encolados', 5)
            ->assertJsonPath('pendientes', 0);

        $this->assertSame(105, DB::table('envios_correo')->where('tipo', 'AVISO')->count());
        $this->assertSame(105, DB::table('envios_correo')->distinct()->count('estudiante_id'));

        // La tanda ya termino: repetirla no envia nada ni falla.
        $this->actingAs($this->cuentaDocente)
            ->postJson($this->avisos(), ['tanda' => $tanda])
            ->assertStatus(202)
            ->assertJsonPath('encolados', 0)
            ->assertJsonPath('pendientes', 0);

        // Un aviso nuevo, mas tarde, vuelve a empezar.
        $this->travelTo('2026-10-26 09:40:00');

        $this->actingAs($this->cuentaDocente)
            ->postJson($this->avisos())
            ->assertStatus(202)
            ->assertJsonPath('encolados', 100)
            ->assertJsonPath('pendientes', 5);
    }

    public function test_a_failed_notice_is_recorded_and_does_not_break_the_request(): void
    {
        [$estudiante] = $this->habilitados(1);

        Mail::shouldReceive('to')->andThrow(new RuntimeException('El servidor de correo no responde.'));

        $this->actingAs($this->cuentaDocente)
            ->postJson($this->avisos())
            ->assertStatus(202);

        $this->assertDatabaseHas('envios_correo', [
            'tipo' => 'AVISO',
            'estudiante_id' => $estudiante->id,
            'estado' => 'FALLIDO',
            'error' => 'El servidor de correo no responde.',
        ]);
    }

    public function test_a_notice_without_enabled_students_is_a_conflict(): void
    {
        Mail::fake();

        $this->actingAs($this->cuentaDocente)
            ->postJson($this->avisos())
            ->assertStatus(409)
            ->assertJsonPath('codigo', 'SIN_HABILITADOS');

        $this->assertDatabaseCount('envios_correo', 0);
    }

    public function test_the_recommendations_are_validated(): void
    {
        $this->actingAs($this->cuentaDocente)
            ->postJson($this->avisos(), ['recomendaciones' => str_repeat('a', 2001), 'tanda' => 'ayer no'])
            ->assertStatus(422)
            ->assertJsonPath('errors.recomendaciones.0', 'Máximo 2000 caracteres')
            ->assertJsonPath('errors.tanda.0', 'La tanda no es válida.');
    }

    public function test_notices_require_a_session_the_permission_and_the_exam(): void
    {
        $this->postJson($this->avisos())->assertStatus(401);

        $this->actingAs(UserFactory::new()->createOne())
            ->postJson($this->avisos())
            ->assertStatus(403)
            ->assertJsonPath('permiso_requerido', 'codigos_qr');

        $this->actingAs($this->docenteAjeno())
            ->postJson($this->avisos())
            ->assertStatus(403)
            ->assertJsonPath('alcance', true);

        $this->actingAs($this->cuentaDocente)
            ->postJson('/api/examenes/999999/codigos-qr/avisos')
            ->assertStatus(404);
    }
}

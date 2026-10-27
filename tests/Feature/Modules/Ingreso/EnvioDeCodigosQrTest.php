<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Ingreso;

use App\Modules\Ingreso\Infrastructure\Mail\CodigoQrMail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Transport\ArrayTransport;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mime\Email;
use Tests\TestCase;

/**
 * HU-20: un correo por estudiante con codigo emitido, a su correo
 * institucional, con registro en `envios_correo`.
 */
final class EnvioDeCodigosQrTest extends TestCase
{
    use EscenarioDeCodigosQr;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-26 09:30:00');
        $this->armarExamen();
    }

    private function envios(): string
    {
        return $this->ruta('/envios');
    }

    public function test_one_mail_per_student_is_queued_to_the_institutional_address(): void
    {
        Mail::fake();

        [$primero, $segundo, $sinCodigo] = $this->habilitados(3);
        $this->emitirCodigo($primero);
        $this->emitirCodigo($segundo);

        $this->actingAs($this->cuentaDocente)
            ->postJson($this->envios())
            ->assertStatus(202)
            ->assertJsonPath('message', '2 códigos enviados.')
            ->assertJsonPath('encolados', 2)
            ->assertJsonPath('pendientes', 0);

        Mail::assertQueuedCount(2);
        Mail::assertNothingSent();

        foreach ([$primero, $segundo] as $estudiante) {
            Mail::assertQueued(
                CodigoQrMail::class,
                fn (CodigoQrMail $correo): bool => $correo->hasTo($estudiante->codigo_universitario.'@est.umss.edu')
                    && $correo->credencial->estudianteId === $estudiante->id,
            );

            $this->assertDatabaseHas('envios_correo', [
                'tipo' => 'CODIGO_QR',
                'destinatario' => $estudiante->codigo_universitario.'@est.umss.edu',
                'examen_id' => $this->examen->id,
                'estudiante_id' => $estudiante->id,
                'estado' => 'PENDIENTE',
                'solicitado_por' => $this->cuentaDocente->id,
                'enviado_en' => null,
            ]);
        }

        $this->assertDatabaseMissing('envios_correo', ['estudiante_id' => $sinCodigo->id]);
        // En cola todavia no salio: el codigo sigue sin recibirse.
        $this->assertDatabaseHas('codigos_qr', ['estudiante_id' => $primero->id, 'enviado_en' => null]);
    }

    public function test_sending_is_written_to_the_audit_log(): void
    {
        Mail::fake();

        [$estudiante] = $this->habilitados(1);
        $this->emitirCodigo($estudiante);

        $this->actingAs($this->cuentaDocente)
            ->postJson($this->envios())
            ->assertStatus(202)
            ->assertJsonPath('message', '1 código enviado.');

        $this->assertDatabaseHas('bitacora_operaciones', [
            'usuario_id' => $this->cuentaDocente->id,
            'operacion' => 'qr.enviar',
            'tabla_afectada' => 'examenes',
            'registro_id' => $this->examen->id,
        ]);
    }

    public function test_when_the_mail_leaves_the_delivery_and_the_code_are_marked_as_sent(): void
    {
        [$estudiante] = $this->habilitados(1);
        $contenido = $this->emitirCodigo($estudiante);

        // Sin simular: la cola de pruebas es sincronica y el correo sale
        // por el transporte en memoria.
        $this->actingAs($this->cuentaDocente)
            ->postJson($this->envios())
            ->assertStatus(202)
            ->assertJsonPath('encolados', 1);

        $this->assertDatabaseHas('envios_correo', [
            'estudiante_id' => $estudiante->id,
            'estado' => 'ENVIADO',
            'enviado_en' => '2026-10-26 09:30:00',
            'error' => null,
        ]);
        $this->assertDatabaseHas('codigos_qr', [
            'contenido' => $contenido,
            'enviado_en' => '2026-10-26 09:30:00',
        ]);

        $this->actingAs($this->cuentaDocente)
            ->getJson($this->ruta())
            ->assertJsonPath('meta.enviados', 1)
            ->assertJsonPath('data.0.envio', 'enviado')
            ->assertJsonPath('data.0.enviado_en', '2026-10-26T09:30:00-04:00');
    }

    public function test_the_mail_carries_the_exam_the_room_and_the_qr_image(): void
    {
        [$estudiante] = $this->habilitados(1);
        $this->emitirCodigo($estudiante);

        $this->actingAs($this->cuentaDocente)->postJson($this->envios())->assertStatus(202);

        $transporte = Mail::mailer()->getSymfonyTransport();
        self::assertInstanceOf(ArrayTransport::class, $transporte);
        $this->assertCount(1, $transporte->messages());

        $enviado = $transporte->messages()->first();
        self::assertInstanceOf(SentMessage::class, $enviado);
        $correo = $enviado->getOriginalMessage();
        self::assertInstanceOf(Email::class, $correo);
        $html = (string) $correo->getHtmlBody();

        $this->assertSame($estudiante->codigo_universitario.'@est.umss.edu', $correo->getTo()[0]->getAddress());
        $this->assertSame('Su código QR · Primer parcial · Introducción a la Programación', $correo->getSubject());
        $this->assertStringContainsString($estudiante->apellidos.' '.$estudiante->nombres, $html);
        $this->assertStringContainsString('30 oct 2026 · 08:15 · 90 min', $html);
        $this->assertStringContainsString('Aula 691A', $html);
        $this->assertStringContainsString('Edificio Académico 2 · 1° Piso', $html);
        $this->assertStringContainsString('cid:', $html);
        $this->assertStringContainsString('sirve solo para este examen', $html);

        $adjuntos = $correo->getAttachments();
        $this->assertGreaterThanOrEqual(1, count($adjuntos));
        $this->assertSame('image', $adjuntos[0]->getMediaType());
        $this->assertStringStartsWith("\x89PNG", $adjuntos[0]->getBody());
    }

    public function test_a_failed_mail_is_recorded_and_does_not_break_the_request(): void
    {
        [$estudiante] = $this->habilitados(1);
        $this->emitirCodigo($estudiante);

        Mail::shouldReceive('to')->andThrow(new RuntimeException('El servidor de correo no responde.'));

        $this->actingAs($this->cuentaDocente)
            ->postJson($this->envios())
            ->assertStatus(202);

        $this->assertDatabaseHas('envios_correo', [
            'estudiante_id' => $estudiante->id,
            'estado' => 'FALLIDO',
            'error' => 'El servidor de correo no responde.',
            'enviado_en' => null,
        ]);
        $this->assertDatabaseHas('codigos_qr', ['estudiante_id' => $estudiante->id, 'enviado_en' => null]);
    }

    public function test_the_filters_limit_who_receives_the_mail(): void
    {
        Mail::fake();

        $otra = $this->aula('617');
        $this->sumarAula($otra);
        [$aqui] = $this->habilitados(1);
        [$alla] = $this->habilitados(1, $otra);
        $this->emitirCodigo($aqui);
        $this->emitirCodigo($alla);

        $this->actingAs($this->cuentaDocente)
            ->postJson($this->envios(), ['aula' => $otra->id])
            ->assertStatus(202)
            ->assertJsonPath('encolados', 1);

        Mail::assertQueued(
            CodigoQrMail::class,
            fn (CodigoQrMail $correo): bool => $correo->hasTo($alla->codigo_universitario.'@est.umss.edu'),
        );
        $this->assertDatabaseMissing('envios_correo', ['estudiante_id' => $aqui->id]);
    }

    public function test_sending_without_issued_codes_is_a_conflict(): void
    {
        Mail::fake();
        $this->habilitados(2);

        $this->actingAs($this->cuentaDocente)
            ->postJson($this->envios())
            ->assertStatus(409)
            ->assertJsonPath('codigo', 'SIN_EMITIR');

        Mail::assertNothingQueued();
        $this->assertDatabaseCount('envios_correo', 0);
    }

    public function test_the_filters_are_validated(): void
    {
        $this->actingAs($this->cuentaDocente)
            ->postJson($this->envios(), ['aula' => 'la-617'])
            ->assertStatus(422)
            ->assertJsonPath('errors.aula.0', 'El aula no es válida.');
    }

    public function test_sending_requires_session_permission_and_being_the_teacher(): void
    {
        Mail::fake();
        [$estudiante] = $this->habilitados(1);
        $this->emitirCodigo($estudiante);

        $this->postJson($this->envios())->assertUnauthorized();

        $this->actingAs($this->usuarioConRol('Auxiliar'))
            ->postJson($this->envios())
            ->assertForbidden()
            ->assertJsonPath('permiso_requerido', 'codigos_qr');

        $this->actingAs($this->docenteAjeno())
            ->postJson($this->envios())
            ->assertForbidden()
            ->assertJsonPath('message', 'Este examen no es tuyo.');

        Mail::assertNothingQueued();
    }

    public function test_with_a_synchronous_queue_the_mails_go_in_batches_of_one_hundred_without_repeating(): void
    {
        Mail::fake();

        foreach ($this->habilitados(105) as $estudiante) {
            $this->emitirCodigo($estudiante);
        }

        // Los tres primeros ya lo recibieron: no se repiten.
        DB::table('codigos_qr')->orderBy('id')->limit(3)->update(['enviado_en' => now()]);
        $yaEnviados = DB::table('codigos_qr')->whereNotNull('enviado_en')->pluck('estudiante_id')->all();

        $this->actingAs($this->cuentaDocente)
            ->postJson($this->envios())
            ->assertStatus(202)
            ->assertJsonPath('encolados', 100)
            ->assertJsonPath('pendientes', 2);

        $this->assertSame(0, DB::table('envios_correo')->whereIn('estudiante_id', $yaEnviados)->count());

        config(['queue.default' => 'database']);

        $this->actingAs($this->cuentaDocente)
            ->postJson($this->envios())
            ->assertStatus(202)
            ->assertJsonPath('encolados', 105)
            ->assertJsonPath('pendientes', 0);
    }

    public function test_a_resend_to_everybody_advances_by_batches_without_repeating(): void
    {
        Mail::fake();

        foreach ($this->habilitados(103) as $estudiante) {
            $this->emitirCodigo($estudiante);
        }
        DB::table('codigos_qr')->update(['enviado_en' => now()]);

        $primera = $this->actingAs($this->cuentaDocente)
            ->postJson($this->envios())
            ->assertStatus(202)
            ->assertJsonPath('encolados', 100)
            ->assertJsonPath('pendientes', 3);

        $this->actingAs($this->cuentaDocente)
            ->postJson($this->envios(), ['tanda' => $primera->json('tanda')])
            ->assertStatus(202)
            ->assertJsonPath('encolados', 3)
            ->assertJsonPath('pendientes', 0);

        $this->actingAs($this->cuentaDocente)
            ->postJson($this->envios(), ['tanda' => $primera->json('tanda')])
            ->assertStatus(202)
            ->assertJsonPath('encolados', 0);

        $this->assertSame(103, DB::table('envios_correo')->distinct()->count('estudiante_id'));
        $this->assertSame(103, DB::table('envios_correo')->count());
    }
}

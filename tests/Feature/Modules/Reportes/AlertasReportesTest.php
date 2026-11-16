<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Reportes;

use App\Modules\Reportes\Application\Actions\ProcesarAlertasDeExamen;
use App\Modules\Reportes\Infrastructure\Mail\AlertaReporteMail;
use Illuminate\Contracts\Mail\Mailer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

final class AlertasReportesTest extends TestCase
{
    use EscenarioDeReportes;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(self::AHORA);
    }

    public function test_a_teacher_can_configure_their_alert_thresholds(): void
    {
        $docente = $this->docente('Blanco Coca Leticia');
        $cuenta = $this->cuentaDe($docente);

        $this->actingAs($cuenta)
            ->getJson('/api/reportes/alertas')
            ->assertOk()
            ->assertJsonPath('preferencias.asistencia_minima', 70)
            ->assertJsonPath('preferencias.incidencias_minimas', 5)
            ->assertJsonPath('alertas', []);

        $this->putJson('/api/reportes/alertas/preferencias', [
            'asistencia_minima' => 80,
            'incidencias_minimas' => 3,
        ])
            ->assertOk()
            ->assertJsonPath('preferencias.asistencia_minima', 80)
            ->assertJsonPath('preferencias.incidencias_minimas', 3);

        $this->assertDatabaseHas('preferencias_alertas_reportes', [
            'user_id' => $cuenta->id,
            'asistencia_minima' => 80,
            'incidencias_minimas' => 3,
        ]);

        $this->putJson('/api/reportes/alertas/preferencias', [
            'asistencia_minima' => 101,
            'incidencias_minimas' => 0,
        ])->assertUnprocessable();
    }

    public function test_closed_exam_alerts_are_queued_once_and_not_duplicated(): void
    {
        Mail::fake();
        $docente = $this->docente('Blanco Coca Leticia');
        $grupo = $this->grupo($docente);
        $examen = $this->examen($docente, [$grupo]);
        $aula = $this->aula();
        $estudiantes = $this->estudiantesInscritos($grupo, 4);
        $this->habilitar($examen, $estudiantes, $aula);
        $this->ingresar($examen, $estudiantes[0], $aula);

        for ($i = 1; $i <= 5; $i++) {
            $this->reportar($examen, $aula, 'OTRA', 'Incidencia '.$i);
        }

        DB::table('examenes')->where('id', $examen->id)->update([
            'finalizado_en' => now(),
        ]);

        self::assertSame(1, app(ProcesarAlertasDeExamen::class)->execute());
        Mail::assertQueuedCount(2);
        Mail::assertQueued(
            AlertaReporteMail::class,
            static fn (AlertaReporteMail $correo): bool => $correo->alerta['email'] !== '',
        );

        $cuenta = $this->cuentaDe($docente);
        $respuesta = $this->actingAs($cuenta)
            ->getJson('/api/reportes/alertas')
            ->assertOk()
            ->assertJsonCount(2, 'alertas')
            ->assertJsonFragment(['tipo' => 'ASISTENCIA_BAJA'])
            ->assertJsonFragment(['tipo' => 'INCIDENCIAS_ALTAS']);

        $fila = $respuesta->json('alertas.0');
        self::assertIsArray($fila);
        self::assertIsInt($fila['id'] ?? null);
        self::assertIsString($fila['asignatura'] ?? null);
        self::assertIsString($fila['tipo_examen'] ?? null);
        self::assertIsString($fila['fecha'] ?? null);
        self::assertIsString($fila['titulo'] ?? null);
        self::assertIsString($fila['mensaje'] ?? null);
        self::assertIsString($cuenta->email);
        $alertaId = $fila['id'];

        $correo = new AlertaReporteMail([
            'id' => $alertaId,
            'email' => $cuenta->email,
            'asignatura' => $fila['asignatura'],
            'tipo_examen' => $fila['tipo_examen'],
            'fecha' => $fila['fecha'],
            'titulo' => $fila['titulo'],
            'mensaje' => $fila['mensaje'],
        ]);
        $mailer = $this->createMock(Mailer::class);
        $mailer->expects(self::once())
            ->method('send')
            ->willThrowException(new \RuntimeException('SMTP no disponible'));

        try {
            $correo->send($mailer);
            self::fail('Se esperaba que el envío de correo fallara.');
        } catch (\RuntimeException $error) {
            self::assertSame('SMTP no disponible', $error->getMessage());
        }

        $this->assertDatabaseHas('alertas_reportes', [
            'id' => $alertaId,
            'correo_estado' => 'FALLIDO',
            'correo_error' => 'SMTP no disponible',
        ]);

        self::assertSame(0, app(ProcesarAlertasDeExamen::class)->execute());
        Mail::assertQueuedCount(2);

        $this->postJson("/api/reportes/alertas/{$alertaId}/leida")
            ->assertNoContent();

        $otroDocente = $this->docente('Otro Docente');
        $this->actingAs($this->cuentaDe($otroDocente))
            ->postJson("/api/reportes/alertas/{$alertaId}/leida")
            ->assertNotFound();
    }
}

<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Ingreso;

use App\Modules\Ingreso\Infrastructure\Mail\RecordatorioExamenMail;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

final class RecordatoriosDeExamenTest extends TestCase
{
    use EscenarioDePuerta;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->armarExamen();
    }

    public function test_it_sends_one_reminder_to_each_enrolled_student_for_tomorrows_exam_only_once(): void
    {
        $this->examen->update(['fecha' => '2026-10-13']);
        [$primero, $segundo] = $this->habilitados(2);
        $tercero = $this->estudiantesInscritos($this->grupo, 1)[0];
        $destinatarios = [$primero, $segundo, $tercero];
        Mail::fake();

        $this->artisan('examenes:recordatorios')
            ->expectsOutputToContain('3 recordatorio(s) de examen enviados.')
            ->assertExitCode(0);

        Mail::assertQueuedCount(3);

        foreach ($destinatarios as $estudiante) {
            Mail::assertQueued(
                RecordatorioExamenMail::class,
                fn (RecordatorioExamenMail $correo): bool => $correo->hasTo($estudiante->codigo_universitario.'@est.umss.edu')
                    && $correo->credencial->fecha === '2026-10-13'
                    && str_contains($correo->render(), 'Recordatorio: mañana tienes examen'),
            );

            $this->assertDatabaseHas('envios_correo', [
                'tipo' => 'RECORDATORIO_EXAMEN',
                'examen_id' => $this->examen->id,
                'estudiante_id' => $estudiante->id,
                'estado' => 'PENDIENTE',
                'solicitado_por' => null,
            ]);
        }

        Mail::assertQueued(
            RecordatorioExamenMail::class,
            fn (RecordatorioExamenMail $correo): bool => $correo->hasTo($tercero->codigo_universitario.'@est.umss.edu')
                && str_contains($correo->render(), 'El aula se confirmará posteriormente.'),
        );

        $this->artisan('examenes:recordatorios')
            ->expectsOutputToContain('0 recordatorio(s) de examen enviados.')
            ->assertExitCode(0);

        Mail::assertQueuedCount(3);
        $this->assertDatabaseCount('envios_correo', 3);
    }

    public function test_it_does_not_send_for_exams_that_are_not_tomorrow(): void
    {
        Mail::fake();

        $this->examen->update(['fecha' => '2026-10-14']);
        $this->habilitados(1);

        $this->artisan('examenes:recordatorios')->assertExitCode(0);

        Mail::assertNothingQueued();
        $this->assertDatabaseCount('envios_correo', 0);
    }

    public function test_it_does_not_send_for_exams_without_enrolled_students(): void
    {
        Mail::fake();
        $this->examen->update(['fecha' => '2026-10-13']);

        $this->artisan('examenes:recordatorios')->assertExitCode(0);

        Mail::assertNothingQueued();
        $this->assertDatabaseCount('envios_correo', 0);
    }

    public function test_the_reminder_command_is_scheduled_daily_at_eight_in_the_local_timezone(): void
    {
        $eventos = $this->app->make(Schedule::class)->events();
        $recordatorio = collect($eventos)->first(
            static fn ($evento): bool => str_contains((string) $evento->command, 'examenes:recordatorios'),
        );

        self::assertNotNull($recordatorio);
        self::assertSame('0 8 * * *', $recordatorio->expression);
        self::assertSame('America/La_Paz', $recordatorio->timezone);
    }
}

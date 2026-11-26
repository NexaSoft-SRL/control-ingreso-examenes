<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Examenes;

use App\Modules\Academico\Domain\Models\Grupo;
use App\Modules\Estudiantes\Domain\Enums\OrigenEstudiante;
use App\Modules\Estudiantes\Domain\Models\Estudiante;
use App\Modules\Estudiantes\Domain\Models\Inscripcion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Aviso del asistente: los estudiantes de los grupos elegidos que ya tienen
 * otro examen a la misma hora. No impide registrar.
 */
final class ChoquesDeEstudiantesTest extends TestCase
{
    use ArmaExamenes;
    use RefreshDatabase;

    private const HORA = ['hora_inicio' => '08:15:00', 'duracion_minutos' => 90];

    /**
     * @param  array<string, mixed>  $parametros
     */
    private function choques(array $parametros): string
    {
        return '/api/examenes/opciones/choques?'.http_build_query($parametros);
    }

    private function inscribir(Estudiante $estudiante, Grupo $grupo): void
    {
        Inscripcion::create([
            'estudiante_id' => $estudiante->id,
            'grupo_id' => $grupo->id,
            'via' => OrigenEstudiante::Administracion,
        ]);
    }

    public function test_it_lists_the_students_who_have_another_exam_at_that_time(): void
    {
        $docente = $this->docente();
        $otro = $this->docente();
        $calculo = $this->asignatura('Cálculo I');
        $fisica = $this->asignatura('Física I');
        $grupoCalculo = $this->grupo($docente, $calculo);
        $grupoFisica = $this->grupo($otro, $fisica);
        $estudiantes = $this->estudiantesInscritos($grupoCalculo, 3);
        $this->inscribir($estudiantes[0], $grupoFisica);
        $this->inscribir($estudiantes[2], $grupoFisica);
        $fecha = $this->diaHabil(3);
        // Fisica, de 08:15 a 09:45.
        $this->examen($otro, [$grupoFisica], [$this->aula()], ['fecha' => $fecha] + self::HORA);

        $this->actingAs($this->cuenta($docente))
            ->getJson($this->choques([
                'grupos' => [$grupoCalculo->id],
                'fecha' => $fecha,
                'hora_inicio' => '09:30',
                'duracion_minutos' => 60,
            ]))
            ->assertOk()
            ->assertJsonPath('total', 2)
            ->assertJsonCount(2, 'estudiantes')
            ->assertJsonPath('estudiantes.0', [
                'nombre' => $estudiantes[0]->apellidos.' '.$estudiantes[0]->nombres,
                'codigo' => $estudiantes[0]->codigo_universitario,
                'examenes' => [['examen' => 'Física I · Primer parcial', 'inicio' => '08:15', 'fin' => '09:45']],
            ])
            ->assertJsonPath('estudiantes.1.codigo', $estudiantes[2]->codigo_universitario);
    }

    public function test_an_exam_that_only_touches_the_end_does_not_clash(): void
    {
        $docente = $this->docente();
        $otro = $this->docente();
        $grupo = $this->grupo($docente, $this->asignatura());
        $grupoOtro = $this->grupo($otro, $this->asignatura());
        [$estudiante] = $this->estudiantesInscritos($grupo, 1);
        $this->inscribir($estudiante, $grupoOtro);
        $fecha = $this->diaHabil(3);
        // 08:15 a 09:45: el examen nuevo empieza justo cuando termina.
        $this->examen($otro, [$grupoOtro], [], ['fecha' => $fecha] + self::HORA);

        $this->actingAs($this->cuenta($docente))
            ->getJson($this->choques([
                'grupos' => [$grupo->id],
                'fecha' => $fecha,
                'hora_inicio' => '09:45',
                'duracion_minutos' => 60,
            ]))
            ->assertOk()
            ->assertJsonPath('total', 0)
            ->assertJsonPath('estudiantes', []);
    }

    public function test_the_same_students_on_another_day_do_not_clash(): void
    {
        $docente = $this->docente();
        $otro = $this->docente();
        $grupo = $this->grupo($docente, $this->asignatura());
        $grupoOtro = $this->grupo($otro, $this->asignatura());
        [$estudiante] = $this->estudiantesInscritos($grupo, 1);
        $this->inscribir($estudiante, $grupoOtro);
        // Dos días hábiles distintos sea cual sea el día en que corre la prueba: si el
        // tercero cae en domingo pasa al lunes, y el quinto nunca coincide con él.
        $this->examen($otro, [$grupoOtro], [], ['fecha' => $this->diaHabil(5)] + self::HORA);

        $this->actingAs($this->cuenta($docente))
            ->getJson($this->choques([
                'grupos' => [$grupo->id],
                'fecha' => $this->diaHabil(3),
                'hora_inicio' => '08:15',
                'duracion_minutos' => 90,
            ]))
            ->assertOk()
            ->assertJsonPath('total', 0);
    }

    public function test_a_student_who_dropped_the_other_group_does_not_clash(): void
    {
        $docente = $this->docente();
        $otro = $this->docente();
        $grupo = $this->grupo($docente, $this->asignatura());
        $grupoOtro = $this->grupo($otro, $this->asignatura());
        [$retirado, $presente] = $this->estudiantesInscritos($grupo, 2);
        $this->inscribir($retirado, $grupoOtro);
        $this->inscribir($presente, $grupoOtro);
        DB::table('inscripciones')
            ->where('estudiante_id', $retirado->id)
            ->where('grupo_id', $grupoOtro->id)
            ->update(['baja_en' => now()]);
        $fecha = $this->diaHabil(3);
        $this->examen($otro, [$grupoOtro], [], ['fecha' => $fecha] + self::HORA);

        $this->actingAs($this->cuenta($docente))
            ->getJson($this->choques([
                'grupos' => [$grupo->id],
                'fecha' => $fecha,
                'hora_inicio' => '08:15',
                'duracion_minutos' => 90,
            ]))
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('estudiantes.0.codigo', $presente->codigo_universitario);
    }

    public function test_the_exam_being_edited_does_not_clash_with_itself(): void
    {
        $docente = $this->docente();
        $grupo = $this->grupo($docente, $this->asignatura());
        $this->estudiantesInscritos($grupo, 2);
        $fecha = $this->diaHabil(3);
        $examen = $this->examen($docente, [$grupo], [$this->aula()], ['fecha' => $fecha] + self::HORA);

        $parametros = ['grupos' => [$grupo->id], 'fecha' => $fecha, 'hora_inicio' => '08:15', 'duracion_minutos' => 90];

        // Sin excluirlo, el propio examen ya registrado cuenta como otro.
        $this->actingAs($this->cuenta($docente))
            ->getJson($this->choques($parametros))
            ->assertOk()
            ->assertJsonPath('total', 2);

        $this->actingAs($this->cuenta($docente))
            ->getJson($this->choques($parametros + ['examen_id' => $examen->id]))
            ->assertOk()
            ->assertJsonPath('total', 0);
    }

    public function test_the_list_is_limited_but_the_total_counts_everyone(): void
    {
        $docente = $this->docente();
        $otro = $this->docente();
        $grupo = $this->grupo($docente, $this->asignatura());
        $grupoOtro = $this->grupo($otro, $this->asignatura());
        foreach ($this->estudiantesInscritos($grupo, 55) as $estudiante) {
            $this->inscribir($estudiante, $grupoOtro);
        }
        $fecha = $this->diaHabil(3);
        $this->examen($otro, [$grupoOtro], [], ['fecha' => $fecha] + self::HORA);

        $this->actingAs($this->cuenta($docente))
            ->getJson($this->choques([
                'grupos' => [$grupo->id],
                'fecha' => $fecha,
                'hora_inicio' => '08:15',
                'duracion_minutos' => 90,
            ]))
            ->assertOk()
            ->assertJsonPath('total', 55)
            ->assertJsonCount(50, 'estudiantes');
    }

    public function test_it_needs_the_data_of_the_exam(): void
    {
        $this->actingAs($this->cuenta($this->docente()))
            ->getJson('/api/examenes/opciones/choques')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['grupos', 'fecha', 'hora_inicio', 'duracion_minutos']);
    }

    public function test_it_needs_a_session_and_the_permission(): void
    {
        $this->getJson('/api/examenes/opciones/choques')->assertUnauthorized();

        $this->actingAs($this->usuarioConRol('Administrador'))
            ->getJson('/api/examenes/opciones/choques')
            ->assertForbidden()
            ->assertJsonPath('permiso_requerido', 'examenes');
    }
}

<?php

declare(strict_types=1);

namespace Tests\Integration\PostgreSQL;

use App\Modules\Academico\Domain\Models\Aula;
use App\Modules\Administracion\Domain\Models\Respaldo;
use App\Modules\Administracion\Domain\Models\User;
use App\Modules\Estudiantes\Domain\Models\Estudiante;
use App\Modules\Estudiantes\Domain\Models\Inscripcion;
use App\Modules\Examenes\Domain\Models\Examen;
use App\Modules\Examenes\Domain\Models\ExamenAula;
use App\Modules\Habilitacion\Domain\Models\Habilitacion;
use App\Modules\Ingreso\Domain\Models\CodigoQr;
use App\Modules\Ingreso\Domain\Models\Incidencia;
use App\Modules\Ingreso\Domain\Models\Ingreso;
use Closure;
use Database\Factories\UserFactory;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\DatosAcademicos;
use Tests\Support\DatosDeExamen;
use Tests\TestCase;

/**
 * Las garantias del esquema nuevo que no dependen de la aplicacion: unicos,
 * indices parciales, CHECK y el disparador que hace inmutable el ingreso.
 */
final class EsquemaReplanteoTest extends TestCase
{
    use DatosAcademicos;
    use DatosDeExamen;
    use RefreshDatabase;

    public function test_username_is_unique_and_contact_email_is_optional(): void
    {
        UserFactory::new()->createOne(['usuario' => 'leticia.blanco', 'email' => null]);
        UserFactory::new()->createOne(['usuario' => 'corina.flores', 'email' => null]);

        $this->assertDatabaseCount('users', 2);

        $this->assertRechaza(
            'uq_users_usuario',
            fn () => UserFactory::new()->createOne(['usuario' => 'leticia.blanco']),
        );
    }

    public function test_student_document_is_unique_only_when_present(): void
    {
        $grupo = $this->grupo();
        [$primero, $segundo, $tercero] = $this->estudiantesInscritos($grupo, 3);

        Estudiante::whereIn('id', [$primero->id, $segundo->id])
            ->update(['documento_identidad' => null]);

        $this->assertSame(2, Estudiante::whereNull('documento_identidad')->count());

        $this->assertRechaza(
            'uq_estudiante_documento',
            fn () => $primero->update(['documento_identidad' => $tercero->documento_identidad]),
        );

        $this->assertRechaza(
            'uq_estudiante_codigo',
            fn () => $primero->update(['codigo_universitario' => $tercero->codigo_universitario]),
        );
    }

    public function test_group_is_unique_by_period_subject_faculty_and_code(): void
    {
        $asignatura = $this->asignatura();

        $this->grupo(null, $asignatura, '1');
        $this->grupo(null, $asignatura, '2');
        $this->grupo(null, $asignatura, '1', null, $this->facultad('fce'));

        $this->assertRechaza(
            'uq_grupo_periodo_asignatura_facultad_codigo',
            fn () => $this->grupo(null, $asignatura, '1'),
        );
    }

    public function test_student_is_enrolled_once_per_group(): void
    {
        $grupo = $this->grupo();
        [$estudiante] = $this->estudiantesInscritos($grupo, 1);

        $this->assertRechaza(
            'uq_inscripcion_estudiante_grupo',
            fn () => Inscripcion::create([
                'estudiante_id' => $estudiante->id,
                'grupo_id' => $grupo->id,
                'via' => 'DOCENTE',
            ]),
        );
    }

    public function test_exam_duration_must_be_between_15_and_480_minutes(): void
    {
        $docente = $this->docenteConCuenta();
        $grupo = $this->grupo($docente);

        $this->examen($docente, [$grupo], [], ['duracion_minutos' => 15]);
        $this->examen($docente, [$grupo], [], ['duracion_minutos' => 480]);

        foreach ([14, 481] as $duracion) {
            $this->assertRechaza(
                'chk_examen_duracion',
                fn () => $this->examen($docente, [$grupo], [], ['duracion_minutos' => $duracion]),
            );
        }
    }

    public function test_two_exams_may_share_a_room_at_the_same_time(): void
    {
        $docente = $this->docenteConCuenta();
        $otro = $this->docenteConCuenta();
        $aula = $this->aula('691A');

        $primero = $this->examen($docente, [$this->grupo($docente)], [$aula]);
        $segundo = $this->examen($otro, [$this->grupo($otro)], [$aula]);

        $this->assertSame(2, ExamenAula::where('aula_id', $aula->id)->count());

        $this->assertRechaza(
            'uq_examen_aula',
            fn () => ExamenAula::create([
                'examen_id' => $primero->id,
                'aula_id' => $aula->id,
                'encargado_id' => $segundo->creado_por,
            ]),
        );
    }

    public function test_room_access_code_is_unique_only_when_present(): void
    {
        $docente = $this->docenteConCuenta();
        $examen = $this->examen(
            $docente,
            [$this->grupo($docente)],
            [$this->aula(), $this->aula(), $this->aula()],
        );

        [$primera, $segunda, $tercera] = ExamenAula::where('examen_id', $examen->id)
            ->orderBy('id')
            ->get()
            ->all();

        $this->assertSame(3, ExamenAula::whereNull('codigo_acceso')->count());

        $primera->update(['codigo_acceso' => '482913', 'codigo_generado_en' => now()]);
        $segunda->update(['codigo_acceso' => '100200', 'codigo_generado_en' => now()]);

        $this->assertRechaza(
            'uq_examen_aula_codigo',
            fn () => $tercera->update(['codigo_acceso' => '482913']),
        );
    }

    public function test_qualification_is_unique_and_a_disqualified_student_has_no_room(): void
    {
        [$examen, $estudiantes, $aula] = $this->examenConInscritos(2);

        $this->habilitar($examen, [$estudiantes[0]], $aula);
        $this->inhabilitar($examen, [$estudiantes[1]]);

        $this->assertRechaza(
            'uq_habilitacion_examen_estudiante',
            fn () => Habilitacion::create([
                'examen_id' => $examen->id,
                'estudiante_id' => $estudiantes[0]->id,
                'habilitado' => true,
            ]),
        );

        $this->assertRechaza(
            'chk_habilitacion_aula_solo_habilitado',
            fn () => Habilitacion::where('estudiante_id', $estudiantes[1]->id)
                ->update(['aula_id' => $aula->id]),
        );
    }

    public function test_qr_code_is_unique_by_token_and_by_exam_and_student(): void
    {
        [$examen, $estudiantes] = $this->examenConInscritos(2);

        CodigoQr::create([
            'examen_id' => $examen->id,
            'estudiante_id' => $estudiantes[0]->id,
            'token' => str_repeat('a', 48),
            'emitido_en' => now(),
        ]);

        $this->assertRechaza(
            'uq_codigo_qr_token',
            fn () => CodigoQr::create([
                'examen_id' => $examen->id,
                'estudiante_id' => $estudiantes[1]->id,
                'token' => str_repeat('a', 48),
                'emitido_en' => now(),
            ]),
        );

        $this->assertRechaza(
            'uq_codigo_qr_examen_estudiante',
            fn () => CodigoQr::create([
                'examen_id' => $examen->id,
                'estudiante_id' => $estudiantes[0]->id,
                'token' => str_repeat('b', 48),
                'emitido_en' => now(),
            ]),
        );
    }

    public function test_student_enters_an_exam_only_once_even_through_another_room(): void
    {
        [$examen, $estudiantes, $aula] = $this->examenConInscritos(1);

        $this->ingresar($examen, $estudiantes[0], $aula);

        $this->assertRechaza(
            'uq_ingreso_examen_estudiante',
            fn () => $this->ingresar($examen, $estudiantes[0], $this->aula()),
        );

        $this->assertDatabaseCount('ingresos', 1);
    }

    public function test_entry_cannot_be_updated(): void
    {
        [$examen, $estudiantes, $aula] = $this->examenConInscritos(1);
        $ingreso = $this->ingresar($examen, $estudiantes[0], $aula);

        $this->assertRechaza(
            'trg_ingresos_inmutables',
            fn () => DB::table('ingresos')
                ->where('id', $ingreso->id)
                ->update(['es_excepcion' => true]),
        );

        $this->assertDatabaseHas('ingresos', [
            'id' => $ingreso->id,
            'es_excepcion' => false,
        ]);
    }

    public function test_entry_cannot_be_deleted(): void
    {
        [$examen, $estudiantes, $aula] = $this->examenConInscritos(1);
        $ingreso = $this->ingresar($examen, $estudiantes[0], $aula);

        $this->assertRechaza(
            'trg_ingresos_inmutables',
            fn () => DB::table('ingresos')->where('id', $ingreso->id)->delete(),
        );

        // Tampoco por arrastre: el examen con ingresos no se borra.
        $this->assertRechaza(
            'ingresos_examen_id_foreign',
            fn () => Examen::where('id', $examen->id)->delete(),
        );

        $this->assertDatabaseHas('ingresos', ['id' => $ingreso->id]);
    }

    public function test_expulsion_requires_an_entry_and_happens_once_per_entry(): void
    {
        [$examen, $estudiantes, $aula] = $this->examenConInscritos(1);
        $ingreso = $this->ingresar($examen, $estudiantes[0], $aula);

        $this->assertRechaza(
            'chk_incidencia_expulsion_con_ingreso',
            fn () => $this->incidencia($examen, 'EXPULSION', null),
        );

        $this->incidencia($examen, 'EXPULSION', $ingreso);

        $this->assertRechaza(
            'uq_expulsion_por_ingreso',
            fn () => $this->incidencia($examen, 'EXPULSION', $ingreso),
        );

        // El unico es parcial: sobre el mismo ingreso caben otras
        // incidencias.
        $this->incidencia($examen, 'MATERIAL_NO_PERMITIDO', $ingreso);
        $this->incidencia($examen, 'MATERIAL_NO_PERMITIDO', $ingreso);

        $this->assertDatabaseCount('incidencias', 3);
    }

    public function test_system_attempt_may_have_no_exam(): void
    {
        $aula = $this->aula();

        Incidencia::create([
            'examen_id' => null,
            'aula_id' => $aula->id,
            'tipo' => 'INTENTO_NO_AUTORIZADO',
            'origen' => 'SISTEMA',
            'causa' => 'CODIGO_INVALIDO',
            'detalle' => 'Código que no existe.',
            'ocurrida_en' => now(),
        ]);

        $this->assertDatabaseHas('incidencias', [
            'aula_id' => $aula->id,
            'examen_id' => null,
            'origen' => 'SISTEMA',
        ]);
    }

    public function test_failed_backups_keep_a_row_without_file(): void
    {
        Respaldo::create(['tipo' => 'PROGRAMADO', 'estado' => 'FALLIDO', 'error' => 'Disco lleno.']);
        Respaldo::create(['tipo' => 'PUNTUAL', 'estado' => 'FALLIDO', 'error' => 'Disco lleno.']);
        Respaldo::create([
            'archivo' => 'respaldo-20261006-030000.sql',
            'tipo' => 'PROGRAMADO',
            'estado' => 'CORRECTO',
        ]);

        $this->assertRechaza(
            'uq_respaldo_archivo',
            fn () => Respaldo::create([
                'archivo' => 'respaldo-20261006-030000.sql',
                'tipo' => 'PUNTUAL',
                'estado' => 'CORRECTO',
            ]),
        );

        $this->assertDatabaseCount('respaldos', 3);
    }

    public function test_audit_log_has_the_indexes_the_screen_filters_by(): void
    {
        $this->assertTrue(Schema::hasIndex('bitacora_operaciones', 'idx_bitacora_fecha'));
        $this->assertTrue(Schema::hasIndex('bitacora_operaciones', 'idx_bitacora_operacion'));
    }

    /**
     * Un examen con un grupo, un aula y `$cantidad` inscritos.
     *
     * @return array{0: Examen, 1: list<Estudiante>, 2: Aula}
     */
    private function examenConInscritos(int $cantidad): array
    {
        $docente = $this->docenteConCuenta();
        $grupo = $this->grupo($docente);
        $aula = $this->aula();

        return [
            $this->examen($docente, [$grupo], [$aula]),
            $this->estudiantesInscritos($grupo, $cantidad),
            $aula,
        ];
    }

    private function incidencia(Examen $examen, string $tipo, ?Ingreso $ingreso): Incidencia
    {
        $autor = User::find($examen->creado_por);

        return Incidencia::create([
            'examen_id' => $examen->id,
            'ingreso_id' => $ingreso?->id,
            'estudiante_id' => $ingreso?->estudiante_id,
            'aula_id' => $ingreso?->aula_id,
            'tipo' => $tipo,
            'origen' => 'PERSONA',
            'detalle' => 'Detalle de la prueba.',
            'ocurrida_en' => now(),
            'reportada_por' => $autor?->getKey(),
        ]);
    }

    /**
     * La operacion corre en una transaccion anidada: PostgreSQL deja
     * inservible la transaccion donde falla una sentencia, y la de la
     * prueba tiene que seguir viva para las afirmaciones siguientes.
     *
     * @param  Closure(): mixed  $operacion
     */
    private function assertRechaza(string $restriccion, Closure $operacion): void
    {
        try {
            DB::transaction($operacion);
        } catch (QueryException $excepcion) {
            self::assertStringContainsString($restriccion, $excepcion->getMessage());

            return;
        }

        self::fail("PostgreSQL debía rechazar la operación por {$restriccion}.");
    }
}

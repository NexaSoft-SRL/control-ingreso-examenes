<?php

declare(strict_types=1);

namespace Tests\Integration\PostgreSQL;

use App\Modules\Academico\Domain\Models\Aula;
use App\Modules\Administracion\Domain\Models\Role;
use App\Modules\Estudiantes\Domain\Models\Estudiante;
use App\Modules\Estudiantes\Domain\Models\Inscripcion;
use App\Modules\Examenes\Domain\Models\Examen;
use App\Modules\Examenes\Domain\Models\ExamenAula;
use App\Modules\Examenes\Domain\Models\ExamenNorma;
use App\Modules\Examenes\Domain\Models\PlantillaNorma;
use App\Modules\Habilitacion\Domain\Models\Habilitacion;
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
 * Las garantias del esquema nuevo que no dependen de la aplicacion: las
 * tablas que existen (y las que ya no), unicos, indices parciales y CHECK.
 */
final class EsquemaReplanteoTest extends TestCase
{
    use DatosAcademicos;
    use DatosDeExamen;
    use RefreshDatabase;

    /**
     * Las 36 tablas del esquema; `migrations` va aparte.
     *
     * @var list<string>
     */
    private const TABLAS = [
        // Marco.
        'cache',
        'cache_locks',
        'failed_jobs',
        'job_batches',
        'jobs',
        'login_attempts',
        'password_reset_tokens',
        'sessions',
        'users',
        'usuarios',
        // Administracion.
        'bitacora_operaciones',
        'permission_role',
        'permissions',
        'roles',
        // Academico.
        'asignaturas',
        'aulas',
        'carreras',
        'docentes',
        'edificios',
        'facultades',
        'grupos',
        'horarios',
        'importaciones_oferta',
        'periodos',
        'plan_estudios',
        // Estudiantes.
        'cargas_inscritos',
        'conflictos_padron',
        'estudiantes',
        'inscripciones',
        // Examenes.
        'examen_aula',
        'examen_grupo',
        'examen_norma',
        'examenes',
        'plantillas_norma',
        // Habilitacion.
        'habilitaciones',
        // Ingreso.
        'ingresos',
    ];

    public function test_schema_has_exactly_the_expected_tables(): void
    {
        $esperadas = [...self::TABLAS, 'migrations'];
        sort($esperadas);

        $reales = DB::table('pg_tables')
            ->where('schemaname', 'public')
            ->orderBy('tablename')
            ->pluck('tablename')
            ->all();

        $this->assertSame($esperadas, $reales);
    }

    public function test_previous_domain_design_is_gone(): void
    {
        foreach ([
            'ambientes',
            'asignaciones_ambiente',
            'examen_ambiente',
            'grupos_asignatura',
            'habilitaciones_examen',
            'normas_examenes',
            'students',
        ] as $tabla) {
            $this->assertFalse(Schema::hasTable($tabla), "La tabla {$tabla} debía eliminarse.");
        }

        // Las aulas no tienen capacidad ni estado.
        $this->assertSame(
            ['id', 'nombre', 'edificio_id', 'facultad_id', 'piso', 'created_at', 'updated_at'],
            Schema::getColumnListing('aulas'),
        );

        // El aula de un examen no lleva encargado ni codigo de acceso.
        $this->assertSame(
            ['id', 'examen_id', 'aula_id', 'created_at', 'updated_at'],
            Schema::getColumnListing('examen_aula'),
        );

        $this->assertFalse(Schema::hasColumn('examenes', 'grupo_id'));
    }

    public function test_username_is_unique_and_email_is_optional(): void
    {
        UserFactory::new()->createOne(['usuario' => 'leticia.blanco', 'correo' => null]);
        UserFactory::new()->createOne(['usuario' => 'corina.flores', 'correo' => null]);

        $this->assertDatabaseCount('usuarios', 2);

        $this->assertRechaza(
            'uq_usuarios_usuario',
            fn () => UserFactory::new()->createOne(['usuario' => 'leticia.blanco']),
        );
    }

    public function test_account_without_username_gets_one_from_its_email(): void
    {
        $primera = UserFactory::new()->createOne(['usuario' => null, 'correo' => 'Ana.Rojas@umss.edu.bo']);
        $segunda = UserFactory::new()->createOne(['usuario' => null, 'correo' => 'ana.rojas@fcyt.umss.edu.bo']);
        $sinCorreo = UserFactory::new()->createOne(['usuario' => null, 'correo' => null]);

        $this->assertSame('ana.rojas', $primera->getAttribute('usuario'));
        $this->assertSame('ana.rojas2', $segunda->getAttribute('usuario'));
        $this->assertSame('usuario', $sinCorreo->getAttribute('usuario'));
    }

    public function test_role_keeps_each_permission_once_and_is_not_a_system_role_by_default(): void
    {
        $rol = Role::create(['name' => 'Coordinador']);

        $this->assertFalse($rol->refresh()->es_sistema);

        $permisoId = DB::table('permissions')->insertGetId([
            'name' => 'periodo_oferta',
            'screen_name' => 'Período y oferta académica',
        ]);

        DB::table('permission_role')->insert(['role_id' => $rol->id, 'permission_id' => $permisoId]);

        $this->assertRechaza(
            'uq_permission_role',
            fn () => DB::table('permission_role')->insert(['role_id' => $rol->id, 'permission_id' => $permisoId]),
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
        $this->examen($otro, [$this->grupo($otro)], [$aula]);

        $this->assertSame(2, ExamenAula::where('aula_id', $aula->id)->count());

        $this->assertRechaza(
            'uq_examen_aula',
            fn () => ExamenAula::create([
                'examen_id' => $primero->id,
                'aula_id' => $aula->id,
            ]),
        );
    }

    public function test_norm_template_text_is_unique_per_owner_and_among_predefined(): void
    {
        $docente = $this->docenteConCuenta();
        $otro = $this->docenteConCuenta();

        PlantillaNorma::create(['usuario_id' => null, 'texto' => 'Sin celular']);
        PlantillaNorma::create(['usuario_id' => $docente->user_id, 'texto' => 'Sin celular']);
        PlantillaNorma::create(['usuario_id' => $otro->user_id, 'texto' => 'Sin celular']);

        $this->assertRechaza(
            'uq_plantilla_predefinida_texto',
            fn () => PlantillaNorma::create(['usuario_id' => null, 'texto' => 'Sin celular']),
        );

        $this->assertRechaza(
            'uq_plantilla_usuario_texto',
            fn () => PlantillaNorma::create(['usuario_id' => $docente->user_id, 'texto' => 'Sin celular']),
        );
    }

    public function test_exam_keeps_the_norm_text_when_its_template_is_removed(): void
    {
        $docente = $this->docenteConCuenta();
        $examen = $this->examen($docente, [$this->grupo($docente)]);
        $plantilla = PlantillaNorma::create(['usuario_id' => $docente->user_id, 'texto' => 'Sin apuntes ni libros']);

        ExamenNorma::create([
            'examen_id' => $examen->id,
            'plantilla_id' => $plantilla->id,
            'texto' => $plantilla->texto,
            'orden' => 1,
        ]);

        $this->assertRechaza(
            'uq_examen_norma',
            fn () => ExamenNorma::create([
                'examen_id' => $examen->id,
                'plantilla_id' => $plantilla->id,
                'texto' => $plantilla->texto,
                'orden' => 2,
            ]),
        );

        $plantilla->delete();

        $this->assertDatabaseHas('examen_norma', [
            'examen_id' => $examen->id,
            'plantilla_id' => null,
            'texto' => 'Sin apuntes ni libros',
        ]);

        // Con el examen se van sus normas marcadas.
        $examen->delete();

        $this->assertDatabaseCount('examen_norma', 0);
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

    public function test_exam_with_entries_cannot_be_deleted(): void
    {
        [$examen, $estudiantes, $aula] = $this->examenConInscritos(1);
        $ingresoId = $this->ingresar($examen, $estudiantes[0], $aula);

        $this->assertRechaza(
            'ingresos_examen_id_foreign',
            fn () => Examen::where('id', $examen->id)->delete(),
        );

        $this->assertDatabaseHas('ingresos', ['id' => $ingresoId]);
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

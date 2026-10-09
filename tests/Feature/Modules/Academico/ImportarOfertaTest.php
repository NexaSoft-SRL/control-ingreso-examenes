<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Academico;

use App\Modules\Academico\Application\Actions\ImportarOferta;
use App\Modules\Academico\Application\DTOs\ResumenImportacionData;
use App\Modules\Academico\Domain\Exceptions\FuenteNoDisponibleException;
use App\Modules\Academico\Domain\Models\Aula;
use App\Modules\Academico\Domain\Models\Docente;
use App\Modules\Academico\Domain\Models\Grupo;
use Database\Factories\UserFactory;
use Database\Seeders\FacultadSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\DatosDeExamen;
use Tests\TestCase;

final class ImportarOfertaTest extends TestCase
{
    use DatosDeExamen;
    use RefreshDatabase;

    /**
     * @var list<string>
     */
    private array $carpetasTemporales = [];

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'umss.ruta_genda' => base_path('tests/Fixtures/genda'),
            'umss.ruta_fuentes' => base_path('tests/Fixtures/genda/fuentes.json'),
        ]);

        $this->seed(FacultadSeeder::class);
    }

    protected function tearDown(): void
    {
        foreach ($this->carpetasTemporales as $carpeta) {
            foreach (glob($carpeta.'/*') ?: [] as $archivo) {
                unlink($archivo);
            }

            rmdir($carpeta);
        }

        parent::tearDown();
    }

    public function test_it_imports_the_offer_of_a_faculty_and_records_the_run(): void
    {
        $autor = UserFactory::new()->createOne();

        $resumen = $this->importar('fcyt', $autor->id);

        $this->assertTrue($resumen->importada);
        $this->assertSame('FCyT', $resumen->sigla);
        $this->assertSame(
            [
                'carreras' => 2,
                'asignaturas' => 3,
                'grupos' => 5,
                'grupos_sin_docente' => 1,
                'docentes' => 3,
                'aulas' => 7,
                'sesiones_descartadas' => 1,
                'carreras_sin_codigo' => 0,
                'grupos_eliminados' => 0,
            ],
            $resumen->resumen(),
        );

        $this->assertDatabaseCount('carreras', 2);
        $this->assertDatabaseCount('asignaturas', 3);
        $this->assertDatabaseCount('grupos', 5);
        $this->assertDatabaseCount('docentes', 3);
        $this->assertDatabaseCount('horarios', 9);
        $this->assertDatabaseCount('edificios', 2);

        $this->assertDatabaseHas('carreras', [
            'codigo' => '411702',
            'nombre' => 'Licenciatura en Ingenieria de Sistemas',
            'regimen' => 'SEMESTRAL',
        ]);
        $this->assertDatabaseHas('asignaturas', ['codigo' => '2008056', 'nombre' => 'Calculo II']);
        $this->assertDatabaseHas('docentes', [
            'nombre_completo' => 'Blanco Coca Leticia',
            'nombre_normalizado' => 'BLANCO COCA LETICIA',
            'user_id' => null,
        ]);
        $this->assertDatabaseHas('periodos', ['codigo' => '2/2026', 'anio' => 2026, 'numero' => 2, 'tipo' => 'SEMESTRE_2']);

        $this->assertDatabaseHas('importaciones_oferta', [
            'id' => $resumen->importacionId,
            'estado' => 'IMPORTADA',
            'periodo_codigo' => '2/2026',
            'fecha_fuente' => '2026-08-03',
            'ejecutada_por' => $autor->id,
            'error' => null,
        ]);
        $this->assertDatabaseHas('bitacora_operaciones', [
            'usuario_id' => $autor->id,
            'operacion' => 'oferta.importar',
            'tabla_afectada' => 'importaciones_oferta',
            'registro_id' => $resumen->importacionId,
        ]);
    }

    public function test_importing_twice_does_not_duplicate_anything(): void
    {
        $this->importar('fcyt');
        $this->importar('fce');

        $antes = $this->conteos();

        $this->assertTrue($this->importar('fcyt')->importada);
        $this->assertTrue($this->importar('fce')->importada);

        $this->assertSame($antes, $this->conteos());
        $this->assertDatabaseCount('importaciones_oferta', 4);
    }

    public function test_the_markers_of_a_vacant_post_leave_the_group_without_teacher(): void
    {
        $this->importar('fcyt');
        $this->importar('fce');
        $this->importar('fhce');

        // «POR DESGINAR DOCENTE» (errata de la fuente), cadena vacia, un
        // valor de menos de cinco letras, «POR DESIGNAR DOCENTE» y un
        // grupo que no trae el campo.
        $this->assertNull($this->grupo('fcyt', '2008019', '6')->docente_id);
        $this->assertNull($this->grupo('fce', '1301010', '01')->docente_id);
        $this->assertNull($this->grupo('fce', '1301010', '02')->docente_id);
        $this->assertNull($this->grupo('fce', '1301010', '03')->docente_id);
        $this->assertNull($this->grupo('fhce', '1801033', '1')->docente_id);

        $this->assertNotNull($this->grupo('fce', '1301010', '04')->docente_id);
        $this->assertDatabaseMissing('docentes', ['nombre_normalizado' => 'VI']);
        $this->assertSame(0, Docente::where('nombre_normalizado', 'like', 'POR DES%')->count());
    }

    public function test_a_group_repeated_in_two_careers_is_a_single_group(): void
    {
        $this->importar('fcyt');

        $asignatura = DB::table('asignaturas')->where('codigo', '2010010')->value('id');

        $this->assertSame(2, DB::table('grupos')->where('asignatura_id', $asignatura)->count());
        $this->assertSame(3, $this->grupo('fcyt', '2010010', '2')->horarios()->count());

        // La asignatura si queda en el plan de las dos carreras.
        $this->assertSame(2, DB::table('plan_estudios')->where('asignatura_id', $asignatura)->count());
        $this->assertDatabaseHas('plan_estudios', ['asignatura_id' => $asignatura, 'nivel' => 'SEMESTRE 1']);
    }

    public function test_a_teacher_of_two_faculties_is_one_row_and_names_are_normalized(): void
    {
        $this->importar('fcyt');
        $this->importar('fce');

        // En el archivo de FCE viene con dos espacios.
        $this->assertSame(1, Docente::where('nombre_normalizado', 'TABORGA ACHA FIDEL')->count());
        $this->assertSame(
            $this->grupo('fcyt', '2008019', '5')->docente_id,
            $this->grupo('fce', '1301010', '04')->docente_id,
        );

        $this->assertDatabaseHas('docentes', [
            'nombre_completo' => 'Montaño Vargas Oscar',
            'nombre_normalizado' => 'MONTANO VARGAS OSCAR',
        ]);
    }

    public function test_a_career_without_code_is_resolved_by_name_against_the_curriculum(): void
    {
        $resumen = $this->importar('fce');

        $this->assertSame(1, $resumen->carrerasSinCodigo);
        $this->assertDatabaseHas('carreras', ['codigo' => '059801', 'nombre' => 'Licenciatura en Economia']);

        // La que el pensum no conoce se crea igual, con un codigo propio
        // que no cambia al reimportar.
        $this->assertDatabaseHas('carreras', ['codigo' => 'SIN-FCE-1', 'nombre' => 'Programa de Finanzas Populares']);

        $this->importar('fce');

        $this->assertDatabaseCount('carreras', 2);
        $this->assertDatabaseMissing('carreras', ['codigo' => 'SIN-FCE-2']);
    }

    public function test_a_corrupt_session_is_discarded_and_counted(): void
    {
        $resumen = $this->importar('fcyt');

        $this->assertSame(1, $resumen->sesionesDescartadas);

        $grupo = $this->grupo('fcyt', '2008019', '6');
        $horario = $grupo->horarios()->firstOrFail();

        $this->assertSame(1, $grupo->horarios()->count());
        $this->assertSame('LU', $horario->dia);
        $this->assertSame('08:15:00', $horario->hora_inicio);
        $this->assertSame('09:45:00', $horario->hora_fin);
    }

    public function test_assistant_sessions_are_flagged(): void
    {
        $this->importar('fcyt');

        $deUnEntero = $this->grupo('fcyt', '2010010', '1')->horarios()->orderBy('id')->pluck('es_auxiliatura')->all();
        $deUnaLista = $this->grupo('fcyt', '2010010', '2')->horarios()->orderBy('id')->pluck('es_auxiliatura')->all();
        $ninguna = $this->grupo('fcyt', '2008019', '5')->horarios()->orderBy('id')->pluck('es_auxiliatura')->all();

        $this->assertSame([false, true], $deUnEntero);
        $this->assertSame([false, true, true], $deUnaLista);
        $this->assertSame([false, false], $ninguna);
    }

    public function test_a_room_missing_from_the_map_is_created_without_building(): void
    {
        $this->importar('fcyt');

        $this->assertDatabaseHas('aulas', [
            'nombre' => 'AULVIR',
            'edificio_id' => null,
            'facultad_id' => DB::table('facultades')->where('clave', 'fcyt')->value('id'),
        ]);

        $ubicada = Aula::where('nombre', '691B')->firstOrFail();

        $this->assertNotNull($ubicada->edificio_id);
        $this->assertSame('1° Piso', $ubicada->piso);

        // Una sesion sin aula queda sin aula.
        $this->importar('fce');

        $this->assertNull($this->grupo('fce', '1301010', '03')->horarios()->firstOrFail()->aula_id);
    }

    public function test_a_room_used_by_two_faculties_is_a_single_room(): void
    {
        $this->importar('fcyt');

        $this->assertDatabaseHas('aulas', ['nombre' => 'TP-7', 'edificio_id' => null]);

        $this->importar('fce');

        $this->assertSame(1, DB::table('aulas')->where('nombre', 'TP-7')->count());

        $aula = Aula::where('nombre', 'TP-7')->firstOrFail();

        // El mapa de FCE la ubica: deja de estar «sin ubicar».
        $this->assertNotNull($aula->edificio_id);
        $this->assertSame($aula->id, $this->grupo('fcyt', '2008056', '1')->horarios()->firstOrFail()->aula_id);
        $this->assertSame($aula->id, $this->grupo('fce', '1301010', '01')->horarios()->firstOrFail()->aula_id);
    }

    public function test_groups_of_a_yearly_career_go_to_the_yearly_period(): void
    {
        $this->importar('fach');

        $this->assertDatabaseHas('carreras', ['codigo' => '202002', 'regimen' => 'ANUAL']);
        $this->assertDatabaseHas('carreras', ['codigo' => '127091', 'regimen' => 'SEMESTRAL']);
        $this->assertDatabaseHas('periodos', ['codigo' => '0/2026', 'numero' => 0, 'tipo' => 'ANUAL']);

        $anual = DB::table('periodos')->where('codigo', '0/2026')->value('id');
        $semestre = DB::table('periodos')->where('codigo', '2/2026')->value('id');

        $this->assertSame($anual, $this->grupo('fach', '1701001', 'A1')->periodo_id);
        $this->assertSame($semestre, $this->grupo('fach', '1701050', '1')->periodo_id);

        // El archivo dice «Gestión 2/2026» y no trae fecha.
        $this->assertDatabaseHas('importaciones_oferta', ['periodo_codigo' => '2/2026', 'fecha_fuente' => null]);
    }

    public function test_a_failure_leaves_a_failed_run_and_nothing_half_written(): void
    {
        // Un codigo de grupo que no cabe en la columna: el error salta
        // cuando ya se escribieron edificios, carreras y asignaturas.
        $this->usarOfertaModificada(static function (array $oferta): array {
            $oferta['CARRERA NUEVA'] = [
                '_CAREER_CODE' => '999999',
                'SEMESTRE 1' => [
                    'MATERIA NUEVA' => [
                        '_CODE' => '9999999',
                        str_repeat('X', 40) => [
                            'PROFESSOR' => 'TABORGA ACHA FIDEL',
                            'AUX_INDEX' => -1,
                            'DAY' => ['LU'],
                            'HOUR' => ['08:15-09:45'],
                            'CLASS' => ['617'],
                        ],
                    ],
                ],
            ];

            return $oferta;
        });

        $resumen = $this->importar('fcyt');

        $this->assertFalse($resumen->importada);
        $this->assertNotNull($resumen->error);

        foreach (['carreras', 'asignaturas', 'docentes', 'grupos', 'horarios', 'edificios', 'aulas', 'periodos'] as $tabla) {
            $this->assertDatabaseCount($tabla, 0);
        }

        $this->assertDatabaseCount('importaciones_oferta', 1);
        $this->assertDatabaseHas('importaciones_oferta', ['id' => $resumen->importacionId, 'estado' => 'FALLO']);
        $this->assertNotNull(DB::table('importaciones_oferta')->value('error'));
        $this->assertNotNull(DB::table('importaciones_oferta')->value('terminada_en'));
        $this->assertDatabaseMissing('bitacora_operaciones', ['operacion' => 'oferta.importar']);
    }

    public function test_a_missing_file_fails_the_run_and_an_unknown_faculty_is_rejected(): void
    {
        config(['umss.ruta_genda' => base_path('tests/Fixtures/no-existe')]);

        $resumen = $this->importar('fcyt');

        $this->assertFalse($resumen->importada);
        $this->assertSame('No se encontró el archivo fcyt.json.', $resumen->error);
        $this->assertDatabaseHas('importaciones_oferta', ['estado' => 'FALLO', 'error' => 'No se encontró el archivo fcyt.json.']);

        $this->expectException(FuenteNoDisponibleException::class);

        $this->importar('fcjp');
    }

    public function test_a_group_gone_from_the_file_is_kept_only_if_it_has_students(): void
    {
        $this->importar('fcyt');

        $conInscritos = $this->grupo('fcyt', '2010010', '2');
        $sinInscritos = $this->grupo('fcyt', '2010010', '1');
        $this->estudiantesInscritos($conInscritos, 2);

        $this->usarOfertaModificada(static function (array $oferta): array {
            // Queda solo la carrera que no trae Introduccion a la
            // Programacion en ningun nivel.
            unset($oferta['LICENCIATURA EN INGENIERIA INFORMATICA']);
            $sistemas = $oferta['LICENCIATURA EN INGENIERIA DE SISTEMAS'] ?? null;

            if (is_array($sistemas) && is_array($sistemas['SEMESTRE 1'] ?? null)) {
                unset($sistemas['SEMESTRE 1']['INTRODUCCION A LA PROGRAMACION']);
                $oferta['LICENCIATURA EN INGENIERIA DE SISTEMAS'] = $sistemas;
            }

            return $oferta;
        });

        $resumen = $this->importar('fcyt');

        $this->assertTrue($resumen->importada);
        $this->assertSame(3, $resumen->grupos);
        $this->assertSame(1, $resumen->gruposEliminados);

        $this->assertDatabaseHas('grupos', ['id' => $conInscritos->id]);
        $this->assertDatabaseMissing('grupos', ['id' => $sinInscritos->id]);
        $this->assertDatabaseCount('inscripciones', 2);

        // El docente que deja de aparecer no se borra.
        $this->assertDatabaseHas('docentes', ['nombre_normalizado' => 'SALAZAR SERRUDO CARLA']);
    }

    public function test_reimporting_never_touches_the_account_of_a_teacher(): void
    {
        $this->importar('fcyt');

        $cuenta = UserFactory::new()->createOne();

        Docente::where('nombre_normalizado', 'BLANCO COCA LETICIA')->update([
            'user_id' => $cuenta->id,
            'nombre_completo' => 'Blanco Coca, Leticia',
        ]);

        $this->importar('fcyt');

        $this->assertDatabaseHas('docentes', [
            'nombre_normalizado' => 'BLANCO COCA LETICIA',
            'user_id' => $cuenta->id,
            'nombre_completo' => 'Blanco Coca, Leticia',
        ]);
        $this->assertNotNull($this->grupo('fcyt', '2010010', '2')->docente_id);
    }

    private function importar(string $facultad, ?int $usuarioId = null): ResumenImportacionData
    {
        return $this->app->make(ImportarOferta::class)->execute($facultad, $usuarioId);
    }

    private function grupo(string $facultad, string $asignatura, string $codigo): Grupo
    {
        return Grupo::query()
            ->where('facultad_id', DB::table('facultades')->where('clave', $facultad)->value('id'))
            ->where('asignatura_id', DB::table('asignaturas')->where('codigo', $asignatura)->value('id'))
            ->where('codigo', $codigo)
            ->firstOrFail();
    }

    /**
     * @return array<string, int>
     */
    private function conteos(): array
    {
        $conteos = [];

        foreach (['periodos', 'carreras', 'asignaturas', 'plan_estudios', 'docentes', 'edificios', 'aulas', 'grupos', 'horarios'] as $tabla) {
            $conteos[$tabla] = DB::table($tabla)->count();
        }

        return $conteos;
    }

    /**
     * Copia las fuentes de prueba a una carpeta temporal con el archivo de
     * FCyT cambiado, y apunta ahi el importador.
     *
     * @param  callable(array<string, mixed>): array<string, mixed>  $cambio
     */
    private function usarOfertaModificada(callable $cambio): void
    {
        $origen = base_path('tests/Fixtures/genda');
        $carpeta = storage_path('framework/testing/genda-'.uniqid());

        mkdir($carpeta, 0777, true);
        $this->carpetasTemporales[] = $carpeta;

        foreach (['locations.json', 'pensum.json'] as $archivo) {
            copy($origen.'/'.$archivo, $carpeta.'/'.$archivo);
        }

        /** @var array<string, mixed> $oferta */
        $oferta = json_decode((string) file_get_contents($origen.'/fcyt.json'), true, 64, JSON_THROW_ON_ERROR);

        file_put_contents($carpeta.'/fcyt.json', json_encode($cambio($oferta), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));

        config(['umss.ruta_genda' => $carpeta]);
    }
}
